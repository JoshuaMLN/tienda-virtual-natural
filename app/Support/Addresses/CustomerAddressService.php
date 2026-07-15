<?php

namespace App\Support\Addresses;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomerAddressService
{
    public const MAX_ADDRESSES = 10;

    public function __construct(
        private readonly LimaCallaoUbigeoCatalog $ubigeoCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): CustomerAddress
    {
        $addressAttributes = $this->canonicalAttributes($attributes);
        $requestedDefault = (bool) ($attributes['is_default'] ?? false);

        return DB::transaction(function () use ($user, $addressAttributes, $requestedDefault): CustomerAddress {
            $lockedUser = $this->lockUser($user);
            $addressCount = $lockedUser->addresses()->count();

            if ($addressCount >= self::MAX_ADDRESSES) {
                throw new AddressLimitExceededException(self::MAX_ADDRESSES);
            }

            $hasDefault = $lockedUser->addresses()->default()->exists();
            $makeDefault = $addressCount === 0 || $requestedDefault || ! $hasDefault;

            if ($makeDefault) {
                $lockedUser->addresses()->update(['is_default' => false]);
            }

            return $lockedUser->addresses()->create([
                ...$addressAttributes,
                'is_default' => $makeDefault,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        User $user,
        CustomerAddress $address,
        array $attributes
    ): CustomerAddress {
        $addressAttributes = $this->canonicalAttributes($attributes);
        $requestedDefault = (bool) ($attributes['is_default'] ?? false);

        return DB::transaction(function () use (
            $user,
            $address,
            $addressAttributes,
            $requestedDefault
        ): CustomerAddress {
            $lockedUser = $this->lockUser($user);
            $lockedAddress = $this->lockOwnedAddress($lockedUser, $address);
            $hasAnotherDefault = $lockedUser->addresses()
                ->default()
                ->whereKeyNot($lockedAddress->getKey())
                ->exists();
            $makeDefault = $requestedDefault || $lockedAddress->is_default || ! $hasAnotherDefault;

            if ($makeDefault) {
                $lockedUser->addresses()
                    ->whereKeyNot($lockedAddress->getKey())
                    ->update(['is_default' => false]);
            }

            $lockedAddress->update([
                ...$addressAttributes,
                'is_default' => $makeDefault,
            ]);
            $address->refresh();

            return $lockedAddress;
        });
    }

    public function setDefault(User $user, CustomerAddress $address): CustomerAddress
    {
        return DB::transaction(function () use ($user, $address): CustomerAddress {
            $lockedUser = $this->lockUser($user);
            $lockedAddress = $this->lockOwnedAddress($lockedUser, $address);

            $lockedUser->addresses()
                ->whereKeyNot($lockedAddress->getKey())
                ->update(['is_default' => false]);
            $lockedAddress->update(['is_default' => true]);
            $address->refresh();

            return $lockedAddress;
        });
    }

    public function delete(User $user, CustomerAddress $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $lockedUser = $this->lockUser($user);
            $lockedAddress = $this->lockOwnedAddress($lockedUser, $address);

            $lockedAddress->delete();
            $this->normalizeDefault($lockedUser);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     label: string,
     *     recipient_name: string,
     *     phone: string,
     *     department: string,
     *     province: string,
     *     district: string,
     *     ubigeo: string,
     *     address_line: string,
     *     reference: string|null
     * }
     */
    private function canonicalAttributes(array $attributes): array
    {
        $provinceCode = $this->requiredText($attributes, 'province_code', 'provincia', 4);
        $ubigeo = $this->requiredText($attributes, 'district_code', 'distrito', 6);
        $location = $this->ubigeoCatalog->resolve($provinceCode, $ubigeo);
        $phone = preg_replace('/\D+/', '', (string) ($attributes['phone'] ?? ''));

        if (! is_string($phone) || ! preg_match('/^9\d{8}$/', $phone)) {
            throw new InvalidArgumentException('El telefono debe ser un celular peruano valido de 9 digitos.');
        }

        $reference = Str::squish((string) ($attributes['reference'] ?? ''));

        if (Str::length($reference) > 255) {
            throw new InvalidArgumentException('La referencia no debe superar los 255 caracteres.');
        }

        return [
            'label' => $this->requiredText($attributes, 'label', 'etiqueta', 50),
            'recipient_name' => $this->requiredText($attributes, 'recipient_name', 'destinatario', 120),
            'phone' => $phone,
            ...$location->toAddressAttributes(),
            'address_line' => $this->requiredText($attributes, 'address_line', 'direccion', 255),
            'reference' => $reference !== '' ? $reference : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requiredText(
        array $attributes,
        string $key,
        string $label,
        int $maxLength
    ): string {
        $value = Str::squish((string) ($attributes[$key] ?? ''));

        if ($value === '') {
            throw new InvalidArgumentException("El campo {$label} es obligatorio.");
        }

        if (Str::length($value) > $maxLength) {
            throw new InvalidArgumentException("El campo {$label} no debe superar los {$maxLength} caracteres.");
        }

        return $value;
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockOwnedAddress(User $user, CustomerAddress $address): CustomerAddress
    {
        return $user->addresses()
            ->whereKey($address->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function normalizeDefault(User $user): void
    {
        $defaultAddress = $user->addresses()
            ->default()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        $selectedAddress = $defaultAddress ?? $user->addresses()
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($selectedAddress === null) {
            return;
        }

        $user->addresses()
            ->whereKeyNot($selectedAddress->getKey())
            ->update(['is_default' => false]);
        $selectedAddress->update(['is_default' => true]);
    }
}
