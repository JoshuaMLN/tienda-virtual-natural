<?php

namespace App\Http\Controllers\Account;

use App\Enums\CustomerOrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ListCustomerOrdersRequest;
use App\Models\Order;
use App\Support\Money\Money;
use App\Support\Orders\CustomerOrderStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderStatusResolver $statuses,
    ) {}

    public function index(ListCustomerOrdersRequest $request): Response
    {
        $validated = $request->validated();
        $search = $validated['q'] ?? null;
        $activeFilter = CustomerOrderFilter::from($validated['estado']);
        $query = $request->user()->orders()->getQuery();

        $query->when($search, fn ($query) => $query->where('code', 'like', "%{$search}%"));
        $this->statuses->constrain($query, $activeFilter);

        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $orders->through(fn (Order $order): array => [
            'order' => $order,
            'status' => $this->statuses->resolve($order),
            'formatted_total' => Money::fromCents($order->total_cents)->formatted(),
        ]);

        return response()->view('account.orders', [
            'orders' => $orders,
            'filters' => CustomerOrderFilter::cases(),
            'activeFilter' => $activeFilter,
            'search' => $search ?? '',
        ])->withHeaders($this->privateHeaders());
    }

    public function show(Request $request, string $code): Response
    {
        $order = $request->user()->orders()
            ->where('code', strtoupper($code))
            ->firstOrFail();

        return response()->view('account.order-show', [
            'order' => $order,
            'commercialStatus' => $this->statuses->resolve($order),
            'formattedTotal' => Money::fromCents($order->total_cents)->formatted(),
        ])->withHeaders($this->privateHeaders());
    }

    /** @return array<string, string> */
    private function privateHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ];
    }
}
