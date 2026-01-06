<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuestionMcqUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles and permissions
        \Spatie\Permission\Models\Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'Student', 'guard_name' => 'web']);
        Permission::create(['name' => 'edit.questions', 'guard_name' => 'web']);
    }

    public function test_update_mcq_question()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('edit.questions');
        Sanctum::actingAs($user);

        $course = Course::factory()->create();
        
        $question = Question::create([
            'course_id' => $course->id,
            'title' => 'MCQ Question',
            'question_text' => 'What is 1+1?',
            'type' => Question::TYPE_MCQ,
            'content' => [
                'options' => ['1', '2', '3', '4'],
                'correctAnswer' => ['1'] // Index 1 which is '2'
            ],
            'points' => 10,
            'difficulty' => 'easy'
        ]);

        $payload = [
            'id' => $question->id,
            'title' => 'MCQ Question Updated',
            'questionText' => 'What is 2+2?',
            'type' => 'mcq',
            'points' => 10,
            'difficulty' => 'easy',
            '_method' => 'PUT',
            // Update options and correct answer
            'options' => ['3', '4', '5', '6'],
            'correctAnswer' => ['1'] // Index 1 which is '4'
        ];

        $response = $this->putJson(
            "/api/admin/courses/{$course->id}/questions/{$question->id}", 
            $payload
        );

        $response->assertStatus(200);

        $question->refresh();
        
        $content = $question->content;
        
        dump($content);

        $this->assertEquals(
            ['3', '4', '5', '6'], 
            $content['options']
        );
        $this->assertEquals(
            ['1'], 
            $content['correctAnswer']
        );
    }
}
