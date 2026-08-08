<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KioskController extends Controller
{
    public function __invoke(Request $request, CartService $cart): View
    {
        $categories = $this->categories();

        return view('kiosk.index', [
            'categories' => $categories,
            'catalogVersion' => $this->catalogVersion($categories),
            'cartCount' => $cart->count(),
            'cartItems' => $cart->items(),
            'cartTotal' => $cart->total(),
        ]);
    }

    public function stocks(CartService $cart): JsonResponse
    {
        $categories = $this->categories();

        return response()->json([
            ...$cart->payload(false),
            'catalog_version' => $this->catalogVersion($categories),
        ]);
    }

    public function catalog(): JsonResponse
    {
        $categories = $this->categories();

        return response()->json([
            'version' => $this->catalogVersion($categories),
            'html' => view('kiosk.partials.catalog', compact('categories'))->render(),
        ]);
    }

    private function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->with(['menus' => fn ($query) => $query
                ->where('is_available', true)
                ->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function catalogVersion(Collection $categories): string
    {
        $catalog = $categories->map(fn (Category $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'order' => $category->sort_order,
            'menus' => $category->menus->map(fn ($menu): array => [
                'id' => $menu->id,
                'name' => $menu->name,
                'price' => $menu->price,
                'image' => $menu->image_path,
            ])->values()->all(),
        ])->values()->all();

        return hash('sha256', json_encode($catalog));
    }
}
