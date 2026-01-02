<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\Course;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConceptSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles required by UserFactory and tests
        Role::create(['name' => 'Student']);
        $adminRole = Role::create(['name' => 'admin']);

        // Create permission and assign to admin
        Permission::create(['name' => 'view.terms']);
        $adminRole->givePermissionTo('view.terms');
    }

    public function test_can_search_concepts_for_select_fields()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $course = Course::factory()->create();

        $concept1 = Concept::factory()->create([
            'course_id' => $course->id,
            'title' => ['en' => 'Algebra'],
        ]);

        $concept2 = Concept::factory()->create([
            'course_id' => $course->id,
            'title' => ['en' => 'Geometry'],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/admin/courses/{$course->id}/concepts/select-fields?search=Alg");

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $concept1->id]);
        $response->assertJsonMissing(['id' => $concept2->id]);
    }

    public function test_can_search_terms_for_select_fields()
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $course = Course::factory()->create();

        $term1 = Term::factory()->create([
            'course_id' => $course->id,
            'term' => ['en' => 'Variable'],
        ]);

        $term2 = Term::factory()->create([
            'course_id' => $course->id,
            'term' => ['en' => 'Constant'],
        ]);

        // TermController uses JSON_EXTRACT logic, let's see if it works with "Var"
        $response = $this->actingAs($user)
            ->getJson("/api/admin/courses/{$course->id}/terms/select-fields?search=Var");

        $response->assertStatus(200);
        // If it returns empty, we know the search logic is flawed for this case.
        // We'll inspect the response if it fails.
        $response->assertJsonFragment(['id' => $term1->id]);
        $response->assertJsonMissing(['id' => $term2->id]);
    }
}
