@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Daftar Penjualan</h4>
        <p class="text-muted small">Kelola dan pantau semua transaksi kasir.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('employee.SaleCreate') }}" class="btn btn-primary rounded-pill shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> Transaksi Baru
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('employee.SaleIndex') }}" class="sale-filter-form row g-3 align-items-end">
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
                <a href="{{ route('employee.SaleIndex') }}" class="btn btn-sm btn-light rounded-pill px-4 border shadow-none">Reset</a>
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
                        <th>Customer</th>
                        <th>Tanggal</th>
                        <th>Total Harga</th>
                        <th>Kasir</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sale as $data)
                    <tr>
                        <td class="ps-4 text-muted small">{{ $loop->iteration }}</td>
                        <td>
                            <div class="fw-bold text-dark">{{ $data->customer->name ?? 'NON-MEMBER' }}</div>
                            @if($data->customer)
                                <div class="badge bg-primary bg-opacity-10 text-primary extra-small rounded-pill">Member</div>
                            @endif
                        </td>
                        <td>
                            <div class="small text-dark">{{ \Carbon\Carbon::parse($data->sale_date)->format('d M Y') }}</div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary">Rp {{ number_format($data->total_price, 0, ',' , '.') }}</div>
                        </td>
                        <td>
                            <div class="small text-muted">{{ $data->user->name ?? $data->user->email ?? '-' }}</div>
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-light border rounded-start-pill px-3" data-bs-toggle="modal" data-bs-target="#seeModal-{{ $data->id }}">
                                    Detail
                                </button>
                                <a class="btn btn-sm btn-outline-primary rounded-end-pill px-3" href="{{ route('employee.ExportPDF', $data->id) }}">
                                    Struk
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">Tidak ada data transaksi ditemukan</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->
@foreach ($sale as $sales)
<div class="modal fade" id="seeModal-{{ $sales->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 p-4">
                <h5 class="modal-title fw-bold">Detail Transaksi #{{ $sales->id }}</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0">
                <div class="bg-light rounded-4 p-3 mb-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="text-muted extra-small text-uppercase fw-bold">Customer</div>
                            <div class="fw-bold">{{ $sales->customer->name ?? 'NON-MEMBER' }}</div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted extra-small text-uppercase fw-bold">Tanggal</div>
                            <div class="fw-bold">{{ \Carbon\Carbon::parse($sales->sale_date)->format('d M Y') }}</div>
                        </div>
                        @if($sales->customer)
                        <div class="col-6">
                            <div class="text-muted extra-small text-uppercase fw-bold">No. Telepon</div>
                            <div class="fw-medium">{{ $sales->customer->phone }}</div>
                        </div>
                        <div class="col-6 text-end">
                            <div class="text-muted extra-small text-uppercase fw-bold">Poin Member</div>
                            <div class="fw-medium text-primary">{{ $sales->customer->point }} Poin</div>
                        </div>
                        @endif
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
                            @foreach ($detail_sale->where('sale_id', $sales->id) as $item)
                            <tr>
                                <td class="py-2">
                                    <div class="fw-medium small">{{ $item->product->name }}</div>
                                    <div class="text-muted extra-small">Rp {{ number_format($item->product->price, 0, ',', '.') }}</div>
                                </td>
                                <td class="text-center small">{{ $item->quantity }}</td>
                                <td class="text-end fw-bold small">Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="2" class="pt-3 text-muted small fw-medium">Subtotal</td>
                                <td class="pt-3 text-end small fw-bold">Rp {{ number_format($sales->total_price + ($sales->used_point ?? 0), 0, ',', '.') }}</td>
                            </tr>
                            @if($sales->used_point > 0)
                            <tr>
                                <td colspan="2" class="text-danger small fw-medium">Potongan Poin</td>
                                <td class="text-end text-danger small fw-bold">- Rp {{ number_format($sales->used_point, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="2" class="text-primary fw-bold">Total Akhir</td>
                                <td class="text-end text-primary fw-bold fs-5">Rp {{ number_format($sales->total_price, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-top-0 p-4 pt-0">
                <button class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                <a href="{{ route('employee.ExportPDF', $sales->id) }}" class="btn btn-primary rounded-pill px-4">Cetak Struk</a>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection