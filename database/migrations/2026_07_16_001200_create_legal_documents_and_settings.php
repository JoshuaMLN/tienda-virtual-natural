<?php

use App\Enums\LegalDocumentType;
use App\Support\Legal\LegalDocumentTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULTS = [
        'legal_trade_name' => 'VitaNatural',
        'legal_provider_name' => '',
        'legal_tax_id' => '',
        'legal_fiscal_address' => '',
        'legal_complaints_book_url' => '',
        'live_sales_enabled' => '0',
        'incident_report_hours' => '48',
        'refund_processing_business_days' => '5',
        'delivery_attempts_per_cycle' => '3',
        'delivery_max_automatic_cycles' => '2',
        'reshipment_payment_days' => '7',
        'pickup_hold_days' => '14',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore(array_map(
            fn (string $key, string $value): array => [
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys(self::DEFAULTS),
            array_values(self::DEFAULTS),
        ));

        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['terms', 'privacy'])->index();
            $table->unsignedInteger('version')->nullable();
            $table->string('title', 160);
            $table->longText('body');
            $table->enum('status', ['draft', 'published', 'replaced'])->default('draft')->index();
            $table->string('active_slot', 20)->nullable()->unique();
            $table->json('settings_snapshot')->nullable();
            $table->char('settings_fingerprint', 64)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'status', 'created_at']);
        });

        $snapshot = $this->snapshot();
        $encodedSnapshot = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $fingerprint = hash('sha256', $encodedSnapshot);
        $template = new LegalDocumentTemplate;

        foreach (LegalDocumentType::cases() as $type) {
            $document = $template->render($type, $snapshot);

            DB::table('legal_documents')->insert([
                'type' => $type->value,
                'version' => 1,
                'title' => $document['title'],
                'body' => $document['body'],
                'status' => 'published',
                'active_slot' => $type->value,
                'settings_snapshot' => $encodedSnapshot,
                'settings_fingerprint' => $fingerprint,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
        DB::table('settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }

    /** @return array<string, int|string> */
    private function snapshot(): array
    {
        $keys = [
            'legal_trade_name' => 'trade_name',
            'legal_provider_name' => 'provider_name',
            'legal_tax_id' => 'tax_id',
            'legal_fiscal_address' => 'fiscal_address',
            'legal_complaints_book_url' => 'complaints_book_url',
            'contact_email' => 'contact_email',
            'contact_whatsapp' => 'contact_whatsapp',
            'business_hours_weekdays' => 'business_hours_weekdays',
            'business_hours_saturday' => 'business_hours_saturday',
            'incident_report_hours' => 'incident_report_hours',
            'refund_processing_business_days' => 'refund_processing_business_days',
            'delivery_attempts_per_cycle' => 'delivery_attempts_per_cycle',
            'delivery_max_automatic_cycles' => 'delivery_max_automatic_cycles',
            'reshipment_payment_days' => 'reshipment_payment_days',
            'pickup_hold_days' => 'pickup_hold_days',
        ];
        $snapshot = [];

        foreach ($keys as $settingKey => $snapshotKey) {
            $value = DB::table('settings')->where('key', $settingKey)->value('value')
                ?? self::DEFAULTS[$settingKey]
                ?? '';
            $snapshot[$snapshotKey] = in_array($snapshotKey, [
                'incident_report_hours',
                'refund_processing_business_days',
                'delivery_attempts_per_cycle',
                'delivery_max_automatic_cycles',
                'reshipment_payment_days',
                'pickup_hold_days',
            ], true) ? (int) $value : (string) $value;
        }

        ksort($snapshot);

        return $snapshot;
    }
};
