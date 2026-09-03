<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_document_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('pdf_path');
            $table->string('reason', 500);
            $table->foreignId('replaced_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('replaced_by_name', 120)->nullable();
            $table->string('replaced_by_email')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['fiscal_document_id', 'version']);
        });

        Schema::create('fiscal_document_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->json('before_values');
            $table->json('after_values');
            $table->string('reason', 500);
            $table->foreignId('corrected_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('corrected_by_name', 120)->nullable();
            $table->string('corrected_by_email')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_corrections');
        Schema::dropIfExists('fiscal_document_file_versions');
    }
};
