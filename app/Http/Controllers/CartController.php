<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Product;
use App\Support\Cart\Cart;
use App\Support\Cart\CartService;
use App\Support\Cart\ProductUnavailableException;
use App\Support\Inventory\InsufficientStockException;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(): View
    {
        return view('shop.cart', [
            'cart' => $this->cartService->get(),
        ]);
    }

    public function info(): JsonResponse
    {
        return $this->cartResponse($this->cartService->get());
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        try {
            $cart = $this->cartService->add(
                (int) $request->validated('product_id'),
                (int) $request->validated('quantity'),
            );
        } catch (ProductUnavailableException|InsufficientStockException $exception) {
            return $this->errorResponse($exception);
        }

        return $this->cartResponse($cart, 'Producto agregado al carrito.');
    }

    public function update(UpdateCartRequest $request, Product $product): JsonResponse
    {
        try {
            $cart = $this->cartService->update(
                $product,
                (int) $request->validated('quantity'),
            );
        } catch (ProductUnavailableException|InsufficientStockException $exception) {
            return $this->errorResponse($exception);
        }

        return $this->cartResponse($cart, 'Carrito actualizado.');
    }

    public function destroy(Product $product): JsonResponse
    {
        return $this->cartResponse(
            $this->cartService->remove($product),
            'Producto retirado del carrito.',
        );
    }

    public function clear(): JsonResponse
    {
        return $this->cartResponse(
            $this->cartService->clear(),
            'Carrito vaciado.',
        );
    }

    public function clearWarnings(): JsonResponse
    {
        return $this->cartResponse($this->cartService->clearWarnings());
    }

    private function cartResponse(Cart $cart, ?string $message = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'cart' => $cart->toArray(),
            'warnings' => $cart->warnings,
            'errors' => [],
        ]);
    }

    private function errorResponse(ProductUnavailableException|InsufficientStockException $exception): JsonResponse
    {
        $message = $exception instanceof InsufficientStockException
            ? "No hay stock suficiente. Stock disponible: {$exception->availableStock}."
            : $exception->getMessage();

        return response()->json([
            'message' => $message,
            'cart' => $this->cartService->get()->toArray(),
            'warnings' => [],
            'errors' => [
                'quantity' => [$message],
            ],
        ], 422);
    }
}
