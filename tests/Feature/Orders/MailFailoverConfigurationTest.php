<?php

namespace Tests\Feature\Orders;

use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Tests\TestCase;

class MailFailoverConfigurationTest extends TestCase
{
    public function test_smtp_failover_uses_two_encrypted_relays_without_a_log_fallback(): void
    {
        $this->assertSame('failover', config('mail.mailers.failover.transport'));
        $this->assertSame(
            ['smtp', 'smtp_fallback'],
            config('mail.mailers.failover.mailers'),
        );
        $this->assertSame('smtp', config('mail.mailers.smtp.transport'));
        $this->assertSame('smtp', config('mail.mailers.smtp_fallback.transport'));
        $this->assertArrayNotHasKey('stream', config('mail.mailers.smtp'));
        $this->assertArrayNotHasKey('stream', config('mail.mailers.smtp_fallback'));

        config()->set('mail.default', 'failover');
        app(MailManager::class)->purge();

        $this->assertInstanceOf(
            FailoverTransport::class,
            app(MailManager::class)->mailer()->getSymfonyTransport(),
        );
    }
}
