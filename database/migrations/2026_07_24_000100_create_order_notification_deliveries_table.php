<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('type', ['created', 'cancelled', 'expired']);
            $table->string('recipient_email');
            $table->string('recipient_name', 120)->nullable();
            $table->enum('status', ['queued', 'sending', 'sent', 'failed'])->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_id', 'type', 'recipient_email'],
                'order_notification_delivery_unique',
            );
            $table->index(['status', 'queued_at']);
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notification_deliveries');
    }
};
