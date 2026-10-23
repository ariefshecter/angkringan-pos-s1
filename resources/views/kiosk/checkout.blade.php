@extends('layouts.kiosk')

@section('title', 'Pembayaran')

@section('content')
    @php
        $selectedPaymentMethod = old('payment_method', $editingPaymentMethod ?? 'cash');
        $selectedPaymentChannel = old('payment_channel', $editingPaymentChannel ?? null);
    @endphp
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-7 sm:px-6 lg:grid-cols-5 lg:py-10">
        <section class="ui-surface h-fit p-5 sm:p-6 lg:col-span-2">
            <p class="ui-eyebrow">Order summary</p><h2 class="mt-1 text-xl font-black">{{ $editingOrder ? 'Perbarui pesanan' : 'Ringkasan pesanan' }}</h2>
            <div class="mt-4 divide-y divide-stone-100">
                @foreach($items as $item)
                    <div class="flex justify-between gap-3 py-3 text-sm">
                        <span>{{ $item['quantity'] }}× {{ $item['menu']->name }}</span>
                        <strong>Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex justify-between border-t border-stone-200 pt-4 text-lg">
                <strong>Total</strong>
                <strong class="text-amber-700">Rp {{ number_format($total, 0, ',', '.') }}</strong>
            </div>
        </section>

        <form method="POST" action="{{ route('kiosk.checkout.store') }}" class="ui-surface p-5 sm:p-7 lg:col-span-3">
            @csrf
            <p class="ui-eyebrow">Checkout</p><h1 class="mt-1 text-2xl font-black tracking-tight">Data dan pembayaran</h1><p class="mt-2 text-sm text-stone-500">Lengkapi data pelanggan dan pilih cara pembayaran yang paling nyaman.</p>
            @if($errors->has('cart'))
                <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first('cart') }}</p>
            @endif

            <div class="mt-5 space-y-5">
                <div>
                    <x-input-label for="customer_name" value="Nama pelanggan" />
                    <x-text-input id="customer_name" name="customer_name" class="mt-1 block w-full" :value="old('customer_name', $editingCustomerName ?? '')" required autofocus />
                    <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                </div>

                <fieldset>
                    <legend class="ui-eyebrow mb-3">Metode pembayaran</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="cursor-pointer rounded-2xl border border-stone-200 bg-stone-50/40 p-4 transition hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                            <input type="radio" name="payment_method" value="cash" class="text-amber-600 focus:ring-amber-500" @checked($selectedPaymentMethod === 'cash')>
                            <span class="ml-2 font-semibold">Tunai</span>
                            <p class="mt-1 pl-6 text-xs text-stone-500">Bayar ke kasir</p>
                        </label>
                        <label class="cursor-pointer rounded-2xl border border-stone-200 bg-stone-50/40 p-4 transition hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                            <input type="radio" name="payment_method" value="qris" class="text-amber-600 focus:ring-amber-500" @checked($selectedPaymentMethod === 'qris')>
                            <span class="ml-2 font-semibold">QRIS (Auto)</span>
                            <p class="mt-1 pl-6 text-xs text-stone-500">Langsung QRIS</p>
                        </label>
                        <label data-bank-method-card class="cursor-pointer rounded-2xl border border-stone-200 bg-stone-50/40 p-4 transition hover:border-amber-300 has-[:checked]:border-amber-500 has-[:checked]:bg-amber-50">
                            <input type="radio" name="payment_method" value="bank_transfer" class="text-amber-600 focus:ring-amber-500" @checked($selectedPaymentMethod === 'bank_transfer')>
                            <span class="ml-2 font-semibold">Transfer Bank</span>
                            <p id="selected-bank-summary" class="mt-1 pl-6 text-xs text-stone-500">Virtual Account</p>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                    <x-input-error :messages="$errors->get('payment_channel')" class="mt-2" />
                </fieldset>

                <section id="bank-options" role="dialog" aria-modal="true" aria-labelledby="bank-options-title" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
                    <button type="button" data-close-bank-options class="absolute inset-0 bg-stone-950/70 backdrop-blur-sm" aria-label="Tutup pilihan bank"></button>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-amber-500 bg-stone-950 text-white shadow-2xl">
                        <div class="flex items-center justify-between bg-gradient-to-b from-amber-300 to-amber-700 px-5 py-4 text-stone-950">
                            <div><h2 id="bank-options-title" class="text-xl font-black">Pilih Bank Virtual Account</h2><p class="text-xs font-medium text-stone-800">Nomor VA akan ditampilkan setelah pesanan dibuat.</p></div>
                            <button type="button" data-close-bank-options class="grid h-9 w-9 place-items-center rounded-full border border-stone-800/30 text-2xl font-bold hover:bg-white/30" aria-label="Tutup">×</button>
                        </div>

                        <div class="min-h-0 overflow-y-auto p-5 sm:p-6">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                @foreach($bankChannels as $channel => $bank)
                                    <label class="cursor-pointer text-center">
                                        <input type="radio" name="payment_channel" value="{{ $channel }}" data-bank-label="{{ $channel === 'other_bank_va' ? 'Bank Lainnya — via Permata VA' : $bank.' — Virtual Account' }}" class="peer sr-only" @checked($selectedPaymentChannel === $channel)>
                                        <span class="grid min-h-14 place-items-center rounded-lg border-2 border-stone-400 bg-white px-3 py-2 text-sm font-black text-stone-900 transition peer-checked:border-amber-400 peer-checked:bg-amber-50 peer-checked:ring-2 peer-checked:ring-amber-400">{{ $bank }}</span>
                                        <span class="mt-1 block text-xs text-stone-300">{{ $channel === 'other_bank_va' ? 'Transfer antarbank' : 'Virtual Account' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p class="mt-5 rounded-lg border border-stone-700 bg-stone-900 p-3 text-xs leading-5 text-stone-300">
                                Tidak menemukan bank Anda? Pilih <strong class="text-white">Bank Lainnya</strong>. Sistem akan memberikan Permata Virtual Account yang dapat dibayar melalui transfer antarbank.
                            </p>
                        </div>

                        <div class="flex flex-col-reverse gap-3 border-t border-stone-700 bg-stone-900 px-5 py-4 sm:flex-row sm:justify-end">
                            <button id="cancel-checkout-from-modal" type="button" class="rounded-lg border border-red-400 px-5 py-2.5 text-sm font-semibold text-red-300 hover:bg-red-600 hover:text-white">Batalkan pesanan</button>
                            <button type="button" data-close-bank-options class="rounded-lg border border-stone-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-800">Batal</button>
                            <button id="confirm-bank-options" type="button" class="rounded-lg bg-amber-500 px-6 py-2.5 text-sm font-bold text-stone-950 hover:bg-amber-400 disabled:cursor-not-allowed disabled:bg-stone-600 disabled:text-stone-300">Gunakan bank ini</button>
                        </div>
                    </div>
                </section>

                <div>
                    <x-input-label for="notes" value="Catatan pesanan (opsional)" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-xl border-stone-300 bg-stone-50/60 px-4 py-3 text-sm focus:border-amber-500 focus:bg-white focus:ring-amber-500">{{ old('notes', $editingNotes ?? '') }}</textarea>
                </div>

                    <button class="ui-button-accent w-full py-3.5">{{ $editingOrder ? 'Perbarui pembayaran' : 'Buat pesanan' }}</button>
            </div>
        </form>

        <form id="discard-checkout-form" method="POST" action="{{ $editingOrder ? route('kiosk.orders.cancel-order', $editingOrder) : route('kiosk.cart.clear') }}" class="lg:col-start-3 lg:col-span-3" data-confirm="{{ $editingOrder ? 'Pesanan akan dibatalkan sepenuhnya dan stok akan dikembalikan.' : 'Semua produk akan dihapus dari pesanan dan stok akan dikembalikan.' }} Lanjutkan?" data-confirm-title="Batalkan pesanan" data-confirm-button="Ya, batalkan">
            @csrf
            <button type="submit" class="ui-button-danger w-full">{{ $editingOrder ? 'Batalkan pesanan ini' : 'Batalkan pesanan' }}</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const bankOptions = document.getElementById('bank-options');
        const checkoutForm = bankOptions.closest('form');
        const bankInputs = bankOptions.querySelectorAll('input[name="payment_channel"]');
        const closeBankOptionsButtons = bankOptions.querySelectorAll('[data-close-bank-options]');
        const confirmBankOptions = document.getElementById('confirm-bank-options');
        const cancelCheckoutFromModal = document.getElementById('cancel-checkout-from-modal');
        const discardCheckoutForm = document.getElementById('discard-checkout-form');
        const selectedBankSummary = document.getElementById('selected-bank-summary');
        const bankMethodCard = document.querySelector('[data-bank-method-card]');
        let confirmedBank = @json(old('payment_channel', $editingPaymentChannel ?? null)) || null;
        let lastNonBankMethod = document.querySelector('input[name="payment_method"]:checked')?.value === 'qris' ? 'qris' : 'cash';

        function renderBankSelection() {
            confirmBankOptions.disabled = !bankOptions.querySelector('input[name="payment_channel"]:checked');
        }

        function updateSelectedBankSummary() {
            const selectedBank = [...bankInputs].find(input => input.value === confirmedBank);
            selectedBankSummary.textContent = selectedBank?.dataset.bankLabel || 'Virtual Account';
        }

        function openBankOptions() {
            bankOptions.classList.remove('hidden');
            bankOptions.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            bankInputs.forEach(input => input.disabled = false);
            renderBankSelection();
        }

        function closeBankOptions(cancelled = true) {
            if (cancelled) {
                bankInputs.forEach(input => input.checked = input.value === confirmedBank);

                if (! confirmedBank) {
                    const fallback = document.querySelector(`input[name="payment_method"][value="${lastNonBankMethod}"]`);
                    if (fallback) fallback.checked = true;
                }
            }

            bankOptions.classList.add('hidden');
            bankOptions.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');

            const usesBankTransfer = document.querySelector('input[name="payment_method"]:checked')?.value === 'bank_transfer';
            bankInputs.forEach(input => input.disabled = !usesBankTransfer);
            updateSelectedBankSummary();
        }

        function updatePaymentMethod(event) {
            const selected = event.target.value;

            if (selected === 'bank_transfer') {
                openBankOptions();
                return;
            }

            lastNonBankMethod = selected;
            closeBankOptions(false);
        }

        paymentMethods.forEach(input => input.addEventListener('change', updatePaymentMethod));
        bankMethodCard.addEventListener('click', () => setTimeout(() => {
            if (document.querySelector('input[name="payment_method"]:checked')?.value === 'bank_transfer') openBankOptions();
        }));
        bankInputs.forEach(input => input.addEventListener('change', renderBankSelection));
        closeBankOptionsButtons.forEach(button => button.addEventListener('click', () => closeBankOptions(true)));
        cancelCheckoutFromModal.addEventListener('click', () => {
            closeBankOptions(true);
            discardCheckoutForm.requestSubmit();
        });
        confirmBankOptions.addEventListener('click', () => {
            const selectedBank = bankOptions.querySelector('input[name="payment_channel"]:checked');
            if (! selectedBank) return;
            confirmedBank = selectedBank.value;
            closeBankOptions(false);
            checkoutForm.requestSubmit();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && ! bankOptions.classList.contains('hidden')) closeBankOptions(true);
        });
        checkoutForm.addEventListener('submit', event => {
            const usesBankTransfer = document.querySelector('input[name="payment_method"]:checked')?.value === 'bank_transfer';
            if (usesBankTransfer && ! confirmedBank) {
                event.preventDefault();
                openBankOptions();
            }
        });

        updateSelectedBankSummary();
        closeBankOptions(false);
        if (@json($errors->has('payment_channel')) && document.querySelector('input[name="payment_method"]:checked')?.value === 'bank_transfer') openBankOptions();
    </script>
@endpush
