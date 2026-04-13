@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Informasi Member</h4>
        <p class="text-muted small">Kelola penggunaan poin untuk transaksi ini.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                        <i class="bi bi-person-check fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Detail Member</h6>
                        <div class="extra-small opacity-75">Penjualan #{{ $sale->id }}</div>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('employee.Member', $sale->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                    <input type="hidden" name="customer_id" value="{{ $sale->customer_id }}">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Nama Member</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 px-3 rounded-start-pill">
                                <i class="bi bi-person text-muted"></i>
                            </span>
                            <input type="text" class="form-control bg-light border-0 py-2 rounded-end-pill fw-bold shadow-none" name="name" value="{{ $sale->customer->name ?? '' }}" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Poin Member Saat Ini</label>
                        <div class="bg-primary bg-opacity-10 rounded-4 p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-star-fill text-primary me-2"></i>
                                <span class="fw-bold text-primary">Saldo Poin</span>
                            </div>
                            <div class="fw-bold fs-4 text-primary">{{ number_format($sale->customer->point, 0, ',', '.') }}</div>
                        </div>
                    </div>

                    @if($sale->customer->point > 0)
                    <div class="mb-4">
                        <div class="form-check form-switch p-0 d-flex align-items-center justify-content-between">
                            <label class="form-check-label fw-bold text-dark" for="usePoint">
                                Gunakan Poin untuk Diskon?
                                <div class="text-muted extra-small fw-normal">1 Poin = Rp 1 (Sesuai kebijakan toko)</div>
                            </label>
                            <input class="form-check-input ms-0 shadow-none" type="checkbox" name="check_point" value="Ya" id="usePoint" role="switch" style="width: 3em; height: 1.5em;">
                        </div>
                    </div>
                    @endif

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill py-3 fw-bold shadow-sm">
                            Selesaikan Transaksi <i class="bi bi-check-lg ms-2"></i>
                        </button>
                        <a href="{{ route('employee.SaleIndex') }}" class="btn btn-light rounded-pill py-2 text-muted small border-0">Batalkan</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
</style>
@endsection