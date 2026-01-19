<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->command->error('ADMIN_EMAIL and ADMIN_PASSWORD environment variables are required.');
            $this->command->info('Add them to your .env file:');
            $this->command->info('  ADMIN_EMAIL=admin@example.com');
            $this->command->info('  ADMIN_PASSWORD=your-secure-password');
            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'is_super_admin' => true,
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->command->info("Super admin user created: {$email}");
        } else {
            $this->command->info("Super admin user updated: {$email}");
        }
    }
}
