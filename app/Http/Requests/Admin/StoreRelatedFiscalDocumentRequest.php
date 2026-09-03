<?php

namespace App\Http\Requests\Admin;

use App\Enums\FiscalDocumentType;
use Illuminate\Validation\Rule;

class StoreRelatedFiscalDocumentRequest extends StoreFiscalDocumentRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['required', Rule::enum(FiscalDocumentType::class)],
        ]);
    }
}
