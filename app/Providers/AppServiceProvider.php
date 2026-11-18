<?php

namespace App\Providers;

use App\Support\CaBundle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        if (! CaBundle::isSystemConfigured()) {
            $verify = CaBundle::httpVerifyOption();
            if (is_string($verify)) {
                Http::globalOptions(['verify' => $verify]);
            }
        }
    }
}
