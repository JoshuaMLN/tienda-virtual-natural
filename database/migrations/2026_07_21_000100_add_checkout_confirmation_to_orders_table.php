<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('checkout_idempotency_key')->nullable()->after('sequence_number');
            $table->char('checkout_review_reference', 64)->nullable()->unique()->after('checkout_idempotency_key');
            $table->foreignId('terms_document_id')
                ->nullable()
                ->after('fiscal_email')
                ->constrained('legal_documents')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedInteger('terms_document_version')->nullable()->after('terms_document_id');
            $table->char('terms_content_fingerprint', 64)->nullable()->after('terms_document_version');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_content_fingerprint');
            $table->json('terms_snapshot')->nullable()->after('terms_accepted_at');
            $table->timestamp('cart_cleaned_at')->nullable()->after('terms_snapshot');

            $table->index(['terms_document_id', 'terms_document_version']);
            $table->unique(['user_id', 'checkout_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['terms_document_id', 'terms_document_version']);
            $table->dropForeign(['terms_document_id']);
            $table->dropUnique(['user_id', 'checkout_idempotency_key']);
            $table->dropUnique(['checkout_review_reference']);
            $table->dropColumn([
                'checkout_idempotency_key',
                'checkout_review_reference',
                'terms_document_id',
                'terms_document_version',
                'terms_content_fingerprint',
                'terms_accepted_at',
                'terms_snapshot',
                'cart_cleaned_at',
            ]);
        });
    }
};
