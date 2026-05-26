<?php

namespace App\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StripeALLInOne
{
    private const STRIPE_API_VERSION = '2026-04-22.dahlia';
    private const SUPPORTED_PAYMENT_METHODS = ['alipay', 'wechat_pay', 'cards', 'card'];
    private const ZERO_DECIMAL_CURRENCIES = [
        'bif',
        'clp',
        'djf',
        'gnf',
        'jpy',
        'kmf',
        'krw',
        'mga',
        'pyg',
        'rwf',
        'vnd',
        'vuv',
        'xaf',
        'xof',
        'xpf',
    ];

    protected array $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function form()
    {
        return [
            'currency' => [
                'label' => '货币单位',
                'description' => '请使用符合ISO 4217标准的三位字母，例如GBP',
                'type' => 'input',
            ],
            'stripe_sk_live' => [
                'label' => 'SK_LIVE',
                'description' => '',
                'type' => 'input',
            ],
            'stripe_webhook_key' => [
                'label' => 'WebHook密钥签名',
                'description' => 'whsec_....',
                'type' => 'input',
            ],
            'description' => [
                'label' => '自定义商品介绍',
                'description' => '',
                'type' => 'input',
            ],
            'payment_method' => [
                'label' => '支付方式',
                'description' => '请输入alipay, wechat_pay, cards',
                'type' => 'input',
            ]
        ];
    }

    public function pay($order)
    {
        $paymentMethod = $this->paymentMethod();
        $currency = strtolower($this->config['currency']);
        $stripeAmount = $this->stripeAmount((int)$order['total_amount'], $currency);
        $metadata = $this->metadata($order, $stripeAmount, $currency);
        $jumpUrl = null;
        $actionType = 0;
        $stripe = $this->stripeClient();

        try {
            if ($paymentMethod !== 'card') {
                $statementDescriptorSuffix = $this->statementDescriptorSuffix($order);

                // Confirm immediately because Alipay and WeChat Pay return redirect or QR actions.
                $params = [
                    'amount' => $stripeAmount,
                    'currency' => $currency,
                    'confirm' => true,
                    'payment_method_data' => [
                        'type' => $paymentMethod,
                    ],
                    'payment_method_types' => [$paymentMethod],
                    'statement_descriptor_suffix' => $statementDescriptorSuffix,
                    'description' => $this->config['description'],
                    'metadata' => $metadata,
                    'return_url' => $order['return_url'],
                ];

                if ($paymentMethod === 'wechat_pay') {
                    $params['payment_method_options'] = [
                        'wechat_pay' => [
                            'client' => 'web',
                        ],
                    ];
                }

                $stripeIntents = $stripe->paymentIntents->create($params);

                if (!$stripeIntents['next_action']) {
                    throw new ApiException(__('Payment gateway request failed'));
                }

                $nextAction = $stripeIntents['next_action'];
                switch ($paymentMethod) {
                    case 'alipay':
                        if (!isset($nextAction['alipay_handle_redirect'])) {
                            throw new ApiException('unable get Alipay redirect url', 500);
                        }
                        $jumpUrl = $nextAction['alipay_handle_redirect']['url'];
                        $actionType = 1;
                        break;
                    case 'wechat_pay':
                        if (!isset($nextAction['wechat_pay_display_qr_code'])) {
                            throw new ApiException('unable get WeChat Pay redirect url', 500);
                        }
                        $jumpUrl = $nextAction['wechat_pay_display_qr_code']['data'];
                        break;
                }
            } else {
                $creditCheckOut = $stripe->checkout->sessions->create([
                    'success_url' => $order['return_url'],
                    'cancel_url' => $order['return_url'],
                    'client_reference_id' => $order['trade_no'],
                    'payment_method_types' => ['card'],
                    'metadata' => $metadata,
                    'payment_intent_data' => [
                        'description' => $this->config['description'],
                        'metadata' => $metadata,
                    ],
                    'line_items' => [
                        [
                            'price_data' => [
                                'currency' => $currency,
                                'unit_amount' => $stripeAmount,
                                'product_data' => [
                                    'name' => $this->statementDescriptorSuffix($order),
                                    'description' => $this->config['description'],
                                ],
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'mode' => 'payment',
                ]);
                $jumpUrl = $creditCheckOut['url'];
                $actionType = 1;
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new ApiException($e->getMessage(), $e->getHttpStatus() ?: 500);
        }

        return [
            'type' => $actionType,
            'data' => $jumpUrl,
        ];
    }

    public function notify($params)
    {
        try {
            \Stripe\Stripe::setApiKey($this->config['stripe_sk_live']);
            \Stripe\Stripe::setApiVersion(self::STRIPE_API_VERSION);
            $payload = $GLOBALS['HTTP_RAW_POST_DATA'] ?? request()->getContent();
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $this->signatureHeader(),
                $this->config['stripe_webhook_key']
            );
        } catch (\UnexpectedValueException $e) {
            throw new ApiException('Error parsing payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new ApiException('signature not match', 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->paymentIntentSucceeded($event->data->object);
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                return $this->checkoutSessionPaid($event->data->object);
            default:
                return $this->ignoredWebhookResult();
        }
    }

    private function paymentMethod(): string
    {
        $paymentMethod = strtolower(trim($this->config['payment_method'] ?? ''));

        if (!in_array($paymentMethod, self::SUPPORTED_PAYMENT_METHODS, true)) {
            throw new ApiException('Unsupported Stripe payment method', 400);
        }

        return $paymentMethod === 'cards' ? 'card' : $paymentMethod;
    }

    private function stripeClient(): \Stripe\StripeClient
    {
        return new \Stripe\StripeClient([
            'api_key' => $this->config['stripe_sk_live'],
            'stripe_version' => self::STRIPE_API_VERSION,
            'max_network_retries' => 2,
        ]);
    }

    private function metadata(array $order, int $stripeAmount, string $currency): array
    {
        return [
            'user_id' => (string)$order['user_id'],
            'out_trade_no' => (string)$order['trade_no'],
            'payment_id' => (string)($this->config['id'] ?? ''),
            'stripe_amount' => (string)$stripeAmount,
            'stripe_currency' => $currency,
        ];
    }

    private function statementDescriptorSuffix(array $order): string
    {
        return substr('sub-' . $order['user_id'] . '-' . substr($order['trade_no'], -8), 0, 22);
    }

    private function paymentIntentSucceeded($object): array
    {
        if (($object->status ?? null) !== 'succeeded') {
            return $this->ignoredWebhookResult();
        }

        $tradeNo = $this->metadataValue($object->metadata ?? null, 'out_trade_no');
        $this->assertTradeNo($tradeNo);
        $order = $this->findWebhookOrder($tradeNo);
        if (!$this->belongsToThisPayment($order)) {
            return $this->ignoredWebhookResult();
        }

        $this->assertWebhookAmount(
            $order,
            (int)($object->amount_received ?? $object->amount ?? 0),
            strtolower((string)($object->currency ?? '')),
            $object->metadata ?? null
        );

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $object->id,
        ];
    }

    private function checkoutSessionPaid($object): array
    {
        if (($object->payment_status ?? null) !== 'paid') {
            return $this->ignoredWebhookResult();
        }

        $tradeNo = (string)($object->client_reference_id ?? '');
        $this->assertTradeNo($tradeNo);
        $order = $this->findWebhookOrder($tradeNo);
        if (!$this->belongsToThisPayment($order)) {
            return $this->ignoredWebhookResult();
        }

        $this->assertWebhookAmount(
            $order,
            (int)($object->amount_total ?? 0),
            strtolower((string)($object->currency ?? '')),
            $object->metadata ?? null
        );

        return [
            'trade_no' => $tradeNo,
            'callback_no' => $object->payment_intent ?: $object->id,
        ];
    }

    private function assertTradeNo(?string $tradeNo): void
    {
        if (!$tradeNo) {
            throw new ApiException('order error', 400);
        }
    }

    private function findWebhookOrder(string $tradeNo): Order
    {
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            throw new ApiException('order error', 400);
        }

        return $order;
    }

    private function belongsToThisPayment(Order $order): bool
    {
        return empty($this->config['id']) || (int)$order->payment_id === (int)$this->config['id'];
    }

    private function assertWebhookAmount(Order $order, int $actualAmount, string $actualCurrency, $metadata): void
    {
        $expectedCurrency = strtolower($this->metadataValue($metadata, 'stripe_currency') ?: $this->config['currency']);
        $expectedAmount = $this->metadataValue($metadata, 'stripe_amount');
        $expectedAmount = $expectedAmount !== null && $expectedAmount !== ''
            ? (int)$expectedAmount
            : $this->stripeAmount((int)$order->total_amount, $expectedCurrency);

        if ($actualCurrency !== $expectedCurrency) {
            throw new ApiException('payment currency mismatch', 400);
        }

        if ($actualAmount !== $expectedAmount) {
            throw new ApiException('payment amount mismatch', 400);
        }
    }

    private function metadataValue($metadata, string $key): ?string
    {
        if (is_array($metadata)) {
            return isset($metadata[$key]) ? (string)$metadata[$key] : null;
        }

        if (is_object($metadata) && isset($metadata->{$key})) {
            return (string)$metadata->{$key};
        }

        return null;
    }

    private function ignoredWebhookResult(): array
    {
        return ['custom_result' => 'success'];
    }

    private function signatureHeader(): string
    {
        $signatureHeader = request()->header('Stripe-Signature', '');
        if ($signatureHeader) {
            return $signatureHeader;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'stripe-signature') {
                return $value;
            }
        }

        return '';
    }

    private function stripeAmount(int $cnyMinorAmount, string $currency): int
    {
        $exchange = $this->exchange('CNY', $currency);
        $majorAmount = ($cnyMinorAmount / 100) * $exchange;
        $amount = in_array(strtolower($currency), self::ZERO_DECIMAL_CURRENCIES, true)
            ? (int)round($majorAmount)
            : (int)round($majorAmount * 100);

        if ($amount <= 0) {
            throw new ApiException('Invalid Stripe payment amount', 400);
        }

        return $amount;
    }

    private function exchange($from, $to): float
    {
        $from = strtolower($from);
        $to = strtolower($to);

        if ($from === $to) {
            return 1.0;
        }

        return Cache::remember("stripe_exchange_{$from}_{$to}", 3600, function () use ($from, $to) {
            try {
                $response = Http::timeout(5)
                    ->retry(1, 100)
                    ->get("https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/{$from}.min.json");

                if (!$response->ok()) {
                    throw new \RuntimeException('Currency API request failed');
                }

                $rate = $response->json("{$from}.{$to}");
                if (!is_numeric($rate) || (float)$rate <= 0) {
                    throw new \RuntimeException('Currency API response is invalid');
                }

                return (float)$rate;
            } catch (\Throwable $e) {
                throw new ApiException('Currency conversion has timed out, please try again later', 500);
            }
        });
    }
}
