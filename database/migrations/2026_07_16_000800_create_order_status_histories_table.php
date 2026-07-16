<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->enum('domain', ['order', 'payment', 'delivery', 'reservation']);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_email')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'domain', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
