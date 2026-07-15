<?php

namespace Tests\Feature;

use App\Models\Cart as CartModel;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Cart\CartMergeCoordinator;
use App\Support\Cart\CartMergeService;
use App\Support\Cart\SessionCartStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CartAuthenticationMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_login_merges_guest_cart_into_saved_cart(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);
        $product = Product::factory()->create(['price' => '25.00', 'stock' => 10]);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 2,
            'price_reference' => '25.00',
        ]);
        app(SessionCartStorage::class)->set($product->id, 3);

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(5, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([], app(SessionCartStorage::class)->all());
    }

    public function test_registration_moves_guest_cart_to_new_customer(): void
    {
        $product = Product::factory()->create(['price' => '18.00', 'stock' => 10]);
        app(SessionCartStorage::class)->set($product->id, 4);

        $this->post(route('register.store'), [
            'name' => 'Cliente Nuevo',
            'phone' => '987654321',
            'email' => 'nuevo@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $user->cart()->firstOrFail()->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'price_reference' => '18.00',
        ]);
        $this->assertSame([], app(SessionCartStorage::class)->all());
    }

    public function test_google_login_merges_guest_cart_into_linked_customer(): void
    {
        $user = User::factory()->create(['email' => 'google@example.com']);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-cart-user',
        ]);
        $product = Product::factory()->create(['price' => '60.00', 'stock' => 10]);
        app(SessionCartStorage::class)->set($product->id, 2);
        Socialite::fake('google');
        $this->get(route('auth.google.redirect'));
        Socialite::fake('google', GoogleUser::fake([
            'id' => 'google-cart-user',
            'name' => 'Cliente Google',
            'email' => 'google@example.com',
            'email_verified' => true,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $user->cart()->firstOrFail()->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertSame([], app(SessionCartStorage::class)->all());
    }

    public function test_failed_merge_does_not_block_login_or_destroy_either_cart(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);
        $product = Product::factory()->create(['price' => '22.00']);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 1,
            'price_reference' => '22.00',
        ]);
        app(SessionCartStorage::class)->set($product->id, 2);
        $this->mock(CartMergeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('merge')
                ->once()
                ->andThrow(new RuntimeException('Database unavailable'));
        });

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([$product->id => 2], app(SessionCartStorage::class)->all());
        $this->assertContains(
            CartMergeCoordinator::FAILURE_WARNING,
            app(SessionCartStorage::class)->warnings()
        );
    }

    public function test_pending_merge_is_retried_when_authenticated_cart_is_consulted(): void
    {
        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);
        $product = Product::factory()->create(['price' => '22.00', 'stock' => 10]);
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create([
            'quantity' => 1,
            'price_reference' => '22.00',
        ]);
        $sessionStorage = app(SessionCartStorage::class);
        $sessionStorage->set($product->id, 2);
        $flakyMerge = new class($sessionStorage) extends CartMergeService
        {
            public int $attempts = 0;

            public function merge(User $user): void
            {
                $this->attempts++;

                if ($this->attempts === 1) {
                    throw new RuntimeException('Temporary database failure');
                }

                parent::merge($user);
            }
        };
        $this->app->instance(CartMergeService::class, $flakyMerge);

        $this->post(route('login.store'), [
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ])->assertRedirect(route('account.profile'));

        $this->getJson(route('shop.cart.info'))
            ->assertOk()
            ->assertJsonPath('cart.total_quantity', 3);

        $this->assertSame(2, $flakyMerge->attempts);
        $this->assertSame([], $sessionStorage->all());
        $this->assertSame(3, $cart->items()->firstOrFail()->quantity);
    }

    public function test_logout_preserves_database_cart_and_starts_empty_guest_session(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = CartModel::factory()->for($user)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 4]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('shop.index'));

        $this->assertGuest();
        $this->assertSame(4, $cart->items()->firstOrFail()->quantity);
        $this->assertSame([], app(SessionCartStorage::class)->all());
    }

    public function test_logout_preserves_pending_guest_cart_when_last_merge_attempt_fails(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        app(SessionCartStorage::class)->set($product->id, 3);
        $this->mock(CartMergeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('merge')
                ->once()
                ->andThrow(new RuntimeException('Database unavailable'));
        });

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('shop.index'));

        $this->assertGuest();
        $this->assertSame(
            [$product->id => 3],
            app(SessionCartStorage::class)->all()
        );
        $this->assertContains(
            CartMergeCoordinator::FAILURE_WARNING,
            app(SessionCartStorage::class)->warnings()
        );
    }
}
