<?php

namespace App\Http\Requests\Checkout;

use App\Enums\FiscalDocumentType;
use App\Enums\FiscalIdentityDocumentType;
use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Support\Fiscal\FiscalIdentityDocument;
use App\Support\Legal\LegalDocumentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewCheckoutRequest extends FormRequest
{
    protected $errorBag = 'checkoutReview';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'fiscal_document_type' => [
                'required',
                Rule::in([
                    FiscalDocumentType::Receipt->value,
                    FiscalDocumentType::Invoice->value,
                ]),
            ],
            'receipt_identity_document_type' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Receipt->value,
                'required',
                Rule::in([
                    FiscalIdentityDocumentType::Dni->value,
                    FiscalIdentityDocumentType::ForeignerCard->value,
                    FiscalIdentityDocumentType::Passport->value,
                ]),
            ],
            'receipt_identity_document_number' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Receipt->value,
                'required',
                'string',
                'max:20',
            ],
            'receipt_first_names' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Receipt->value,
                'required',
                'string',
                'max:120',
            ],
            'receipt_last_names' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Receipt->value,
                'required',
                'string',
                'max:120',
            ],
            'receipt_email' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Receipt->value,
                'required',
                'email:rfc',
                'max:255',
            ],
            'invoice_ruc' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Invoice->value,
                'required',
                'string',
                'size:11',
            ],
            'invoice_business_name' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Invoice->value,
                'required',
                'string',
                'max:200',
            ],
            'invoice_address' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Invoice->value,
                'required',
                'string',
                'max:255',
            ],
            'invoice_email' => [
                'exclude_unless:fiscal_document_type,'.FiscalDocumentType::Invoice->value,
                'required',
                'email:rfc',
                'max:255',
            ],
        ];

        if ($this->requiresTermsAcceptance()) {
            $rules['terms_document_id'] = [
                'required',
                'integer',
                Rule::exists(LegalDocument::class, 'id'),
            ];
            $rules['terms_accepted'] = ['required', 'accepted'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fiscal_document_type.required' => 'Selecciona boleta o factura.',
            'fiscal_document_type.in' => 'El tipo de comprobante seleccionado no es valido.',
            'receipt_identity_document_type.required' => 'Selecciona el documento para la boleta.',
            'receipt_identity_document_type.in' => 'El documento seleccionado no es valido para una boleta.',
            'receipt_identity_document_number.required' => 'Ingresa el numero de documento para la boleta.',
            'receipt_first_names.required' => 'Ingresa los nombres para la boleta.',
            'receipt_first_names.max' => 'Los nombres no deben superar los 120 caracteres.',
            'receipt_last_names.required' => 'Ingresa los apellidos para la boleta.',
            'receipt_last_names.max' => 'Los apellidos no deben superar los 120 caracteres.',
            'receipt_email.required' => 'Ingresa el correo donde deseas recibir la boleta.',
            'receipt_email.email' => 'Ingresa un correo valido para la boleta.',
            'invoice_ruc.required' => 'Ingresa el RUC para la factura.',
            'invoice_ruc.size' => 'El RUC debe tener 11 digitos.',
            'invoice_business_name.required' => 'Ingresa la razon social para la factura.',
            'invoice_business_name.max' => 'La razon social no debe superar los 200 caracteres.',
            'invoice_address.required' => 'Ingresa el domicilio fiscal para la factura.',
            'invoice_address.max' => 'El domicilio fiscal no debe superar los 255 caracteres.',
            'invoice_email.required' => 'Ingresa el correo donde deseas recibir la factura.',
            'invoice_email.email' => 'Ingresa un correo valido para la factura.',
            'terms_document_id.required' => 'Los terminos vigentes no estan disponibles. Intentalo nuevamente.',
            'terms_document_id.exists' => 'La version de terminos mostrada ya no esta disponible.',
            'terms_accepted.required' => 'Debes aceptar los terminos y condiciones para revisar el pedido.',
            'terms_accepted.accepted' => 'Debes aceptar los terminos y condiciones para revisar el pedido.',
        ];
    }

    /** @return array<string, mixed> */
    public function fiscalAttributes(): array
    {
        $type = FiscalDocumentType::from($this->validated('fiscal_document_type'));

        if ($type === FiscalDocumentType::Invoice) {
            return [
                'fiscal_document_type' => $type,
                'fiscal_identity_document_type' => FiscalIdentityDocumentType::Ruc,
                'fiscal_identity_document_number' => $this->validated('invoice_ruc'),
                'fiscal_first_names' => null,
                'fiscal_last_names' => null,
                'fiscal_business_name' => $this->validated('invoice_business_name'),
                'fiscal_address' => $this->validated('invoice_address'),
                'fiscal_email' => $this->validated('invoice_email'),
            ];
        }

        return [
            'fiscal_document_type' => $type,
            'fiscal_identity_document_type' => FiscalIdentityDocumentType::from(
                $this->validated('receipt_identity_document_type'),
            ),
            'fiscal_identity_document_number' => $this->validated('receipt_identity_document_number'),
            'fiscal_first_names' => $this->validated('receipt_first_names'),
            'fiscal_last_names' => $this->validated('receipt_last_names'),
            'fiscal_business_name' => null,
            'fiscal_address' => null,
            'fiscal_email' => $this->validated('receipt_email'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = trim((string) $this->input('fiscal_document_type'));
        $receiptIdentity = FiscalIdentityDocumentType::tryFrom(
            trim((string) $this->input('receipt_identity_document_type')),
        );

        $this->merge([
            'fiscal_document_type' => $type,
            'receipt_identity_document_type' => $receiptIdentity?->value,
            'receipt_identity_document_number' => $receiptIdentity
                ? FiscalIdentityDocument::normalize($receiptIdentity, $this->input('receipt_identity_document_number'))
                : trim((string) $this->input('receipt_identity_document_number')),
            'receipt_first_names' => Str::squish((string) $this->input('receipt_first_names')),
            'receipt_last_names' => Str::squish((string) $this->input('receipt_last_names')),
            'receipt_email' => Str::lower(trim((string) $this->input('receipt_email'))),
            'invoice_ruc' => FiscalIdentityDocument::normalize(
                FiscalIdentityDocumentType::Ruc,
                $this->input('invoice_ruc'),
            ),
            'invoice_business_name' => Str::squish((string) $this->input('invoice_business_name')),
            'invoice_address' => Str::squish((string) $this->input('invoice_address')),
            'invoice_email' => Str::lower(trim((string) $this->input('invoice_email'))),
            'terms_document_id' => $this->integer('terms_document_id'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = FiscalDocumentType::tryFrom((string) $this->input('fiscal_document_type'));

            if ($type === FiscalDocumentType::Receipt) {
                if ($this->hasAnyInput(['invoice_ruc', 'invoice_business_name', 'invoice_address', 'invoice_email'])) {
                    $validator->errors()->add(
                        'fiscal_document_type',
                        'La boleta no admite datos exclusivos de una factura.',
                    );
                }

                $identity = FiscalIdentityDocumentType::tryFrom(
                    (string) $this->input('receipt_identity_document_type'),
                );

                if (
                    $identity !== null
                    && ! $validator->errors()->has('receipt_identity_document_number')
                    && ! FiscalIdentityDocument::isValid(
                        $identity,
                        $this->input('receipt_identity_document_number'),
                    )
                ) {
                    $validator->errors()->add(
                        'receipt_identity_document_number',
                        FiscalIdentityDocument::invalidMessage($identity),
                    );
                }
            } elseif ($type === FiscalDocumentType::Invoice) {
                if ($this->hasAnyInput([
                    'receipt_identity_document_type',
                    'receipt_identity_document_number',
                    'receipt_first_names',
                    'receipt_last_names',
                    'receipt_email',
                ])) {
                    $validator->errors()->add(
                        'fiscal_document_type',
                        'La factura no admite datos exclusivos de una boleta.',
                    );
                }

                if (
                    ! $validator->errors()->has('invoice_ruc')
                    && ! FiscalIdentityDocument::isValid(
                        FiscalIdentityDocumentType::Ruc,
                        $this->input('invoice_ruc'),
                    )
                ) {
                    $validator->errors()->add(
                        'invoice_ruc',
                        FiscalIdentityDocument::invalidMessage(FiscalIdentityDocumentType::Ruc),
                    );
                }
            }

            if (! $this->requiresTermsAcceptance()) {
                return;
            }

            $terms = app(LegalDocumentService::class)->active(LegalDocumentType::Terms);

            if ($terms === null || (int) $this->input('terms_document_id') !== (int) $terms->getKey()) {
                $validator->errors()->add(
                    'terms_document_id',
                    'La version de terminos cambio. Revisa y acepta la version vigente.',
                );
            }
        });
    }

    protected function requiresTermsAcceptance(): bool
    {
        return true;
    }

    /** @param list<string> $fields */
    private function hasAnyInput(array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string) $this->input($field)) !== '') {
                return true;
            }
        }

        return false;
    }
}
