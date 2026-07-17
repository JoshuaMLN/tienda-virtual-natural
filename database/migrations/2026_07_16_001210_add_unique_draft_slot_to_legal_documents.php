<?php

use App\Enums\LegalDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->string('draft_slot', 20)->nullable()->unique()->after('active_slot');
        });

        foreach (LegalDocumentType::cases() as $type) {
            $draftId = DB::table('legal_documents')
                ->where('type', $type->value)
                ->where('status', 'draft')
                ->orderByDesc('id')
                ->value('id');

            if ($draftId !== null) {
                DB::table('legal_documents')->where('id', $draftId)->update(['draft_slot' => $type->value]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('legal_documents', function (Blueprint $table) {
            $table->dropUnique(['draft_slot']);
            $table->dropColumn('draft_slot');
        });
    }
};
