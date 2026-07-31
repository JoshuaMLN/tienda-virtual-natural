<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WITH_SUPERSEDED = [
        'queued',
        'sending',
        'sent',
        'failed',
        'superseded',
    ];

    private const WITHOUT_SUPERSEDED = [
        'queued',
        'sending',
        'sent',
        'failed',
    ];

    public function up(): void
    {
        Schema::table('order_notification_deliveries', function (Blueprint $table): void {
            $table->enum('status', self::WITH_SUPERSEDED)
                ->default('queued')
                ->change();
            $table->timestamp('superseded_at')->nullable()->after('failed_at');
            $table->text('superseded_reason')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        DB::table('order_notification_deliveries')
            ->where('status', 'superseded')
            ->update([
                'status' => 'failed',
                'failed_at' => DB::raw('COALESCE(superseded_at, CURRENT_TIMESTAMP)'),
                'last_error' => DB::raw("COALESCE(superseded_reason, 'Comunicacion omitida antes de revertir la migracion.')"),
            ]);

        Schema::table('order_notification_deliveries', function (Blueprint $table): void {
            $table->dropColumn(['superseded_at', 'superseded_reason']);
            $table->enum('status', self::WITHOUT_SUPERSEDED)
                ->default('queued')
                ->change();
        });
    }
};
