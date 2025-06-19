<?php

namespace App\Observers;

use App\Models\Slide;
use App\Models\TrashItem;

class SlideObserver
{
    /**
     * Handle the Slide "created" event.
     */
    public function created(Slide $slide): void
    {
        //
    }

    /**
     * Handle the Slide "updated" event.
     */
    public function updated(Slide $slide): void
    {
        //
    }

    /**
     * Handle the Slide "deleted" event.
     */
    public function deleted(Slide $slide): void
    {
        // If this is a cascading delete, don't create a trash item
        if (Slide::$cascadingDelete) {
            // Just reorder remaining slides to fill the gap
            $this->reorderSlidesAfterDeletion($slide);
            return;
        }

        // This is a direct deletion, create a trash item record
        TrashItem::create([
            'model_type' => Slide::class,
            'model_id' => $slide->id,
            'name' => "Slide #{$slide->id} ({$slide->type})",
            'deleted_at' => now(),
            'additional_data' => [
                'lesson_id' => $slide->lesson_id,
                'type' => $slide->type,
                'sort_order' => $slide->sort_order,
            ],
        ]);

        // Reorder remaining slides to fill the gap
        $this->reorderSlidesAfterDeletion($slide);
    }

    /**
     * Handle the Slide "restored" event.
     */
    public function restored(Slide $slide): void
    {
        // Remove from trash when restored
        TrashItem::where('model_type', Slide::class)
            ->where('model_id', $slide->id)
            ->delete();

        // Recalculate slide_order based on existing slides
        $this->recalculateSlideOrderAfterRestore($slide);
    }

    /**
     * Handle the Slide "force deleted" event.
     */
    public function forceDeleted(Slide $slide): void
    {
        // Remove from trash when force deleted
        TrashItem::where('model_type', Slide::class)
            ->where('model_id', $slide->id)
            ->delete();
    }

    /**
     * Reorder slides after a slide is deleted.
     */
    private function reorderSlidesAfterDeletion(Slide $deletedSlide): void
    {
        // Get all slides in the same lesson with a higher sort order
        $higherSlides = Slide::where('lesson_id', $deletedSlide->lesson_id)
            ->where('sort_order', '>', $deletedSlide->sort_order)
            ->orderBy('sort_order')
            ->get();

        // Decrease the sort_order of each higher slide by 1
        foreach ($higherSlides as $slide) {
            $slide->sort_order = $slide->sort_order - 1;
            $slide->saveQuietly(); // Save without triggering events
        }
    }

    /**
     * Recalculate slide order after a slide is restored.
     */
    private function recalculateSlideOrderAfterRestore(Slide $restoredSlide): void
    {
        // Get the max sort_order in the lesson
        $maxOrder = Slide::where('lesson_id', $restoredSlide->lesson_id)
            ->max('sort_order');

        // If the restored slide's original position is now occupied
        $conflictingSlide = Slide::where('lesson_id', $restoredSlide->lesson_id)
            ->where('sort_order', $restoredSlide->sort_order)
            ->where('id', '!=', $restoredSlide->id)
            ->first();

        if ($conflictingSlide) {
            // Move all slides with equal or higher sort_order up by 1
            Slide::where('lesson_id', $restoredSlide->lesson_id)
                ->where('sort_order', '>=', $restoredSlide->sort_order)
                ->where('id', '!=', $restoredSlide->id)
                ->increment('sort_order');
        } else {
            // If the original position is available, keep it
            // If the original position is beyond the current max, place it at the end
            if ($restoredSlide->sort_order > $maxOrder) {
                $restoredSlide->sort_order = $maxOrder + 1;
                $restoredSlide->saveQuietly();
            }
        }
    }
}
