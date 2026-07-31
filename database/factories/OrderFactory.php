<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaxAffectation;
use App\Models\Order;
use App\Models\User;
use App\Support\Delivery\BusinessDayCalendar;
use App\Support\Orders\OrderHistoryRecorder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $year = (int) now()->format('Y');
        $number = fake()->unique()->numberBetween(1, 999_999);

        return [
            'code' => sprintf('PED-%d-%06d', $year, $number),
            'sequence_year' => $year,
            'sequence_number' => $number,
            'user_id' => User::factory(),
            'customer_address_id' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '9'.fake()->numerify('########'),
            'order_status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
            'delivery_status' => DeliveryStatus::Pending,
            'delivery_method' => DeliveryMethod::HomeDelivery,
            'delivery_recipient_name' => fake()->name(),
            'delivery_phone' => '9'.fake()->numerify('########'),
            'delivery_department' => 'Lima',
            'delivery_province' => 'Lima',
            'delivery_district' => 'San Isidro',
            'delivery_ubigeo' => '150131',
            'delivery_address' => fake()->streetAddress(),
            'delivery_reference' => fake()->sentence(4),
            'pickup_address' => null,
            'fiscal_document_type' => FiscalDocumentType::Receipt,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Dni,
            'fiscal_identity_document_number' => fake()->numerify('########'),
            'fiscal_first_names' => fake()->firstName(),
            'fiscal_last_names' => fake()->lastName(),
            'fiscal_business_name' => null,
            'fiscal_address' => null,
            'fiscal_email' => fake()->safeEmail(),
            'products_subtotal_cents' => 10_000,
            'discount_cents' => 0,
            'shipping_fee_cents' => 0,
            'shipping_tax_affectation' => TaxAffectation::Taxed,
            'shipping_tax_rate_bps' => 1800,
            'shipping_net_value_cents' => 0,
            'shipping_tax_cents' => 0,
            'taxable_value_cents' => 8475,
            'exempt_value_cents' => 0,
            'unaffected_value_cents' => 0,
            'net_value_cents' => 8475,
            'tax_cents' => 1525,
            'total_cents' => 10_000,
            'delivery_business_days_min' => 1,
            'delivery_business_days_max' => 2,
            'delivery_estimated_from' => null,
            'delivery_estimated_to' => null,
            'reservation_expires_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            if (! $order->statusHistories()->exists()) {
                app(OrderHistoryRecorder::class)->recordInitialStates($order);
            }
        });
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes): array {
            $paidAt = now();
            $estimatedDates = app(BusinessDayCalendar::class)->estimate(
                (int) ($attributes['delivery_business_days_min'] ?? 1),
                (int) ($attributes['delivery_business_days_max'] ?? 2),
                $paidAt,
            );

            return [
                'payment_status' => PaymentStatus::Paid,
                'paid_at' => $paidAt,
                'delivery_window_starts_at' => $paidAt,
                'delivery_estimated_from' => $estimatedDates->from,
                'delivery_estimated_to' => $estimatedDates->to,
            ];
        });
    }

    public function processing(): static
    {
        return $this->paid()->state(fn (): array => [
            'order_status' => OrderStatus::Processing,
        ]);
    }

    public function refundPending(): static
    {
        return $this->paid()->state(fn (): array => [
            'order_status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::RefundPending,
            'delivery_status' => DeliveryStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->refundPending()->state(fn (): array => [
            'payment_status' => PaymentStatus::Refunded,
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn (): array => [
            'delivery_method' => DeliveryMethod::Pickup,
            'delivery_recipient_name' => null,
            'delivery_phone' => null,
            'delivery_department' => null,
            'delivery_province' => null,
            'delivery_district' => null,
            'delivery_ubigeo' => null,
            'delivery_address' => null,
            'delivery_reference' => null,
            'pickup_address' => 'Av. Javier Prado 1234, San Isidro',
        ]);
    }

    public function invoice(): static
    {
        return $this->state(fn (): array => [
            'fiscal_document_type' => FiscalDocumentType::Invoice,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::Ruc,
            'fiscal_identity_document_number' => '20131312955',
            'fiscal_first_names' => null,
            'fiscal_last_names' => null,
            'fiscal_business_name' => fake()->company(),
            'fiscal_address' => fake()->address(),
        ]);
    }
}
