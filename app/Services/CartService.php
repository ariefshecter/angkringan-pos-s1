<?php

namespace App\Services;

use App\Models\CartReservation;
use App\Models\Device;
use App\Models\Menu;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;

class CartService
{
    public const RESERVATION_MINUTES = 10;

    private ?Collection $loadedItems = null;

    public function items(bool $renew = true): Collection
    {
        if ($this->loadedItems !== null) {
            return $this->loadedItems;
        }

        $this->purgeExpired();
        $device = $this->device();
        if ($renew) {
            CartReservation::query()
                ->where('device_id', $device->id)
                ->where('expires_at', '>', now())
                ->update([
                    'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                    'updated_at' => now(),
                ]);
        }

        $reservations = CartReservation::query()
            ->with(['menu.category'])
            ->where('device_id', $device->id)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->get();

        $this->syncLegacySession($reservations->pluck('quantity', 'menu_id')->all());

        return $this->loadedItems = $reservations
            ->filter(fn (CartReservation $reservation): bool => $reservation->menu !== null)
            ->map(function (CartReservation $reservation): array {
                $menu = $reservation->menu;

                return [
                    'menu' => $menu,
                    'quantity' => $reservation->quantity,
                    'subtotal' => $menu->price * $reservation->quantity,
                    'max_quantity' => $menu->available_stock + $reservation->quantity,
                ];
            })
            ->values();
    }

    public function add(Menu $menu, int $quantity = 1): void
    {
        $this->mutate($menu, fn (int $current): int => $current + $quantity);
    }

    public function setQuantity(Menu $menu, int $quantity): void
    {
        $this->mutate($menu, fn (): int => $quantity);
    }

    public function remove(Menu $menu): void
    {
        $this->setQuantity($menu, 0);
    }

    public function total(): int
    {
        return $this->items()->sum('subtotal');
    }

    public function count(): int
    {
        return $this->items()->sum('quantity');
    }

    public function payload(bool $renew = true): array
    {
        $items = $this->items($renew);

        return [
            'items' => $items->map(fn (array $item): array => [
                'id' => $item['menu']->id,
                'name' => $item['menu']->name,
                'price' => $item['menu']->price,
                'quantity' => $item['quantity'],
                'subtotal' => $item['subtotal'],
                'available_stock' => $item['menu']->available_stock,
                'max_quantity' => $item['max_quantity'],
                'image_url' => $item['menu']->image_path ? Storage::url($item['menu']->image_path) : null,
            ])->all(),
            'count' => $items->sum('quantity'),
            'total' => $items->sum('subtotal'),
            'stocks' => $this->stocks(),
        ];
    }

    /** @return array<int, int> */
    public function stocks(): array
    {
        $this->purgeExpired();

        return Menu::query()
            ->where('is_available', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Menu $menu): array => [$menu->id => $menu->available_stock])
            ->all();
    }

    public function clear(): void
    {
        $device = $this->device();
        $menuIds = CartReservation::query()
            ->where('device_id', $device->id)
            ->pluck('menu_id');

        foreach ($menuIds as $menuId) {
            $menu = Menu::query()->find($menuId);
            if ($menu) {
                $this->remove($menu);
            }
        }

        $this->loadedItems = collect();
        session()->forget('cart');
    }

    public function restoreFromTransaction(Transaction $transaction): void
    {
        $transaction->loadMissing('items');
        $menus = Menu::query()
            ->whereIn('id', $transaction->items->pluck('menu_id')->filter())
            ->get()
            ->keyBy('id');

        $items = $transaction->items
            ->map(fn ($item): ?array => $menus->has($item->menu_id) ? [
                'menu' => $menus->get($item->menu_id),
                'quantity' => $item->quantity,
            ] : null)
            ->filter();

        $this->restoreItems($items);
    }

    public function restoreItems(Collection $items): void
    {
        $cart = [];

        foreach ($items as $item) {
            try {
                $this->setQuantity($item['menu'], (int) $item['quantity']);
                $cart[$item['menu']->id] = (int) $item['quantity'];
            } catch (ValidationException) {
                // Another kiosk may have reserved the released stock first.
            }
        }

        $this->loadedItems = null;
        $this->syncLegacySession($cart);
    }

    public function purgeExpired(?int $menuId = null): void
    {
        $menuIds = CartReservation::query()
            ->where('expires_at', '<=', now())
            ->when($menuId, fn ($query) => $query->where('menu_id', $menuId))
            ->distinct()
            ->orderBy('menu_id')
            ->pluck('menu_id');

        foreach ($menuIds as $expiredMenuId) {
            DB::transaction(function () use ($expiredMenuId): void {
                $menu = Menu::query()->lockForUpdate()->find($expiredMenuId);
                $reservations = CartReservation::query()
                    ->where('menu_id', $expiredMenuId)
                    ->where('expires_at', '<=', now())
                    ->lockForUpdate()
                    ->get();

                if ($reservations->isEmpty()) {
                    return;
                }

                if ($menu) {
                    $menu->reserved_stock = max(0, $menu->reserved_stock - $reservations->sum('quantity'));
                    $menu->save();
                }

                CartReservation::query()->whereKey($reservations->modelKeys())->delete();
            });
        }

        $this->loadedItems = null;
    }

    private function mutate(Menu $menu, callable $quantityResolver): void
    {
        $device = $this->device();
        $this->purgeExpired($menu->id);

        DB::transaction(function () use ($device, $menu, $quantityResolver): void {
            $lockedMenu = Menu::query()->lockForUpdate()->findOrFail($menu->id);
            $reservation = CartReservation::query()
                ->where('device_id', $device->id)
                ->where('menu_id', $lockedMenu->id)
                ->lockForUpdate()
                ->first();
            $current = (int) ($reservation?->quantity ?? 0);
            $quantity = max(0, (int) $quantityResolver($current));
            $maximum = $lockedMenu->available_stock + $current;

            if ((! $lockedMenu->is_available && $quantity > $current) || $quantity > $maximum) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$lockedMenu->name} hanya tersisa {$maximum} untuk keranjang ini.",
                ]);
            }

            $lockedMenu->reserved_stock += $quantity - $current;
            $lockedMenu->save();

            if ($quantity === 0) {
                $reservation?->delete();
            } elseif ($reservation) {
                $reservation->update([
                    'quantity' => $quantity,
                    'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                ]);
            } else {
                CartReservation::query()->create([
                    'device_id' => $device->id,
                    'menu_id' => $lockedMenu->id,
                    'quantity' => $quantity,
                    'expires_at' => now()->addMinutes(self::RESERVATION_MINUTES),
                ]);
            }
        });

        $this->loadedItems = null;
    }

    private function device(): Device
    {
        $device = request()->attributes->get('device');

        if (! $device instanceof Device) {
            throw new LogicException('Perangkat kiosk tidak ditemukan pada request.');
        }

        return $device;
    }

    /** @param array<int, int> $cart */
    private function syncLegacySession(array $cart): void
    {
        session(['cart' => $cart]);
    }
}
