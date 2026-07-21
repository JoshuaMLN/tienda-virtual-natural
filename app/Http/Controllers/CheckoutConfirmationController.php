<?php

namespace App\Http\Controllers;

use App\Enums\CheckoutRevalidationStatus;
use App\Http\Requests\Checkout\ConfirmCheckoutRequest;
use App\Support\Checkout\CheckoutConfirmationResult;
use App\Support\Checkout\CheckoutOrderCreationService;
use App\Support\Checkout\CheckoutRevalidationException;
use App\Support\Checkout\CheckoutRevalidationResult;
use Illuminate\Http\JsonResponse;

class CheckoutConfirmationController extends Controller
{
    public function __construct(
        private readonly CheckoutOrderCreationService $orderCreation,
    ) {}

    public function __invoke(ConfirmCheckoutRequest $request): JsonResponse
    {
        try {
            $result = $this->orderCreation->confirm(
                $request->user(),
                $request->validated('review_reference'),
                $request->validated('idempotency_key'),
                $request->validated('accepted_proposal_reference'),
            );
        } catch (CheckoutRevalidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'revalidation' => null,
                'reload_url' => route('checkout.index'),
            ], $exception->httpStatus);
        }

        if ($result->blockedByPendingOrder) {
            return $this->pendingOrderResponse($result);
        }

        if ($result->created()) {
            return $this->createdResponse($result);
        }

        $revalidation = $result->revalidation;

        return response()->json([
            'message' => match ($revalidation?->status) {
                CheckoutRevalidationStatus::Changed => 'Encontramos cambios antes de continuar. Revisa y acepta la propuesta vigente.',
                CheckoutRevalidationStatus::Blocked => $revalidation->requiresTermsAcceptance
                    ? 'Los terminos y condiciones cambiaron. Vuelve al comprobante para revisarlos y aceptarlos.'
                    : 'No podemos continuar con esta propuesta. Revisa tu carrito o la modalidad de entrega.',
                default => 'No pudimos confirmar el pedido.',
            },
            'revalidation' => $revalidation?->toArray(),
            'redirect_url' => $this->redirectUrl($revalidation),
        ], match ($revalidation?->status) {
            CheckoutRevalidationStatus::Changed => 409,
            default => 422,
        });
    }

    private function pendingOrderResponse(CheckoutConfirmationResult $result): JsonResponse
    {
        $order = $result->order;

        return response()->json([
            'message' => "Ya tienes el pedido {$order->code} pendiente de pago.",
            'order' => [
                'code' => $order->code,
                'status' => $order->order_status->value,
                'reservation_expires_at' => $order->reservation_expires_at?->toAtomString(),
            ],
            'revalidation' => null,
            'redirect_url' => route('checkout.order.pending', $order->code),
            'idempotent_replay' => false,
            'pending_order' => true,
        ]);
    }

    private function createdResponse(CheckoutConfirmationResult $result): JsonResponse
    {
        $order = $result->order;

        return response()->json([
            'message' => $result->idempotentReplay
                ? "El pedido {$order->code} ya habia sido creado."
                : "Pedido {$order->code} creado correctamente.",
            'order' => [
                'code' => $order->code,
                'status' => $order->order_status->value,
                'reservation_expires_at' => $order->reservation_expires_at?->toAtomString(),
            ],
            'revalidation' => $result->revalidation?->toArray(),
            'redirect_url' => route('checkout.order.pending', $order->code),
            'idempotent_replay' => $result->idempotentReplay,
        ], $result->idempotentReplay ? 200 : 201);
    }

    private function redirectUrl(?CheckoutRevalidationResult $result): ?string
    {
        if ($result?->requiresTermsAcceptance) {
            return route('checkout.index', ['paso' => 2]);
        }

        return $result?->status === CheckoutRevalidationStatus::Blocked
            ? route('shop.cart')
            : null;
    }
}
