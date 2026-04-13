@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold mb-1">Employee Dashboard</h4>
        <p class="text-muted">Selamat bertugas, {{ Auth::user()->name }}! Pantau performa penjualan hari ini.</p>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- Today's Sales -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                        <i class="bi bi-cart-check fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small fw-medium text-uppercase">Transaksi Hari Ini</p>
                        <h3 class="fw-bold mb-0">{{ \App\Models\Sale::whereDate('created_at', today())->count() }}</h3>
                    </div>
                </div>
            </div>
            <div class="bg-success bg-opacity-10 px-4 py-2">
                <a href="{{ route('employee.SaleIndex') }}" class="text-success text-decoration-none small fw-medium">
                    Lihat daftar <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Today's Revenue -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-4">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                        <i class="bi bi-currency-dollar fs-4"></i>
                    </div>
                    <div>
                        <p class="text-muted mb-0 small fw-medium text-uppercase">Pendapatan Hari Ini</p>
                        <h3 class="fw-bold mb-0">Rp {{ number_format(\App\Models\Sale::whereDate('created_at', today())->sum('total_price'), 0, ',', '.') }}</h3>
                    </div>
                </div>
            </div>
            <div class="bg-info bg-opacity-10 px-4 py-2 text-info small fw-medium">
                Update otomatis setiap transaksi
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5 text-center">
                <div class="mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="bi bi-plus-lg fs-1"></i>
                    </div>
                </div>
                <h4 class="fw-bold">Mulai Transaksi Baru</h4>
                <p class="text-muted mx-auto mb-4" style="max-width: 400px;">
                    Siap melayani pelanggan? Klik tombol di bawah untuk membuka halaman kasir dan mulai mencatat penjualan.
                </p>
                <a href="{{ route('employee.SaleCreate') }}" class="btn btn-primary btn-lg rounded-pill px-5">
                    <i class="bi bi-cart-plus me-2"></i> Buka Kasir
                </a>
            </div>
        </div>
    </div>
</div>
@endsection