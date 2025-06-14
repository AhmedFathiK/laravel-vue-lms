<?php

namespace App\Observers;

use App\Models\Course;
use App\Models\TrashItem;

class CourseObserver
{
    /**
     * Handle the Course "created" event.
     */
    public function created(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "updated" event.
     */
    public function updated(Course $course): void
    {
        //
    }

    /**
     * Handle the Course "deleted" event.
     */
    public function deleted(Course $course): void
    {
        // Create a trash item record
        TrashItem::create([
            'model_type' => Course::class,
            'model_id' => $course->id,
            'name' => $course->title,
            'deleted_at' => now(),
            'additional_data' => [
                'category_id' => $course->course_category_id,
                'status' => $course->status,
                'is_featured' => $course->is_featured,
                'is_free' => $course->is_free,
            ],
        ]);
    }

    /**
     * Handle the Course "restored" event.
     */
    public function restored(Course $course): void
    {
        // Remove from trash when restored
        TrashItem::where('model_type', Course::class)
            ->where('model_id', $course->id)
            ->delete();

        // Restore all related levels that were soft-deleted with this course
        $course->levels()->onlyTrashed()->each(function ($level) {
            // Check if the level was deleted separately (has its own trash item)
            $hasOwnTrashItem = TrashItem::where('model_type', get_class($level))
                ->where('model_id', $level->id)
                ->exists();

            // Only restore levels that don't have their own trash item
            if (!$hasOwnTrashItem) {
                $level->restore();
            }
        });
    }

    /**
     * Handle the Course "force deleted" event.
     */
    public function forceDeleted(Course $course): void
    {
        // Remove from trash when force deleted
        TrashItem::where('model_type', Course::class)
            ->where('model_id', $course->id)
            ->delete();
    }
}
