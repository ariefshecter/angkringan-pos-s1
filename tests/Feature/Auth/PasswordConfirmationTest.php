<?php

namespace Tests\Feature\Auth;

use App\Models\Kasir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_guard_can_confirm_password(): void
    {
        $kasir = Kasir::factory()->create();
        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->get(route('password.confirm'))->assertOk();

        $this->post(route('password.confirm'), ['password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_invalid_password_is_rejected_by_active_staff_guard(): void
    {
        $kasir = Kasir::factory()->create();

        $this->activateDevice()->actingAs($kasir, 'kasir')->withSession(['staff_guard' => 'kasir'])
            ->post(route('password.confirm'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
    }
}
