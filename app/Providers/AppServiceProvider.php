<?php

namespace App\Providers;

use App\Support\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['view']->addNamespace('theme', public_path() . '/theme');

        $this->app['validator']->resolver(function ($translator, $data, $rules, $messages, $attributes) {
            return new Validator($translator, $data, $rules, $messages, $attributes);
        });
    }
}
