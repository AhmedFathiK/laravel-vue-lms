<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Course;
use App\Models\Question;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuestionRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        \Spatie\Permission\Models\Role::create(['name' => 'Student', 'guard_name' => 'web']);

        // Create permissions
        Permission::create(['name' => 'create.questions', 'guard_name' => 'web']);
        Permission::create(['name' => 'view.questions', 'guard_name' => 'web']);
        Permission::create(['name' => 'edit.questions', 'guard_name' => 'web']);
    }

    public function test_can_create_question_with_terms_and_concepts()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['create.questions', 'view.questions']);
        Sanctum::actingAs($user);

        $course = Course::factory()->create();
        $term = Term::factory()->create(['course_id' => $course->id]);

        // Create concept manually since factory might be missing
        $concept = Concept::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Test Concept'],
            'explanation' => ['en' => 'Test Explanation'],
            'type' => 'grammar',
        ]);

        $data = [
            'course_id' => $course->id,
            'question_text' => 'Test Question?',
            'type' => 'mcq',
            'difficulty' => 'medium',
            'points' => 1,
            'options' => ['Option 1', 'Option 2'],
            'correct_answer' => ['0'],
            'term_ids' => [$term->id],
            'concept_ids' => [$concept->id],
        ];

        $response = $this->postJson("/api/admin/courses/{$course->id}/questions", $data);

        $response->assertStatus(201);

        $question = Question::latest()->first();
        $this->assertCount(1, $question->terms);
        $this->assertEquals($term->id, $question->terms->first()->id);

        $this->assertCount(1, $question->concepts);
        $this->assertEquals($concept->id, $question->concepts->first()->id);
    }

    public function test_can_update_question_relationships()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['create.questions', 'view.questions', 'edit.questions']);
        Sanctum::actingAs($user);

        $course = Course::factory()->create();
        $term1 = Term::factory()->create(['course_id' => $course->id]);
        $term2 = Term::factory()->create(['course_id' => $course->id]);

        $concept1 = Concept::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Concept 1'],
            'explanation' => ['en' => 'Exp 1'],
            'type' => 'grammar',
        ]);

        $concept2 = Concept::create([
            'course_id' => $course->id,
            'title' => ['en' => 'Concept 2'],
            'explanation' => ['en' => 'Exp 2'],
            'type' => 'grammar',
        ]);

        // Create question with term1 and concept1
        $question = Question::factory()->create([
            'course_id' => $course->id,
            'type' => 'mcq',
            'question_text' => 'Test Question',
            'content' => ['options' => ['A', 'B'], 'correct_answer' => ['0']],
            'points' => 1,
            'difficulty' => 'medium',
        ]);

        $question->terms()->attach($term1->id);
        $question->concepts()->attach($concept1->id);

        $data = [
            'course_id' => $course->id,
            'question_text' => 'Updated Question',
            'type' => 'mcq',
            'difficulty' => 'hard',
            'points' => 2,
            'options' => ['A', 'B'],
            'correct_answer' => ['0'],
            'term_ids' => [$term2->id], // Switch to term2
            'concept_ids' => [$concept1->id, $concept2->id], // Add concept2
        ];

        $response = $this->putJson("/api/admin/courses/{$course->id}/questions/{$question->id}", $data);

        $response->assertStatus(200);

        $question->refresh();

        $this->assertCount(1, $question->terms);
        $this->assertEquals($term2->id, $question->terms->first()->id);

        $this->assertCount(2, $question->concepts);
        $this->assertTrue($question->concepts->contains($concept1));
        $this->assertTrue($question->concepts->contains($concept2));
    }

    public function test_show_question_loads_relationships()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['view.questions']);
        Sanctum::actingAs($user);

        $course = Course::factory()->create();
        $term = Term::factory()->create(['course_id' => $course->id]);

        $question = Question::factory()->create([
            'course_id' => $course->id,
            'type' => 'mcq',
            'content' => ['options' => ['A', 'B'], 'correct_answer' => ['0']],
        ]);

        $question->terms()->attach($term->id);

        $response = $this->getJson("/api/admin/courses/{$course->id}/questions/{$question->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'terms',
            'concepts',
        ]);

        $this->assertEquals($term->id, $response->json('terms.0.id'));
    }
}
