<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use RuntimeException;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_start_google_authentication(): void
    {
        Socialite::fake('google');

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('https://socialite.fake/google/authorize')
            ->assertSessionHas('auth.google.mode', 'login');
    }

    public function test_verified_google_identity_creates_and_authenticates_a_customer(): void
    {
        $this->startGuestFlow($this->googleUser([
            'id' => 'google-new-customer',
            'name' => '  Maria   Google  ',
            'email' => 'MARIA.GOOGLE@EXAMPLE.COM',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.profile'));

        $user = User::query()->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Maria Google', $user->name);
        $this->assertSame('maria.google@example.com', $user->email);
        $this->assertNull($user->password);
        $this->assertNull($user->avatar_path);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-new-customer',
        ]);
        $this->assertFalse(Schema::hasColumn('social_accounts', 'token'));
        $this->assertFalse(Schema::hasColumn('social_accounts', 'refresh_token'));
    }

    public function test_linked_google_identity_logs_into_the_same_customer_without_duplicates(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-existing',
        ]);

        $this->startGuestFlow($this->googleUser([
            'id' => 'google-existing',
            'email' => 'cliente@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_existing_local_email_requires_password_before_linking(): void
    {
        User::factory()->unverified()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->startGuestFlow($this->googleUser([
            'id' => 'google-local-account',
            'email' => 'cliente@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('auth.google.confirm'))
            ->assertSessionHas('auth.google.pending_link');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);

        $this->get(route('auth.google.confirm'))
            ->assertOk()
            ->assertSee('cliente@example.com')
            ->assertSee(route('auth.google.confirm.store'), false);
    }

    public function test_admin_email_cannot_enter_or_link_through_google_customer_access(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'AdminAccess123',
        ]);

        $this->startGuestFlow($this->googleUser([
            'id' => 'google-admin-email',
            'email' => 'admin@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_identity_previously_linked_to_admin_is_rejected(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $admin->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-linked-admin',
        ]);

        $this->startGuestFlow($this->googleUser([
            'id' => 'google-linked-admin',
            'email' => 'admin@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertGuest();
    }

    public function test_existing_local_account_is_linked_only_after_correct_password(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'cliente@example.com',
            'password' => 'secret123',
        ]);

        $this->startGuestFlow($this->googleUser([
            'id' => 'google-confirmed-link',
            'email' => 'cliente@example.com',
        ]));
        $this->get(route('auth.google.callback'));

        $this->post(route('auth.google.confirm.store'), ['password' => 'incorrecta'])
            ->assertSessionHasErrors(['password'], null, 'googleLink');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);

        $this->post(route('auth.google.confirm.store'), ['password' => 'secret123'])
            ->assertRedirect(route('account.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'google-confirmed-link',
        ]);
    }

    public function test_authenticated_customer_can_link_google_with_the_same_email(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'cliente@example.com']);

        Socialite::fake('google');
        $this->actingAs($user)
            ->post(route('account.google.link'))
            ->assertRedirect('https://socialite.fake/google/authorize')
            ->assertSessionHas('auth.google.mode', 'link');

        Socialite::fake('google', $this->googleUser([
            'id' => 'google-account-link',
            'email' => 'cliente@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.security'))
            ->assertSessionHas('status', 'google-linked');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'google-account-link',
        ]);
    }

    public function test_authenticated_customer_cannot_link_a_different_google_email(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);

        Socialite::fake('google');
        $this->actingAs($user)->post(route('account.google.link'));

        Socialite::fake('google', $this->googleUser([
            'id' => 'google-other-email',
            'email' => 'otro@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.security'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_google_identity_cannot_be_linked_to_two_customers(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $owner->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-owned',
        ]);
        $other = User::factory()->create(['email' => 'other@example.com']);

        Socialite::fake('google');
        $this->actingAs($other)->post(route('account.google.link'));

        Socialite::fake('google', $this->googleUser([
            'id' => 'google-owned',
            'email' => 'other@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('account.security'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_google_cannot_be_unlinked_when_it_is_the_last_authentication_method(): void
    {
        $user = User::factory()->create([
            'email' => 'google-only@example.com',
            'password' => null,
        ]);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-only',
        ]);

        $this->actingAs($user)
            ->delete(route('account.google.unlink'))
            ->assertRedirect()
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_google_can_be_unlinked_when_local_password_exists(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-removable',
        ]);

        $this->actingAs($user)
            ->delete(route('account.google.unlink'))
            ->assertRedirect()
            ->assertSessionHas('status', 'google-unlinked');

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_unverified_or_incomplete_google_profile_is_rejected(): void
    {
        $this->startGuestFlow($this->googleUser([
            'id' => 'google-unverified',
            'email_verified' => false,
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        $this->startGuestFlow($this->googleUser([
            'id' => '',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_cancellation_and_invalid_callback_fail_readably(): void
    {
        Socialite::fake('google');
        $this->get(route('auth.google.redirect'));

        $this->get(route('auth.google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        Socialite::fake('google');
        $this->get(route('auth.google.redirect'));
        Socialite::fake('google', fn () => throw new RuntimeException('Invalid callback'));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['google'], null, 'google');

        $this->assertGuest();
    }

    private function startGuestFlow(GoogleUser $googleUser): void
    {
        Socialite::fake('google');
        $this->get(route('auth.google.redirect'));
        Socialite::fake('google', $googleUser);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function googleUser(array $attributes = []): GoogleUser
    {
        return GoogleUser::fake(array_merge([
            'id' => 'google-123',
            'name' => 'Cliente Google',
            'email' => 'google@example.com',
            'email_verified' => true,
        ], $attributes));
    }
}
