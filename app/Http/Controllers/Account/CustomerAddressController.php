<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\SaveCustomerAddressRequest;
use App\Http\Requests\Account\SetDefaultAddressRequest;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\Addresses\AddressLimitExceededException;
use App\Support\Addresses\CustomerAddressService;
use App\Support\Geography\LimaCallaoUbigeoCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerAddressController extends Controller
{
    public function __construct(
        private readonly CustomerAddressService $addressService,
        private readonly LimaCallaoUbigeoCatalog $ubigeoCatalog,
    ) {}

    public function index(Request $request): View
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('account.addresses.index', [
            'addresses' => $addresses,
            'addressLimit' => CustomerAddressService::MAX_ADDRESSES,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $addressCount = $request->user()->addresses()->count();

        if ($addressCount >= CustomerAddressService::MAX_ADDRESSES) {
            return redirect()
                ->route('account.addresses')
                ->with('warning', 'Alcanzaste el limite de 10 direcciones guardadas.');
        }

        return view('account.addresses.form', [
            'address' => new CustomerAddress([
                'recipient_name' => $request->user()->name,
                'phone' => $request->user()->phone,
            ]),
            'isFirstAddress' => $addressCount === 0,
            'locationCatalog' => $this->locationCatalog(),
        ]);
    }

    public function store(SaveCustomerAddressRequest $request): RedirectResponse
    {
        try {
            $this->addressService->create($request->user(), $request->validated());
        } catch (AddressLimitExceededException $exception) {
            return redirect()
                ->route('account.addresses')
                ->with('warning', $exception->getMessage());
        }

        return redirect()
            ->route('account.addresses')
            ->with('status', 'address-created');
    }

    public function edit(Request $request, CustomerAddress $address): View
    {
        $ownedAddress = $this->ownedAddress($request->user(), $address);

        return view('account.addresses.form', [
            'address' => $ownedAddress,
            'isFirstAddress' => false,
            'locationCatalog' => $this->locationCatalog(),
        ]);
    }

    public function update(
        SaveCustomerAddressRequest $request,
        CustomerAddress $address
    ): RedirectResponse {
        $ownedAddress = $this->ownedAddress($request->user(), $address);
        $this->addressService->update(
            $request->user(),
            $ownedAddress,
            $request->validated()
        );

        return redirect()
            ->route('account.addresses')
            ->with('status', 'address-updated');
    }

    public function setDefault(SetDefaultAddressRequest $request): RedirectResponse
    {
        $address = $request->user()->addresses()
            ->whereKey($request->integer('address_id'))
            ->firstOrFail();

        $this->addressService->setDefault($request->user(), $address);

        return redirect()
            ->route('account.addresses')
            ->with('status', 'address-default-updated');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $ownedAddress = $this->ownedAddress($request->user(), $address);
        $wasDefault = $ownedAddress->is_default;

        $this->addressService->delete($request->user(), $ownedAddress);
        $promotedAddress = $wasDefault
            ? $request->user()->addresses()->default()->first()
            : null;

        return redirect()
            ->route('account.addresses')
            ->with('status', $promotedAddress
                ? 'address-deleted-default-promoted'
                : 'address-deleted')
            ->with('promoted_address_label', $promotedAddress?->label);
    }

    private function ownedAddress(User $user, CustomerAddress $address): CustomerAddress
    {
        return $user->addresses()
            ->whereKey($address->getKey())
            ->firstOrFail();
    }

    /**
     * @return array<string, array{
     *     name: string,
     *     department: string,
     *     districts: array<int, array{code: string, name: string}>
     * }>
     */
    private function locationCatalog(): array
    {
        $catalog = [];

        foreach ($this->ubigeoCatalog->provinces() as $province) {
            $catalog[$province['code']] = [
                'name' => $province['name'],
                'department' => $province['department'],
                'districts' => $this->ubigeoCatalog->districts($province['code']),
            ];
        }

        return $catalog;
    }
}
