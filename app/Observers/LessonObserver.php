<?php

namespace App\Observers;

use App\Models\Lesson;
use App\Models\TrashItem;

class LessonObserver
{
    /**
     * Handle the Lesson "created" event.
     */
    public function created(Lesson $lesson): void
    {
        //
    }

    /**
     * Handle the Lesson "updated" event.
     */
    public function updated(Lesson $lesson): void
    {
        //
    }

    /**
     * Handle the Lesson "deleted" event.
     */
    public function deleted(Lesson $lesson): void
    {
        // If this is a cascading delete, don't create a trash item
        if (Lesson::$cascadingDelete) {
            // Just reorder remaining lessons to fill the gap
            $this->reorderLessonsAfterDeletion($lesson);
            return;
        }

        // This is a direct deletion, create a trash item record
        TrashItem::create([
            'model_type' => Lesson::class,
            'model_id' => $lesson->id,
            'name' => $lesson->title,
            'deleted_at' => now(),
            'additional_data' => [
                'level_id' => $lesson->level_id,
                'sort_order' => $lesson->sort_order,
                'status' => $lesson->status,
            ],
        ]);

        // Reorder remaining lessons to fill the gap
        $this->reorderLessonsAfterDeletion($lesson);
    }

    /**
     * Handle the Lesson "restored" event.
     */
    public function restored(Lesson $lesson): void
    {
        // Remove from trash when restored
        TrashItem::where('model_type', Lesson::class)
            ->where('model_id', $lesson->id)
            ->delete();

        // Recalculate lesson_order based on existing lessons
        $this->recalculateLessonOrderAfterRestore($lesson);

        // Restore all related slides that were soft-deleted with this lesson
        $lesson->slides()->onlyTrashed()->each(function ($slide) {
            // Check if the slide was deleted separately (has its own trash item)
            $hasOwnTrashItem = TrashItem::where('model_type', get_class($slide))
                ->where('model_id', $slide->id)
                ->exists();

            // Only restore slides that don't have their own trash item
            if (!$hasOwnTrashItem) {
                $slide->restore();
            }
        });

        // Restore user progress records
        $lesson->studiedBy()->onlyTrashed()->restore();
    }

    /**
     * Handle the Lesson "force deleted" event.
     */
    public function forceDeleted(Lesson $lesson): void
    {
        // Remove from trash when force deleted
        TrashItem::where('model_type', Lesson::class)
            ->where('model_id', $lesson->id)
            ->delete();
    }

    /**
     * Reorder lessons after a lesson is deleted.
     */
    private function reorderLessonsAfterDeletion(Lesson $deletedLesson): void
    {
        // Get all lessons in the same level with a higher sort order
        $higherLessons = Lesson::where('level_id', $deletedLesson->level_id)
            ->where('sort_order', '>', $deletedLesson->sort_order)
            ->orderBy('sort_order')
            ->get();

        // Decrease the sort_order of each higher lesson by 1
        foreach ($higherLessons as $lesson) {
            $lesson->sort_order = $lesson->sort_order - 1;
            $lesson->saveQuietly(); // Save without triggering events
        }
    }

    /**
     * Recalculate lesson order after a lesson is restored.
     */
    private function recalculateLessonOrderAfterRestore(Lesson $restoredLesson): void
    {
        // Get the max sort_order in the level
        $maxOrder = Lesson::where('level_id', $restoredLesson->level_id)
            ->max('sort_order');

        // If the restored lesson's original position is now occupied
        $conflictingLesson = Lesson::where('level_id', $restoredLesson->level_id)
            ->where('sort_order', $restoredLesson->sort_order)
            ->where('id', '!=', $restoredLesson->id)
            ->first();

        if ($conflictingLesson) {
            // Move all lessons with equal or higher sort_order up by 1
            Lesson::where('level_id', $restoredLesson->level_id)
                ->where('sort_order', '>=', $restoredLesson->sort_order)
                ->where('id', '!=', $restoredLesson->id)
                ->increment('sort_order');
        } else {
            // If the original position is available, keep it
            // If the original position is beyond the current max, place it at the end
            if ($restoredLesson->sort_order > $maxOrder) {
                $restoredLesson->sort_order = $maxOrder + 1;
                $restoredLesson->saveQuietly();
            }
        }
    }
}
