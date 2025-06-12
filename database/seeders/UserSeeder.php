<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default super admin user
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@demo.com',
            'password' => Hash::make('admin'),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('Super Admin');

        // Create a default student user
        $student = User::create([
            'first_name' => 'Demo',
            'last_name' => 'Student',
            'email' => 'student@demo.com',
            'password' => Hash::make('student'),
            'email_verified_at' => now(),
        ]);
        $student->assignRole('Student');

        $this->command->info('Users seeded successfully!');
        $this->command->info('Super Admin: admin@demo.com (password: admin)');
        $this->command->info('Student: student@demo.com (password: student)');
    }
}
