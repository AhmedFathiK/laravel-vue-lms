<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Level;
use App\Observers\CourseObserver;
use App\Observers\LevelObserver;
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
        Course::observe(CourseObserver::class);
        Level::observe(LevelObserver::class);
    }
}
