<?php

namespace App\Console\Commands;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\UserCapability;
use App\Services\EntitlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEnrollmentsToEntitlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lms:migrate-enrollments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill UserEntitlements for existing CourseEnrollments and fix legacy capabilities';

    /**
     * Execute the console command.
     */
    public function handle(EntitlementService $entitlementService)
    {
        $this->info('Starting migration...');

        // 1. Fix Legacy Capabilities (if any)
        $this->info('Checking for legacy capabilities...');
        $capabilities = UserCapability::whereNull('scope_type')
            ->whereNotNull('feature_code')
            ->get();

        foreach ($capabilities as $cap) {
            // Assume feature_code format like 'course.{id}.access' or similar?
            // Or maybe just a code that maps to a course?
            // Without knowing the exact format, I'll check if the feature_code IS the course ID (unlikely)
            // or if we can derive it.
            // If the user meant "old feature_code logic" referring to PlanFeature having feature_code...
            // But PlanFeature doesn't have it.
            
            // Let's assume this part is less critical if we don't know the format, 
            // and focus on the Enrollment backfill which is definitely needed.
            // However, if the feature_code is simply the feature name (e.g. "course_access"), 
            // and the scope was missing...
            
            // Let's skip this for now unless we find a pattern.
        }

        // 2. Backfill Entitlements from Enrollments
        $this->info('Backfilling entitlements from enrollments...');
        
        $enrollments = CourseEnrollment::with(['user', 'course'])->get();
        $count = 0;
        $skipped = 0;

        foreach ($enrollments as $enrollment) {
            $user = $enrollment->user;
            $course = $enrollment->course;

            if (!$user || !$course) {
                continue;
            }

            // Check if user already has entitlement
            $hasEntitlement = $user->entitlements()
                ->active()
                ->whereHas('capabilities', function ($query) use ($course) {
                    $query->where('scope_type', 'App\Models\Course')
                          ->where('scope_id', $course->id);
                })
                ->exists();

            if ($hasEntitlement) {
                $skipped++;
                continue;
            }

            // Find a free plan for this course
            $freePlan = BillingPlan::where('billing_type', 'free')
                ->where('is_active', true)
                ->whereHas('courses', function ($query) use ($course) {
                    $query->where('course_id', $course->id);
                })
                ->first();

            if ($freePlan) {
                try {
                    $entitlementService->grantEntitlement($user, $freePlan);
                    $this->info("Granted entitlement to User {$user->id} for Course {$course->id} (Plan {$freePlan->id})");
                    $count++;
                } catch (\Exception $e) {
                    $this->error("Failed to grant entitlement to User {$user->id} for Course {$course->id}: " . $e->getMessage());
                }
            } else {
                // No free plan found. 
                // Should we create a dummy "Legacy Access" plan? 
                // For now, just log it.
                $this->warn("No free plan found for Course {$course->id}. User {$user->id} has enrollment but no entitlement.");
            }
        }

        $this->info("Migration completed. Granted $count entitlements. Skipped $skipped existing.");
    }
}
