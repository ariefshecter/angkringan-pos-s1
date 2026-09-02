<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Kasir;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_admin_can_request_and_use_a_password_reset_link(): void
    {
        Notification::fake();
        $admin = Admin::factory()->create();
        $this->post(route('password.email'), ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function (ResetPassword $notification) use ($admin): bool {
            $this->get(route('password.reset', $notification->token))->assertOk();
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $admin->refresh()->password));
    }

    public function test_kasir_can_not_request_a_public_password_reset_link(): void
    {
        Notification::fake();
        $kasir = Kasir::factory()->create();

        $this->post(route('password.email'), ['email' => $kasir->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }
}
