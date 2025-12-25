<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Receipt;
use App\Models\Slide;
use App\Observers\CourseObserver;
use App\Observers\CourseCategoryObserver;
use App\Observers\LessonObserver;
use App\Observers\LevelObserver;
use App\Observers\ReceiptObserver;
use App\Observers\SlideObserver;
use App\Policies\ReceiptPolicy;
use App\Services\Payment\MyFatoorahService;
use App\Services\Payment\NullPaymentGatewayService;
use App\Services\Payments\PaymentServiceInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentServiceInterface::class, function ($app) {
            $gateway = (string) config('services.payment.gateway', 'myfatoorah');

            return match ($gateway) {
                'myfatoorah' => $app->make(MyFatoorahService::class),
                'null' => $app->make(NullPaymentGatewayService::class),
                default => $app->make(MyFatoorahService::class),
            };
        });
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
        Receipt::observe(ReceiptObserver::class);

        Gate::policy(Receipt::class, ReceiptPolicy::class);
    }
}
