<?php

namespace Database\Factories;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FiscalDocument> */
class FiscalDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'parent_document_id' => null,
            'type' => FiscalDocumentType::Receipt,
            'sale_document_slot' => 'sale',
            'series' => 'B001',
            'correlative' => fake()->unique()->numerify('########'),
            'issued_at' => now(),
            'status' => FiscalDocumentStatus::Issued,
            'pdf_path' => 'fiscal/'.fake()->uuid().'.pdf',
            'xml_path' => null,
            'registered_by' => null,
            'registrar_name' => null,
            'registrar_email' => null,
        ];
    }
}
