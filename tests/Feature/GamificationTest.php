<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Trophy;
use App\Models\UserPoint;
use App\Models\UserTrophy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create necessary roles
        Role::create(['name' => 'Student']);
    }

    public function test_get_user_points_with_breakdown()
    {
        $user = User::factory()->create();

        // Create some points
        UserPoint::create([
            'user_id' => $user->id,
            'type' => 'lesson_completed',
            'points' => 10,
        ]);

        UserPoint::create([
            'user_id' => $user->id,
            'type' => 'lesson_completed',
            'points' => 20,
        ]);

        UserPoint::create([
            'user_id' => $user->id,
            'type' => 'exam_passed',
            'points' => 50,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/gamification/points?with_breakdown=1');

        $response->assertStatus(200)
            ->assertJson([
                'totalPoints' => 80,
                'breakdown' => [
                    'lessonCompleted' => 30,
                    'examPassed' => 50,
                ]
            ]);
    }

    public function test_get_trophy_statistics()
    {
        $user = User::factory()->create();

        // Create trophies with different rarities
        $commonTrophy = Trophy::create([
            'name' => ['en' => 'Common Trophy'],
            'description' => ['en' => 'A common trophy'],
            'rarity' => 'common',
            'trigger_type' => 'manual',
            'is_active' => true,
        ]);

        $rareTrophy = Trophy::create([
            'name' => ['en' => 'Rare Trophy'],
            'description' => ['en' => 'A rare trophy'],
            'rarity' => 'rare',
            'trigger_type' => 'manual',
            'is_active' => true,
        ]);

        // Award trophies to user
        UserTrophy::create([
            'user_id' => $user->id,
            'trophy_id' => $commonTrophy->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/gamification/trophy-statistics');

        $response->assertStatus(200);

        // Check structure and data
        $response->assertJsonStructure([
            'earnedTrophies',
            'totalTrophies',
            'completionPercentage',
            'rarityBreakdown'
        ]);

        $response->assertJson([
            'earnedTrophies' => 1,
            'rarityBreakdown' => [
                'common' => 1,
                'rare' => 0,
            ]
        ]);
    }
}
