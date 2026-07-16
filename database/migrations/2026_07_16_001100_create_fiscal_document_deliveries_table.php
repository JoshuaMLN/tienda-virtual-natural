<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_document_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('recipient_email');
            $table->enum('status', ['sent', 'failed']);
            $table->foreignId('attempted_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('attempted_by_name', 120)->nullable();
            $table->string('attempted_by_email')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['fiscal_document_id', 'attempted_at']);
            $table->index(['status', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_document_deliveries');
    }
};
