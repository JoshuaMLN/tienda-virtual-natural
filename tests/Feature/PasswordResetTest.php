<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_uses_a_real_form(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee(route('password.email'), false)
            ->assertSee('name="email"', false);
    }

    public function test_existing_customer_receives_custom_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('password.email'), ['email' => ' CLIENTE@EXAMPLE.COM '])
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'Si el correo pertenece a una cuenta, recibiras un enlace para restablecer tu contrasena.'
            );

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_receives_the_same_neutral_response(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nadie@example.com'])
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'Si el correo pertenece a una cuenta, recibiras un enlace para restablecer tu contrasena.'
            );

        Notification::assertNothingSent();
    }

    public function test_customer_can_reset_password_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'old-password',
            'remember_token' => 'old-remember-token',
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'cliente@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Tu contrasena fue restablecida. Ya puedes iniciar sesion.');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
    }

    public function test_invalid_reset_token_returns_a_readable_error(): void
    {
        User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('password.update'), [
            'token' => 'token-invalido',
            'email' => 'cliente@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertSessionHasErrors(['email'], null, 'resetPassword');
    }

    public function test_password_reset_request_is_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->post(route('password.email'), ['email' => 'nadie@example.com'])
                ->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'nadie@example.com'])
            ->assertTooManyRequests();
    }
}
