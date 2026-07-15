<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_routes_require_authentication(): void
    {
        $address = CustomerAddress::factory()->create();

        $this->get(route('account.addresses'))
            ->assertRedirect(route('login'));
        $this->get(route('account.addresses.create'))
            ->assertRedirect(route('login'));
        $this->post(route('account.addresses.store'), $this->validAddress())
            ->assertRedirect(route('login'));
        $this->patch(route('account.addresses.default'), ['address_id' => $address->id])
            ->assertRedirect(route('login'));
        $this->get(route('account.addresses.edit', $address))
            ->assertRedirect(route('login'));
        $this->put(route('account.addresses.update', $address), $this->validAddress())
            ->assertRedirect(route('login'));
        $this->delete(route('account.addresses.destroy', $address))
            ->assertRedirect(route('login'));
    }

    public function test_customer_sees_only_own_addresses_with_default_first(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        CustomerAddress::factory()->for($user)->create([
            'label' => 'Trabajo',
            'is_default' => false,
        ]);
        CustomerAddress::factory()->for($user)->default()->create([
            'label' => 'Casa principal',
        ]);
        CustomerAddress::factory()->for($otherUser)->default()->create([
            'label' => 'Direccion ajena',
        ]);

        $this->actingAs($user)
            ->get(route('account.addresses'))
            ->assertOk()
            ->assertSeeInOrder(['Casa principal', 'Trabajo'])
            ->assertDontSee('Direccion ajena')
            ->assertSee('2 de 10 direcciones')
            ->assertSee('data-default-address-radio', false)
            ->assertSee('data-address-delete', false)
            ->assertSee('id="deleteAddressModal"', false);
    }

    public function test_create_form_prefills_contact_and_derives_geographic_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Fernanda Perez',
            'phone' => '987654321',
        ]);

        $this->actingAs($user)
            ->get(route('account.addresses.create'))
            ->assertOk()
            ->assertSee('Maria Fernanda Perez')
            ->assertSee('987654321')
            ->assertSee('Entrega disponible solo en Lima Metropolitana y Callao.')
            ->assertSee('name="province_code"', false)
            ->assertSee('name="district_code"', false)
            ->assertDontSee('name="department"', false)
            ->assertDontSee('name="ubigeo"', false)
            ->assertSee('data-address-location-catalog', false)
            ->assertSee('Tu primera direccion sera predeterminada automaticamente.');
    }

    public function test_customer_can_store_a_normalized_canonical_first_address(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->post(route('account.addresses.store'), [
                ...$this->validAddress(),
                'user_id' => $otherUser->id,
                'label' => '  Casa   principal ',
                'recipient_name' => '  Ana   Torres ',
                'phone' => '987 654 321',
                'department' => 'Ancash',
                'province' => 'Huaraz',
                'district' => 'Independencia',
                'ubigeo' => '999999',
            ])
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', 'address-created');

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $user->id,
            'label' => 'Casa principal',
            'recipient_name' => 'Ana Torres',
            'phone' => '987654321',
            'department' => 'Lima',
            'province' => 'Lima',
            'district' => 'Santiago de Surco',
            'ubigeo' => '150140',
            'is_default' => true,
        ]);
        $this->assertDatabaseMissing('customer_addresses', [
            'user_id' => $otherUser->id,
            'label' => 'Casa principal',
        ]);
    }

    public function test_store_rejects_invalid_contact_and_mismatched_location(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.addresses.create'))
            ->post(route('account.addresses.store'), [
                ...$this->validAddress(),
                'phone' => '123',
                'province_code' => '0701',
                'district_code' => '150140',
            ])
            ->assertRedirect(route('account.addresses.create'))
            ->assertSessionHasErrors(['phone', 'district_code'], null, 'address');

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    public function test_customer_cannot_open_or_create_more_than_ten_addresses(): void
    {
        $user = User::factory()->create();
        CustomerAddress::factory()->count(10)->for($user)->create();

        $this->actingAs($user)
            ->get(route('account.addresses'))
            ->assertOk()
            ->assertSee('10 de 10 direcciones')
            ->assertSee('Alcanzaste el limite de 10 direcciones');

        $this->get(route('account.addresses.create'))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('warning');

        $this->post(route('account.addresses.store'), $this->validAddress())
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('warning');

        $this->assertSame(10, $user->addresses()->count());
    }

    public function test_customer_can_edit_and_update_an_owned_address(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create([
            'label' => 'Casa antigua',
        ]);

        $this->actingAs($user)
            ->get(route('account.addresses.edit', $address))
            ->assertOk()
            ->assertSee('Casa antigua')
            ->assertSee('150140')
            ->assertSee('value="1501" selected', false)
            ->assertSee('value="150140" selected', false)
            ->assertSee('No puedes dejar tu cuenta sin una direccion predeterminada.');

        $this->put(route('account.addresses.update', $address), $this->validAddress([
            'label' => 'Casa Callao',
            'province_code' => '0701',
            'district_code' => '070104',
        ]))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', 'address-updated');

        $address->refresh();
        $this->assertSame('Casa Callao', $address->label);
        $this->assertSame('Callao', $address->department);
        $this->assertSame('Callao', $address->province);
        $this->assertSame('La Perla', $address->district);
        $this->assertSame('070104', $address->ubigeo);
        $this->assertTrue($address->is_default);
    }

    public function test_customer_cannot_access_another_customers_address(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $foreignAddress = CustomerAddress::factory()->for($owner)->default()->create();

        $this->actingAs($user)
            ->get(route('account.addresses.edit', $foreignAddress))
            ->assertNotFound();
        $this->put(route('account.addresses.update', $foreignAddress), $this->validAddress())
            ->assertNotFound();
        $this->delete(route('account.addresses.destroy', $foreignAddress))
            ->assertNotFound();
        $this->patch(route('account.addresses.default'), [
            'address_id' => $foreignAddress->id,
        ])->assertNotFound();

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $foreignAddress->id,
            'user_id' => $owner->id,
            'is_default' => true,
        ]);
    }

    public function test_customer_can_choose_a_new_default_address(): void
    {
        $user = User::factory()->create();
        $oldDefault = CustomerAddress::factory()->for($user)->default()->create();
        $newDefault = CustomerAddress::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('account.addresses.default'), [
                'address_id' => $newDefault->id,
            ])
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', 'address-default-updated');

        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertTrue($newDefault->fresh()->is_default);
    }

    public function test_setting_default_requires_a_valid_address_identifier(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.addresses'))
            ->patch(route('account.addresses.default'), [
                'address_id' => 'invalid',
            ])
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHasErrors(['address_id'], null, 'defaultAddress');
    }

    public function test_deleting_default_promotes_the_oldest_remaining_address(): void
    {
        $user = User::factory()->create();
        $default = CustomerAddress::factory()->for($user)->default()->create([
            'label' => 'Casa',
            'created_at' => '2026-07-10 10:00:00',
        ]);
        $oldest = CustomerAddress::factory()->for($user)->create([
            'label' => 'Trabajo',
            'created_at' => '2026-01-10 10:00:00',
        ]);
        $newest = CustomerAddress::factory()->for($user)->create([
            'label' => 'Familia',
            'created_at' => '2026-06-10 10:00:00',
        ]);

        $this->actingAs($user)
            ->delete(route('account.addresses.destroy', $default))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', 'address-deleted-default-promoted')
            ->assertSessionHas('promoted_address_label', 'Trabajo');

        $this->assertDatabaseMissing('customer_addresses', ['id' => $default->id]);
        $this->assertTrue($oldest->fresh()->is_default);
        $this->assertFalse($newest->fresh()->is_default);
    }

    public function test_deleting_the_only_address_leaves_the_account_without_addresses(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->delete(route('account.addresses.destroy', $address))
            ->assertRedirect(route('account.addresses'))
            ->assertSessionHas('status', 'address-deleted');

        $this->assertDatabaseCount('customer_addresses', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAddress(array $overrides = []): array
    {
        return [
            'label' => 'Casa',
            'recipient_name' => 'Maria Fernanda Perez',
            'phone' => '987654321',
            'province_code' => '1501',
            'district_code' => '150140',
            'address_line' => 'Av. Caminos del Inca 1234, dpto. 502',
            'reference' => 'Frente al parque',
            'is_default' => false,
            ...$overrides,
        ];
    }
}
