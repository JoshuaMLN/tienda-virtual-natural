<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_requires_authentication(): void
    {
        $this->get(route('account.security'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_can_open_security_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('Verificado')
            ->assertSee(route('account.password.update'), false);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)
            ->patch(route('account.password.update'), [
                'current_password' => 'incorrecta',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors(['current_password'], null, 'updatePassword');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_customer_can_change_password_and_rotate_remember_token(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
            'remember_token' => 'old-remember-token',
        ]);

        $this->actingAs($user)
            ->patch(route('account.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
    }

    public function test_account_without_password_can_define_its_first_password(): void
    {
        $user = User::factory()->create([
            'password' => null,
            'remember_token' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('account.password.update'), [
                'password' => 'first-password',
                'password_confirmation' => 'first-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('first-password', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->remember_token);
    }
}
