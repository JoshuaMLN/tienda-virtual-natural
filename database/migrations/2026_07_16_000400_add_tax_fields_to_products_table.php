<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('tax_affectation', ['taxed', 'exempt', 'unaffected'])
                ->default('taxed')
                ->after('compare_at_price')
                ->index();
            $table->unsignedSmallInteger('tax_rate_bps')
                ->default(1800)
                ->after('tax_affectation');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tax_affectation']);
            $table->dropColumn(['tax_affectation', 'tax_rate_bps']);
        });
    }
};
