<?php

namespace Tests\Feature\Orders;

use App\Enums\FiscalDeliveryStatus;
use App\Jobs\SendFiscalDocumentEmail;
use App\Models\FiscalDocument;
use App\Models\Order;
use App\Models\User;
use App\Notifications\FiscalDocumentNotification;
use App\Support\Orders\Fiscal\FiscalDocumentService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FiscalDocumentEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_job_sends_the_current_pdf_to_the_fiscal_snapshot_and_records_the_admin_snapshot(): void
    {
        Notification::fake();
        $order = Order::factory()->paid()->create([
            'fiscal_email' => 'facturacion@example.test',
        ]);
        $document = FiscalDocument::factory()->for($order)->create([
            'series' => 'B001',
            'correlative' => '00000073',
            'pdf_path' => 'fiscal-documents/73.pdf',
        ]);
        Storage::disk('local')->put($document->pdf_path, 'PDF de prueba');
        $admin = User::factory()->admin()->create([
            'name' => 'Admin de facturacion',
            'email' => 'admin.facturacion@example.test',
        ]);
        $job = new SendFiscalDocumentEmail($document->id, $admin->id, $admin->name, $admin->email);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $job);
        $this->assertSame(1, $job->tries);
        $this->assertSame((string) $document->id, $job->uniqueId());

        $job->handle(app(FiscalDocumentService::class));

        $delivery = $document->deliveries()->sole();
        $this->assertSame(FiscalDeliveryStatus::Sent, $delivery->status);
        $this->assertSame('facturacion@example.test', $delivery->recipient_email);
        $this->assertSame($admin->id, $delivery->attempted_by);
        $this->assertSame('Admin de facturacion', $delivery->attempted_by_name);
        $this->assertSame('admin.facturacion@example.test', $delivery->attempted_by_email);
        $this->assertNotNull($delivery->attempted_at);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->error_message);

        Notification::assertSentOnDemand(
            FiscalDocumentNotification::class,
            function ($notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $channels === ['mail']
                    && $notifiable->routes['mail'] === 'facturacion@example.test';
            },
        );
    }

    public function test_job_records_a_missing_pdf_or_transport_error_as_failed_without_sending_another_document(): void
    {
        Notification::fake();
        $order = Order::factory()->paid()->create();
        $missing = FiscalDocument::factory()->for($order)->create([
            'pdf_path' => 'fiscal-documents/missing.pdf',
        ]);

        (new SendFiscalDocumentEmail($missing->id, null, 'Admin', 'admin@example.test'))
            ->handle(app(FiscalDocumentService::class));

        $failed = $missing->deliveries()->sole();
        $this->assertSame(FiscalDeliveryStatus::Failed, $failed->status);
        $this->assertSame('El PDF vigente del comprobante ya no esta disponible.', $failed->error_message);
        Notification::assertNothingSent();

        $transport = FiscalDocument::factory()->for($order)->create([
            'sale_document_slot' => null,
            'pdf_path' => 'fiscal-documents/transport.pdf',
        ]);
        Storage::disk('local')->put($transport->pdf_path, 'PDF de prueba');
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('sendNow')->once()->andThrow(new RuntimeException('SMTP no disponible'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        (new SendFiscalDocumentEmail($transport->id, null, 'Admin', 'admin@example.test'))
            ->handle(app(FiscalDocumentService::class));

        $failedTransport = $transport->deliveries()->sole();
        $this->assertSame(FiscalDeliveryStatus::Failed, $failedTransport->status);
        $this->assertSame('SMTP no disponible', $failedTransport->error_message);
    }

    public function test_job_does_not_send_or_record_a_document_annulled_before_the_worker_runs(): void
    {
        Notification::fake();
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create([
            'pdf_path' => 'fiscal-documents/annulled.pdf',
        ]);
        Storage::disk('local')->put($document->pdf_path, 'PDF de prueba');
        app(FiscalDocumentService::class)->annul($document, 'Anulado externamente');

        (new SendFiscalDocumentEmail($document->id, null, 'Admin', 'admin@example.test'))
            ->handle(app(FiscalDocumentService::class));

        $this->assertDatabaseCount('fiscal_document_deliveries', 0);
        Notification::assertNothingSent();
    }

    public function test_notification_attaches_only_the_private_current_pdf(): void
    {
        $order = Order::factory()->paid()->create();
        $document = FiscalDocument::factory()->for($order)->create([
            'series' => 'F001',
            'correlative' => '00000081',
            'pdf_path' => 'fiscal-documents/81.pdf',
        ]);
        Storage::disk('local')->put($document->pdf_path, 'PDF de prueba');

        $mail = (new FiscalDocumentNotification($document->load('order')))
            ->toMail(new AnonymousNotifiable);

        $this->assertStringContainsString('F001-00000081', $mail->subject);
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('PDF de prueba', $mail->rawAttachments[0]['data']);
        $this->assertSame('boleta-F001-00000081.pdf', $mail->rawAttachments[0]['name']);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
    }
}
