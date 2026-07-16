<?php

namespace Database\Factories;

use App\Enums\FiscalDeliveryStatus;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalDocumentDelivery> */
class FiscalDocumentDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscal_document_id' => FiscalDocument::factory(),
            'recipient_email' => fake()->safeEmail(),
            'status' => FiscalDeliveryStatus::Sent,
            'attempted_by' => null,
            'attempted_by_name' => null,
            'attempted_by_email' => null,
            'attempted_at' => now(),
            'sent_at' => now(),
            'error_message' => null,
        ];
    }
}
