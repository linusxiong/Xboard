<?php

namespace App\Support;

use App\Services\AuthService;
use Illuminate\Http\Request;

class HorizonAuthorization
{
    public static function check(Request $request): bool
    {
        $authorization = self::tokenFromRequest($request);
        if (!$authorization) {
            return false;
        }

        $user = AuthService::decryptAuthData($authorization);
        return (bool)($user && !empty($user['is_admin']));
    }

    public static function tokenFromRequest(Request $request): ?string
    {
        $authorization = $request->input('auth_data') ?? $request->bearerToken() ?? $request->header('authorization');
        if (!$authorization) {
            return null;
        }

        if (stripos($authorization, 'Bearer ') === 0) {
            $authorization = trim(substr($authorization, 7));
        }

        return $authorization;
    }
}
