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
        // Credentials to feed
        $email = 'admin@gmail.com';
        $password = 'admin123@';

        // Update or Create the user
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('User updated/created successfully!');
        $this->command->info("Email: $email");
        // Don't show password in logs for security, but confirm it's set
        $this->command->info('Password: [HIDDEN] (set as requested)');
    }
}
