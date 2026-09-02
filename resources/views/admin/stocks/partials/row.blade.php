@php($isAvailable = $menu->is_available && $menu->available_stock > 0)
<tr>
    <td class="px-6 py-4 font-semibold text-stone-900">{{ $menu->name }}</td>
    <td class="px-6 py-4 text-stone-600">{{ $menu->category?->name ?? 'Tanpa kategori' }}</td>
    <td class="px-6 py-4">{{ $menu->stock }}</td>
    <td class="px-6 py-4 text-amber-700">{{ $menu->reserved_stock }}</td>
    <td class="px-6 py-4 font-bold {{ $isAvailable ? 'text-emerald-700' : 'text-red-700' }}">{{ $menu->available_stock }}</td>
    <td class="px-6 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $isAvailable ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ $isAvailable ? 'Tersedia' : 'Tidak tersedia' }}</span></td>
</tr>
