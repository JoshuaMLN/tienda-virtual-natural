<?php

namespace App\Support\Cart;

use App\Models\User;
use Throwable;

class CartMergeCoordinator
{
    public const FAILURE_WARNING = 'No pudimos combinar tu carrito invitado con el de tu cuenta. Conservamos ambos carritos y volveremos a intentarlo.';

    private const PENDING_SESSION_KEY = 'shop.cart.merge_pending';

    private bool $attempted = false;

    private ?int $requestId = null;

    public function __construct(
        private readonly CartMergeService $mergeService,
        private readonly SessionCartStorage $sessionStorage,
    ) {}

    public function mergeFor(User $user): bool
    {
        $this->resetAttemptForNewRequest();

        if ($this->attempted) {
            return ! $this->isPending();
        }

        $this->attempted = true;

        if ($this->sessionStorage->all() === []) {
            $this->markCompleted();

            return true;
        }

        session()->put(self::PENDING_SESSION_KEY, true);

        try {
            $this->mergeService->merge($user);
        } catch (Throwable $exception) {
            report($exception);
            $this->sessionStorage->addWarnings([self::FAILURE_WARNING]);

            return false;
        }

        $this->markCompleted();

        return true;
    }

    public function ensureMerged(User $user): bool
    {
        return $this->mergeFor($user);
    }

    public function isPending(): bool
    {
        return (bool) session()->get(self::PENDING_SESSION_KEY, false);
    }

    public function discardPendingGuestCart(): void
    {
        session()->forget(self::PENDING_SESSION_KEY);
        $this->sessionStorage->clear();
    }

    private function markCompleted(): void
    {
        session()->forget(self::PENDING_SESSION_KEY);
        $this->sessionStorage->removeWarning(self::FAILURE_WARNING);
    }

    private function resetAttemptForNewRequest(): void
    {
        $requestId = spl_object_id(request());

        if ($this->requestId === $requestId) {
            return;
        }

        $this->requestId = $requestId;
        $this->attempted = false;
    }
}
