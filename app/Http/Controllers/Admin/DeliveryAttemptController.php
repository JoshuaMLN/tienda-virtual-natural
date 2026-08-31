<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryAttemptAttribution;
use App\Enums\DeliveryAttemptResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDeliveryAttemptRequest;
use App\Models\Order;
use App\Support\Orders\OrderFulfillmentService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;

class DeliveryAttemptController extends Controller
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    public function store(StoreDeliveryAttemptRequest $request, Order $order): RedirectResponse
    {
        $validated = $request->validated();
        $routeParameters = array_merge(
            ['order' => $order->code],
            $validated['return'] ?? [],
        );

        try {
            $attempt = $this->fulfillment->recordDeliveryAttempt(
                $order,
                $request->user(),
                $validated['operation_token'],
                DeliveryAttemptResult::from($validated['result']),
                DeliveryAttemptAttribution::from($validated['attribution']),
                $validated['responsible_name'],
                $validated['attempt_reason'] ?? null,
                CarbonImmutable::createFromFormat(
                    'Y-m-d\TH:i',
                    $validated['occurred_at'],
                    config('app.timezone'),
                ),
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.orders.show', $routeParameters)
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.orders.show', $routeParameters)
            ->with('success', $attempt->result === DeliveryAttemptResult::Delivered
                ? 'La entrega fue confirmada y el pedido quedo completado.'
                : 'La incidencia de entrega fue registrada.');
    }
}
