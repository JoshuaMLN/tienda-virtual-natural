<?php

namespace App\Http\Requests\Account;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    protected $errorBag = 'profile';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'regex:/^9\d{8}$/'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user()),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'cropped_avatar' => [
                'nullable',
                'string',
                'max:8000000',
                'regex:#^data:image/(?:jpeg|png|webp);base64,#',
            ],
            'remove_avatar' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa tu nombre completo.',
            'name.max' => 'El nombre no debe superar los 120 caracteres.',
            'phone.regex' => 'Ingresa un celular peruano valido de 9 digitos.',
            'email.required' => 'Ingresa tu correo electronico.',
            'email.email' => 'Ingresa un correo electronico valido.',
            'email.unique' => 'Ya existe una cuenta con este correo electronico.',
            'avatar.image' => 'Selecciona una imagen valida para tu avatar.',
            'avatar.mimes' => 'El avatar debe ser JPG, PNG o WebP.',
            'avatar.max' => 'El avatar no debe superar los 4 MB.',
            'cropped_avatar.regex' => 'No se pudo procesar el recorte del avatar.',
            'cropped_avatar.max' => 'El recorte del avatar es demasiado grande.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = preg_replace('/\D+/', '', (string) $this->input('phone'));

        $this->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $this->input('name'))),
            'phone' => $phone !== '' ? $phone : null,
            'email' => Str::lower(trim((string) $this->input('email'))),
            'remove_avatar' => $this->boolean('remove_avatar'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $email = $this->string('email')->toString();

            if (
                $user !== null
                && ! $validator->errors()->has('email')
                && ! hash_equals($user->email, $email)
                && $user->socialAccounts()
                    ->where('provider', SocialAccount::PROVIDER_GOOGLE)
                    ->exists()
            ) {
                $validator->errors()->add(
                    'email',
                    'Desvincula Google desde Seguridad antes de cambiar tu correo electronico.'
                );
            }

            if (! $this->filled('cropped_avatar')) {
                return;
            }

            $payload = $this->string('cropped_avatar')->toString();

            if (! preg_match('#^data:image/(?:jpeg|png|webp);base64,#', $payload)) {
                return;
            }

            $contents = base64_decode(Str::after($payload, ','), true);
            $dimensions = is_string($contents) ? @getimagesizefromstring($contents) : false;

            if ($dimensions === false) {
                $validator->errors()->add('cropped_avatar', 'No se pudo leer el recorte del avatar.');

                return;
            }

            if ($dimensions[0] !== $dimensions[1]) {
                $validator->errors()->add('cropped_avatar', 'El recorte del avatar debe ser cuadrado.');
            }
        });
    }
}
