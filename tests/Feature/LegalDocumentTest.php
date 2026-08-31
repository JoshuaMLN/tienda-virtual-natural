<?php

namespace Tests\Feature;

use App\Enums\LegalDocumentStatus;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\Setting;
use App\Models\User;
use App\Support\Legal\LegalDocumentException;
use App\Support\Legal\LegalDocumentService;
use App\Support\Legal\LegalReadinessService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::clearLocalCache();
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();
        parent::tearDown();
    }

    public function test_migration_provides_one_active_demo_version_for_each_document_type(): void
    {
        $this->assertDatabaseCount('legal_documents', 2);

        foreach (LegalDocumentType::cases() as $type) {
            $document = LegalDocument::query()->ofType($type)->published()->sole();

            $this->assertSame(1, $document->version);
            $this->assertSame($type->value, $document->active_slot);
            $this->assertNotNull($document->settings_snapshot);
            $this->assertSame(64, strlen((string) $document->settings_fingerprint));
        }
    }

    public function test_admin_creates_only_one_draft_per_type_and_can_update_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $first = $this->post(route('admin.legal.documents.store'), ['type' => 'terms']);
        $draft = LegalDocument::query()
            ->ofType(LegalDocumentType::Terms)
            ->where('status', LegalDocumentStatus::Draft->value)
            ->sole();

        $first->assertRedirect(route('admin.legal.documents.edit', $draft));

        $this->post(route('admin.legal.documents.store'), ['type' => 'terms'])
            ->assertRedirect(route('admin.legal.documents.edit', $draft))
            ->assertSessionHas('info');

        $this->put(route('admin.legal.documents.update', $draft), [
            'title' => 'Terminos comerciales actualizados',
            'body' => str_repeat('Contenido legal revisado para la nueva version. ', 4),
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('Terminos comerciales actualizados', $draft->fresh()->title);
        $this->assertSame(1, LegalDocument::query()->where('status', LegalDocumentStatus::Draft->value)->count());
    }

    public function test_publishing_replaces_the_active_version_and_preserves_snapshot_and_actor(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(LegalDocumentService::class);
        $previous = $service->active(LegalDocumentType::Terms);
        Setting::setValue(Setting::LEGAL_TRADE_NAME, 'Nueva Marca');
        Setting::clearLocalCache();
        $draft = $service->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];
        $published = $service->publish($draft, $admin);

        $this->assertSame(2, $published->version);
        $this->assertSame(LegalDocumentStatus::Published, $published->status);
        $this->assertSame('terms', $published->active_slot);
        $this->assertSame('Nueva Marca', $published->settings_snapshot['trade_name']);
        $this->assertTrue($published->publisher->is($admin));
        $this->assertTrue($published->creator->is($admin));
        $this->assertSame(LegalDocumentStatus::Replaced, $previous->fresh()->status);
        $this->assertNull($previous->fresh()->active_slot);
        $this->assertNotNull($previous->fresh()->replaced_at);
        $this->assertSame(1, LegalDocument::query()->ofType(LegalDocumentType::Terms)->published()->count());
    }

    public function test_published_and_replaced_versions_are_immutable_and_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(LegalDocumentService::class);
        $previous = $service->active(LegalDocumentType::Terms);
        $published = $service->publish(
            $service->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'],
            $admin,
        );

        foreach ([$previous->fresh(), $published->fresh()] as $document) {
            try {
                $document->update(['title' => 'Cambio prohibido']);
                $this->fail('Una version historica no debe poder modificarse.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }

            try {
                $document->delete();
                $this->fail('Una version historica no debe poder eliminarse.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_database_prevents_two_active_documents_of_the_same_type(): void
    {
        $this->expectException(QueryException::class);

        LegalDocument::factory()->create([
            'type' => LegalDocumentType::Terms,
            'version' => 99,
            'status' => LegalDocumentStatus::Published,
            'active_slot' => LegalDocumentType::Terms->value,
            'published_at' => now(),
        ]);
    }

    public function test_database_prevents_two_drafts_of_the_same_type(): void
    {
        $admin = User::factory()->admin()->create();
        app(LegalDocumentService::class)->findOrCreateDraft(LegalDocumentType::Terms, $admin);

        $this->expectException(QueryException::class);

        LegalDocument::factory()->create([
            'type' => LegalDocumentType::Terms,
            'draft_slot' => LegalDocumentType::Terms->value,
        ]);
    }

    public function test_draft_can_be_discarded_without_affecting_published_version(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(LegalDocumentService::class);
        $draft = $service->findOrCreateDraft(LegalDocumentType::Privacy, $admin)['document'];

        $service->discardDraft($draft);

        $this->assertModelMissing($draft);
        $this->assertNotNull($service->active(LegalDocumentType::Privacy));
    }

    public function test_public_pages_render_active_documents_and_demo_notice(): void
    {
        $this->get(route('shop.terms'))
            ->assertOk()
            ->assertSee('Terminos y condiciones')
            ->assertSee('Version 1')
            ->assertSee('No se aceptan devoluciones por cambio de opinion')
            ->assertSee('desde que la tienda los marca como listos')
            ->assertSee('Este sitio funciona en modo demostrativo')
            ->assertSee(route('shop.privacy'), false);

        $this->get(route('shop.privacy'))
            ->assertOk()
            ->assertSee('Politica de privacidad')
            ->assertSee('Las comunicaciones publicitarias requieren una autorizacion separada')
            ->assertSee(route('shop.terms'), false);
    }

    public function test_changing_legal_settings_invalidates_previous_document_fingerprints(): void
    {
        $readiness = app(LegalReadinessService::class);

        $this->assertFalse($readiness->canEnableLiveSales());
        Setting::setValue(Setting::LEGAL_TRADE_NAME, 'Marca modificada');
        Setting::clearLocalCache();

        $this->assertContains(
            'Republicar Terminos y condiciones con la configuracion vigente',
            $readiness->missingRequirements(),
        );
    }

    public function test_stale_draft_must_be_regenerated_before_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(LegalDocumentService::class);
        $draft = $service->findOrCreateDraft(LegalDocumentType::Terms, $admin)['document'];

        Setting::setValue(Setting::LEGAL_TRADE_NAME, 'Marca posterior');
        Setting::clearLocalCache();

        try {
            $service->publish($draft, $admin);
            $this->fail('Un borrador desactualizado no debe publicarse.');
        } catch (LegalDocumentException $exception) {
            $this->assertStringContainsString('Regeneralo', $exception->getMessage());
        }

        $refreshed = $service->refreshDraftTemplate($draft);
        $published = $service->publish($refreshed, $admin);

        $this->assertSame('Marca posterior', $published->settings_snapshot['trade_name']);
        $this->assertStringContainsString('Marca posterior', $published->body);
    }
}
