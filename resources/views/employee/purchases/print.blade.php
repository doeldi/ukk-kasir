@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Invoice Transaksi</h4>
        <p class="text-muted small">Transaksi #TRX-{{ $sale->id }} berhasil diselesaikan.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <a href="{{ route('employee.ExportPDF', $sale->id) }}" class="btn btn-primary rounded-start-pill px-4 shadow-sm">
                <i class="bi bi-download me-2"></i> Unduh PDF
            </a>
            <a href="{{ route('employee.SaleIndex') }}" class="btn btn-outline-primary rounded-end-pill px-4">
                Daftar Penjualan <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <!-- Header Invoice -->
                <div class="bg-primary bg-opacity-10 p-4 border-bottom border-primary border-opacity-10">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3 mb-md-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-primary">STRUK PENJUALAN</h5>
                                    <div class="text-muted extra-small">ID: #TRX-{{ $sale->id }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d F Y') }}</div>
                            <div class="text-muted small">Waktu: {{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }} WIB</div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="row mb-3 g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Detail Pelanggan</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                @if ($sale->customer)
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                        <div class="fw-bold text-dark">{{ $sale->customer->name }}</div>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-telephone me-2"></i>{{ $sale->customer->phone }}
                                    </div>
                                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">Member Store</div>
                                @else
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person-x text-muted"></i>
                                        </div>
                                        <div class="fw-bold text-muted">NON-MEMBER</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Informasi Kasir</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                        <i class="bi bi-person-badge text-primary"></i>
                                    </div>
                                    <div class="fw-bold text-dark">{{ $sale->user->name ?? $sale->user->email ?? '-' }}</div>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-clock me-2"></i>Selesai pada {{ $sale->updated_at->format('H:i:s') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr class="extra-small text-uppercase text-muted">
                                    <th class="ps-3">Produk</th>
                                    <th class="text-center">Harga Satuan</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-3">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detail_sale as $data)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">{{ $data->product->name }}</div>
                                        </td>
                                        <td class="text-center text-muted">
                                            Rp {{ number_format($data->product->price, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border rounded-pill px-3">{{ $data->quantity }}</span>
                                        </td>
                                        <td class="text-end pe-3 fw-bold text-dark">
                                            Rp {{ number_format($data->sub_total, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="bg-light rounded-4 p-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Subtotal</span>
                                    <span class="fw-bold text-dark">Rp {{ number_format($sale->total_price + ($sale->used_point ?? 0), 0, ',', '.') }}</span>
                                </div>
                                @if($sale->used_point > 0)
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span class="small">Potongan Poin</span>
                                    <span class="fw-bold">- Rp {{ number_format($sale->used_point, 0, ',', '.') }}</span>
                                </div>
                                @endif
                                <hr class="my-3 opacity-10">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold text-primary">TOTAL AKHIR</span>
                                    <span class="fw-bold text-primary fs-4">Rp {{ number_format($sale->total_price, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted extra-small">DIBAYAR</span>
                                    <span class="fw-medium text-dark small">Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted extra-small">KEMBALIAN</span>
                                    <span class="fw-bold text-success small">Rp {{ number_format($sale->change, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-4 text-center border-top">
                    <p class="text-muted small mb-0">Terima kasih telah berbelanja di toko kami. Simpan struk ini sebagai bukti transaksi yang sah.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
</style>
@endsection