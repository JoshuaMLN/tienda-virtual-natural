<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('parent_document_id')->nullable()->constrained('fiscal_documents')->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['receipt', 'invoice', 'credit_note', 'debit_note']);
            $table->string('sale_document_slot', 20)->nullable();
            $table->string('series', 10);
            $table->string('correlative', 20);
            $table->timestamp('issued_at');
            $table->enum('status', ['issued', 'annulled'])->default('issued')->index();
            $table->string('pdf_path');
            $table->string('xml_path')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('registrar_name', 120)->nullable();
            $table->string('registrar_email')->nullable();
            $table->timestamp('annulled_at')->nullable();
            $table->foreignId('annulled_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('annulled_by_name', 120)->nullable();
            $table->string('annulled_by_email')->nullable();
            $table->string('annulment_reason')->nullable();
            $table->timestamps();

            $table->unique(['type', 'series', 'correlative']);
            $table->unique(['order_id', 'sale_document_slot']);
            $table->index(['order_id', 'type']);
            $table->index(['parent_document_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
    }
};
