<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_the_custom_verification_notification(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Maria Fernanda',
            'email' => 'maria@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->sole();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_unverified_customer_can_open_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee(route('verification.send'), false)
            ->assertSee('data-bs-target="#logoutConfirmationModal"', false)
            ->assertSee('Confirmar cierre de sesion');
    }

    public function test_verification_link_marks_the_email_as_verified(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('account.profile'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_verification_link_rejects_an_invalid_email_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('otro@example.com')]
        );

        $this->actingAs($user)
            ->get($url)
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_customer_can_request_another_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_checkout_requires_authentication_and_verified_email(): void
    {
        $this->get(route('checkout.index'))
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->get(route('checkout.index'))
            ->assertRedirect(route('verification.notice'));

        $verified = User::factory()->create();
        $this->actingAs($verified)
            ->get(route('checkout.index'))
            ->assertOk();
    }

    public function test_changing_email_invalidates_its_verification(): void
    {
        $user = User::factory()->create();

        $user->update(['email' => 'nuevo@example.com']);

        $this->assertSame('nuevo@example.com', $user->fresh()->email);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
