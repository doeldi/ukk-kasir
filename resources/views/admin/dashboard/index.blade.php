@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold mb-1">Admin Dashboard</h4>
        <p class="text-muted">Selamat bertugas, {{ Auth::user()->name }}! Pantau performa penjualan hari ini.</p>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- total produk -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small fw-medium text-uppercase">Total Produk</p>
                        <h3 class="fw-bold mb-0">{{ $products->count() }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- total penjualan -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="bi bi-cart3 fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small fw-medium text-uppercase">Total Penjualan</p>
                        <h3 class="fw-bold mb-0"> {{ $sales->count() }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection