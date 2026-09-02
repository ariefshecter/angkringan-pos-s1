<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $transaction->order_number }}</title>
    <style>
        @page { margin: 18px; }
        body { color: #111; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 16px; margin: 0; text-align: center; }
        p { margin: 2px 0; }
        .center { text-align: center; }
        .line { border-top: 1px dashed #222; margin: 9px 0; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 3px 0; vertical-align: top; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .total { font-size: 12px; }
    </style>
</head>
<body>
    <h1>ANGKRINGAN S1</h1>
    <p class="center">Struk Pembayaran</p>
    <div class="line"></div>
    <p>{{ $transaction->order_number }}</p>
    <p>{{ $transaction->created_at->format('d/m/Y H:i') }}</p>
    <p>Pelanggan: {{ $transaction->customer_name }}</p>
    <div class="line"></div>
    <table>
        @foreach($transaction->items as $item)
            <tr><td colspan="2" class="bold">{{ $item->menu_name }}</td></tr>
            <tr><td>{{ $item->quantity }} x Rp {{ number_format($item->price, 0, ',', '.') }}</td><td class="right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td></tr>
        @endforeach
    </table>
    <div class="line"></div>
    <table><tr class="bold total"><td>TOTAL</td><td class="right">Rp {{ number_format($transaction->total, 0, ',', '.') }}</td></tr></table>
    <p>Pembayaran: {{ $transaction->payment_label }}</p>
    <p>Status: Lunas</p>
    <div class="line"></div>
    <p class="center">Terima kasih</p>
</body>
</html>
