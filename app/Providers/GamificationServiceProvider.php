<?php

namespace App\Providers;

use App\Services\GamificationService;
use Illuminate\Support\ServiceProvider;

class GamificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GamificationService::class, function ($app) {
            return new GamificationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register scheduled tasks for leaderboard resets
        if ($this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);

                // Run reset at midnight
                $schedule->call(function () {
                    $gamificationService = app(GamificationService::class);
                    $gamificationService->resetLeaderboards();
                })->dailyAt('00:00');
            });
        }
    }
}
