<?php

namespace App\Console\Commands;

use App\Models\BillingPlan;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\UserFeature;
use App\Services\EntitlementService;
use Illuminate\Console\Command;

class MigrateEnrollmentsToEntitlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entitlements:migrate-enrollments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill UserEntitlements for existing CourseEnrollments and fix legacy features';

    /**
     * Execute the console command.
     */
    public function handle(EntitlementService $entitlementService)
    {
        $this->info('Starting migration...');

        $this->info('Checking for legacy features...');
        $features = UserFeature::whereNull('scope_type')
            ->whereNull('scope_id')
            ->get();

        foreach ($features as $feature) {
            $entitlement = $feature->entitlement;
            if (!$entitlement) continue;

            // Try to find course from entitlement's enrollments?
            // Entitlements might not be linked to enrollments yet.
            // But if scope is missing, we need to fix it.

            // For now, just log
            $this->warn("Found feature {$feature->id} without scope for entitlement {$entitlement->id}");
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
                ->whereHas('features', function ($query) use ($course) {
                    $query->where('feature_code', 'content.paid.access') // Assuming this is the main access
                        ->where('scope_type', 'App\Models\Course')
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
