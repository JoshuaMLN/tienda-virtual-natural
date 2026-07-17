<?php

namespace Tests\Feature;

use App\Enums\LegalDocumentType;
use App\Models\Setting;
use App\Models\User;
use App\Support\Legal\LegalDocumentService;
use App\Support\Legal\LegalReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLegalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::clearLocalCache();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Setting::clearLocalCache();

        parent::tearDown();
    }

    public function test_admin_can_open_legal_settings_in_demo_mode(): void
    {
        $this->get(route('admin.legal.index'))
            ->assertOk()
            ->assertSee('Configuracion legal')
            ->assertSee('Modo demostrativo')
            ->assertSee('Terminos y condiciones')
            ->assertSee('Politica de privacidad')
            ->assertSee('Version activa')
            ->assertSee('v1');
    }

    public function test_admin_can_update_identity_and_commercial_policy_values(): void
    {
        $this->patch(route('admin.legal.settings.update'), $this->validSettings([
            'incident_report_hours' => '72',
            'pickup_hold_days' => '21',
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', [
            'key' => Setting::LEGAL_PROVIDER_NAME,
            'value' => 'Natural Demo S.A.C.',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => Setting::LEGAL_TAX_ID,
            'value' => '20131312955',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => Setting::INCIDENT_REPORT_HOURS,
            'value' => '72',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => Setting::PICKUP_HOLD_DAYS,
            'value' => '21',
        ]);
    }

    public function test_invalid_ruc_and_policy_ranges_are_rejected(): void
    {
        $this->from(route('admin.legal.index'))
            ->patch(route('admin.legal.settings.update'), $this->validSettings([
                'legal_tax_id' => '20131312954',
                'delivery_attempts_per_cycle' => '11',
            ]))
            ->assertRedirect(route('admin.legal.index'))
            ->assertSessionHasErrors(['legal_tax_id', 'delivery_attempts_per_cycle']);
    }

    public function test_configured_complaints_book_is_visible_in_the_public_footer(): void
    {
        $this->patch(route('admin.legal.settings.update'), $this->validSettings())
            ->assertSessionHasNoErrors();

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Libro de Reclamaciones')
            ->assertSee('https://example.test/libro-reclamaciones', false);
    }

    public function test_live_sales_stay_disabled_until_current_documents_are_published(): void
    {
        $this->patch(route('admin.legal.settings.update'), $this->validSettings([
            'live_sales_enabled' => '1',
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $this->assertSame(0, Setting::integer(Setting::LIVE_SALES_ENABLED));
        $this->assertFalse(app(LegalReadinessService::class)->canEnableLiveSales());
    }

    public function test_admin_can_enable_live_sales_after_publishing_both_current_documents(): void
    {
        $this->patch(route('admin.legal.settings.update'), $this->validSettings())
            ->assertSessionHasNoErrors();

        $documents = app(LegalDocumentService::class);

        foreach (LegalDocumentType::cases() as $type) {
            $draft = $documents->findOrCreateDraft($type, $this->admin)['document'];
            $documents->publish($draft, $this->admin);
        }

        $this->patch(route('admin.legal.settings.update'), $this->validSettings([
            'live_sales_enabled' => '1',
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Setting::clearLocalCache();
        $this->assertSame(1, Setting::integer(Setting::LIVE_SALES_ENABLED));
        $this->assertTrue(app(LegalReadinessService::class)->canEnableLiveSales());
        $this->assertFalse(app(LegalReadinessService::class)->isDemoMode());

        $this->get(route('shop.terms'))
            ->assertOk()
            ->assertDontSee('Este sitio funciona en modo demostrativo');
    }

    public function test_customer_cannot_manage_legal_configuration_or_documents(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.legal.index'))->assertForbidden();
        $this->patch(route('admin.legal.settings.update'), $this->validSettings())->assertForbidden();
        $this->post(route('admin.legal.documents.store'), ['type' => 'terms'])->assertForbidden();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'legal_trade_name' => 'VitaNatural Demo',
            'legal_provider_name' => 'Natural Demo S.A.C.',
            'legal_tax_id' => '20131312955',
            'legal_fiscal_address' => 'Av. Demo 123, San Isidro, Lima',
            'legal_complaints_book_url' => 'https://example.test/libro-reclamaciones',
            'live_sales_enabled' => '0',
            'incident_report_hours' => '48',
            'refund_processing_business_days' => '5',
            'delivery_attempts_per_cycle' => '3',
            'delivery_max_automatic_cycles' => '2',
            'reshipment_payment_days' => '7',
            'pickup_hold_days' => '14',
        ], $overrides);
    }
}
