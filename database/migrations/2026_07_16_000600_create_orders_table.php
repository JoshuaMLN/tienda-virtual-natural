<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('code', 15)->unique();
            $table->unsignedSmallInteger('sequence_year');
            $table->decimal('sequence_number', 6, 0)->unsigned();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();

            $table->string('customer_name', 120);
            $table->string('customer_email')->index();
            $table->string('customer_phone', 20)->nullable();

            $table->enum('order_status', ['pending_payment', 'processing', 'completed', 'cancelled', 'expired'])
                ->default('pending_payment')
                ->index();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'expired', 'refunded'])
                ->default('pending')
                ->index();
            $table->enum('delivery_status', ['pending', 'preparing', 'shipped', 'ready_for_pickup', 'delivered', 'picked_up', 'cancelled'])
                ->default('pending')
                ->index();
            $table->enum('delivery_method', ['home_delivery', 'pickup'])->index();

            $table->string('delivery_recipient_name', 120)->nullable();
            $table->string('delivery_phone', 20)->nullable();
            $table->string('delivery_department', 80)->nullable();
            $table->string('delivery_province', 80)->nullable();
            $table->string('delivery_district', 100)->nullable();
            $table->char('delivery_ubigeo', 6)->nullable()->index();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_reference')->nullable();
            $table->string('pickup_address')->nullable();

            $table->enum('fiscal_document_type', ['receipt', 'invoice'])->index();
            $table->enum('fiscal_identity_document_type', ['dni', 'foreigner_card', 'passport', 'ruc'])->nullable();
            $table->string('fiscal_identity_document_number', 20)->nullable();
            $table->string('fiscal_first_names', 120)->nullable();
            $table->string('fiscal_last_names', 120)->nullable();
            $table->string('fiscal_business_name', 200)->nullable();
            $table->string('fiscal_address')->nullable();
            $table->string('fiscal_email')->index();

            $table->unsignedBigInteger('products_subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('shipping_fee_cents')->default(0);
            $table->enum('shipping_tax_affectation', ['taxed', 'exempt', 'unaffected'])->default('taxed');
            $table->unsignedSmallInteger('shipping_tax_rate_bps')->default(1800);
            $table->unsignedBigInteger('shipping_net_value_cents')->default(0);
            $table->unsignedBigInteger('shipping_tax_cents')->default(0);
            $table->unsignedBigInteger('taxable_value_cents')->default(0);
            $table->unsignedBigInteger('exempt_value_cents')->default(0);
            $table->unsignedBigInteger('unaffected_value_cents')->default(0);
            $table->unsignedBigInteger('net_value_cents')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');

            $table->unsignedTinyInteger('delivery_business_days_min');
            $table->unsignedTinyInteger('delivery_business_days_max');
            $table->timestamp('delivery_window_starts_at')->nullable();
            $table->timestamp('reservation_expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['sequence_year', 'sequence_number']);
            $table->index(['user_id', 'created_at']);
            $table->index(['order_status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index(['delivery_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
