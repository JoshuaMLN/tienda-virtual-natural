<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_real_login_and_registration_forms(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('login.store'), false)
            ->assertSee(route('register.store'), false)
            ->assertSee('name="remember"', false)
            ->assertSee('Continuar con Google')
            ->assertSee(asset('images/brands/google-g-neutral@4x.png'), false)
            ->assertDontSee('Continuar con Facebook')
            ->assertDontSee('Continuar con Apple');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('register.store'), false)
            ->assertSee('name="terms"', false);
    }

    public function test_customer_can_register_with_normalized_data(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => '  Maria   Fernanda Perez  ',
            'phone' => '987 654 321',
            'email' => '  MARIA@EXAMPLE.COM ',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();

        $user = User::query()->sole();

        $this->assertSame('Maria Fernanda Perez', $user->name);
        $this->assertSame('987654321', $user->phone);
        $this->assertSame('maria@example.com', $user->email);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_registration_accepts_an_optional_phone_and_honors_intended_destination(): void
    {
        $this->withSession(['url.intended' => route('account.addresses')])
            ->post(route('register.store'), [
                'name' => 'Cliente sin telefono',
                'phone' => '',
                'email' => 'cliente@example.com',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'terms' => '1',
            ])
            ->assertRedirect(route('account.addresses'));

        $this->assertAuthenticated();
        $this->assertNull(User::query()->sole()->phone);
    }

    public function test_registration_returns_readable_errors_in_its_own_error_bag(): void
    {
        $this->post(route('register.store'), [
            'name' => '',
            'phone' => '123',
            'email' => 'correo-invalido',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertSessionHasErrors(
                ['name', 'phone', 'email', 'password', 'terms'],
                null,
                'register'
            );

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_a_duplicate_email_after_normalization(): void
    {
        User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('register.store'), [
            'name' => 'Otro cliente',
            'email' => ' CLIENTE@EXAMPLE.COM ',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ])
            ->assertSessionHasErrors(['email'], null, 'register');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_customer_can_login_with_normalized_email(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => ' CLIENTE@EXAMPLE.COM ',
            'password' => 'secret123',
            'remember' => '1',
        ])
            ->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_cookie_uses_the_configured_duration(): void
    {
        config()->set('auth.remember.days', 7);

        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $rememberCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie);
        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $rememberCookie->getExpiresTime(),
            5
        );
    }

    public function test_login_rejects_invalid_credentials_in_login_error_bag(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'incorrecta',
        ])
            ->assertSessionHasErrors(['email'], null, 'login');

        $this->assertGuest();
    }

    public function test_login_is_temporarily_blocked_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'cliente@example.com',
                'password' => 'incorrecta',
            ])->assertSessionHasErrors(['email'], null, 'login');
        }

        $response = $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'incorrecta',
        ]);

        $response->assertSessionHasErrors(['email'], null, 'login');
        $this->assertStringContainsString(
            'Demasiados intentos.',
            session('errors')->getBag('login')->first('email')
        );
    }

    public function test_account_without_password_cannot_use_local_login(): void
    {
        User::factory()->create([
            'email' => 'google@example.com',
            'password' => null,
        ]);

        $this->post(route('login.store'), [
            'email' => 'google@example.com',
            'password' => 'secret123',
        ])
            ->assertSessionHasErrors(['email'], null, 'login');

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_and_returns_to_intended_account_page(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->get(route('account.addresses'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])
            ->assertRedirect(route('account.addresses'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_customer_cannot_reopen_guest_auth_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('account.profile'));

        $this->get(route('register'))
            ->assertRedirect(route('account.profile'));
    }

    public function test_customer_can_logout_from_the_account_area(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee(route('logout'), false);

        $this->post(route('logout'))
            ->assertRedirect(route('shop.index'))
            ->assertSessionHas('status', 'Sesion cerrada correctamente.');

        $this->assertGuest();
    }
}
