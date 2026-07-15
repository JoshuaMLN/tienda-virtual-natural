<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->get(route('account.profile'))
            ->assertRedirect(route('login'));

        $this->patch(route('account.profile.update'), [
            'name' => 'Cliente',
            'email' => 'cliente@example.com',
        ])->assertRedirect(route('login'));
    }

    public function test_customer_sees_real_profile_data_and_initials_fallback(): void
    {
        $user = User::factory()->create([
            'name' => 'Lucia Fernanda Ramos',
            'email' => 'lucia@example.com',
            'phone' => '987654321',
            'avatar_path' => null,
            'created_at' => '2026-06-12 10:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('Lucia Fernanda Ramos')
            ->assertSee('lucia@example.com')
            ->assertSee('987654321')
            ->assertSee('LR')
            ->assertSee('12/06/2026')
            ->assertSee('Correo verificado')
            ->assertDontSee('Maria Fernanda')
            ->assertDontSee('S/ 1,245.80');
    }

    public function test_customer_can_update_own_profile_with_normalized_values(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'cliente@example.com',
            'email_verified_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Otro cliente',
            'email' => 'otro@example.com',
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'user_id' => $otherUser->id,
                'name' => '  Ana   Torres  ',
                'phone' => '987 654 321',
                'email' => ' CLIENTE@EXAMPLE.COM ',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertSame('Ana Torres', $user->name);
        $this->assertSame('987654321', $user->phone);
        $this->assertSame('cliente@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Otro cliente', $otherUser->fresh()->name);
        Notification::assertNothingSent();
    }

    public function test_profile_rejects_an_email_used_by_another_customer(): void
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);
        User::factory()->create(['email' => 'ocupado@example.com']);

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => 'ocupado@example.com',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors(['email'], null, 'profile');

        $this->assertSame('cliente@example.com', $user->fresh()->email);
    }

    public function test_google_linked_email_is_readonly_and_explains_how_to_unlock_it(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'password' => null,
        ]);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-profile-lock',
        ]);

        $this->actingAs($user)
            ->get(route('account.profile'))
            ->assertOk()
            ->assertSee('data-google-email-locked', false)
            ->assertSee('readonly', false)
            ->assertSee('desvincula Google desde Seguridad')
            ->assertSee('Antes deberas definir una contrasena.')
            ->assertSee(route('account.security'), false);
    }

    public function test_profile_rejects_email_change_while_google_is_linked(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'google@example.com',
            'email_verified_at' => now(),
        ]);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-blocked-email-change',
        ]);

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->patch(route('account.profile.update'), [
                'name' => 'Nombre manipulado',
                'phone' => '999888777',
                'email' => 'nuevo@example.com',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors(['email'], null, 'profile');

        $user->refresh();
        $this->assertNotSame('Nombre manipulado', $user->name);
        $this->assertNotSame('999888777', $user->phone);
        $this->assertSame('google@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_google_linked_customer_can_update_other_profile_fields(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'google@example.com',
        ]);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-allowed-profile-change',
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => 'Nuevo Nombre',
                'phone' => '987654321',
                'email' => 'google@example.com',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();
        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertSame('987654321', $user->phone);
        $this->assertSame('google@example.com', $user->email);
        Notification::assertNothingSent();
    }

    public function test_customer_can_change_email_after_unlinking_google(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'google@example.com',
            'password' => 'local-password',
            'email_verified_at' => now(),
        ]);
        $user->socialAccounts()->create([
            'provider' => SocialAccount::PROVIDER_GOOGLE,
            'provider_user_id' => 'google-unlinked-before-email-change',
        ]);

        $this->actingAs($user)
            ->delete(route('account.google.unlink'))
            ->assertRedirect()
            ->assertSessionHas('status', 'google-unlinked');

        $this->patch(route('account.profile.update'), [
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => 'nuevo@example.com',
        ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'profile-updated-verification-required');

        $user->refresh();
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => SocialAccount::PROVIDER_GOOGLE,
        ]);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_changing_email_invalidates_verification_and_sends_a_new_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'anterior@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => 'nuevo@example.com',
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'profile-updated-verification-required');

        $user->refresh();
        $this->assertSame('nuevo@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->get(route('checkout.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_customer_can_store_a_square_webp_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'cropped_avatar' => $this->imageDataUrl(720, 720),
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('status', 'profile-updated');

        $avatarPath = $user->fresh()->avatar_path;
        $this->assertNotNull($avatarPath);
        $this->assertStringEndsWith('.webp', $avatarPath);
        Storage::disk('public')->assertExists($avatarPath);

        $dimensions = getimagesizefromstring(Storage::disk('public')->get($avatarPath));
        $this->assertIsArray($dimensions);
        $this->assertSame(512, $dimensions[0]);
        $this->assertSame(512, $dimensions[1]);
    }

    public function test_customer_can_replace_an_avatar_without_leaving_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old-avatar.webp', 'old-image');

        $user = User::factory()->create([
            'avatar_path' => 'avatars/old-avatar.webp',
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'cropped_avatar' => $this->imageDataUrl(720, 720, [46, 125, 50]),
            ])
            ->assertRedirect(route('account.profile'));

        $newAvatarPath = $user->fresh()->avatar_path;
        $this->assertNotSame('avatars/old-avatar.webp', $newAvatarPath);
        Storage::disk('public')->assertMissing('avatars/old-avatar.webp');
        Storage::disk('public')->assertExists($newAvatarPath);
    }

    public function test_customer_can_remove_avatar_and_return_to_initials_fallback(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/current.webp', 'current-image');

        $user = User::factory()->create([
            'name' => 'Diego Soto',
            'avatar_path' => 'avatars/current.webp',
        ]);

        $this->actingAs($user)
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'remove_avatar' => '1',
            ])
            ->assertRedirect(route('account.profile'));

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing('avatars/current.webp');

        $this->get(route('account.profile'))
            ->assertOk()
            ->assertSee('DS');
    }

    public function test_profile_rejects_a_non_square_crop(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.profile'))
            ->patch(route('account.profile.update'), [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'cropped_avatar' => $this->imageDataUrl(720, 480),
            ])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHasErrors(['cropped_avatar'], null, 'profile');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_account_order_and_address_pages_do_not_show_mock_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee('Aun no tienes pedidos')
            ->assertDontSee('VN-2024-000123');

        $this->get(route('account.addresses'))
            ->assertOk()
            ->assertSee('Aun no tienes direcciones guardadas')
            ->assertDontSee('Av. Caminos del Inca');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function imageDataUrl(
        int $width,
        int $height,
        array $rgb = [91, 153, 63]
    ): string {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode((string) $contents);
    }
}
