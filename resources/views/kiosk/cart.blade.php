@extends('layouts.kiosk')

@section('title', 'Keranjang Belanja')

@section('cart')
    <a href="{{ route('kiosk.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100">Kembali ke Menu</a>
@endsection

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="mb-6 text-xl font-black uppercase tracking-tight text-stone-950">Keranjang Belanja</h1>

        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Pesanan belum dapat diproses:</p>
                <ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if($items->isEmpty())
            <div class="rounded-xl border border-stone-200 bg-white p-12 text-center shadow-sm">
                <p class="text-stone-500">Keranjang masih kosong.</p>
                <a class="mt-4 inline-block rounded-lg bg-stone-900 px-5 py-3 text-sm font-bold text-white" href="{{ route('kiosk.index') }}">Pilih Menu</a>
            </div>
        @else
            <form id="checkout-form" method="POST" action="{{ route('kiosk.checkout.store') }}">@csrf</form>

            <div class="grid items-start gap-7 lg:grid-cols-2">
                <div class="space-y-5">
                    <section class="overflow-hidden rounded-xl border border-stone-200 bg-white px-4 shadow-sm">
                        @foreach($items as $item)
                            <article class="flex items-center gap-3 border-b border-stone-100 py-4 last:border-b-0">
                                <div class="h-16 w-16 shrink-0 overflow-hidden rounded-md border border-stone-400 bg-stone-50">
                                    @if($item['menu']->image_path)
                                        <img src="{{ Storage::url($item['menu']->image_path) }}" alt="{{ $item['menu']->name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="grid h-full place-items-center px-1 text-center text-[8px] font-semibold text-stone-500">[ {{ Str::limit($item['menu']->name, 8) }} ]</div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h2 class="truncate text-sm font-extrabold text-stone-950">{{ $item['menu']->name }}</h2>
                                    <p class="truncate text-[10px] text-stone-500">{{ $item['menu']->description ?: 'Menu Angkringan S1' }}</p>
                                    <p class="mt-1 text-sm font-bold">Rp {{ number_format($item['menu']->price, 0, ',', '.') }}</p>
                                </div>

                                <div class="shrink-0 text-right">
                                    <form method="POST" action="{{ route('kiosk.cart.update', $item['menu']) }}" class="flex items-center justify-end gap-1">@csrf @method('PATCH')
                                        <input name="quantity" type="number" min="0" max="{{ $item['max_quantity'] }}" value="{{ $item['quantity'] }}" class="h-8 w-14 rounded border-stone-300 p-1 text-center text-xs focus:border-stone-700 focus:ring-stone-700" aria-label="Jumlah {{ $item['menu']->name }}">
                                        <button class="h-8 rounded border border-stone-300 px-2 text-[10px] font-semibold hover:bg-stone-100">Ubah</button>
                                    </form>
                                    <p class="mt-2 text-xs font-medium">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                                    <form method="POST" action="{{ route('kiosk.cart.destroy', $item['menu']) }}" class="mt-1">@csrf @method('DELETE')<button class="text-[10px] font-semibold text-red-600 hover:underline">Hapus</button></form>
                                </div>
                            </article>
                        @endforeach
                    </section>

                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-black">Formulir Pelanggan</h2>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="customer_name" class="text-xs font-medium text-stone-700">Nama Pelanggan <span class="text-red-600">(Wajib)</span></label>
                                <input form="checkout-form" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required maxlength="100" placeholder="NAMA PELANGGAN" class="mt-1 block w-full rounded border-stone-400 text-sm uppercase focus:border-stone-800 focus:ring-stone-800">
                            </div>
                            <div>
                                <label for="notes" class="text-xs font-medium text-stone-700">Catatan Pesanan <span class="text-stone-400">(Opsional)</span></label>
                                <textarea form="checkout-form" id="notes" name="notes" rows="2" maxlength="500" placeholder="Contoh: tidak pedas" class="mt-1 block w-full rounded border-stone-300 text-sm focus:border-stone-800 focus:ring-stone-800">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="space-y-5">
                    <section class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                        <h2 class="text-base font-black uppercase">Ringkasan Pemesanan</h2>
                        <div class="mt-4 flex items-center justify-between text-sm"><span>Subtotal</span><span>Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                        <div class="mt-8 flex items-center justify-between border-t border-stone-200 pt-5 text-xl font-black"><span>Total Pembayaran</span><span>Rp {{ number_format($total, 0, ',', '.') }}</span></div>
                    </section>

                    <section>
                        <h2 class="mb-3 text-base font-black uppercase">Pilih Metode Pembayaran</h2>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative min-h-48 cursor-pointer rounded-xl border border-stone-300 bg-white p-5 shadow-sm transition has-[:checked]:border-amber-600 has-[:checked]:ring-2 has-[:checked]:ring-amber-200">
                                <input form="checkout-form" type="radio" name="payment_method" value="cash" class="absolute left-4 top-4 text-amber-600 focus:ring-amber-500" @checked(old('payment_method', 'cash') === 'cash')>
                                <div class="flex h-full flex-col items-center justify-center text-center">
                                    <p class="text-xs font-black uppercase">Cash (Tunai di Kasir)</p>
                                    <svg class="my-5 h-12 w-12" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 6.75A2.25 2.25 0 0 1 4.5 4.5h15a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75Zm5.25 0A2.25 2.25 0 0 1 5.25 9v6a2.25 2.25 0 0 1 2.25 2.25h9A2.25 2.25 0 0 1 18.75 15V9a2.25 2.25 0 0 1-2.25-2.25h-9ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                                    <p class="text-[10px] leading-4 text-stone-500">Bayar tunai kepada kasir setelah membuat pesanan.</p>
                                </div>
                            </label>

                            <label class="relative min-h-48 cursor-pointer rounded-xl border border-stone-300 bg-white p-5 shadow-sm transition has-[:checked]:border-amber-600 has-[:checked]:ring-2 has-[:checked]:ring-amber-200">
                                <input form="checkout-form" type="radio" name="payment_method" value="qris" class="absolute left-4 top-4 text-amber-600 focus:ring-amber-500" @checked(old('payment_method') === 'qris')>
                                <div class="flex h-full flex-col items-center justify-center text-center">
                                    <p class="text-xs font-black uppercase">Digital (QRIS / Midtrans)</p>
                                    <svg class="my-4 h-16 w-16" viewBox="0 0 64 64" fill="currentColor" aria-hidden="true"><path d="M4 4h24v24H4V4Zm6 6v12h12V10H10Zm26-6h24v24H36V4Zm6 6v12h12V10H42ZM4 36h24v24H4V36Zm6 6v12h12V42H10Zm30-6h8v8h-8v-8Zm12 0h8v16h-8V36Zm-16 12h8v12h-8V48Zm12 0h8v8h-8v-8Zm12 8h-8v4h8v-4Z"/></svg>
                                    <div class="flex gap-1"><span class="h-3 w-5 rounded-sm bg-stone-300"></span><span class="h-3 w-5 rounded-sm bg-cyan-500"></span><span class="h-3 w-5 rounded-sm bg-orange-500"></span><span class="h-3 w-5 rounded-sm bg-stone-600"></span><span class="h-3 w-5 rounded-sm bg-emerald-600"></span></div>
                                </div>
                            </label>
                        </div>
                    </section>

                    <button form="checkout-form" class="w-full rounded border border-stone-900 bg-white px-5 py-4 text-sm font-black uppercase tracking-wide text-stone-950 transition hover:bg-stone-900 hover:text-white">
                        Lengkapi Pesanan
                    </button>
                    <form method="POST" action="{{ route('kiosk.cart.clear') }}" data-confirm="Semua produk akan dihapus dari pesanan dan stok akan dikembalikan. Lanjutkan?" data-confirm-title="Batalkan pesanan" data-confirm-button="Ya, batalkan">
                        @csrf
                        <button type="submit" class="w-full rounded border border-red-300 bg-white px-5 py-4 text-sm font-black uppercase tracking-wide text-red-600 transition hover:bg-red-600 hover:text-white">Batalkan pesanan</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
