@extends('layouts.kiosk')

@section('title', 'Daftar Menu')

@section('cart')
    <div class="grid h-10 w-10 place-items-center text-stone-600" aria-label="Device pelanggan">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0 17.9 17.9 0 0 1-15 0Z" /></svg>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-[1600px] lg:grid lg:grid-cols-[minmax(0,3fr)_minmax(340px,2fr)]">
        <section id="catalog-section" class="min-w-0 bg-[#f7f3eb] px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
            <div id="kiosk-catalog">
                @include('kiosk.partials.catalog', ['categories' => $categories])
            </div>
        </section>

        <aside class="border-t border-stone-200 bg-white lg:sticky lg:top-[4.5rem] lg:h-[calc(100vh-4.5rem)] lg:border-l lg:border-t-0">
            <div class="flex h-full flex-col p-5 sm:p-6 lg:p-8">
                <div class="mb-5 flex items-center justify-between border-b border-stone-100 pb-5">
                    <div><p class="ui-eyebrow">Order summary</p><h1 class="mt-1 text-lg font-black">Pesanan dipilih</h1><p class="mt-1 text-xs text-stone-500">Klik produk untuk menambah jumlah.</p></div>
                    <span id="selected-count" class="grid h-9 min-w-9 place-items-center rounded-2xl bg-stone-950 px-2 text-xs font-bold text-amber-300">{{ $cartCount }}</span>
                </div>

                <div id="selected-items" class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                    @forelse($cartItems as $item)
                        <div class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50/50 p-3" data-selected-item="{{ $item['menu']->id }}">
                            <div class="h-14 w-14 overflow-hidden rounded-xl border border-stone-200 bg-stone-50">
                                @if($item['menu']->image_path)<img src="{{ Storage::url($item['menu']->image_path) }}" alt="{{ $item['menu']->name }}" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center text-[9px] font-semibold text-stone-400">[ Gambar ]</div>@endif
                            </div>
                            <div class="min-w-0"><p class="truncate text-sm font-bold">{{ $item['menu']->name }}</p><p class="mt-1 text-xs text-stone-500">{{ $item['quantity'] }} × Rp {{ number_format($item['menu']->price, 0, ',', '.') }}</p></div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-black">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                                <div class="mt-2 flex items-center justify-end gap-2">
                                    <button type="button" data-quantity-action="decrease" data-menu-id="{{ $item['menu']->id }}" data-quantity="{{ $item['quantity'] }}" data-max="{{ $item['max_quantity'] }}" class="grid h-7 w-7 place-items-center rounded bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700" aria-label="Kurangi {{ $item['menu']->name }}">−</button>
                                    <span class="min-w-5 text-center text-xs font-bold">{{ $item['quantity'] }}</span>
                                    <button type="button" data-quantity-action="increase" data-menu-id="{{ $item['menu']->id }}" data-quantity="{{ $item['quantity'] }}" data-max="{{ $item['max_quantity'] }}" class="grid h-7 w-7 place-items-center rounded bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-stone-300" aria-label="Tambah {{ $item['menu']->name }}" @disabled($item['quantity'] >= $item['max_quantity'])>+</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div id="empty-cart" class="grid h-48 place-items-center rounded-xl border-2 border-dashed border-stone-200 px-8 text-center text-sm text-stone-400">Belum ada produk dipilih.</div>
                    @endforelse
                </div>

                <div class="mt-5 border-t border-stone-100 pt-5">
                    <div class="flex items-center justify-between text-lg font-black"><span>Total</span><span id="selected-total">Rp {{ number_format($cartTotal, 0, ',', '.') }}</span></div>
                    <a id="pay-button" href="{{ route('kiosk.checkout.create') }}" class="mt-5 block w-full rounded-xl bg-amber-500 px-5 py-4 text-center text-sm font-black uppercase tracking-wide text-stone-950 shadow-sm transition hover:bg-amber-400 {{ $cartCount < 1 ? 'pointer-events-none opacity-40' : '' }}" aria-disabled="{{ $cartCount < 1 ? 'true' : 'false' }}">Lanjut ke pembayaran</a>
                    <p class="mt-2 text-center text-[11px] text-stone-500">Stok pilihan ditahan selama 10 menit.</p>
                </div>
            </div>
        </aside>
    </div>

@endsection

@push('scripts')
    @php
        $initialCartItems = $cartItems->map(function ($item) {
            return [
                'id' => $item['menu']->id,
                'quantity' => $item['quantity'],
                'price' => $item['menu']->price,
                'name' => $item['menu']->name,
            ];
        })->values();
    @endphp
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const addUrl = @json(route('kiosk.cart.store'));
        const stocksUrl = @json(route('kiosk.stocks'));
        const catalogUrl = @json(route('kiosk.catalog'));
        const updateUrlTemplate = @json(route('kiosk.cart.update', ['menu' => '__MENU__']));
        const catalogSection = document.getElementById('catalog-section');
        const catalogContainer = document.getElementById('kiosk-catalog');
        const selectedItems = document.getElementById('selected-items');
        const selectedCount = document.getElementById('selected-count');
        const selectedTotal = document.getElementById('selected-total');
        const payButton = document.getElementById('pay-button');
        let catalogVersion = @json($catalogVersion);
        let selectedCategory = 'all';
        let lastCartSignature = JSON.stringify(@json($initialCartItems));

        const rupiah = value => `Rp ${new Intl.NumberFormat('id-ID').format(value)}`;
        const cartSignature = cart => JSON.stringify(cart.items.map(item => ({id: item.id, quantity: item.quantity, price: item.price, name: item.name})));
        const categoryButtons = () => document.querySelectorAll('[data-category-button]');
        const menuCards = () => document.querySelectorAll('[data-menu-card]');

        function showToast(message, error = false) {
            window.appToast(message, error ? 'error' : 'success', {duration: 5000});
        }

        function renderCart(cart) {
            lastCartSignature = cartSignature(cart);
            selectedCount.textContent = cart.count;
            selectedTotal.textContent = rupiah(cart.total);
            payButton.classList.toggle('pointer-events-none', cart.count < 1);
            payButton.classList.toggle('opacity-40', cart.count < 1);
            payButton.setAttribute('aria-disabled', cart.count < 1 ? 'true' : 'false');
            renderStocks(cart.stocks);

            if (!cart.items.length) {
                selectedItems.innerHTML = '<div class="grid h-48 place-items-center rounded-xl border-2 border-dashed border-stone-200 px-8 text-center text-sm text-stone-400">Belum ada produk dipilih.</div>';
                return;
            }

            selectedItems.innerHTML = cart.items.map(item => `
                <div class="grid grid-cols-[56px_minmax(0,1fr)_auto] items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50/50 p-3">
                    <div class="item-image h-14 w-14 overflow-hidden rounded-xl border border-stone-200 bg-stone-50"></div>
                    <div class="min-w-0"><p class="item-name truncate text-sm font-bold"></p><p class="item-detail mt-1 text-xs text-stone-500"></p></div>
                    <div class="shrink-0 text-right">
                        <p class="item-subtotal text-sm font-black"></p>
                        <div class="mt-2 flex items-center justify-end gap-2">
                            <button type="button" data-quantity-action="decrease" data-menu-id="${item.id}" data-quantity="${item.quantity}" data-max="${item.max_quantity}" class="grid h-7 w-7 place-items-center rounded bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700">−</button>
                            <span class="min-w-5 text-center text-xs font-bold">${item.quantity}</span>
                            <button type="button" data-quantity-action="increase" data-menu-id="${item.id}" data-quantity="${item.quantity}" data-max="${item.max_quantity}" class="grid h-7 w-7 place-items-center rounded bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-stone-300" ${item.quantity >= item.max_quantity ? 'disabled' : ''}>+</button>
                        </div>
                    </div>
                </div>`).join('');

            [...selectedItems.children].forEach((row, index) => {
                const item = cart.items[index];
                row.querySelector('.item-name').textContent = item.name;
                row.querySelector('.item-detail').textContent = `${item.quantity} × ${rupiah(item.price)}`;
                row.querySelector('.item-subtotal').textContent = rupiah(item.subtotal);
                const image = row.querySelector('.item-image');
                if (item.image_url) {
                    const img = document.createElement('img'); img.src = item.image_url; img.alt = item.name; img.className = 'h-full w-full object-cover'; image.appendChild(img);
                } else {
                    image.innerHTML = '<div class="grid h-full place-items-center text-[9px] font-semibold text-stone-400">[ Gambar ]</div>';
                }
            });
        }

        function renderStocks(stocks = {}) {
            menuCards().forEach(card => {
                const stock = Number(stocks[card.dataset.menuId] ?? 0);
                const badge = card.querySelector('[data-stock-badge]');
                if (!badge) return;
                badge.textContent = stock;
                badge.setAttribute('aria-label', `Stok tersedia ${stock}`);
                card.disabled = stock < 1;

                const cartButtons = selectedItems.querySelectorAll(`[data-menu-id="${card.dataset.menuId}"][data-quantity-action]`);
                cartButtons.forEach(button => {
                    const current = Number(button.dataset.quantity);
                    button.dataset.max = current + stock;
                    if (button.dataset.quantityAction === 'increase') button.disabled = stock < 1;
                });
            });
        }

        function applyCategoryFilter(category = selectedCategory) {
            const buttons = [...categoryButtons()];
            const activeButton = buttons.find(button => button.dataset.categoryButton === category)
                || buttons.find(button => button.dataset.categoryButton === 'all');
            selectedCategory = activeButton?.dataset.categoryButton || 'all';
            let visibleCount = 0;

            buttons.forEach(button => {
                const active = button === activeButton;
                button.classList.toggle('bg-fuchsia-700', active);
                button.classList.toggle('border-fuchsia-700', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('bg-white', !active);
                button.classList.toggle('text-stone-700', !active);
            });
            menuCards().forEach(card => {
                const visible = selectedCategory === 'all' || card.dataset.category === selectedCategory;
                card.classList.toggle('hidden', !visible);
                if (visible) visibleCount++;
            });
            document.getElementById('empty-category')?.classList.toggle('hidden', visibleCount > 0);
        }

        async function syncCatalog(stocks) {
            const response = await fetch(catalogUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            if (data.version === catalogVersion) return;

            catalogContainer.innerHTML = data.html;
            catalogVersion = data.version;
            applyCategoryFilter(selectedCategory);
            renderStocks(stocks);
            window.appToast('Daftar menu telah diperbarui oleh admin.', 'info', {duration: 5000});
        }

        async function syncStocks() {
            try {
                const response = await fetch(stocksUrl, {headers: {'Accept': 'application/json'}});
                if (!response.ok) return;
                const data = await response.json();
                if (data.catalog_version !== catalogVersion) await syncCatalog(data.stocks);
                const nextSignature = cartSignature(data);

                if (nextSignature !== lastCartSignature) {
                    const reservationExpired = lastCartSignature !== '[]' && nextSignature === '[]';
                    renderCart(data);
                    if (reservationExpired) showToast('Waktu keranjang habis. Stok telah dikembalikan.', true);
                } else {
                    renderStocks(data.stocks);
                }
            } catch (error) {
                console.error(error);
            }
        }

        async function sendCartRequest(url, method, body = null) {
            const response = await fetch(url, {
                method,
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: body ? JSON.stringify(body) : null,
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Keranjang gagal diperbarui.');
            renderCart(data);
            return data;
        }

        catalogSection.addEventListener('click', async event => {
            const categoryButton = event.target.closest('[data-category-button]');
            if (categoryButton) {
                applyCategoryFilter(categoryButton.dataset.categoryButton);
                return;
            }

            const button = event.target.closest('[data-add-product]');
            if (!button || button.disabled) return;

            button.disabled = true;
            try { await sendCartRequest(addUrl, 'POST', {menu_id: button.dataset.menuId, quantity: 1}); showToast('Produk ditambahkan.'); }
            catch (error) { showToast(error.message, true); }
            finally { button.disabled = false; }
        });

        selectedItems.addEventListener('click', async event => {
            const button = event.target.closest('[data-quantity-action]');
            if (!button) return;
            const current = Number(button.dataset.quantity);
            const maximum = Number(button.dataset.max);
            const quantity = button.dataset.quantityAction === 'increase' ? Math.min(current + 1, maximum) : Math.max(current - 1, 0);
            button.disabled = true;
            try { await sendCartRequest(updateUrlTemplate.replace('__MENU__', button.dataset.menuId), 'PATCH', {quantity}); showToast('Jumlah pesanan diperbarui.'); }
            catch (error) { showToast(error.message, true); button.disabled = false; }
        });

        setInterval(syncStocks, 2000);
    </script>
@endpush
