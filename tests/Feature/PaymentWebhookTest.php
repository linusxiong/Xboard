<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!class_exists('App\\Payments\\TestWebhookGateway')) {
            eval('
                namespace App\\Payments;

                class TestWebhookGateway {
                    public function __construct($config) {}
                    public function notify($params) {
                        return [
                            "trade_no" => $params["trade_no"],
                            "callback_no" => "callback-from-test"
                        ];
                    }
                }
            ');
        }

        if (!class_exists('App\\Payments\\IgnoredWebhookGateway')) {
            eval('
                namespace App\\Payments;

                class IgnoredWebhookGateway {
                    public function __construct($config) {}
                    public function notify($params) {
                        return [
                            "custom_result" => "success"
                        ];
                    }
                }
            ');
        }

        if (!class_exists('App\\Payments\\StringWebhookGateway')) {
            eval('
                namespace App\\Payments;

                class StringWebhookGateway {
                    public function __construct($config) {}
                    public function notify($params) {
                        return "pending";
                    }
                }
            ');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => database_path('testing.sqlite'),
        ]);

        if (!file_exists(database_path('testing.sqlite'))) {
            touch(database_path('testing.sqlite'));
        }

        Schema::dropIfExists('v2_payment');
        Schema::dropIfExists('v2_order');

        Schema::create('v2_payment', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 32);
            $table->string('payment', 64);
            $table->string('name');
            $table->string('icon')->nullable();
            $table->text('config');
            $table->string('notify_domain', 128)->nullable();
            $table->integer('handling_fee_fixed')->nullable();
            $table->decimal('handling_fee_percent', 5)->nullable();
            $table->boolean('enable')->default(false);
            $table->integer('sort')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Schema::create('v2_order', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('plan_id')->nullable();
            $table->integer('payment_id')->nullable();
            $table->string('trade_no');
            $table->integer('status')->default(Order::STATUS_PENDING);
            $table->integer('total_amount')->default(0);
            $table->string('period')->nullable();
            $table->string('callback_no')->nullable();
            $table->integer('paid_at')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });

        Cache::flush();
    }

    public function testWebhookIgnoresOrderPaidWithAnotherPaymentMethod(): void
    {
        $primaryPayment = $this->createPayment('primary-webhook');
        $otherPayment = $this->createPayment('other-webhook');
        $order = Order::query()->create([
            'user_id' => 1,
            'payment_id' => $primaryPayment->id,
            'trade_no' => 'TRADE_NO_1',
            'status' => Order::STATUS_PENDING,
            'total_amount' => 100,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $response = $this->postJson("/api/v1/guest/payment/notify/TestWebhookGateway/{$otherPayment->uuid}", [
            'trade_no' => $order->trade_no,
        ]);

        $response->assertOk();
        $this->assertSame('success', $response->getContent());

        $order->refresh();
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertNull($order->callback_no);
    }

    public function testWebhookReturnsCustomResultWithoutOrderHandling(): void
    {
        $payment = $this->createPayment('ignored-webhook', 'IgnoredWebhookGateway');

        $response = $this->postJson("/api/v1/guest/payment/notify/IgnoredWebhookGateway/{$payment->uuid}", []);

        $response->assertOk();
        $this->assertSame('success', $response->getContent());
    }

    public function testWebhookReturnsStringResultWithoutOrderHandling(): void
    {
        $payment = $this->createPayment('string-webhook', 'StringWebhookGateway');

        $response = $this->postJson("/api/v1/guest/payment/notify/StringWebhookGateway/{$payment->uuid}", []);

        $response->assertOk();
        $this->assertSame('pending', $response->getContent());
    }

    private function createPayment(string $uuid, string $paymentMethod = 'TestWebhookGateway'): Payment
    {
        return Payment::query()->create([
            'uuid' => $uuid,
            'payment' => $paymentMethod,
            'name' => $uuid,
            'config' => [],
            'enable' => true,
            'created_at' => time(),
            'updated_at' => time(),
        ]);
    }
}
