<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'code' => 'revision.access',
                'description' => 'Access to the revision system',
            ],
            [
                'code' => 'content.free.access',
                'description' => 'Access to free content',
            ],
            [
                'code' => 'content.paid.access',
                'description' => 'Access to paid content',
            ],
            [
                'code' => 'placement_test.access',
                'description' => 'Access to placement tests',
            ],
        ];

        foreach ($features as $feature) {
            Feature::firstOrCreate(
                ['code' => $feature['code']],
                ['description' => $feature['description']]
            );
        }
    }
}
