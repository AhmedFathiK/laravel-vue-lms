<?php

namespace App\Services;

use App\Models\Concept;
use App\Models\Course;
use App\Models\RevisionItem;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class RevisionSessionService
{
    /**
     * Generate a revision session with questions.
     */
    public function generateSession(User $user, ?Course $course = null, string $type = 'both', int $limit = 20, bool $earlyReview = false): array
    {
        // 0. Get allowed course IDs
        $allowedCourseIds = $user->entitlements()
            ->active()
            ->whereHas('capabilities', function ($q) {
                $q->where('feature_code', 'revision.access')
                  ->where('scope_type', 'App\Models\Course');
            })
            ->with(['capabilities' => function ($q) {
                $q->where('feature_code', 'revision.access')
                  ->where('scope_type', 'App\Models\Course');
            }])
            ->get()
            ->flatMap(function ($entitlement) {
                return $entitlement->capabilities->pluck('scope_id');
            })
            ->unique()
            ->values()
            ->all();

        // 1. Fetch due items
        $query = RevisionItem::where('user_id', $user->id)
            ->with('revisionable')
            ->orderBy('due_date', 'asc');

        if (!$earlyReview) {
            $query->where('due_date', '<=', now());
        }

        if ($course) {
            // Check if user has access to this course
            if (!in_array($course->id, $allowedCourseIds)) {
                return [];
            }

            $query->whereHasMorph(
                'revisionable',
                [Term::class, Concept::class],
                function ($q) use ($course) {
                    $q->where('course_id', $course->id);
                }
            );
        } else {
            // Filter by allowed courses
            $query->whereHasMorph(
                'revisionable',
                [Term::class, Concept::class],
                function ($q) use ($allowedCourseIds) {
                    $q->whereIn('course_id', $allowedCourseIds);
                }
            );
        }

        if ($type !== 'both') {
            $modelType = $type === 'term' ? Term::class : Concept::class;
            $query->where('revisionable_type', $modelType);
        }

        $items = $query->take($limit)->get();

        // 2. Generate questions for each item
        $sessionSlides = [];

        foreach ($items as $item) {
            $slides = [];
            if ($item->revisionable_type === Term::class) {
                $slides = $this->generateTermQuestions($item->revisionable, $course, $user);
            } elseif ($item->revisionable_type === Concept::class) {
                $slides = $this->generateConceptQuestions($item->revisionable, $user);
            }

            foreach ($slides as $slide) {
                $sessionSlides[] = [
                    'revision_item_id' => $item->id,
                    'type' => $slide['type'],
                    'data' => $slide,
                ];
            }
        }

        // 3. Shuffle slides to avoid grouping
        shuffle($sessionSlides);

        return $sessionSlides;
    }

    /**
     * Generate questions for a Term.
     */
    private function generateTermQuestions(Term $term, ?Course $course, User $user): array
    {
        $questions = [];
        $uiLocale = $user->interface_language ?? 'en';
        $targetLocale = $term->course->locale ?? 'en';

        // Type A: Fill in blank (Target -> UI Lang Meaning)
        // Show Image/Audio, ask for meaning in UI lang
        if ($term->meaning) {
            $questions[] = [
                'type' => 'fill_blank',
                'title' => 'Translate the term',
                'question' => [
                    'questionText' => 'What is the meaning of this term? [blank1]',
                    'media_url' => $term->media_url,
                    'media_type' => $term->media_type,
                    'audio_url' => $term->audio_url,
                    'term_text' => $term->term,
                    'content' => [
                        'blanks' => [
                            ['answer' => $term->getTranslation('meaning', $uiLocale)]
                        ]
                    ]
                ],
                // 'term' => $term, // Removed as requested
                'mode' => 'meaning_input',
            ];
        }

        // Type B: Fill in blank (Target Audio -> Target Term)
        // Dictation
        $questions[] = [
            'type' => 'fill_blank',
            'title' => 'Type what you hear',
            'question' => [
                'questionText' => 'Type the term you hear/see. [blank1]',
                'media_url' => $term->media_url,
                'media_type' => $term->media_type,
                'audio_url' => $term->audio_url,
                'term_text' => null, // Don't show term text as it is the answer
                'content' => [
                    'blanks' => [
                        ['answer' => $term->term]
                    ]
                ]
            ],
            // 'term' => $term, // Removed as requested
            'mode' => 'term_input',
        ];

        // Type C: Matching (Term <-> Meaning)
        // Need distractors
        $distractors = Term::where('course_id', $term->course_id)
            ->where('id', '!=', $term->id)
            ->inRandomOrder()
            ->take(2)
            ->get();

        $pairs = [];
        // Main pair
        $pairs[] = [
            'left' => $term->term,
            'right' => $term->getTranslation('meaning', $uiLocale),
        ];
        // Distractors
        foreach ($distractors as $distractor) {
            $pairs[] = [
                'left' => $distractor->term,
                'right' => $distractor->getTranslation('meaning', $uiLocale),
            ];
        }
        shuffle($pairs);

        $questions[] = [
            'type' => 'matching',
            'title' => 'Match terms',
            'question' => [
                'questionText' => 'Match the terms with their meanings.',
                'content' => [
                    'pairs' => $pairs,
                    'options' => $pairs, // For compatibility
                ]
            ],
            // 'term' => $term, // Removed as requested
        ];

        // Type D: Reordering (Sentence)
        $example = $term->example;
        if ($example && str_word_count($example) > 3) {
            $words = explode(' ', $example);
            // Do not shuffle here; frontend component handles shuffling and assumes input is correct order

            $questions[] = [
                'type' => 'reordering',
                'title' => 'Order the sentence',
                'question' => [
                    'questionText' => 'Put the words in the correct order.',
                    'media_url' => $term->media_url,
                    'media_type' => $term->media_type,
                    'audio_url' => $term->audio_url, // Maybe show term media for context?
                    'content' => [
                        'items' => $words,
                        'correct_order' => $words,
                        'options' => $words,
                        'correct' => $example
                    ]
                ],
                // 'term' => $term, // Removed as requested
            ];
        }

        // Return top 3 (or all if > 3?) "Show student the picture with term and the student writes the meaning... you need the student to go through 3 questions".
        // I'll return up to 3 or 4.

        return array_slice($questions, 0, 3);
    }

    /**
     * Generate questions for a Concept.
     */
    private function generateConceptQuestions(Concept $concept, User $user): array
    {
        $questions = $concept->questions()->inRandomOrder()->take(3)->get();

        $slides = [];
        if ($questions->isEmpty()) {
            // Log warning as requested
            Log::warning("Concept {$concept->id} has no related questions for revision.");
            return [];
        }

        // If < 3, repeat available questions
        $count = $questions->count();
        $needed = 3;
        $pool = $questions->all();

        for ($i = 0; $i < $needed; $i++) {
            $question = $pool[$i % $count]; // Cycle through available

            // Format for frontend
            $slides[] = [
                'type' => $question->type ?? 'mcq', // Default to mcq if type missing
                'title' => $concept->title,
                'question' => [
                    'questionText' => $question->text ?? $question->question_text, // Handle schema change
                    'content' => $question->content, // JSON options
                    'id' => $question->id
                ],
                'concept_id' => $concept->id
            ];
        }

        return $slides;
    }

    /**
     * Calculate FSRS Grade based on session results for an item.
     * Results: array of booleans (true = correct, false = wrong)
     */
    public function calculateGrade(array $results): int
    {
        $correctCount = count(array_filter($results));
        $total = count($results);

        // Logic:
        // 0 correct -> GRADE_AGAIN (1)
        // 1 correct -> GRADE_HARD (2)
        // 2 correct -> GRADE_GOOD (3)
        // 3+ correct -> GRADE_EASY (4)

        if ($correctCount === 0) {
            return 1; // Again
        } elseif ($correctCount === 1) {
            return 2; // Hard
        } elseif ($correctCount === 2) {
            return 3; // Good
        } else {
            return 4; // Easy
        }
    }
}
