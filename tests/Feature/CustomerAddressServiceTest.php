<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Addresses\AddressLimitExceededException;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Geography\InvalidUbigeoException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CustomerAddressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_address_is_default_and_uses_canonical_location_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre de la cuenta',
            'phone' => '999999999',
        ]);

        $address = $this->service()->create($user, $this->addressData([
            'label' => '  Casa principal  ',
            'recipient_name' => '  Rosa   Mendoza  ',
            'phone' => '987 654 321',
            'reference' => '  Frente   al parque  ',
        ]));

        $this->assertTrue($address->is_default);
        $this->assertSame('Casa principal', $address->label);
        $this->assertSame('Rosa Mendoza', $address->recipient_name);
        $this->assertSame('987654321', $address->phone);
        $this->assertSame('Lima', $address->department);
        $this->assertSame('Lima', $address->province);
        $this->assertSame('Santiago de Surco', $address->district);
        $this->assertSame('150140', $address->ubigeo);
        $this->assertSame('Frente al parque', $address->reference);
        $this->assertNotSame($user->name, $address->recipient_name);
        $this->assertNotSame($user->phone, $address->phone);
    }

    public function test_new_address_does_not_replace_existing_default_unless_requested(): void
    {
        $user = User::factory()->create();
        $first = $this->service()->create($user, $this->addressData(['label' => 'Casa']));
        $second = $this->service()->create($user, $this->addressData(['label' => 'Trabajo']));

        $this->assertTrue($first->fresh()->is_default);
        $this->assertFalse($second->is_default);

        $third = $this->service()->create($user, $this->addressData([
            'label' => 'Familia',
            'is_default' => true,
        ]));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertFalse($second->fresh()->is_default);
        $this->assertTrue($third->is_default);
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_user_cannot_store_more_than_ten_addresses(): void
    {
        $user = User::factory()->create();

        foreach (range(1, CustomerAddressService::MAX_ADDRESSES) as $index) {
            $this->service()->create($user, $this->addressData([
                'label' => "Direccion {$index}",
            ]));
        }

        try {
            $this->service()->create($user, $this->addressData(['label' => 'Direccion 11']));
            $this->fail('Expected address limit exception.');
        } catch (AddressLimitExceededException $exception) {
            $this->assertSame(10, $exception->limit);
            $this->assertSame('No puedes guardar mas de 10 direcciones.', $exception->getMessage());
        }

        $this->assertSame(10, $user->addresses()->count());
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_address_limit_is_independent_for_each_user(): void
    {
        $fullUser = User::factory()->create();
        $otherUser = User::factory()->create();

        CustomerAddress::factory()
            ->count(CustomerAddressService::MAX_ADDRESSES)
            ->for($fullUser)
            ->create();

        $address = $this->service()->create($otherUser, $this->addressData());

        $this->assertTrue($address->is_default);
        $this->assertSame($otherUser->id, $address->user_id);
    }

    public function test_setting_default_only_changes_addresses_from_same_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $first = $this->service()->create($user, $this->addressData(['label' => 'Casa']));
        $second = $this->service()->create($user, $this->addressData(['label' => 'Trabajo']));
        $otherAddress = $this->service()->create($otherUser, $this->addressData());

        $this->service()->setDefault($user, $second);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertTrue($otherAddress->fresh()->is_default);
    }

    public function test_update_derives_new_location_and_can_change_default(): void
    {
        $user = User::factory()->create();
        $first = $this->service()->create($user, $this->addressData(['label' => 'Casa']));
        $second = $this->service()->create($user, $this->addressData(['label' => 'Trabajo']));

        $updated = $this->service()->update($user, $second, $this->addressData([
            'label' => 'Oficina',
            'province_code' => '0701',
            'district_code' => '070104',
            'is_default' => true,
        ]));

        $this->assertSame('Oficina', $updated->label);
        $this->assertSame('Callao', $updated->department);
        $this->assertSame('Callao', $updated->province);
        $this->assertSame('La Perla', $updated->district);
        $this->assertSame('070104', $updated->ubigeo);
        $this->assertTrue($updated->is_default);
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_updating_current_default_without_requesting_another_keeps_it_default(): void
    {
        $user = User::factory()->create();
        $default = $this->service()->create($user, $this->addressData(['label' => 'Casa']));
        $this->service()->create($user, $this->addressData(['label' => 'Trabajo']));

        $updated = $this->service()->update($user, $default, $this->addressData([
            'label' => 'Casa actualizada',
            'is_default' => false,
        ]));

        $this->assertTrue($updated->is_default);
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_deleting_default_promotes_oldest_remaining_address(): void
    {
        $user = User::factory()->create();
        $first = $this->service()->create($user, $this->addressData(['label' => 'Primera']));
        $second = $this->service()->create($user, $this->addressData(['label' => 'Segunda']));
        $third = $this->service()->create($user, $this->addressData(['label' => 'Tercera']));

        $this->service()->delete($user, $first);

        $this->assertDatabaseMissing('customer_addresses', ['id' => $first->id]);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($third->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_deleting_non_default_preserves_current_default(): void
    {
        $user = User::factory()->create();
        $default = $this->service()->create($user, $this->addressData(['label' => 'Casa']));
        $secondary = $this->service()->create($user, $this->addressData(['label' => 'Trabajo']));

        $this->service()->delete($user, $secondary);

        $this->assertTrue($default->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->count());
    }

    public function test_deleting_only_address_leaves_user_without_addresses(): void
    {
        $user = User::factory()->create();
        $address = $this->service()->create($user, $this->addressData());

        $this->service()->delete($user, $address);

        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_operations_reject_address_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = $this->service()->create($owner, $this->addressData());
        $operations = [
            fn () => $this->service()->update(
                $intruder,
                $address,
                $this->addressData(['label' => 'Manipulada'])
            ),
            fn () => $this->service()->setDefault($intruder, $address),
            fn () => $this->service()->delete($intruder, $address),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Expected model not found exception.');
            } catch (ModelNotFoundException) {
                $this->assertSame('Casa', $address->fresh()->label);
            }

            $this->assertTrue($address->fresh()->is_default);
        }

        $this->assertSame(1, $owner->addresses()->count());
        $this->assertSame(0, $intruder->addresses()->count());
    }

    public function test_invalid_province_district_combination_is_rejected_without_writes(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidUbigeoException::class);

        try {
            $this->service()->create($user, $this->addressData([
                'province_code' => '1501',
                'district_code' => '070104',
            ]));
        } finally {
            $this->assertSame(0, $user->addresses()->count());
        }
    }

    public function test_required_data_and_phone_are_validated_in_domain(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El telefono debe ser un celular peruano valido de 9 digitos.');

        $this->service()->create($user, $this->addressData(['phone' => '123']));
    }

    private function service(): CustomerAddressService
    {
        return app(CustomerAddressService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function addressData(array $overrides = []): array
    {
        return array_replace([
            'label' => 'Casa',
            'recipient_name' => 'Maria Fernanda Perez',
            'phone' => '987654321',
            'province_code' => '1501',
            'district_code' => '150140',
            'address_line' => 'Av. Caminos del Inca 1234',
            'reference' => null,
            'is_default' => false,
        ], $overrides);
    }
}
