<?php

namespace Tests\Unit;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    public function testNotifyUrlUsesConfiguredAppUrlInsteadOfRequestPort(): void
    {
        config(['v2board.app_url' => 'https://bb.p-p.men']);
        app()->instance('request', Request::create(
            'http://bb.p-p.men:7001/admin/payment',
            'GET',
            [],
            [],
            [],
            ['HTTP_ORIGIN' => 'https://b.p-p.men']
        ));

        $notifyUrl = PaymentService::notifyUrl('StripeALLInOne', 'b0Sm2c8M');

        $this->assertSame(
            'https://bb.p-p.men/api/v1/guest/payment/notify/StripeALLInOne/b0Sm2c8M',
            $notifyUrl
        );
    }

    public function testNotifyUrlUsesCustomNotifyDomainWhenConfigured(): void
    {
        config(['v2board.app_url' => 'https://bb.p-p.men']);

        $notifyUrl = PaymentService::notifyUrl('StripeALLInOne', 'b0Sm2c8M', 'https://pay.example.com/');

        $this->assertSame(
            'https://pay.example.com/api/v1/guest/payment/notify/StripeALLInOne/b0Sm2c8M',
            $notifyUrl
        );
    }

    public function testReturnUrlUsesConfiguredAppUrlInsteadOfRequestPort(): void
    {
        config(['v2board.app_url' => 'https://bb.p-p.men']);
        app()->instance('request', Request::create('http://bb.p-p.men:7001/api/v1/user/order/checkout', 'POST'));

        $returnUrl = PaymentService::returnUrl('2026052608055083999481891');

        $this->assertSame(
            'https://bb.p-p.men/#/order/2026052608055083999481891',
            $returnUrl
        );
    }

    public function testReturnUrlUsesRequestOriginForSeparatedDeployment(): void
    {
        config(['v2board.app_url' => 'https://bb.p-p.men']);
        app()->instance('request', Request::create(
            'http://bb.p-p.men:7001/api/v1/user/order/checkout',
            'POST',
            [],
            [],
            [],
            ['HTTP_ORIGIN' => 'https://b.p-p.men']
        ));

        $returnUrl = PaymentService::returnUrl('2026052608055083999481891');

        $this->assertSame(
            'https://b.p-p.men/#/order/2026052608055083999481891',
            $returnUrl
        );
    }

    public function testReturnUrlCanUseRefererWhenOriginIsUnavailable(): void
    {
        config(['v2board.app_url' => 'https://bb.p-p.men']);
        app()->instance('request', Request::create(
            'http://bb.p-p.men:7001/api/v1/user/order/checkout',
            'POST',
            [],
            [],
            [],
            ['HTTP_REFERER' => 'https://b.p-p.men/dashboard']
        ));

        $returnUrl = PaymentService::returnUrl('2026052608055083999481891');

        $this->assertSame(
            'https://b.p-p.men/#/order/2026052608055083999481891',
            $returnUrl
        );
    }
}
