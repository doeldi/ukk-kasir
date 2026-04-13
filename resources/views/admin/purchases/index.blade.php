@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Riwayat Penjualan</h4>
        <p class="text-muted small">Total ada {{ $sales->count() }} transaksi berhasil dicatat.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('admin.Excel') }}" class="btn btn-success rounded-pill shadow-sm px-4">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.SaleHome') }}" class="sale-filter-form row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase mb-1 d-block">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm rounded-pill px-3 shadow-none" value="{{ $start_date }}">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold text-muted text-uppercase mb-1 d-block">Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control form-control-sm rounded-pill px-3 shadow-none" value="{{ $end_date }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 shadow-none">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                <a href="{{ route('admin.SaleHome') }}" class="btn btn-sm btn-light rounded-pill px-4 border shadow-none">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Tanggal & Waktu</th>
                        <th>ID Transaksi</th>
                        <th>Customer</th>
                        <th>Kasir</th>
                        <th>Total Harga</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales ?? [] as $index => $sale)
                    <tr>
                        <td class="ps-4 text-muted small">{{ $index + 1 }}</td>
                        <td>
                            <div class="fw-medium text-dark">{{ $sale->created_at->format('d M Y') }}</div>
                            <div class="text-muted extra-small">{{ $sale->created_at->format('H:i') }} WIB</div>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border rounded-pill px-3">
                                #TRX-{{ $sale->id }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div class="fw-bold text-dark">{{ $sale->customer->name ?? 'NON-MEMBER' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="small text-muted">
                                <i class="bi bi-person-badge me-1"></i> {{ $sale->user->name ?? $sale->user->email ?? '-' }}
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary">Rp {{ number_format($sale->total_price, 0, ',', '.') }}</div>
                            <div class="text-muted extra-small">{{ $sale->detail_sales->count() }} item terjual</div>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-light border rounded-start-pill px-3" data-bs-toggle="modal" data-bs-target="#seeModal-{{ $sale->id }}">
                                    <i class="bi bi-eye me-1"></i> Detail
                                </button>
                                <a href="{{ route('admin.exportPDFAd', $sale->id) }}" class="btn btn-sm btn-outline-primary rounded-end-pill px-3 shadow-none" target="_blank">
                                    <i class="bi bi-printer me-1"></i> Struk
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted small">Belum ada riwayat penjualan</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals Detail -->
@foreach ($sales as $sale)
<div class="modal fade" id="seeModal-{{ $sale->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 p-4">
                <h5 class="modal-title fw-bold text-dark">Detail Transaksi #TRX-{{ $sale->id }}</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="bg-light rounded-4 p-3 mb-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted extra-small text-uppercase fw-bold">Customer</div>
                            <div class="fw-bold text-dark">{{ $sale->customer->name ?? 'NON-MEMBER' }}</div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted extra-small text-uppercase fw-bold">Tanggal & Waktu</div>
                            <div class="fw-bold text-dark">{{ $sale->created_at->format('d M Y, H:i') }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted extra-small text-uppercase fw-bold">Petugas Kasir</div>
                            <div class="fw-medium text-dark">{{ $sale->user->name ?? $sale->user->email ?? '-' }}</div>
                        </div>
                        <div class="col-6 text-end">
                            @if($sale->customer)
                            <div class="text-muted extra-small text-uppercase fw-bold">Poin Member</div>
                            <div class="fw-medium text-primary">{{ $sale->customer->point }} Poin</div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr class="text-muted extra-small text-uppercase fw-bold">
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail_sales->where('sale_id', $sale->id) as $item)
                            <tr>
                                <td class="py-2">
                                    <div class="fw-medium small text-dark">{{ $item->product->name }}</div>
                                    <div class="text-muted extra-small">Rp {{ number_format($item->product->price, 0, ',', '.') }}</div>
                                </td>
                                <td class="text-center small">{{ $item->quantity }}</td>
                                <td class="text-end fw-bold small text-dark">Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="2" class="pt-3 text-muted small fw-medium">Subtotal</td>
                                <td class="pt-3 text-end small fw-bold text-dark">Rp {{ number_format($sale->total_price + ($sale->used_point ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            @if($sale->used_point > 0)
                            <tr>
                                <td colspan="2" class="text-danger small fw-medium">Potongan Poin</td>
                                <td class="text-end text-danger small fw-bold">- Rp {{ number_format($sale->used_point, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="2" class="text-primary fw-bold">Total Akhir</td>
                                <td class="text-end text-primary fw-bold fs-5">Rp {{ number_format($sale->total_price, 0, ',', '.') }}</td>
                            </tr>
                            <tr class="border-top">
                                <td colspan="2" class="pt-3 text-muted extra-small">DIBAYAR</td>
                                <td class="pt-3 text-end text-dark small fw-medium">Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="text-muted extra-small">KEMBALIAN</td>
                                <td class="text-end text-success small fw-bold">Rp {{ number_format($sale->change, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0">
                <button class="btn btn-light rounded-pill px-4 shadow-none border" data-bs-dismiss="modal">Tutup</button>
                <a href="{{ route('admin.exportPDFAd', $sale->id) }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    <i class="bi bi-printer me-1"></i> Cetak Struk
                </a>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection