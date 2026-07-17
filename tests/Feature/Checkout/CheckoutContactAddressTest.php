<?php

namespace Tests\Feature\Checkout;

use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\User;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutContactAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_prefills_contact_and_prioritizes_the_owned_default_address(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Fernanda Perez',
            'email' => 'maria@example.com',
            'phone' => '987654321',
        ]);
        $secondary = CustomerAddress::factory()->for($user)->create([
            'label' => 'Trabajo',
            'is_default' => false,
        ]);
        $default = CustomerAddress::factory()->for($user)->default()->create([
            'label' => 'Casa principal',
        ]);
        $foreign = CustomerAddress::factory()->default()->create([
            'label' => 'Direccion ajena',
        ]);
        $this->withCart($user);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', function (array $form) use ($default): bool {
                return $form['selected_address_id'] === $default->id
                    && $form['contact']['name'] === 'Maria Fernanda Perez'
                    && $form['contact']['email'] === 'maria@example.com'
                    && $form['contact']['phone'] === '987654321';
            })
            ->assertSeeInOrder(['Casa principal', 'Trabajo'])
            ->assertSee('value="address:'.$default->id.'"', false)
            ->assertSee('value="address:'.$secondary->id.'"', false)
            ->assertSee('name="contact_email"', false)
            ->assertSee('readonly', false)
            ->assertSee(route('account.addresses'), false)
            ->assertDontSee($foreign->label);
    }

    public function test_checkout_without_addresses_selects_the_new_address_form(): void
    {
        $user = User::factory()->create();
        $this->withCart($user);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => $form['is_first_address']
                && $form['selected_address_id'] === null
                && $form['can_create_address'])
            ->assertSee('value="new"', false)
            ->assertSee('Guardar y usar esta direccion')
            ->assertSee('Tu primera direccion sera predeterminada automaticamente.')
            ->assertSee('data-address-location-catalog', false);
    }

    public function test_customer_can_use_an_owned_address_without_modifying_profile_or_domain_records(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre del perfil',
            'email' => 'perfil@example.com',
            'phone' => '999999999',
        ]);
        $address = CustomerAddress::factory()->for($user)->default()->create();
        $this->withCart($user);

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto para compra',
            'contact_phone' => '987 654 321',
            'contact_email' => 'manipulado@example.com',
            'address_choice' => 'address:'.$address->id,
        ])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('status', 'checkout-contact-address-saved')
            ->assertSessionHas('checkout.draft', fn (array $draft): bool => $draft === [
                'user_id' => $user->id,
                'contact_name' => 'Contacto para compra',
                'contact_phone' => '987654321',
                'address_id' => $address->id,
            ]);

        $user->refresh();
        $this->assertSame('Nombre del perfil', $user->name);
        $this->assertSame('perfil@example.com', $user->email);
        $this->assertSame('999999999', $user->phone);
        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertNoCheckoutDomainRecords();

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('value="Contacto para compra"', false)
            ->assertSee('value="987654321"', false)
            ->assertSee('Datos de contacto y direccion guardados para esta compra.');
    }

    public function test_customer_can_create_and_use_a_canonical_first_address(): void
    {
        $user = User::factory()->create([
            'name' => 'Nombre del perfil',
            'phone' => '999999999',
        ]);
        $this->withCart($user);

        $this->post(route('checkout.contact-address.store'), $this->newAddressData([
            'label' => '  Casa   principal ',
            'recipient_name' => '  Ana   Torres ',
            'phone' => '986 111 222',
            'department' => 'Ancash',
            'province' => 'Huaraz',
            'district' => 'Independencia',
            'ubigeo' => '999999',
        ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('status', 'checkout-contact-address-saved');

        $address = $user->addresses()->firstOrFail();
        $this->assertSame('Casa principal', $address->label);
        $this->assertSame('Ana Torres', $address->recipient_name);
        $this->assertSame('986111222', $address->phone);
        $this->assertSame('Lima', $address->department);
        $this->assertSame('Lima', $address->province);
        $this->assertSame('Santiago de Surco', $address->district);
        $this->assertSame('150140', $address->ubigeo);
        $this->assertTrue($address->is_default);
        $this->assertSame('Nombre del perfil', $user->fresh()->name);
        $this->assertSame('999999999', $user->fresh()->phone);
        $this->assertSame($address->id, session('checkout.draft.address_id'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_new_address_only_replaces_the_default_when_explicitly_requested(): void
    {
        $user = User::factory()->create();
        $oldDefault = CustomerAddress::factory()->for($user)->default()->create([
            'label' => 'Principal',
        ]);
        $this->withCart($user);

        $this->post(route('checkout.contact-address.store'), $this->newAddressData([
            'label' => 'Trabajo',
            'is_default' => false,
        ]))->assertRedirect(route('checkout.index'));

        $work = $user->addresses()->where('label', 'Trabajo')->firstOrFail();
        $this->assertTrue($oldDefault->fresh()->is_default);
        $this->assertFalse($work->is_default);

        $this->post(route('checkout.contact-address.store'), $this->newAddressData([
            'label' => 'Familia',
            'is_default' => true,
        ]))->assertRedirect(route('checkout.index'));

        $family = $user->addresses()->where('label', 'Familia')->firstOrFail();
        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertFalse($work->fresh()->is_default);
        $this->assertTrue($family->is_default);
        $this->assertSame(1, $user->addresses()->default()->count());
    }

    public function test_checkout_rejects_an_address_owned_by_another_customer(): void
    {
        $user = User::factory()->create();
        $foreignAddress = CustomerAddress::factory()->create();
        $this->withCart($user);

        $this->from(route('checkout.index'))
            ->post(route('checkout.contact-address.store'), [
                'contact_name' => 'Maria Perez',
                'contact_phone' => '987654321',
                'address_choice' => 'address:'.$foreignAddress->id,
            ])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['address_id'], null, 'checkout');

        $this->assertNull(session('checkout.draft'));
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $foreignAddress->id,
            'user_id' => $foreignAddress->user_id,
        ]);
    }

    public function test_invalid_new_address_keeps_the_selection_and_rejects_mismatched_ubigeo(): void
    {
        $user = User::factory()->create();
        $this->withCart($user);

        $this->from(route('checkout.index'))
            ->post(route('checkout.contact-address.store'), $this->newAddressData([
                'province_code' => '0701',
                'district_code' => '150140',
            ]))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['district_code'], null, 'checkout')
            ->assertSessionHasInput('address_choice', 'new')
            ->assertSessionHasInput('province_code', '0701')
            ->assertSessionHasInput('district_code', '150140');

        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertNull(session('checkout.draft'));
    }

    public function test_checkout_disables_and_rejects_new_addresses_at_the_limit(): void
    {
        $user = User::factory()->create();
        CustomerAddress::factory()
            ->count(CustomerAddressService::MAX_ADDRESSES)
            ->for($user)
            ->create();
        $this->withCart($user);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', fn (array $form): bool => ! $form['can_create_address']
                && $form['address_count'] === CustomerAddressService::MAX_ADDRESSES)
            ->assertSee('Guardaste 10 de 10 direcciones.')
            ->assertDontSee('value="new"', false);

        $this->from(route('checkout.index'))
            ->post(route('checkout.contact-address.store'), $this->newAddressData())
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors(['address_choice'], null, 'checkout');

        $this->assertSame(CustomerAddressService::MAX_ADDRESSES, $user->addresses()->count());
        $this->assertNull(session('checkout.draft'));
    }

    public function test_contact_address_route_requires_verified_customer_and_non_empty_cart(): void
    {
        $this->post(route('checkout.contact-address.store'), [])
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->post(route('checkout.contact-address.store'), [])
            ->assertRedirect(route('verification.notice'));

        $verified = User::factory()->create();
        $address = CustomerAddress::factory()->for($verified)->default()->create();
        $this->actingAs($verified)
            ->post(route('checkout.contact-address.store'), [
                'contact_name' => 'Maria Perez',
                'contact_phone' => '987654321',
                'address_choice' => 'address:'.$address->id,
            ])
            ->assertRedirect(route('checkout.index'));

        $this->assertNull(session('checkout.draft'));
        $this->assertNoCheckoutDomainRecords();
    }

    public function test_checkout_draft_is_not_reused_by_another_customer_in_the_same_session(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'Primer cliente',
            'phone' => '987654321',
        ]);
        $firstAddress = CustomerAddress::factory()->for($firstUser)->default()->create();
        $this->withCart($firstUser);

        $this->post(route('checkout.contact-address.store'), [
            'contact_name' => 'Contacto privado',
            'contact_phone' => '986111222',
            'address_choice' => 'address:'.$firstAddress->id,
        ])->assertRedirect(route('checkout.index'));

        $secondUser = User::factory()->create([
            'name' => 'Segundo cliente',
            'phone' => '999888777',
        ]);
        $secondAddress = CustomerAddress::factory()->for($secondUser)->default()->create();
        $this->withCart($secondUser);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertViewHas('checkoutForm', function (array $form) use ($secondAddress): bool {
                return $form['contact']['name'] === 'Segundo cliente'
                    && $form['contact']['phone'] === '999888777'
                    && $form['selected_address_id'] === $secondAddress->id;
            })
            ->assertDontSee('Contacto privado')
            ->assertDontSee('986111222');
    }

    private function withCart(User $user): void
    {
        $this->actingAs($user);
        app(CartService::class)->add(Product::factory()->create(['stock' => 20]), 1);
    }

    /** @param array<string, mixed> $overrides */
    private function newAddressData(array $overrides = []): array
    {
        return [
            'contact_name' => 'Maria Fernanda Perez',
            'contact_phone' => '987654321',
            'address_choice' => 'new',
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

    private function assertNoCheckoutDomainRecords(): void
    {
        $this->assertDatabaseCount('order_sequences', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_reservations', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }
}
