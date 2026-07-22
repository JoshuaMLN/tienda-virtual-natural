<?php

namespace App\Http\Controllers\Account;

use App\Enums\CustomerOrderFilter;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ListCustomerOrdersRequest;
use App\Models\Order;
use App\Support\Money\Money;
use App\Support\Orders\CustomerOrderCapabilityResolver;
use App\Support\Orders\CustomerOrderDateFormatter;
use App\Support\Orders\CustomerOrderDetailPresenter;
use App\Support\Orders\CustomerOrderStatusResolver;
use App\Support\Settings\StorefrontSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerOrderController extends Controller
{
    public function __construct(
        private readonly CustomerOrderStatusResolver $statuses,
        private readonly CustomerOrderCapabilityResolver $capabilities,
        private readonly CustomerOrderDateFormatter $dates,
        private readonly CustomerOrderDetailPresenter $details,
        private readonly StorefrontSettings $settings,
    ) {}

    public function index(ListCustomerOrdersRequest $request): Response
    {
        $validated = $request->validated();
        $search = $validated['q'] ?? null;
        $activeFilter = CustomerOrderFilter::from($validated['estado']);
        $query = $request->user()->orders()->getQuery();

        $query->when($search, fn ($query) => $query->where('code', 'like', "%{$search}%"));
        $this->statuses->constrain($query, $activeFilter);
        $query->with(['stockReservations' => fn ($query) => $query
            ->where('stock_reservations.status', ReservationStatus::Active->value)]);

        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $orders->through(fn (Order $order): array => [
            'order' => $order,
            'status' => $this->statuses->resolve($order),
            'formatted_total' => Money::fromCents($order->total_cents)->formatted(),
            'formatted_date' => $this->dates->compactDate($order->created_at),
            'formatted_time' => $this->dates->compactTime($order->created_at),
            'capabilities' => $this->capabilities->resolve($order),
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
            ->with([
                'items' => fn ($query) => $query->orderBy('id'),
                'items.product' => fn ($query) => $query->active(),
                'stockReservations',
                'statusHistories',
            ])
            ->where('code', strtoupper($code))
            ->firstOrFail();
        $commercialStatus = $this->statuses->resolve($order);
        $capabilities = $this->capabilities->resolve($order);

        return response()->view('account.order-show', [
            'order' => $order,
            'commercialStatus' => $commercialStatus,
            'capabilities' => $capabilities,
            'detail' => $this->details->present($order, $commercialStatus),
            'support' => [
                'whatsapp_url' => $this->settings->whatsappUrl(),
                'whatsapp_display' => $this->settings->whatsappDisplay(),
                'email' => $this->settings->email(),
            ],
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
