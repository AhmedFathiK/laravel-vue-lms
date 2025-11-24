<?php

namespace App\Services;

use App\Models\RevisionItem;
use Carbon\Carbon;

class FSRSService
{
    // FSRS-5 default parameters (19 weights)
    private array $weights = [
        0.40255, 1.18385, 3.173, 15.69105, 7.1949, 0.5345, 1.4604, 0.0046, 
        1.54575, 0.1192, 1.01925, 1.9395, 0.11, 0.29605, 2.2698, 0.2315, 
        2.9898, 0.51655, 0.6621
    ];

    // Grade constants
    const GRADE_AGAIN = 1;
    const GRADE_HARD = 2;
    const GRADE_GOOD = 3;
    const GRADE_EASY = 4;

    // Configuration
    private float $requestRetention = 0.9;    // Target retention rate
    private float $maximumInterval = 36500;   // Maximum interval in days
    

    public function __construct(?array $customWeights = null)
    {
        if ($customWeights && count($customWeights) === 19) {
            $this->weights = $customWeights;
        }
    }

    /**
     * FORMULA 1: Initial Stability after first rating
     * S₀(G) = w[G-1] where G ∈ {1,2,3,4}
     */
    private function getInitialStability(int $grade): float
    {
        return max(0.1, $this->weights[$grade - 1]);
    }

    /**
     * FORMULA 2: Initial Difficulty after first rating
     * D₀(G) = w[4] - e^(w[5] * (G - 1)) + 1
     * Constrained to [1, 10]
     */
    private function getInitialDifficulty(int $grade): float
    {
        $difficulty = $this->weights[4] - exp($this->weights[5] * ($grade - 1)) + 1;
        return min(max(1.0, $difficulty), 10.0);
    }

    /**
     * FORMULA 3: Difficulty update after review (Linear Damping & Mean Reversion)
     * D' = D - w[6] * (G - 3)
     * D'' = w[7] * D₀(4) + (1 - w[7]) * D'
     * Constrained to [1, 10]
     */
    private function updateDifficulty(float $currentDifficulty, int $grade): float
    {
        $w = $this->weights;
        $newDifficulty = $currentDifficulty - $w[6] * ($grade - 3);
        
        // Mean reversion
        $initialDifficultyEasy = $this->getInitialDifficulty(self::GRADE_EASY);
        $meanRevertedDifficulty = $w[7] * $initialDifficultyEasy + (1 - $w[7]) * $newDifficulty;

        return min(max(1.0, $meanRevertedDifficulty), 10.0);
    }

    /**
     * FORMULA 4: Retrievability (Forgetting Curve)
     * R(t,S) = (1 + t/(9×S))^(-1)
     */
    private function calculateRetrievability(float $stability, float $elapsedDays): float
    {
        return pow(1 + $elapsedDays / (9 * $stability), -1);
    }

    /**
     * FORMULA 5: Interval calculation from stability and retention
     * I = S × ln(R) / ln(0.9)
     */
    private function calculateInterval(float $stability, float $targetRetention): float
    {
        if ($targetRetention >= 0.99) {
            return $stability;
        }
        return $stability * log($targetRetention) / log(0.9);
    }

    /**
     * FORMULA 6: Stability after successful review (Recall)
     * S' = S * (1 + e^(w[8]) * (11 - D) * S^(-w[9]) * (e^((1-R)*w[10]) - 1))
     */
    private function calculateStabilityAfterRecall(
        float $difficulty,
        float $stability,
        float $retrievability
    ): float {
        $w = $this->weights;

        $stabilityIncrease = 1 + (
            exp($w[8]) *
            (11 - $difficulty) *
            pow($stability, -$w[9]) *
            (exp((1 - $retrievability) * $w[10]) - 1)
        );

        return $stability * $stabilityIncrease;
    }

    /**
     * FORMULA 7: Post-lapse stability (after forgetting/lapse)
     * S' = w[11] * D^(-w[12]) * ((S+1)^w[13] - 1) * e^(w[14] * (1-R))
     */
    private function calculatePostLapseStability(float $difficulty, float $stability, float $retrievability): float
    {
        $w = $this->weights;

        $postLapseStability = $w[11] *
            pow($difficulty, -$w[12]) *
            (pow($stability + 1, $w[13]) - 1) *
            exp($w[14] * (1 - $retrievability));

        return max(0.1, $postLapseStability);
    }

    /**
     * Initialize a new revision item
     */
    public function initializeRevisionItem(RevisionItem $item, int $grade): RevisionItem
    {
        // Calculate initial values using FSRS formulas
        $stability = $this->getInitialStability($grade);
        $difficulty = $this->getInitialDifficulty($grade);

        // Calculate first interval
        $interval = $this->calculateInterval($stability, $this->requestRetention);
        $interval = min(max(1, round($interval)), $this->maximumInterval);

        $now = now();

        // Update the revision item
        $item->difficulty = round($difficulty, 3);
        $item->stability = round($stability, 3);
        $item->interval = (int)$interval;
        $item->due_date = $now->copy()->addDays($interval);
        $item->last_review = $now;
        $item->review_count = 1;
        $item->lapse_count = ($grade === self::GRADE_AGAIN) ? 1 : 0;
        $item->state = ($grade === self::GRADE_AGAIN) ? 'relearning' : 'learning';
        $item->response_history = [
            [
                'date' => $now->toDateTimeString(),
                'grade' => $grade,
                'elapsed_days' => 0
            ]
        ];

        return $item;
    }

    /**
     * Update a revision item after review
     */
    public function updateRevisionItem(RevisionItem $item, int $grade, ?Carbon $reviewDate = null): RevisionItem
    {
        $reviewDate = $reviewDate ?? now();
        $lastReview = $item->last_review ?? $reviewDate->copy()->subDay();

        // Calculate elapsed time and current retrievability
        $elapsedDays = $lastReview->diffInDays($reviewDate, false);
        $elapsedDays = max(0, $elapsedDays); // Ensure non-negative
        $retrievability = $this->calculateRetrievability($item->stability, $elapsedDays);

        // Update difficulty (applies to all grades)
        $newDifficulty = $this->updateDifficulty($item->difficulty, $grade);

        // Calculate new stability based on grade
        if ($grade === self::GRADE_AGAIN) {
            // Lapse: use post-lapse stability formula
            $newStability = $this->calculatePostLapseStability(
                $item->difficulty,
                $item->stability,
                $retrievability
            );
            $state = 'relearning';
            $lapseCount = ($item->lapse_count ?? 0) + 1;
        } else {
            // Successful review: use recall stability formula
            $newStability = $this->calculateStabilityAfterRecall(
                $item->difficulty,
                $item->stability,
                $retrievability
            );
            $state = 'review';
            $lapseCount = $item->lapse_count ?? 0;
        }

        // Calculate new interval
        $newInterval = $this->calculateInterval($newStability, $this->requestRetention);
        
        // FSRS-5 handles grade-specific adjustments internally, but we can add a fuzz factor
        if ($elapsedDays > 0) {
            $newInterval = $this->applyFuzz($newInterval, $lastReview->timestamp);
        }

        $newInterval = min(max(1, round($newInterval)), $this->maximumInterval);

        // Update response history
        $responseHistory = $item->response_history ?? [];
        $responseHistory[] = [
            'date' => $reviewDate->toDateTimeString(),
            'grade' => $grade,
            'elapsed_days' => $elapsedDays,
            'retrievability' => round($retrievability, 3),
            'difficulty' => round($newDifficulty, 3),
            'stability' => round($newStability, 3),
            'interval' => $newInterval
        ];

        // Update the revision item
        $item->difficulty = round($newDifficulty, 3);
        $item->stability = round($newStability, 3);
        $item->interval = (int)$newInterval;
        $item->due_date = $reviewDate->copy()->addDays($newInterval);
        $item->last_review = $reviewDate;
        $item->review_count = ($item->review_count ?? 0) + 1;
        $item->lapse_count = $lapseCount;
        $item->state = $state;
        $item->retrievability = round($retrievability, 3);
        $item->response_history = $responseHistory;

        return $item;
    }

    /**
     * Add a small random fuzz to the interval to prevent items from clumping together
     */
    private function applyFuzz(float $interval, int $lastReviewTimestamp): float
    {
        if ($interval < 2.5) {
            return $interval;
        }

        $fuzzFactor = ($lastReviewTimestamp % 1000) / 1000; // Consistent fuzz per item
        $fuzz = $interval * 0.05 * $fuzzFactor; // Max 5% fuzz
        
        return $interval + $fuzz;
    }

    /**
     * Get preview of next intervals for all possible grades
     */
    public function getNextIntervals(RevisionItem $item): array
    {
        $intervals = [];
        $now = now();

        foreach ([self::GRADE_AGAIN, self::GRADE_HARD, self::GRADE_GOOD, self::GRADE_EASY] as $grade) {
            // Create a clone to avoid modifying the original
            $clonedItem = clone $item;

            if ($item->review_count === 0) {
                $result = $this->initializeRevisionItem($clonedItem, $grade);
            } else {
                $result = $this->updateRevisionItem($clonedItem, $grade);
            }

            $intervals[$grade] = [
                'days' => $result->interval,
                'due_date' => $result->due_date->format('Y-m-d H:i:s'),
                'stability' => $result->stability,
                'difficulty' => $result->difficulty
            ];
        }

        return $intervals;
    }

    /**
     * Calculate current retention probability
     */
    public function calculateRetention(RevisionItem $item, ?Carbon $date = null): float
    {
        $date = $date ?? now();
        $lastReview = $item->last_review ?? $date->copy()->subDay();
        $elapsedDays = $lastReview->diffInDays($date, false);
        $elapsedDays = max(0, $elapsedDays); // Ensure non-negative

        return $this->calculateRetrievability($item->stability, $elapsedDays);
    }

    /**
     * Set custom parameters/weights
     */
    public function setWeights(array $weights): void
    {
        if (count($weights) === 19) {
            $this->weights = $weights;
        }
    }

    public function setRequestRetention(float $retention): void
    {
        $this->requestRetention = max(0.01, min(0.99, $retention));
    }

    public function setMaximumInterval(float $days): void
    {
        $this->maximumInterval = max(1, $days);
    }

    

    

    /**
     * Access current configuration
     */
    public function getWeights(): array
    {
        return $this->weights;
    }

    public function getRequestRetention(): float
    {
        return $this->requestRetention;
    }
}
