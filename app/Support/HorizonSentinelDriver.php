<?php

namespace App\Support;

use Illuminate\Http\Request;
use Laravel\Sentinel\Drivers\Driver;

class HorizonSentinelDriver extends Driver
{
    public function authorize(Request $request): bool
    {
        return HorizonAuthorization::check($request);
    }
}
