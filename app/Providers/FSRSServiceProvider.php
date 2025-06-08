<?php

namespace App\Providers;

use App\Services\FSRSService;
use Illuminate\Support\ServiceProvider;

class FSRSServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FSRSService::class, function ($app) {
            return new FSRSService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
