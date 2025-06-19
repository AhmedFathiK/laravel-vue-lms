<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Slide;
use App\Observers\CourseObserver;
use App\Observers\CourseCategoryObserver;
use App\Observers\LessonObserver;
use App\Observers\LevelObserver;
use App\Observers\SlideObserver;
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
        Lesson::observe(LessonObserver::class);
        Slide::observe(SlideObserver::class);
        CourseCategory::observe(CourseCategoryObserver::class);
    }
}
