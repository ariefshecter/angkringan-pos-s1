<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $periodLabel }} {{ $rangeLabel }}</title>
    <style>
        @page { margin: 36px; }
        body { color: #292524; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 24px 0 8px; }
        p { margin: 3px 0; }
        .muted { color: #78716c; }
        .summary { border-collapse: collapse; margin-top: 18px; width: 100%; }
        .summary td { border: 1px solid #d6d3d1; padding: 10px; width: 33.33%; }
        .label { color: #78716c; font-size: 9px; text-transform: uppercase; }
        .value { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .details { border-collapse: collapse; width: 100%; }
        .details th, .details td { border-bottom: 1px solid #e7e5e4; padding: 6px; text-align: left; }
        .details th { background: #f5f5f4; font-size: 9px; text-transform: uppercase; }
        .right { text-align: right !important; }
    </style>
</head>
<body>
    <h1>ANGKRINGAN S1</h1>
    <p><strong>{{ $periodLabel }}</strong></p>
    <p class="muted">Periode {{ $rangeLabel }}</p>
    @if($isSnapshot)
        <p class="muted">Snapshot tersimpan{{ $recordedBy ? ' oleh '.$recordedBy : '' }}</p>
    @endif

    <table class="summary">
        <tr>
            <td><div class="label">Total pendapatan</div><div class="value">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</div></td>
            <td><div class="label">Transaksi lunas</div><div class="value">{{ number_format($summary['transactions'], 0, ',', '.') }}</div></td>
            <td><div class="label">Rata-rata transaksi</div><div class="value">Rp {{ number_format($summary['average'], 0, ',', '.') }}</div></td>
        </tr>
        @if(!$isSnapshot)
            <tr>
                <td><div class="label">Transaksi tunai</div><div class="value">{{ $summary['cash'] }}</div></td>
                <td><div class="label">Transaksi digital</div><div class="value">{{ $summary['digital'] }}</div></td>
                <td><div class="label">Dicetak</div><div class="value">{{ now()->format('d/m/Y H:i') }}</div></td>
            </tr>
        @endif
    </table>

    @if($chartRows->isNotEmpty())
        <h2>Rincian Periode</h2>
        <table class="details">
            <thead><tr><th>Periode</th><th class="right">Transaksi</th><th class="right">Pendapatan</th></tr></thead>
            <tbody>
                @foreach($chartRows as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="right">{{ $row['orders'] }}</td><td class="right">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
