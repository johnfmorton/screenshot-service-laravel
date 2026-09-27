<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failures_lock_the_account_out_even_for_the_right_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_a_successful_login_resets_the_counter(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post('/admin/logout');

        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_login_route_is_throttled_per_ip_across_accounts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/admin/login', ['email' => "user{$i}@example.com", 'password' => 'guess']);
        }

        $this->post('/admin/login', ['email' => 'another@example.com', 'password' => 'guess'])
            ->assertTooManyRequests();
    }
}
