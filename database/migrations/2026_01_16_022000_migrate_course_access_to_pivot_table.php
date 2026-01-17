<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Feature;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $courseAccessFeature = Feature::where('code', 'course.access')->first();

        if ($courseAccessFeature) {
            // Get all plan features that use course.access
            $planFeatures = DB::table('plan_features')
                ->where('feature_id', $courseAccessFeature->id)
                ->where('scope_type', 'App\Models\Course')
                ->get();

            foreach ($planFeatures as $pf) {
                // Insert into new pivot table if not exists
                DB::table('billing_plan_course')->updateOrInsert([
                    'billing_plan_id' => $pf->billing_plan_id,
                    'course_id' => $pf->scope_id,
                ], [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Delete the plan features
            DB::table('plan_features')
                ->where('feature_id', $courseAccessFeature->id)
                ->delete();

            // Delete the feature itself
            $courseAccessFeature->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversal is complex because we'd need to recreate the feature and plan_features
    }
};
