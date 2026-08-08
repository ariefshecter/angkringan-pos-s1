<nav class="mb-7 flex gap-3 overflow-x-auto pb-2" aria-label="Filter kategori">
    <button type="button" data-category-button="all" class="category-button shrink-0 rounded-full border border-fuchsia-700 bg-fuchsia-700 px-7 py-2.5 text-xs font-bold text-white shadow-sm transition">Semua</button>
    @foreach($categories as $category)
        @if($category->menus->isNotEmpty())
            <button type="button" data-category-button="{{ $category->id }}" class="category-button shrink-0 rounded-full border border-fuchsia-200 bg-white px-7 py-2.5 text-xs font-bold text-stone-700 shadow-sm transition hover:border-fuchsia-700 hover:text-fuchsia-700">{{ $category->name }}</button>
        @endif
    @endforeach
</nav>

<div id="menu-grid" class="grid grid-cols-2 gap-5 sm:grid-cols-3 xl:grid-cols-4">
    @foreach($categories as $category)
        @foreach($category->menus as $menu)
            <button type="button" data-add-product data-menu-id="{{ $menu->id }}" data-menu-card data-category="{{ $category->id }}" class="group flex min-w-0 flex-col overflow-hidden rounded-[1.6rem] bg-white text-left shadow-[0_8px_24px_rgba(75,45,110,0.08)] ring-1 ring-purple-100 transition duration-200 hover:-translate-y-1 hover:shadow-[0_14px_30px_rgba(75,45,110,0.16)] active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-50" @disabled($menu->available_stock < 1)>
                <div class="aspect-square w-full overflow-hidden bg-purple-50">
                    @if($menu->image_path)
                        <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105" src="{{ Storage::url($menu->image_path) }}" alt="{{ $menu->name }}">
                    @else
                        <div class="grid h-full place-items-center bg-gradient-to-br from-purple-50 to-fuchsia-100 text-center text-xs font-semibold text-purple-400">Foto produk</div>
                    @endif
                </div>
                <div class="flex w-full flex-1 flex-col px-4 pb-5 pt-4 sm:px-5">
                    <h2 class="text-sm font-black leading-5 text-stone-900 sm:text-base">{{ $menu->name }}</h2>
                    <div class="mt-1.5 flex items-center justify-between gap-2">
                        <p class="whitespace-nowrap text-sm font-black text-[#b12988] sm:text-base">Rp {{ number_format($menu->price, 0, ',', '.') }}</p>
                        <p data-stock-badge class="grid h-8 min-w-8 shrink-0 place-items-center rounded-full bg-fuchsia-600 px-2 text-[11px] font-bold text-white shadow-sm sm:text-xs" aria-label="Stok tersedia {{ $menu->available_stock }}">{{ $menu->available_stock }}</p>
                    </div>
                </div>
            </button>
        @endforeach
    @endforeach
</div>

<div id="empty-category" class="{{ $categories->every(fn ($category) => $category->menus->isEmpty()) ? '' : 'hidden' }} py-16 text-center text-sm text-stone-500">Tidak ada menu yang tersedia.</div>
