<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerAddressModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_addresses_table_has_the_expected_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('customer_addresses', [
            'id',
            'user_id',
            'label',
            'recipient_name',
            'phone',
            'department',
            'province',
            'district',
            'ubigeo',
            'address_line',
            'reference',
            'is_default',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_user_and_address_relations_are_available(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::factory()->default()->for($user)->create();

        $this->assertTrue($user->addresses->contains($address));
        $this->assertTrue($address->user->is($user));
        $this->assertTrue($address->is_default);
        $this->assertTrue($user->addresses()->default()->first()->is($address));
    }

    public function test_factory_can_create_a_canonical_callao_address(): void
    {
        $address = CustomerAddress::factory()->callao()->create();

        $this->assertSame('Callao', $address->department);
        $this->assertSame('Callao', $address->province);
        $this->assertSame('La Perla', $address->district);
        $this->assertSame('070104', $address->ubigeo);
    }

    public function test_deleting_user_physically_deletes_saved_addresses(): void
    {
        $user = User::factory()->create();
        CustomerAddress::factory()->count(2)->for($user)->create();

        $user->delete();

        $this->assertDatabaseCount('customer_addresses', 0);
    }
}
