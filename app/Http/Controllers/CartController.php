<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(CartService $cart): View
    {
        return view('kiosk.cart', [
            'items' => $cart->items(),
            'total' => $cart->total(),
        ]);
    }

    public function store(Request $request, CartService $cartService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'menu_id' => ['required', 'exists:menu,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);
        $menu = Menu::query()->findOrFail($validated['menu_id']);

        if (! $menu->is_available) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Menu tidak tersedia atau stok habis.'], 422);
            }

            return back()->with('error', 'Menu tidak tersedia atau stok habis.');
        }

        $cartService->add($menu, $validated['quantity'] ?? 1);

        if ($request->expectsJson()) {
            return response()->json($cartService->payload());
        }

        return back()->with('success', "{$menu->name} ditambahkan ke keranjang.");
    }

    public function update(Request $request, Menu $menu, CartService $cartService): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);
        $cartService->setQuantity($menu, $validated['quantity']);

        if ($request->expectsJson()) {
            return response()->json($cartService->payload());
        }

        return back()->with('success', 'Keranjang diperbarui.');
    }

    public function destroy(Request $request, Menu $menu, CartService $cartService): RedirectResponse|JsonResponse
    {
        $cartService->remove($menu);

        if ($request->expectsJson()) {
            return response()->json($cartService->payload());
        }

        return back()->with('success', 'Menu dihapus dari keranjang.');
    }

    public function clear(CartService $cartService): RedirectResponse
    {
        $cartService->clear();

        return redirect()->route('kiosk.index')->with('success', 'Pesanan dibatalkan dan stok telah dikembalikan.');
    }
}
