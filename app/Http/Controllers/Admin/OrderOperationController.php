<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminOrderActionRequest;
use App\Models\Order;
use App\Support\Orders\AdminOrderOperationService;
use DomainException;
use Illuminate\Http\RedirectResponse;

class OrderOperationController extends Controller
{
    public function __construct(
        private readonly AdminOrderOperationService $operations,
    ) {}

    public function startPreparation(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::StartPreparation);
    }

    public function markShipped(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::MarkShipped);
    }

    public function markReadyForPickup(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::MarkReadyForPickup);
    }

    public function confirmDelivery(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::ConfirmDelivery);
    }

    public function confirmPickup(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::ConfirmPickup);
    }

    public function cancel(AdminOrderActionRequest $request, Order $order): RedirectResponse
    {
        return $this->perform($request, $order, AdminOrderAction::Cancel);
    }

    private function perform(
        AdminOrderActionRequest $request,
        Order $order,
        AdminOrderAction $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $routeParameters = array_merge(
            ['order' => $order->code],
            $validated['return'] ?? [],
        );

        try {
            $this->operations->perform(
                $order,
                $action,
                $request->user(),
                $validated['reason'] ?? null,
            );
        } catch (DomainException $exception) {
            return redirect()
                ->route('admin.orders.show', $routeParameters)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.orders.show', $routeParameters)
            ->with('success', $action->successMessage());
    }
}
