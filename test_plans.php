<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillingPlan;
use App\Models\Course;

$course = Course::where('title', 'like', '%German%')->first();
if (!$course) {
    echo "Course not found\n";
    exit;
}

echo "Course ID: " . $course->id . "\n";

$plans = BillingPlan::whereHas('courses', function ($query) use ($course) {
    $query->where('courses.id', $course->id);
})->where('is_active', true)->get();

echo "Plans count: " . $plans->count() . "\n";
foreach ($plans as $plan) {
    echo "Plan: " . $plan->id . " - " . $plan->name . " (Price: " . $plan->price . ")\n";
}
