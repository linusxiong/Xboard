<?php

namespace App\Services;


use App\Exceptions\ApiException;
use App\Models\Payment;

class PaymentService
{
    public $method;
    protected $class;
    protected $config;
    protected $payment;
    protected $paymentModel;

    public function __construct($method, $id = NULL, $uuid = NULL)
    {
        $this->method = $method;
        $this->class = '\\App\\Payments\\' . $this->method;
        if (!class_exists($this->class)) throw new ApiException('gate is not found');
        if ($id) $this->paymentModel = Payment::find($id);
        if ($uuid) $this->paymentModel = Payment::where('uuid', $uuid)->first();
        if (($id || $uuid) && !$this->paymentModel) throw new ApiException('gate is not found');
        if ($this->paymentModel && $this->paymentModel->payment !== $this->method) {
            throw new ApiException('gate is not match');
        }
        if ($this->paymentModel) $payment = $this->paymentModel->makeVisible('config')->toArray();
        $this->config = [];
        if (isset($payment)) {
            $this->config = $payment['config'];
            $this->config['enable'] = $payment['enable'];
            $this->config['id'] = $payment['id'];
            $this->config['uuid'] = $payment['uuid'];
            $this->config['notify_domain'] = $payment['notify_domain'];
        };
        $this->payment = new $this->class($this->config);
    }

    public function getPaymentId()
    {
        return $this->paymentModel ? $this->paymentModel->id : null;
    }

    public function notify($params)
    {
        if (!$this->config['enable']) throw new ApiException('gate is not enable');
        return $this->payment->notify($params);
    }

    public function pay($order)
    {
        return $this->payment->pay([
            'notify_url' => self::notifyUrl($this->method, $this->config['uuid'], $this->config['notify_domain']),
            'return_url' => self::returnUrl($order['trade_no']),
            'trade_no' => $order['trade_no'],
            'total_amount' => $order['total_amount'],
            'user_id' => $order['user_id'],
            'stripe_token' => $order['stripe_token']
        ]);
    }

    public static function notifyUrl(string $method, string $uuid, ?string $notifyDomain = null): string
    {
        $path = "/api/v1/guest/payment/notify/{$method}/{$uuid}";
        $baseUrl = $notifyDomain ?: self::publicBaseUrl();

        return rtrim($baseUrl, '/') . $path;
    }

    public static function returnUrl(string $tradeNo): string
    {
        return rtrim(self::publicBaseUrl(), '/') . '/#/order/' . $tradeNo;
    }

    private static function publicBaseUrl(): string
    {
        return admin_setting('app_url') ?: config('app.url');
    }

    public function form()
    {
        $form = $this->payment->form();
        $keys = array_keys($form);
        foreach ($keys as $key) {
            if (isset($this->config[$key])) $form[$key]['value'] = $this->config[$key];
        }
        return $form;
    }
}
