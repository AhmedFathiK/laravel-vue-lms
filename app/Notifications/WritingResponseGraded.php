<?php

namespace App\Notifications;

use App\Models\ExamResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WritingResponseGraded extends Notification implements ShouldQueue
{
    use Queueable;

    protected ExamResponse $response;

    /**
     * Create a new notification instance.
     */
    public function __construct(ExamResponse $response)
    {
        $this->response = $response;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $examName = $this->response->examAttempt->exam->title;
        $score = $this->response->score;
        $maxScore = $this->response->question->points;

        return (new MailMessage)
            ->subject('Your Writing Question Has Been Graded')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your writing response in the exam "' . $examName . '" has been graded.')
            ->line('You received a score of ' . $score . ' out of ' . $maxScore . ' points.')
            ->line('Feedback: ' . ($this->response->feedback ?? 'No feedback provided.'))
            ->action('View Your Exam Results', url('/learner/exams/' . $this->response->examAttempt->exam_id . '/attempts/' . $this->response->examAttempt->id))
            ->line('Thank you for your continued learning!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'exam_attempt_id' => $this->response->examAttempt->id,
            'exam_id' => $this->response->examAttempt->exam_id,
            'question_id' => $this->response->question_id,
            'response_id' => $this->response->id,
            'score' => $this->response->score,
            'max_score' => $this->response->question->points,
            'feedback' => $this->response->feedback,
            'message' => 'Your writing response has been graded.',
        ];
    }
}
