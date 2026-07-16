<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_users_are_customers_by_default(): void
    {
        $customer = User::factory()->create();

        $this->assertSame(UserRole::Customer, $customer->role);
        $this->assertTrue($customer->isCustomer());
        $this->assertFalse($customer->isAdmin());
    }

    public function test_every_private_admin_route_has_auth_and_admin_middleware(): void
    {
        $publicAdminRoutes = [
            'admin.login',
            'admin.login.store',
            'admin.password.request',
            'admin.password.email',
            'admin.password.reset',
            'admin.password.update',
        ];

        $privateRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.'))
            ->reject(fn ($route) => in_array($route->getName(), $publicAdminRoutes, true));

        $this->assertNotEmpty($privateRoutes);

        foreach ($privateRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, $route->getName().' no tiene auth.');
            $this->assertContains('admin', $middleware, $route->getName().' no tiene middleware admin.');
        }
    }

    public function test_guest_is_redirected_to_admin_login_and_customer_is_forbidden(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_only_admin_credentials_can_start_an_admin_session(): void
    {
        $customer = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'CustomerPass123',
        ]);
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminAccess123',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $customer->email,
            'password' => 'CustomerPass123',
        ])->assertSessionHasErrors(['email'], null, 'adminLogin');

        $this->assertGuest();

        $this->post(route('admin.login.store'), [
            'email' => ' ADMIN@EXAMPLE.COM ',
            'password' => 'AdminAccess123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(auth()->viaRemember());
    }

    public function test_admin_credentials_are_rejected_by_customer_login(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminAccess123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'admin@example.com',
            'password' => 'AdminAccess123',
        ])->assertSessionHasErrors(['email'], null, 'login');

        $this->assertGuest();
    }

    public function test_admin_login_has_no_google_or_remember_option(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.login.store'), false)
            ->assertSee(route('admin.password.request'), false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('Continuar con Google');
    }

    public function test_admin_login_is_temporarily_blocked_after_repeated_failures(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminAccess123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('admin.login.store'), [
                'email' => 'admin@example.com',
                'password' => 'incorrecta',
            ])->assertSessionHasErrors(['email'], null, 'adminLogin');
        }

        $this->post(route('admin.login.store'), [
            'email' => 'admin@example.com',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors(['email'], null, 'adminLogin');

        $this->assertStringContainsString(
            'Demasiados intentos.',
            session('errors')->getBag('adminLogin')->first('email')
        );
    }

    public function test_admin_can_logout_from_the_protected_route(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Sesion administrativa cerrada correctamente.');

        $this->assertGuest();
    }

    public function test_admin_cannot_open_customer_account_checkout_or_cart(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('account.profile'))->assertForbidden();
        $this->actingAs($admin)->get(route('checkout.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('shop.cart'))->assertForbidden();
    }

    public function test_storefront_identifies_admin_and_hides_purchase_controls(): void
    {
        $category = Category::query()->create([
            'name' => 'Suplementos',
            'slug' => 'suplementos',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Omega 3',
            'slug' => 'omega-3',
            'sku' => 'VN-OMEGA',
            'price' => 79.90,
            'stock' => 10,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('shop.product', $product))
            ->assertOk()
            ->assertSee('Panel admin')
            ->assertSee('Las cuentas administrativas no realizan compras.')
            ->assertDontSee('data-cart-add', false)
            ->assertDontSee('data-cart-count', false);

        $this->assertDatabaseMissing('carts', ['user_id' => $admin->id]);
    }

    public function test_admin_create_command_stores_a_verified_admin_with_a_hidden_password_prompt(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nombre del administrador', 'Maria Admin')
            ->expectsQuestion('Correo electronico', ' ADMIN@EXAMPLE.COM ')
            ->expectsQuestion('Contrasena (minimo 12 caracteres)', 'AdminSecure123')
            ->expectsQuestion('Confirma la contrasena', 'AdminSecure123')
            ->expectsOutput('Administrador admin@example.com creado correctamente.')
            ->assertSuccessful();

        $admin = User::query()->sole();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('AdminSecure123', $admin->password));
        $this->assertNull($admin->terms_accepted_at);
    }

    public function test_admin_create_command_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);

        $this->artisan('admin:create')
            ->expectsQuestion('Nombre del administrador', 'Maria Admin')
            ->expectsQuestion('Correo electronico', 'ocupado@example.com')
            ->expectsQuestion('Contrasena (minimo 12 caracteres)', 'AdminSecure123')
            ->expectsQuestion('Confirma la contrasena', 'AdminSecure123')
            ->expectsOutput('Ya existe una cuenta con este correo.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_password_recovery_is_neutral_and_role_scoped(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $customer = User::factory()->create(['email' => 'cliente@example.com']);

        $this->post(route('admin.password.email'), ['email' => $customer->email])
            ->assertRedirect()
            ->assertSessionHas(
                'status',
                'Si el correo pertenece a una cuenta administrativa, recibiras un enlace de recuperacion.'
            );

        Notification::assertNothingSent();

        $this->post(route('admin.password.email'), ['email' => ' ADMIN@EXAMPLE.COM '])
            ->assertRedirect();

        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
        Notification::assertNotSentTo($customer, AdminResetPasswordNotification::class);
    }

    public function test_admin_can_reset_password_only_with_an_admin_token(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'OldAdminPass123',
        ]);
        $token = Password::createToken($admin);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'NewAdminPass123',
            'password_confirmation' => 'NewAdminPass123',
        ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Tu contrasena administrativa fue restablecida.');

        $this->assertTrue(Hash::check('NewAdminPass123', $admin->fresh()->password));
    }

    public function test_customer_reset_token_cannot_be_used_in_admin_recovery(): void
    {
        $customer = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'OldCustomer123',
        ]);
        $token = Password::createToken($customer);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $customer->email,
            'password' => 'NewCustomer123',
            'password_confirmation' => 'NewCustomer123',
        ])->assertSessionHasErrors(['email'], null, 'resetPassword');

        $this->assertTrue(Hash::check('OldCustomer123', $customer->fresh()->password));
    }
}
