<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(): View
    {
        return view('admin.stocks.index', [
            'menus' => $this->menus(),
        ]);
    }

    public function feed(): JsonResponse
    {
        return response()->json([
            'updated_at' => now()->toIso8601String(),
            'menus' => $this->menus()->map(fn (Menu $menu): array => $this->serializeMenu($menu))->values(),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Menu> */
    private function menus(): \Illuminate\Database\Eloquent\Collection
    {
        return Menu::query()
            ->with('category:id,name')
            ->orderBy('is_available', 'desc')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'stock', 'reserved_stock', 'is_available']);
    }

    /** @return array<string, mixed> */
    private function serializeMenu(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'category' => $menu->category?->name ?? 'Tanpa kategori',
            'stock' => $menu->stock,
            'reserved_stock' => $menu->reserved_stock,
            'available_stock' => $menu->available_stock,
            'is_available' => $menu->is_available,
        ];
    }
}
