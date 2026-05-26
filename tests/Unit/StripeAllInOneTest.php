<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Payments\StripeALLInOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StripeAllInOneTest extends TestCase
{
    private const WEBHOOK_SECRET = 'whsec_test_secret';

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

        Schema::dropIfExists('v2_order');

        Schema::create('v2_order', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
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

    public function testNotifyAcknowledgesUnsupportedStripeEvents(): void
    {
        $payment = $this->stripePayment();
        $this->bindSignedStripeRequest([
            'id' => 'evt_ignored',
            'object' => 'event',
            'type' => 'customer.created',
            'data' => [
                'object' => [
                    'id' => 'cus_test',
                    'object' => 'customer',
                ],
            ],
        ]);

        $result = $payment->notify([]);

        $this->assertSame(['custom_result' => 'success'], $result);
    }

    public function testNotifyRejectsPaymentIntentAmountMismatch(): void
    {
        Order::query()->create([
            'user_id' => 1,
            'payment_id' => 7,
            'trade_no' => 'TRADE_STRIPE_1',
            'status' => Order::STATUS_PENDING,
            'total_amount' => 10000,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $payment = $this->stripePayment(['id' => 7]);
        $this->bindSignedStripeRequest([
            'id' => 'evt_pi_succeeded',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                    'amount' => 1400,
                    'amount_received' => 1399,
                    'currency' => 'usd',
                    'metadata' => [
                        'out_trade_no' => 'TRADE_STRIPE_1',
                        'stripe_amount' => '1400',
                        'stripe_currency' => 'usd',
                    ],
                ],
            ],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('payment amount mismatch');

        $payment->notify([]);
    }

    public function testNotifyReturnsStructuredErrorForMissingTradeNo(): void
    {
        $payment = $this->stripePayment();
        $this->bindSignedStripeRequest([
            'id' => 'evt_missing_trade_no',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_missing_trade_no',
                    'object' => 'payment_intent',
                    'status' => 'succeeded',
                    'amount_received' => 1000,
                    'currency' => 'usd',
                    'metadata' => [],
                ],
            ],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('order error');

        $payment->notify([]);
    }

    public function testPayRejectsUnsupportedPaymentMethodBeforeExternalRequests(): void
    {
        $payment = $this->stripePayment(['payment_method' => 'paypal']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unsupported Stripe payment method');

        $payment->pay([
            'return_url' => 'https://example.com/order/TRADE',
            'trade_no' => 'TRADE',
            'total_amount' => 1000,
            'user_id' => 1,
        ]);
    }

    private function stripePayment(array $config = []): StripeALLInOne
    {
        return new StripeALLInOne(array_merge([
            'id' => 7,
            'currency' => 'USD',
            'stripe_sk_live' => 'sk_test_dummy',
            'stripe_webhook_key' => self::WEBHOOK_SECRET,
            'description' => 'Test subscription',
            'payment_method' => 'cards',
        ], $config));
    }

    private function bindSignedStripeRequest(array $event): void
    {
        $payload = json_encode($event, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, self::WEBHOOK_SECRET);
        $request = Request::create(
            '/api/v1/guest/payment/notify/StripeALLInOne/test',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        );

        app()->instance('request', $request);
    }
}
