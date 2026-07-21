<?php

namespace App\Support\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\LegalDocumentType;
use App\Models\User;
use App\Support\Legal\LegalDocumentService;

class CheckoutReviewService
{
    public function __construct(
        private readonly CheckoutReadService $checkoutReadService,
        private readonly CheckoutDeliveryService $deliveryService,
        private readonly CheckoutDraftStore $draftStore,
        private readonly LegalDocumentService $legalDocuments,
    ) {}

    /** @param array<string, mixed> $fiscalAttributes */
    public function review(
        User $user,
        array $fiscalAttributes,
        int $termsDocumentId,
    ): CheckoutReviewSnapshot {
        [$checkout, $draft, $fiscal] = $this->prepareFiscalDraft($user, $fiscalAttributes);

        $delivery = match ($draft->deliveryMethod) {
            DeliveryMethod::HomeDelivery => $draft->addressId !== null
                ? $this->deliveryService->homeForAddress($user, $draft->addressId, $checkout)
                : throw new CheckoutReviewException('Selecciona una direccion de entrega valida.'),
            DeliveryMethod::Pickup => $this->deliveryService->pickup($checkout),
        };
        $currentDelivery = $delivery->snapshot();

        if (! hash_equals($draft->deliveryQuote->fingerprint(), $currentDelivery->fingerprint())) {
            $this->draftStore->replaceDeliveryQuote($user, $currentDelivery);

            throw new CheckoutQuoteChangedException(
                'El total o las condiciones de tu compra cambiaron mientras completabas los datos fiscales. Actualizamos la informacion; revisa el resumen y presiona Continuar al pago nuevamente.',
            );
        }

        $terms = $this->legalDocuments->active(LegalDocumentType::Terms);

        if ($terms === null || (int) $terms->getKey() !== $termsDocumentId) {
            throw new CheckoutReviewException(
                'La version de terminos cambio. Revisa y acepta la version vigente.',
            );
        }

        $review = CheckoutReviewSnapshot::create(
            userId: (int) $user->getKey(),
            contactName: $draft->contactName,
            customerEmail: $user->email,
            contactPhone: $draft->contactPhone,
            deliveryQuote: $currentDelivery,
            fiscal: $fiscal,
            terms: $terms,
        );

        if ($this->draftStore->putReview($user, $review) === null) {
            throw new CheckoutReviewException(
                'Los datos del checkout cambiaron. Actualiza la pagina e intentalo nuevamente.',
            );
        }

        return $review;
    }

    /** @param array<string, mixed> $fiscalAttributes */
    public function saveFiscal(User $user, array $fiscalAttributes): CheckoutFiscalData
    {
        [, , $fiscal] = $this->prepareFiscalDraft($user, $fiscalAttributes);

        return $fiscal;
    }

    /**
     * @param  array<string, mixed>  $fiscalAttributes
     * @return array{CheckoutSummary, CheckoutDraft, CheckoutFiscalData}
     */
    private function prepareFiscalDraft(User $user, array $fiscalAttributes): array
    {
        $checkout = $this->checkoutReadService->current();

        if ($checkout === null) {
            throw new CheckoutReviewException('Tu carrito esta vacio. Regresa al carrito para continuar.');
        }

        $draft = $this->draftStore->get($user);

        if ($draft === null || $draft->deliveryMethod === null || $draft->deliveryQuote === null) {
            throw new CheckoutReviewException('Guarda primero tus datos de contacto y entrega.');
        }

        $fiscal = CheckoutFiscalData::fromValidated($fiscalAttributes);

        if ($this->draftStore->putFiscal($user, $fiscal) === null) {
            throw new CheckoutReviewException(
                'Los datos del checkout cambiaron. Actualiza la pagina e intentalo nuevamente.',
            );
        }

        return [$checkout, $draft, $fiscal];
    }
}
