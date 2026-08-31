<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->uuid('operation_token')->unique();
            $table->unsignedTinyInteger('cycle');
            $table->unsignedSmallInteger('attempt_number');
            $table->unsignedTinyInteger('counted_attempt_number')->nullable();
            $table->enum('result', ['delivered', 'incident']);
            $table->enum('attribution', ['customer', 'store', 'carrier', 'unattributed']);
            $table->boolean('consumes_attempt')->default(false);
            $table->string('responsible_name', 120);
            $table->string('reason', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('recorded_by_name', 120)->nullable();
            $table->string('recorded_by_email')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['order_id', 'cycle', 'attempt_number']);
            $table->index(['order_id', 'occurred_at']);
            $table->index(['order_id', 'cycle', 'consumes_attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};
