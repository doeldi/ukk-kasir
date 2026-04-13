<!DOCTYPE html>
<html>
<head>
    <title>Struk Pembelian - {{ $sale->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>STORE</h2>
        <p>Struk Pembelian</p>
    </div>

    <div class="details">
        <p><strong>No. Transaksi:</strong> {{ $sale->id }}</p>
        <p><strong>Tanggal:</strong> {{ $sale->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>Customer:</strong> {{ $sale->customer->name ?? 'NON-MEMBER' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>Harga</th>
                <th>Qty</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
@foreach($sale->detail_sales as $detail)
            <tr>
                <td>{{ $detail->product->name }}</td>
                <td>Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                <td>{{ $detail->quantity }}</td>
                <td>Rp {{ number_format($detail->sub_total, 0, ',', '.') }}</td>
            </tr>
@endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="3">TOTAL</td>
                <td>Rp {{ number_format($sale->total_price, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 50px; text-align: center;">
        <p>Terima Kasih Telah Berbelanja di Store!</p>
    </div>
</body>
</html>