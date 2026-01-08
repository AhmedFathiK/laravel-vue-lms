<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\Slide;
use App\Models\TrashItem;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

class VerifyCascadingDelete extends Command
{
    protected $signature = 'test:cascading-delete';

    public function handle()
    {
        $course = Course::first();
        if (!$course) {
            $this->error("No course found.");
            return;
        }

        DB::beginTransaction();

        try {
            $this->info("1. Creating test data...");
            $level = Level::create([
                'course_id' => $course->id,
                'title' => ['en' => 'Test Level', 'ar' => 'مستوى تجريبي'],
                'sort_order' => 999,
            ]);

            $lesson = Lesson::create([
                'level_id' => $level->id,
                'title' => 'Test Lesson',
                'sort_order' => 1,
            ]);

            $slide = Slide::create([
                'lesson_id' => $lesson->id,
                'type' => 'explanation',
                'content' => ['en' => 'Test Slide Content'],
                'sort_order' => 1,
            ]);

            $this->info("   Level ID: {$level->id}, Lesson ID: {$lesson->id}, Slide ID: {$slide->id}");

            $this->info("\n2. Soft deleting Level...");
            $level->delete();

            $levelStatus = Level::withTrashed()->find($level->id)->deleted_at !== null;
            $lessonStatus = Lesson::withTrashed()->find($lesson->id)->deleted_at !== null;
            $slideStatus = Slide::withTrashed()->find($slide->id)->deleted_at !== null;

            $this->info("   Level soft deleted: " . ($levelStatus ? "YES" : "NO"));
            $this->info("   Lesson soft deleted: " . ($lessonStatus ? "YES" : "NO"));
            $this->info("   Slide soft deleted: " . ($slideStatus ? "YES" : "NO"));

            $levelTrash = TrashItem::where('model_type', Level::class)->where('model_id', $level->id)->exists();
            $lessonTrash = TrashItem::where('model_type', Lesson::class)->where('model_id', $lesson->id)->exists();
            $slideTrash = TrashItem::where('model_type', Slide::class)->where('model_id', $slide->id)->exists();

            $this->info("   Trash record for Level exists: " . ($levelTrash ? "YES" : "NO"));
            $this->info("   Trash record for Lesson exists: " . ($lessonTrash ? "YES" : "NO") . " (Expected: NO)");
            $this->info("   Trash record for Slide exists: " . ($slideTrash ? "YES" : "NO") . " (Expected: NO)");

            if ($levelTrash && !$lessonTrash && !$slideTrash) {
                $this->info("\nSUCCESS: Soft delete logic working as expected!");
            } else {
                $this->error("\nFAILURE: Soft delete logic check failed.");
            }

            $this->info("\n3. Restoring Level...");
            $level->restore();

            $levelRestored = Level::find($level->id)->deleted_at === null;
            $lessonRestored = Lesson::find($lesson->id)->deleted_at === null;
            $slideRestored = Slide::find($slide->id)->deleted_at === null;

            $this->info("   Level restored: " . ($levelRestored ? "YES" : "NO"));
            $this->info("   Lesson restored: " . ($lessonRestored ? "YES" : "NO"));
            $this->info("   Slide restored: " . ($slideRestored ? "YES" : "NO"));

            $levelTrashAfterRestore = TrashItem::where('model_type', Level::class)->where('model_id', $level->id)->exists();
            $this->info("   Trash record for Level still exists: " . ($levelTrashAfterRestore ? "YES" : "NO"));

            if ($levelRestored && $lessonRestored && $slideRestored && !$levelTrashAfterRestore) {
                $this->info("\nSUCCESS: Restoration logic working as expected!");
            } else {
                $this->error("\nFAILURE: Restoration logic check failed.");
            }
        } catch (\Exception $e) {
            $this->error("ERROR: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        } finally {
            DB::rollBack();
            $this->info("\nTransaction rolled back.");
        }
    }
}
