<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_uses_username_for_both_staff_types(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Login Admin dan Kasir tanpa aktivasi perangkat')
            ->assertSee('name="username"', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_admin_can_authenticate_from_the_shared_login_screen(): void
    {
        $admin = Admin::factory()->create(['username' => 'administrator']);

        $response = $this->post(route('login'), [
            'username' => $admin->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('kasir');
        $response->assertSessionHas('staff_guard', 'admin')
            ->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_kasir_can_authenticate_from_the_shared_login_screen(): void
    {
        $kasir = Kasir::factory()->create(['username' => 'kasir-satu']);

        $response = $this->post(route('login'), [
            'username' => $kasir->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($kasir, 'kasir');
        $this->assertGuest('admin');
        $response->assertSessionHas('staff_guard', 'kasir')
            ->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_staff_can_not_authenticate_with_invalid_password(): void
    {
        $admin = Admin::factory()->create();

        $this->post(route('login'), [
            'username' => $admin->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('admin');
        $this->assertGuest('kasir');
    }

    public function test_inactive_staff_can_not_authenticate(): void
    {
        $kasir = Kasir::factory()->create(['is_active' => false]);

        $this->post(route('login'), [
            'username' => $kasir->username,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('admin');
        $this->assertGuest('kasir');
    }

    public function test_staff_can_logout_and_both_guards_are_cleared(): void
    {
        $kasir = Kasir::factory()->create();

        $response = $this
            ->actingAs($kasir, 'kasir')
            ->withSession(['staff_guard' => 'kasir'])
            ->post(route('logout'));

        $this->assertGuest('admin');
        $this->assertGuest('kasir');
        $response->assertSessionMissing('staff_guard')->assertRedirect('/');
    }

    public function test_login_is_rate_limited_by_username_and_ip(): void
    {
        RateLimiter::clear('rate-limited|127.0.0.1');
        Admin::factory()->create(['username' => 'rate-limited']);
        $request = $this;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $request->post(route('login'), [
                'username' => 'rate-limited',
                'password' => 'wrong-password',
            ]);
        }

        $request->post(route('login'), [
            'username' => 'rate-limited',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');
        $this->assertTrue(RateLimiter::tooManyAttempts('rate-limited|127.0.0.1', 5));
    }
}
