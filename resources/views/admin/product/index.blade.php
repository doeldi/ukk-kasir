@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Kelola Produk</h4>
        <p class="text-muted small">Total ada {{ $products->count() }} produk tersedia.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('admin.ProductCreate') }}" class="btn btn-primary rounded-pill shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Tambah Produk
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Gambar</th>
                        <th>Nama Produk</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products ?? [] as $index => $product)
                    <tr>
                        <td class="ps-4 text-muted small">{{ $index + 1 }}</td>
                        <td>
                            @if($product->image)
                                <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" 
                                     class="rounded-3 shadow-sm" style="width: 48px; height: 48px; object-fit: cover;">
                            @else
                                <div class="bg-light text-muted d-flex align-items-center justify-content-center rounded-3 shadow-sm" 
                                     style="width: 48px; height: 48px;">
                                    <i class="bi bi-image small"></i>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $product->name }}</div>
                            <div class="text-muted extra-small">ID: #PROD-{{ $product->id }}</div>
                        </td>
                        <td class="fw-medium text-primary">Rp {{ number_format($product->price, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge {{ $product->stock < 10 ? 'bg-danger bg-opacity-10 text-danger' : 'bg-success bg-opacity-10 text-success' }} rounded-pill px-3 fw-bold">
                                {{ $product->stock }}
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <!-- Update Stok Modal Button -->
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-circle d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px;" 
                                        data-bs-toggle="modal" data-bs-target="#stockModal-{{ $product->id }}" title="Update Stok">
                                    <i class="bi bi-box-seam"></i>
                                </button>

                                <!-- Edit -->
                                <a href="{{ route('admin.ProductEdit', $product->id) }}" class="btn btn-sm btn-outline-warning rounded-circle d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px;" title="Edit Produk">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <!-- Delete -->
                                <form method="POST" action="{{ route('admin.ProductDelete', $product->id) }}"
                                      onsubmit="return confirm('Yakin hapus produk ini?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px;" title="Hapus Produk">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted small">Belum ada produk terdaftar</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals Update Stok -->
@foreach ($products as $product)
<div class="modal fade" id="stockModal-{{ $product->id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 p-4 pb-0">
                <h6 class="modal-title fw-bold text-dark">Update Stok Produk</h6>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Produk</div>
                    <div class="fw-bold text-dark">{{ $product->name }}</div>
                </div>
                
                <form method="POST" action="{{ route('admin.ProductStock', $product->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block text-center">Jumlah Stok Baru</label>
                        <div class="input-group input-group-lg">
                            <input type="number" name="stock" value="{{ $product->stock }}" 
                                   class="form-control text-center fw-bold border-primary rounded-pill shadow-none" 
                                   placeholder="0" required autofocus>
                        </div>
                        <div class="text-center mt-2 extra-small text-muted italic">Stok saat ini: {{ $product->stock }}</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">
                        Simpan Perubahan <i class="bi bi-check2-circle ms-1"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

<style>
    .extra-small { font-size: 0.75rem; }
</style>
@endsection