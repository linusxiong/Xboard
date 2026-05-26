<?php

namespace App\Http\Controllers\V1\Guest;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function notify($method, $uuid, Request $request)
    {
        try {
            $paymentService = new PaymentService($method, null, $uuid);
            $verify = $paymentService->notify($request->input());
            if (!$verify)
                return $this->fail([422, 'verify error']);
            if (is_string($verify)) {
                return $verify;
            }
            if (!is_array($verify)) {
                return $this->fail([422, 'verify error']);
            }
            if (isset($verify['custom_result']) && (!isset($verify['trade_no']) || !isset($verify['callback_no']))) {
                return $verify['custom_result'];
            }
            if (!$this->handle($verify['trade_no'], $verify['callback_no'], $paymentService->getPaymentId())) {
                return $this->fail([400, 'handle error']);
            }
            return (isset($verify['custom_result']) ? $verify['custom_result'] : 'success');
        } catch (ApiException $e) {
            return $this->fail([$e->getCode(), $e->getMessage()], null, $e->errors());
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500, 'fail']);
        }
    }

    private function handle($tradeNo, $callbackNo, $paymentId = null)
    {
        $order = Order::where('trade_no', $tradeNo)->first();
        if (!$order) {
            return $this->fail([400202, 'order is not found']);
        }
        if ($paymentId && (int)$order->payment_id !== (int)$paymentId) {
            return true;
        }
        if ($order->status !== Order::STATUS_PENDING)
            return true;
        $orderService = new OrderService($order);
        if (!$orderService->paid($callbackNo)) {
            return false;
        }

        $payment = Payment::where('id', $order->payment_id)->first();
        $telegramService = new TelegramService();
        $message = sprintf(
            "💰成功收款%s元\n" .
            "———————————————\n" .
            "支付接口：%s\n" .
            "支付渠道：%s\n" .
            "本站订单：`%s`"
            ,
            $order->total_amount / 100,
            $payment->payment,
            $payment->name,
            $order->trade_no
        );

        $telegramService->sendMessageWithAdmin($message);
        return true;
    }
}
