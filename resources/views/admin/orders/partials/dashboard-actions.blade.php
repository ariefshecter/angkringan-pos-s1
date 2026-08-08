<div class="flex flex-col items-stretch gap-2">
    @if($transaction->payment_method === 'cash' && $transaction->payment_status === 'pending')
        <form method="POST" action="{{ route('admin.orders.confirm-cash', $transaction) }}">
            @csrf
            <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">
                Konfirmasi & Cetak
            </button>
        </form>
        <form method="POST" action="{{ route('admin.orders.cancel', $transaction) }}" data-confirm="Pesanan akan dibatalkan dan stok yang direservasi akan dilepas. Lanjutkan?" data-confirm-title="Batalkan pesanan" data-confirm-button="Ya, batalkan">
            @csrf
            <button class="w-full rounded-lg border border-red-300 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-600 hover:text-white">
                Batalkan
            </button>
        </form>
    @elseif($transaction->payment_method === 'midtrans' && $transaction->payment_status === 'paid' && ! $transaction->confirmed_at)
        <form method="POST" action="{{ route('admin.orders.accept-digital', $transaction) }}">
            @csrf
            <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-emerald-700">
                Terima & Cetak
            </button>
        </form>
    @elseif($transaction->payment_status === 'paid')
        <a href="{{ route('admin.orders.receipt', $transaction) }}" class="rounded-lg bg-stone-900 px-3 py-2 text-center text-xs font-bold text-white transition hover:bg-stone-700">
            Cetak Struk
        </a>
    @else
        <a href="{{ route('admin.orders.show', $transaction) }}" class="rounded-lg border border-stone-400 px-3 py-2 text-center text-xs font-semibold text-stone-700 transition hover:bg-stone-900 hover:text-white">
            Cek Status
        </a>
    @endif
</div>
