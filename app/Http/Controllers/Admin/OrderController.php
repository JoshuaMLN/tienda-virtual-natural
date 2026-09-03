<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminFulfillmentFilter;
use App\Enums\DeliveryMethod;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrackingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListAdminOrdersRequest;
use App\Models\Order;
use App\Support\Orders\AdminOrderPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    /** @var list<string> */
    private const FILTER_KEYS = [
        'q',
        'estado_pedido',
        'estado_pago',
        'estado_entrega',
        'modalidad',
        'seguimiento',
        'desde',
        'hasta',
        'page',
    ];

    public function __construct(
        private readonly AdminOrderPresenter $presenter,
    ) {}

    public function index(ListAdminOrdersRequest $request): Response
    {
        $filters = $request->validated();
        $query = $this->applyFilters(Order::query(), $filters);

        $orders = $query
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $orders->through(fn (Order $order): array => $this->presenter->listItem($order));

        return response()->view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'orderStatuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'deliveryStatuses' => DeliveryStatus::cases(),
            'deliveryMethods' => DeliveryMethod::cases(),
            'fulfillmentFilters' => AdminFulfillmentFilter::cases(),
            'detailQuery' => array_filter(
                array_merge($filters, ['page' => $orders->currentPage()]),
                fn (mixed $value): bool => $value !== null && $value !== '',
            ),
        ])->withHeaders($this->privateHeaders());
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load([
            'user:id,name,email,email_verified_at',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product:id,slug',
            'stockReservations' => fn ($query) => $query
                ->with('orderItem:id,order_id,product_name,product_sku')
                ->orderBy('stock_reservations.id'),
            'statusHistories' => fn ($query) => $query
                ->with('actor:id,name,email')
                ->oldest('created_at')
                ->oldest('id'),
            'fiscalDocuments' => fn ($query) => $query
                ->with([
                    'parentDocument:id,series,correlative',
                    'relatedDocuments:id,parent_document_id,type,status,sale_document_slot',
                    'fileVersions',
                    'corrections',
                    'deliveries' => fn ($query) => $query
                        ->oldest('attempted_at')
                        ->oldest('id'),
                ])
                ->oldest('issued_at')
                ->oldest('id'),
            'notificationDeliveries' => fn ($query) => $query
                ->latest('queued_at')
                ->latest('id'),
            'deliveryAttempts' => fn ($query) => $query
                ->with('recordedBy:id,name,email')
                ->oldest('cycle')
                ->oldest('attempt_number'),
        ]);

        return response()->view('admin.orders.show', [
            'order' => $order,
            'detail' => $this->presenter->detail($order),
            'backQuery' => $request->only(self::FILTER_KEYS),
        ])->withHeaders($this->privateHeaders());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = $filters['q'] ?? null;

        return $query
            ->when($search, function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes((string) $search, '\\%_').'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->where('code', 'like', $pattern)
                        ->orWhere('customer_name', 'like', $pattern)
                        ->orWhere('customer_email', 'like', $pattern)
                        ->orWhere('customer_phone', 'like', $pattern)
                        ->orWhere('fiscal_identity_document_number', 'like', $pattern)
                        ->orWhere('fiscal_business_name', 'like', $pattern);
                });
            })
            ->when($filters['estado_pedido'] ?? null, fn (Builder $query, string $status) => $query
                ->where('order_status', $status))
            ->when($filters['estado_pago'] ?? null, fn (Builder $query, string $status) => $query
                ->where('payment_status', $status))
            ->when($filters['estado_entrega'] ?? null, fn (Builder $query, string $status) => $query
                ->where('delivery_status', $status))
            ->when($filters['modalidad'] ?? null, fn (Builder $query, string $method) => $query
                ->where('delivery_method', $method))
            ->when($filters['seguimiento'] ?? null, fn (Builder $query, string $filter) => $this
                ->applyFulfillmentFilter($query, AdminFulfillmentFilter::from($filter)))
            ->when($filters['desde'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '>=', CarbonImmutable::parse($date, config('app.timezone'))->startOfDay()))
            ->when($filters['hasta'] ?? null, fn (Builder $query, string $date) => $query
                ->where('created_at', '<=', CarbonImmutable::parse($date, config('app.timezone'))->endOfDay()));
    }

    private function applyFulfillmentFilter(Builder $query, AdminFulfillmentFilter $filter): Builder
    {
        return match ($filter) {
            AdminFulfillmentFilter::PickupDueSoon => $query
                ->where('delivery_method', DeliveryMethod::Pickup->value)
                ->where('delivery_status', DeliveryStatus::ReadyForPickup->value)
                ->where('pickup_deadline_at', '>', now())
                ->where('pickup_deadline_at', '<=', now()->addHours(48)),
            AdminFulfillmentFilter::PickupOverdue => $query
                ->where('delivery_method', DeliveryMethod::Pickup->value)
                ->where('delivery_status', DeliveryStatus::ReadyForPickup->value)
                ->whereNotNull('pickup_deadline_at')
                ->where('pickup_deadline_at', '<=', now()),
            AdminFulfillmentFilter::ReshipmentPending => $query
                ->where('delivery_tracking_status', DeliveryTrackingStatus::AwaitingReshipmentPayment->value),
            AdminFulfillmentFilter::ManualFollowUp => $query
                ->where('delivery_method', DeliveryMethod::HomeDelivery->value)
                ->where('delivery_tracking_status', DeliveryTrackingStatus::ManualFollowUp->value),
        };
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
