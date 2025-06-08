<?php

namespace App\Services;

use App\Models\RevisionItem;
use Carbon\Carbon;

class FSRSService
{
    // FSRS-6 default parameters (17 weights)
    private array $weights = [
        0.4,    // w[0] - initial stability for Again
        0.6,    // w[1] - initial stability for Hard  
        2.4,    // w[2] - initial stability for Good
        5.8,    // w[3] - initial stability for Easy
        4.93,   // w[4] - initial difficulty base
        0.94,   // w[5] - initial difficulty adjustment
        0.86,   // w[6] - difficulty decay rate
        0.01,   // w[7] - minimum stability
        1.49,   // w[8] - stability increase factor
        0.14,   // w[9] - stability power
        0.94,   // w[10] - retrievability factor
        2.18,   // w[11] - post-lapse stability base
        0.05,   // w[12] - post-lapse difficulty factor
        0.34,   // w[13] - post-lapse stability power
        1.26,   // w[14] - post-lapse difficulty decay
        0.29,   // w[15] - hard penalty
        2.61    // w[16] - easy bonus
    ];

    // Grade constants
    const GRADE_AGAIN = 1;
    const GRADE_HARD = 2;
    const GRADE_GOOD = 3;
    const GRADE_EASY = 4;

    // Configuration
    private float $requestRetention = 0.9;    // Target retention rate
    private float $maximumInterval = 36500;   // Maximum interval in days
    private float $easyBonus = 1.3;          // Easy button bonus multiplier
    private float $hardInterval = 1.2;       // Hard button interval multiplier

    public function __construct(array $customWeights = null)
    {
        if ($customWeights && count($customWeights) === 17) {
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
     * D₀(G) = w[4] - w[5] × (G - 3)
     * Constrained to [1, 10]
     */
    private function getInitialDifficulty(int $grade): float
    {
        $difficulty = $this->weights[4] - $this->weights[5] * ($grade - 3);
        return min(max(1.0, $difficulty), 10.0);
    }

    /**
     * FORMULA 3: Difficulty update after review (Linear Damping)
     * D' = D + w[6] × (8 - 9G) / 17
     * Constrained to [1, 10]
     */
    private function updateDifficulty(float $currentDifficulty, int $grade): float
    {
        $deltaD = $this->weights[6] * (8 - 9 * $grade) / 17;
        $newDifficulty = $currentDifficulty + $deltaD;
        return min(max(1.0, $newDifficulty), 10.0);
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
     * S' = S × SInc
     */
    private function calculateStabilityAfterRecall(
        float $difficulty,
        float $stability,
        float $retrievability,
        int $grade
    ): float {
        $w = $this->weights;

        $hardPenalty = ($grade === self::GRADE_HARD) ? $w[15] : 1.0;
        $easyBonus = ($grade === self::GRADE_EASY) ? $w[16] : 1.0;

        $stabilityIncrease = 1 + (
            exp($w[8]) *
            (11 - $difficulty) *
            pow($stability, -$w[9]) *
            (exp((1 - $retrievability) * $w[10]) - 1) *
            $hardPenalty *
            $easyBonus
        );

        return $stability * $stabilityIncrease;
    }

    /**
     * FORMULA 7: Post-lapse stability (after forgetting/lapse)
     * S' = w[11] × D^(-w[12]) × ((S+1)^w[13] - 1) × exp(-w[14] × D)
     */
    private function calculatePostLapseStability(float $difficulty, float $stability): float
    {
        $w = $this->weights;

        $postLapseStability = $w[11] *
            pow($difficulty, -$w[12]) *
            (pow($stability + 1, $w[13]) - 1) *
            exp(-$w[14] * $difficulty);

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
    public function updateRevisionItem(RevisionItem $item, int $grade, Carbon $reviewDate = null): RevisionItem
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
                $item->stability
            );
            $state = 'relearning';
            $lapseCount = ($item->lapse_count ?? 0) + 1;
        } else {
            // Successful review: use recall stability formula
            $newStability = $this->calculateStabilityAfterRecall(
                $item->difficulty,
                $item->stability,
                $retrievability,
                $grade
            );
            $state = 'review';
            $lapseCount = $item->lapse_count ?? 0;
        }

        // Calculate new interval
        $baseInterval = $this->calculateInterval($newStability, $this->requestRetention);
        $newInterval = $this->adjustIntervalByGrade($baseInterval, $grade, $item->interval ?? 1);
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
     * INTERVAL ADJUSTMENT: Apply grade-specific interval modifications
     */
    private function adjustIntervalByGrade(float $baseInterval, int $grade, float $lastInterval): float
    {
        switch ($grade) {
            case self::GRADE_HARD:
                // Hard: constrain to not increase too much from last interval
                return max(1, $lastInterval * $this->hardInterval);

            case self::GRADE_EASY:
                // Easy: apply bonus multiplier
                return $baseInterval * $this->easyBonus;

            case self::GRADE_GOOD:
            case self::GRADE_AGAIN:
            default:
                return $baseInterval;
        }
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
    public function calculateRetention(RevisionItem $item, Carbon $date = null): float
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
        if (count($weights) === 17) {
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

    public function setEasyBonus(float $bonus): void
    {
        $this->easyBonus = max(1.0, $bonus);
    }

    public function setHardInterval(float $multiplier): void
    {
        $this->hardInterval = max(1.0, $multiplier);
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
