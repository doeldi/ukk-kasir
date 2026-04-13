@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Daftar Produk</h4>
        <p class="text-muted small">Lihat informasi produk dan ketersediaan stok.</p>
    </div>
</div>

<div class="row g-4">
    @forelse($products ?? [] as $product)
    <div class="col-lg-3 col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm hover-shadow transition-all">
            <div class="position-relative">
                @if($product->image)
                    <img src="{{ asset('storage/' . $product->image) }}" class="card-img-top p-3 rounded-5" alt="{{ $product->name }}" style="height: 180px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center rounded-top" style="height: 180px;">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                @endif
                <div class="position-absolute top-0 end-0 m-3">
                    <span class="badge {{ $product->stock < 10 ? 'bg-danger' : 'bg-success' }} rounded-pill px-3 shadow-sm">
                        Stok: {{ $product->stock }}
                    </span>
                </div>
            </div>
            <div class="card-body p-4 pt-0">
                <h6 class="card-title fw-bold text-dark mb-1">{{ $product->name }}</h6>
                <div class="text-muted extra-small mb-3">ID: #PROD-{{ $product->id }}</div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-primary fw-bold">
                        Rp {{ number_format($product->price, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card border-0 shadow-sm py-5 text-center">
            <div class="card-body">
                <i class="bi bi-box-seam fs-1 text-muted opacity-25 mb-3 d-block"></i>
                <h5 class="fw-bold text-muted">Belum ada produk tersedia</h5>
                <p class="text-muted small">Hubungi admin untuk menambahkan produk baru.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

<style>
    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.1) !important;
    }
    .transition-all {
        transition: all 0.3s ease;
    }
    .extra-small {
        font-size: 0.75rem;
    }
</style>
@endsection