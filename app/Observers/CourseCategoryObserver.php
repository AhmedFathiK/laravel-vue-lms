<?php

namespace App\Observers;

use App\Models\CourseCategory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class CourseCategoryObserver
{
    /**
     * Handle the CourseCategory "creating" event.
     * This will run before a new category is created
     */
    public function creating(CourseCategory $courseCategory): void
    {
        $this->handleSlugGeneration($courseCategory);
    }

    /**
     * Handle the CourseCategory "updating" event.
     * This will run before a category is updated
     */
    public function updating(CourseCategory $courseCategory): void
    {
        $this->handleSlugGeneration($courseCategory);
    }

    /**
     * Handle slug generation based on available translations and current locale
     */
    private function handleSlugGeneration(CourseCategory $courseCategory): void
    {
        // Skip if slug is already set
        if (!empty($courseCategory->slug)) {
            return;
        }

        // Get current locale
        $currentLocale = App::getLocale();

        // Get all translations of the name
        $nameTranslations = $courseCategory->getTranslations('name');

        // Get the dirty attributes to find the new value being set
        $dirty = $courseCategory->getDirty();
        $newName = $dirty['name'] ?? null;

        // Determine which name to use for slug generation
        if ($currentLocale === 'en' && $newName) {
            // If current locale is English and we have a new value, use it
            $nameForSlug = is_array($newName) ? ($newName['en'] ?? reset($newName)) : $newName;
        } else {
            // For non-English locales:
            // 1. Try to use existing English translation if available
            // 2. Otherwise, use the new value in the current locale
            if (isset($nameTranslations['en']) && !empty($nameTranslations['en'])) {
                $nameForSlug = $nameTranslations['en'];
            } elseif ($newName) {
                // Use the new value being set (could be any locale)
                $nameForSlug = is_array($newName) ? reset($newName) : $newName;
            } else {
                // Fallback to any available translation
                $nameForSlug = reset($nameTranslations);
            }
        }

        // Generate the slug
        $slug = Str::slug($nameForSlug);

        // Check if this slug already exists
        $count = 1;
        $originalSlug = $slug;

        while (CourseCategory::where('slug', $slug)
            ->where('id', '!=', $courseCategory->id)
            ->exists()
        ) {
            $slug = $originalSlug . '-' . $count++;
        }

        // Set the slug
        $courseCategory->slug = $slug;
    }

    /**
     * Handle the CourseCategory "deleted" event.
     */
    public function deleted(CourseCategory $courseCategory): void
    {
        //
    }

    /**
     * Handle the CourseCategory "restored" event.
     */
    public function restored(CourseCategory $courseCategory): void
    {
        //
    }

    /**
     * Handle the CourseCategory "force deleted" event.
     */
    public function forceDeleted(CourseCategory $courseCategory): void
    {
        //
    }
}
