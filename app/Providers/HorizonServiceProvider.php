<?php

namespace App\Providers;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Horizon::auth(function (Request $request) {
            $authorization = $request->input('auth_data') ?? $request->header('authorization');
            if (!$authorization) {
                return false;
            }

            if (stripos($authorization, 'Bearer ') === 0) {
                $authorization = trim(substr($authorization, 7));
            }

            $user = AuthService::decryptAuthData($authorization);
            return (bool)($user && !empty($user['is_admin']));
        });

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');

        // Horizon::night();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * @return void
     */
    protected function gate()
    {
        Gate::define('viewHorizon', function ($user) {
            return in_array($user->email, [
                //
            ]);
        });
    }
}
