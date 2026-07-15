<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerNavigationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_navigation_invites_the_customer_to_log_in(): void
    {
        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('aria-label="Iniciar sesion"', false)
            ->assertSee(route('login'), false)
            ->assertDontSee(route('account.profile'), false)
            ->assertDontSee(route('logout'), false);
    }

    public function test_authenticated_navigation_exposes_the_account_and_logout(): void
    {
        $user = User::factory()->create(['name' => 'Maria Fernanda Perez']);

        $this->actingAs($user)
            ->get(route('shop.index'))
            ->assertOk()
            ->assertSee(route('account.profile'), false)
            ->assertSee('Mi cuenta')
            ->assertSee(route('logout'), false)
            ->assertSee('data-bs-target="#logoutConfirmationModal"', false)
            ->assertDontSee('href="'.route('login').'"', false);
    }

    public function test_unverified_navigation_exposes_the_verification_link(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('shop.index'))
            ->assertOk()
            ->assertSee(route('verification.notice'), false)
            ->assertSee('Verificar correo');
    }

    public function test_verified_customer_returns_to_checkout_after_local_login(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->get(route('checkout.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('checkout.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_customer_returns_to_checkout_after_verifying_email(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->get(route('checkout.index'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('checkout.index'));

        $this->get(route('checkout.index'))
            ->assertRedirect(route('verification.notice'));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('status', 'Correo electronico verificado correctamente.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Correo electronico verificado correctamente.');
    }

    public function test_login_regenerates_the_session_identifier(): void
    {
        User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->withSession(['session_probe' => 'before-login']);
        $sessionIdBeforeLogin = $this->app['session']->getId();

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('account.profile'));

        $this->assertNotSame($sessionIdBeforeLogin, $this->app['session']->getId());
        $this->assertSame('before-login', session('session_probe'));
    }

    public function test_logout_invalidates_the_private_session_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['private_probe' => 'remove-me']);

        $sessionIdBeforeLogout = $this->app['session']->getId();

        $this->post(route('logout'))
            ->assertRedirect(route('shop.index'))
            ->assertSessionMissing('private_probe');

        $this->assertGuest();
        $this->assertNotSame($sessionIdBeforeLogout, $this->app['session']->getId());
    }

    public function test_logout_feedback_is_visible_after_the_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('logout'))
            ->assertOk()
            ->assertSee('Sesion cerrada correctamente.');

        $this->assertGuest();
    }

    public function test_all_checkout_pages_require_a_verified_customer(): void
    {
        $user = User::factory()->unverified()->create();

        foreach (['checkout.index', 'checkout.success', 'checkout.failed', 'checkout.pending'] as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertRedirect(route('verification.notice'));
        }
    }

    public function test_footer_offers_link_applies_the_catalog_filter(): void
    {
        $footer = view('components.shop.footer')->render();

        $this->assertStringContainsString(
            'href="'.route('shop.catalog', ['oferta' => 1]).'"',
            $footer
        );
    }
}
