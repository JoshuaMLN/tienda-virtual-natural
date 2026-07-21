<?php

namespace App\Http\Controllers;

use App\Enums\CheckoutRevalidationStatus;
use App\Http\Requests\Checkout\RevalidateCheckoutRequest;
use App\Support\Checkout\CheckoutRevalidationException;
use App\Support\Checkout\CheckoutRevalidationResult;
use App\Support\Checkout\CheckoutRevalidationService;
use Illuminate\Http\JsonResponse;

class CheckoutRevalidationController extends Controller
{
    public function __construct(
        private readonly CheckoutRevalidationService $revalidationService,
    ) {}

    public function __invoke(RevalidateCheckoutRequest $request): JsonResponse
    {
        try {
            $acceptedProposalReference = $request->validated('accepted_proposal_reference');
            $result = $acceptedProposalReference === null
                ? $this->revalidationService->revalidate(
                    $request->user(),
                    $request->validated('review_reference'),
                )
                : $this->revalidationService->accept(
                    $request->user(),
                    $request->validated('review_reference'),
                    $acceptedProposalReference,
                );
        } catch (CheckoutRevalidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'revalidation' => null,
                'reload_url' => route('checkout.index'),
            ], $exception->httpStatus);
        }

        return response()->json([
            'message' => $this->message($result),
            'revalidation' => $result->toArray(),
            'redirect_url' => $this->redirectUrl($result),
        ], $this->statusCode($result));
    }

    private function statusCode(CheckoutRevalidationResult $result): int
    {
        return match ($result->status) {
            CheckoutRevalidationStatus::Unchanged => 200,
            CheckoutRevalidationStatus::Changed => 409,
            CheckoutRevalidationStatus::Blocked => 422,
        };
    }

    private function message(CheckoutRevalidationResult $result): string
    {
        return match ($result->status) {
            CheckoutRevalidationStatus::Unchanged => 'Tu pedido fue revisado y sigue vigente.',
            CheckoutRevalidationStatus::Changed => 'Encontramos cambios antes de continuar. Revisa y acepta la propuesta vigente.',
            CheckoutRevalidationStatus::Blocked => $result->requiresTermsAcceptance
                ? 'Los terminos y condiciones cambiaron. Vuelve al comprobante para revisarlos y aceptarlos.'
                : 'No podemos continuar con esta propuesta. Revisa tu carrito o la modalidad de entrega.',
        };
    }

    private function redirectUrl(CheckoutRevalidationResult $result): ?string
    {
        if ($result->requiresTermsAcceptance) {
            return route('checkout.index', ['paso' => 2]);
        }

        return $result->status === CheckoutRevalidationStatus::Blocked
            ? route('shop.cart')
            : null;
    }
}
