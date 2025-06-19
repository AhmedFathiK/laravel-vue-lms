<?php

namespace App\Observers;

use App\Models\Level;
use App\Models\TrashItem;
use App\Models\Lesson;

class LevelObserver
{
    /**
     * Handle the Level "created" event.
     */
    public function created(Level $level): void
    {
        //
    }

    /**
     * Handle the Level "updated" event.
     */
    public function updated(Level $level): void
    {
        //
    }

    /**
     * Handle the Level "deleted" event.
     */
    public function deleted(Level $level): void
    {
        // If this is a cascading delete from course, don't create a trash item
        if (Level::$cascadingDelete) {
            // Just reorder remaining levels to fill the gap
            $this->reorderLevelsAfterDeletion($level);
            return;
        }

        // This is a direct deletion, create a trash item record
        TrashItem::create([
            'model_type' => Level::class,
            'model_id' => $level->id,
            'name' => $level->title,
            'deleted_at' => now(),
            'additional_data' => [
                'course_id' => $level->course_id,
                'sort_order' => $level->sort_order,
                'status' => $level->status,
                'is_free' => $level->is_free,
            ],
        ]);

        // Reorder remaining levels to fill the gap
        $this->reorderLevelsAfterDeletion($level);
    }

    /**
     * Handle the Level "restored" event.
     */
    public function restored(Level $level): void
    {
        // Remove from trash when restored
        TrashItem::where('model_type', Level::class)
            ->where('model_id', $level->id)
            ->delete();

        // Recalculate level_order based on existing levels
        $this->recalculateLevelOrderAfterRestore($level);

        // Restore all related lessons that were soft-deleted with this level
        Lesson::onlyTrashed()
            ->where('level_id', $level->id)
            ->get()
            ->each(function ($lesson) {
                // Check if the lesson was deleted separately (has its own trash item)
                $hasOwnTrashItem = TrashItem::where('model_type', get_class($lesson))
                    ->where('model_id', $lesson->id)
                    ->exists();

                // Only restore lessons that don't have their own trash item
                if (!$hasOwnTrashItem) {
                    $lesson->restore();
                }
            });
    }

    /**
     * Handle the Level "force deleted" event.
     */
    public function forceDeleted(Level $level): void
    {
        // Remove from trash when force deleted
        TrashItem::where('model_type', Level::class)
            ->where('model_id', $level->id)
            ->delete();
    }

    /**
     * Reorder levels after a level is deleted.
     */
    private function reorderLevelsAfterDeletion(Level $deletedLevel): void
    {
        // Get all levels in the same course with a higher sort order
        $higherLevels = Level::where('course_id', $deletedLevel->course_id)
            ->where('sort_order', '>', $deletedLevel->sort_order)
            ->orderBy('sort_order')
            ->get();

        // Decrease the sort_order of each higher level by 1
        foreach ($higherLevels as $level) {
            $level->sort_order = $level->sort_order - 1;
            $level->saveQuietly(); // Save without triggering events
        }
    }

    /**
     * Recalculate level order after a level is restored.
     */
    private function recalculateLevelOrderAfterRestore(Level $restoredLevel): void
    {
        // Get the max sort_order in the course
        $maxOrder = Level::where('course_id', $restoredLevel->course_id)
            ->max('sort_order');

        // If the restored level's original position is now occupied
        $conflictingLevel = Level::where('course_id', $restoredLevel->course_id)
            ->where('sort_order', $restoredLevel->sort_order)
            ->where('id', '!=', $restoredLevel->id)
            ->first();

        if ($conflictingLevel) {
            // Move all levels with equal or higher sort_order up by 1
            Level::where('course_id', $restoredLevel->course_id)
                ->where('sort_order', '>=', $restoredLevel->sort_order)
                ->where('id', '!=', $restoredLevel->id)
                ->increment('sort_order');
        } else {
            // If the original position is available, keep it
            // If the original position is beyond the current max, place it at the end
            if ($restoredLevel->sort_order > $maxOrder) {
                $restoredLevel->sort_order = $maxOrder + 1;
                $restoredLevel->saveQuietly();
            }
        }
    }
}
