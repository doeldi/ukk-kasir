<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Struk Penjualan</title>
  <style>
    body { font-family: 'Arial', sans-serif; font-size: 12px; background: #f8f9fa; }
    #receipt { background: #fff; padding: 25px; margin: 0 auto; width: 600px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); border-radius: 8px; }
    h2 { font-size: 1.5rem; margin: 0; text-align: center; color: #333; }
    small { font-size: 11px; color: #555; }
    .info { display: flex; justify-content: space-between; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 8px 10px; text-align: right; }
    th { background-color: #e9ecef; font-size: 11px; color: #333; border: 1px solid #dee2e6; }
    td { font-size: 11px; border: 1px solid #dee2e6; }
    .total-row td { font-weight: bold; background-color: #f1f3f5; }
    #legalcopy { text-align: center; margin-top: 30px; }
    .legal { font-size: 11px; color: #333; }
    .highlight { color: #007bff; font-weight: bold; }
    .logo { text-align: center; margin-bottom: 20px; }
    .store-info { text-align: center; font-size: 12px; margin-top: 15px; }
  </style>
</head>
<body>
    @php
        $imagePath = public_path('assets/images/store.jpeg');
        $src = '';
        if (file_exists($imagePath)) {
            $imageData = base64_encode(file_get_contents($imagePath));
            $src = 'data:' . mime_content_type($imagePath) . ';base64,' . $imageData;
        }
    @endphp
    <div id="receipt">
        <div class="logo">@if($src)<img src="{{ $src }}" width="100">@endif</div>
        <h2>Store</h2>
        <div class="store-info">
            <small>Telp: 081234098765<br>Jl. Raya Puncak</small>
        </div>
        <div class="info">
            <div>
                <small>Status: <span class="highlight">{{ $sale->customer ? 'Member' : 'Non-Member' }}</span><br>
                Poin: {{ $sale->customer ? $sale->customer->point : '-' }}</small>
            </div>
            <div>
                <small>Kasir: {{ $sale->user->name ?? $sale->user->email ?? '-' }}<br>
                Tanggal: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</small>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detail_sale as $item)
                <tr>
                    <td style="text-align: left;">{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rp {{ number_format($item->product->price, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row"><td colspan="3">Total</td><td>Rp {{ number_format($sale->total_price + $sale->used_point, 0, ',', '.') }}</td></tr>
                @if($sale->used_point > 0)<tr class="total-row"><td colspan="3">Poin Digunakan</td><td>Rp {{ number_format($sale->used_point, 0, ',', '.') }}</td></tr>@endif
                <tr class="total-row"><td colspan="3">Total Bayar</td><td>Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td colspan="3">Kembalian</td><td>Rp {{ number_format($sale->change, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
        <div id="legalcopy">
            <p class="legal">Invoice: #{{ $sale->id }}</p>
            <p class="legal">~ Terima kasih ~</p>
        </div>
    </div>
</body>
</html>