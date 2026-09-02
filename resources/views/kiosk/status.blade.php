@extends('layouts.kiosk')

@section('title', 'Status Pesanan')

@php
    $bankName = config('services.midtrans.bank_channels.'.$transaction->payment_channel);
    $issuerBankName = config('services.midtrans.bank_code_labels.'.$transaction->midtrans_bank, $bankName);
    $isOtherBank = $transaction->payment_channel === 'other_bank_va';
    $isVirtualAccount = filled($transaction->midtrans_va_number);
    $digitalLabel = $transaction->payment_channel === 'other_qris' ? 'QRIS' : ($bankName ? 'Transfer '.$bankName : 'Digital');
    $isPending = $transaction->payment_status === \App\Models\Transaction::PAYMENT_PENDING;
    $isReplacing = $transaction->payment_status === \App\Models\Transaction::PAYMENT_REPLACING;
    $isReplaced = $transaction->payment_status === \App\Models\Transaction::PAYMENT_REPLACED;
    $isFailed = in_array($transaction->payment_status, [
        \App\Models\Transaction::PAYMENT_CANCELLED,
        \App\Models\Transaction::PAYMENT_FAILED,
        \App\Models\Transaction::PAYMENT_EXPIRED,
    ], true);
    $isExplicitlyCancelled = $transaction->isExplicitlyCancelled();
    $isRetryable = $isFailed && ! $isExplicitlyCancelled;
    $canChangePayment = ($isPending || $isReplacing || $isRetryable) && ! $isExplicitlyCancelled;
    $pendingHelp = $transaction->payment_method === 'cash'
        ? 'Silakan lakukan pembayaran tunai kepada kasir.'
        : ($isVirtualAccount
            ? 'Transfer sesuai total ke nomor Virtual Account di bawah ini.'
            : 'Selesaikan pembayaran '.$digitalLabel.' pada jendela Midtrans.');
@endphp

@if(($isPending || $isReplacing) && $transaction->payment_method === 'midtrans' && $transaction->midtrans_snap_token)
    @push('head')
        <script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
    @endpush
@endif

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-8">
        <div class="rounded-3xl border border-stone-200 bg-white p-7 text-center shadow-lg">
            <p class="text-sm text-stone-500">Nomor pesanan</p>
            <h1 class="mt-1 text-3xl font-black tracking-wide">{{ $transaction->order_number }}</h1>

            <div id="status-box" class="mt-6 rounded-2xl {{ filled($transaction->confirmed_at) || $transaction->payment_status === 'paid' ? 'bg-emerald-50' : ($isFailed ? 'bg-red-50' : 'bg-amber-50') }} p-5">
                <p id="status-title" class="text-xl font-bold text-amber-800">
                    @if(filled($transaction->confirmed_at))
                        Pesanan sedang diproses
                    @elseif($transaction->payment_status === 'paid')
                        Pembayaran berhasil
                    @elseif($isReplacing)
                        Sedang mengganti metode pembayaran
                    @elseif($isReplaced)
                        Metode pembayaran diganti
                    @elseif($isFailed && $isExplicitlyCancelled)
                        Pesanan dibatalkan
                    @elseif($isFailed)
                        Pesanan tidak berhasil
                    @else
                        Menunggu pembayaran
                    @endif
                </p>
                <p id="status-help" class="mt-2 text-sm text-stone-600">
                    @if(filled($transaction->confirmed_at))
                        Pesanan sudah diterima oleh Admin/Kasir.
                    @elseif($transaction->payment_status === 'paid')
                        Pembayaran diterima. Menunggu Admin/Kasir menerima pesanan.
                    @elseif($isReplacing)
                        Instruksi pembayaran lama sedang ditutup. Mohon tunggu.
                    @elseif($isReplaced)
                        Produk tetap disimpan. Lanjutkan ke checkout untuk memilih metode pembayaran baru.
                    @elseif($isFailed && $isExplicitlyCancelled)
                        Pesanan telah dibatalkan. Anda dapat membuat pesanan baru.
                    @elseif($isFailed)
                        Pilih metode pembayaran lain untuk mencoba pesanan yang sama kembali.
                    @else
                        {{ $pendingHelp }}
                    @endif
                </p>
            </div>

            <div class="mt-6 flex justify-between border-t border-stone-100 pt-5">
                <span>Total</span>
                <strong class="text-xl">Rp {{ number_format($transaction->total, 0, ',', '.') }}</strong>
            </div>

            @if($isVirtualAccount && ($isPending || $isReplacing))
                <section id="virtual-account-panel" class="mt-6 rounded-2xl border-2 border-amber-400 bg-amber-50 p-5 text-left {{ $isReplacing ? 'hidden' : '' }}">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-800">{{ $issuerBankName }} Virtual Account</p>
                    @if($isOtherBank)
                        <p class="mt-2 rounded-lg bg-white/80 p-3 text-sm leading-5 text-stone-700">
                            Anda memilih <strong>Bank Lainnya</strong>. Transfer dari aplikasi bank Anda melalui menu transfer antarbank ke <strong>{{ $issuerBankName }} Virtual Account</strong>.
                        </p>
                    @endif
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <strong class="break-all font-mono text-2xl tracking-wider text-stone-950">{{ $transaction->midtrans_va_number }}</strong>
                        <button id="copy-va-button" type="button" class="shrink-0 rounded-lg bg-stone-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-stone-700">Salin nomor VA</button>
                    </div>
                    <p class="mt-3 text-xs leading-5 text-stone-600">
                        @if($isOtherBank)
                            Pilih transfer antarbank dengan bank tujuan {{ $issuerBankName }}, masukkan nomor di atas, lalu bayar tepat sebesar total pesanan. Biaya transfer mengikuti ketentuan bank Anda.
                        @else
                            Buka mobile banking, pilih pembayaran Virtual Account sesuai bank, lalu masukkan nomor di atas. Bayar tepat sebesar total pesanan.
                        @endif
                    </p>
                    @if($transaction->expires_at)
                        <p class="mt-2 text-xs font-semibold text-red-600">Berlaku sampai {{ $transaction->expires_at->format('d/m/Y H:i') }} WIB</p>
                    @endif
                </section>
            @endif

            @if($transaction->payment_method === 'midtrans' && $transaction->midtrans_snap_token && ($isPending || $isReplacing))
                <button id="pay-button" type="button" class="mt-6 w-full rounded-xl bg-amber-500 px-4 py-3 font-bold text-white {{ $isReplacing ? 'hidden' : '' }}" {{ $isReplacing ? 'disabled' : '' }}>
                    Buka pembayaran {{ $digitalLabel }}
                </button>
            @endif

            @if($canChangePayment)
                <form id="change-payment-form" class="{{ $isReplacing ? 'hidden' : '' }}" method="POST" action="{{ route('kiosk.orders.change-payment', $transaction->order_number) }}" data-confirm="Instruksi pembayaran saat ini akan ditutup. Produk tetap disimpan agar Anda dapat memilih metode pembayaran lain." data-confirm-title="Ganti metode pembayaran" data-confirm-button="Ya, ganti metode">
                    @csrf
                    <button type="submit" class="mt-3 w-full rounded-xl border border-amber-400 px-4 py-3 font-bold text-amber-700 transition hover:bg-amber-500 hover:text-white" {{ $isReplacing ? 'disabled' : '' }}>
                        Pilih metode pembayaran lain
                    </button>
                </form>

                <form id="cancel-order-form" class="{{ $isReplacing ? 'hidden' : '' }}" method="POST" action="{{ route('kiosk.orders.cancel-order', $transaction->order_number) }}" data-confirm="Pesanan akan dibatalkan sepenuhnya dan stok akan dikembalikan. Lanjutkan?" data-confirm-title="Batalkan pesanan" data-confirm-button="Ya, batalkan">
                    @csrf
                    <button type="submit" class="mt-3 w-full rounded-xl border border-red-300 px-4 py-3 font-bold text-red-600 transition hover:bg-red-600 hover:text-white" {{ $isReplacing ? 'disabled' : '' }}>
                        Batalkan pesanan
                    </button>
                </form>

                @if($transaction->payment_method === 'midtrans' && $transaction->midtrans_snap_token)
                    <button id="snap-cancel-order" type="button" class="fixed bottom-6 right-6 z-[2147483647] hidden rounded-xl bg-red-600 px-5 py-3 font-bold text-white shadow-2xl ring-2 ring-white hover:bg-red-700">
                        Batalkan pesanan
                    </button>
                @endif
            @endif

            <a id="replacement-checkout" href="{{ route('kiosk.checkout.create') }}" class="mt-4 {{ $isReplaced ? 'block' : 'hidden' }} rounded-xl bg-amber-500 px-4 py-3 font-bold text-white">
                Kembali ke checkout
            </a>

            <a id="new-order" href="{{ route('kiosk.index') }}" class="mt-4 {{ $isExplicitlyCancelled ? 'block' : 'hidden' }} rounded-xl bg-stone-900 px-4 py-3 font-bold text-white">
                Pesanan baru
            </a>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const statusUrl = @json(route('kiosk.orders.status', $transaction->order_number));
        const snapToken = @json($transaction->midtrans_snap_token);
        const title = document.getElementById('status-title');
        const help = document.getElementById('status-help');
        const box = document.getElementById('status-box');
        const newOrder = document.getElementById('new-order');
        const replacementCheckout = document.getElementById('replacement-checkout');
        const payButton = document.getElementById('pay-button');
        const virtualAccountPanel = document.getElementById('virtual-account-panel');
        const copyVaButton = document.getElementById('copy-va-button');
        const pendingActionForms = document.querySelectorAll('#change-payment-form, #cancel-order-form');
        const changePaymentForm = document.getElementById('change-payment-form');
        const cancelOrderForm = document.getElementById('cancel-order-form');
        const snapCancelOrder = document.getElementById('snap-cancel-order');
        let lastStatusKey = @json($transaction->payment_status.'|'.$transaction->order_status.'|'.(filled($transaction->confirmed_at) ? '1' : '0'));

        async function refreshStatus() {
            try {
                const response = await fetch(statusUrl, {
                    headers: {'Accept': 'application/json'},
                    cache: 'no-store',
                });
                if (!response.ok) return;

                const data = await response.json();
                const statusKey = `${data.payment_status}|${data.order_status}|${data.confirmed ? '1' : '0'}`;

                if (statusKey !== lastStatusKey) {
                    if (data.confirmed) {
                        window.appToast?.('Pesanan telah diterima Admin/Kasir dan sedang diproses.', 'success');
                    } else if (data.payment_status === 'paid') {
                        window.appToast?.('Pembayaran berhasil diterima.', 'success');
                    } else if (data.payment_status === 'replacing') {
                        window.appToast?.('Sedang mengganti metode pembayaran.', 'info');
                    @if($isReplaced)
                    } else if (data.payment_status === 'replaced') {
                        window.appToast?.('Metode pembayaran berhasil diganti.', 'success');
                    @endif
                    } else if (['cancelled', 'failed', 'expired'].includes(data.payment_status)) {
                        window.appToast?.('Status pesanan telah diperbarui oleh Admin/Kasir.', 'error');
                    }

                    lastStatusKey = statusKey;
                }

                if (data.payment_status === 'replacing') {
                    title.textContent = 'Sedang mengganti metode pembayaran';
                    help.textContent = 'Instruksi pembayaran lama sedang ditutup. Mohon tunggu.';
                    box.className = 'mt-6 rounded-2xl bg-amber-50 p-5';
                    payButton?.classList.add('hidden');
                    if (payButton) payButton.disabled = true;
                    virtualAccountPanel?.classList.add('hidden');
                    pendingActionForms.forEach(form => {
                        form.classList.add('hidden');
                        form.querySelectorAll('button').forEach(button => button.disabled = true);
                    });
                    snapCancelOrder?.classList.add('hidden');
                }

                if (data.payment_status === 'pending') {
                    title.textContent = 'Menunggu pembayaran';
                    help.textContent = @json($pendingHelp);
                    box.className = 'mt-6 rounded-2xl bg-amber-50 p-5';
                    payButton?.classList.remove('hidden');
                    if (payButton) payButton.disabled = false;
                    virtualAccountPanel?.classList.remove('hidden');
                    pendingActionForms.forEach(form => {
                        form.classList.remove('hidden');
                        form.querySelectorAll('button').forEach(button => button.disabled = false);
                    });
                    replacementCheckout?.classList.add('hidden');
                    newOrder.classList.add('hidden');
                }

                @if($isReplaced)
                if (data.payment_status === 'replaced') {
                    title.textContent = 'Metode pembayaran diganti';
                    help.textContent = 'Produk tetap disimpan. Lanjutkan ke checkout untuk memilih metode pembayaran baru.';
                    box.className = 'mt-6 rounded-2xl bg-amber-50 p-5';
                    payButton?.classList.add('hidden');
                    if (payButton) payButton.disabled = true;
                    virtualAccountPanel?.classList.add('hidden');
                    pendingActionForms.forEach(form => form.classList.add('hidden'));
                    snapCancelOrder?.classList.add('hidden');
                    replacementCheckout?.classList.remove('hidden');
                    replacementCheckout?.classList.add('block');
                }
                @endif

                if (data.payment_status === 'paid' && !data.confirmed) {
                    title.textContent = 'Pembayaran berhasil';
                    help.textContent = 'Pembayaran diterima. Menunggu Admin/Kasir menerima pesanan.';
                    box.className = 'mt-6 rounded-2xl bg-emerald-50 p-5';
                    payButton?.classList.add('hidden');
                    if (payButton) payButton.disabled = true;
                    virtualAccountPanel?.classList.add('hidden');
                    pendingActionForms.forEach(form => form.classList.add('hidden'));
                    replacementCheckout?.classList.add('hidden');
                }

                if (data.confirmed) {
                    title.textContent = 'Pesanan sedang diproses';
                    help.textContent = 'Pesanan sudah diterima oleh Admin/Kasir.';
                    box.className = 'mt-6 rounded-2xl bg-emerald-50 p-5';
                    payButton?.classList.add('hidden');
                    if (payButton) payButton.disabled = true;
                    virtualAccountPanel?.classList.add('hidden');
                    pendingActionForms.forEach(form => form.classList.add('hidden'));
                    replacementCheckout?.classList.add('hidden');
                    newOrder.classList.remove('hidden');
                    newOrder.classList.add('block');
                }

                @if($isPending || $isReplacing || $isFailed)
                    if (['cancelled', 'failed', 'expired'].includes(data.payment_status)) {
                        title.textContent = 'Pesanan tidak berhasil';
                        help.textContent = data.explicitly_cancelled
                            ? 'Pesanan telah dibatalkan. Anda dapat membuat pesanan baru.'
                            : 'Pilih metode pembayaran lain untuk mencoba pesanan yang sama kembali.';
                        box.className = 'mt-6 rounded-2xl bg-red-50 p-5';
                        payButton?.classList.add('hidden');
                        virtualAccountPanel?.classList.add('hidden');
                        if (data.explicitly_cancelled) {
                            pendingActionForms.forEach(form => form.classList.add('hidden'));
                            newOrder.classList.remove('hidden');
                            newOrder.classList.add('block');
                        } else {
                            changePaymentForm?.classList.remove('hidden');
                            cancelOrderForm?.classList.remove('hidden');
                            pendingActionForms.forEach(form => form.querySelectorAll('button').forEach(button => button.disabled = false));
                            newOrder.classList.add('hidden');
                        }
                        replacementCheckout?.classList.add('hidden');
                    }
                @endif

                return data;
            } catch (error) {
                console.error(error);
            }
        }

        function openSnapPayment() {
            if (!snapToken || !window.snap) return;

            snapCancelOrder?.classList.remove('hidden');
            const finishSnap = () => {
                snapCancelOrder?.classList.add('hidden');
                refreshStatus();
            };

            window.snap.pay(snapToken, {
                onSuccess: finishSnap,
                onPending: finishSnap,
                onError: finishSnap,
                onClose: finishSnap,
            });
        }

        payButton?.addEventListener('click', openSnapPayment);
        changePaymentForm?.addEventListener('submit', event => {
            requestAnimationFrame(() => {
                if (event.defaultPrevented) return;

                title.textContent = 'Sedang mengganti metode pembayaran';
                help.textContent = 'Instruksi pembayaran lama sedang ditutup. Mohon tunggu.';
                box.className = 'mt-6 rounded-2xl bg-amber-50 p-5';
                payButton?.classList.add('hidden');
                if (payButton) payButton.disabled = true;
                virtualAccountPanel?.classList.add('hidden');
                snapCancelOrder?.classList.add('hidden');
                pendingActionForms.forEach(form => {
                    form.classList.add('hidden');
                    form.querySelectorAll('button').forEach(button => button.disabled = true);
                });
            });
        });
        copyVaButton?.addEventListener('click', async () => {
            const copied = await window.copyText(@json($transaction->midtrans_va_number));
            if (copied) {
                window.appToast('Nomor Virtual Account berhasil disalin.', 'success');
            } else {
                window.appShowCopyText(@json($transaction->midtrans_va_number), 'Salin nomor Virtual Account');
            }
        });
        snapCancelOrder?.addEventListener('click', () => {
            window.snap?.hide?.();
            snapCancelOrder.classList.add('hidden');
            cancelOrderForm?.requestSubmit();
        });
        setInterval(refreshStatus, 2000);
        refreshStatus();
    </script>
@endpush
