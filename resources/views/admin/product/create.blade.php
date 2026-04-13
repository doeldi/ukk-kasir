@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Tambah Produk</h4>
        <p class="text-muted small">Masukkan detail produk baru untuk ditambahkan ke inventaris.</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold">Form Tambah Produk</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.ProductStore') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nama Produk</label>
                            <input type="text" class="form-control rounded-pill px-3 shadow-none" name="name" placeholder="Masukkan nama produk" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Harga Jual</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 rounded-start-pill px-3 text-muted">Rp</span>
                                <input type="number" class="form-control border-0 bg-light rounded-end-pill px-3 shadow-none" name="price" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Stok Awal</label>
                            <input type="number" class="form-control rounded-pill px-3 shadow-none" name="stock" placeholder="0" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Gambar Produk</label>
                            <div class="bg-light rounded-4 p-4 text-center border-2 border-dashed border-muted position-relative overflow-hidden" id="drop-area">
                                <div id="preview-container" class="d-none mb-3">
                                    <img id="image-preview" src="#" alt="Preview" class="img-fluid rounded-3 shadow-sm mx-auto" style="max-height: 200px; object-fit: cover;">
                                    <div id="file-name" class="mt-2 fw-bold text-primary small"></div>
                                </div>
                                <div id="upload-placeholder">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
                                    <div class="mt-2 text-muted small">Pilih file gambar (JPG, PNG)</div>
                                </div>
                                <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" name="image" id="image-input" accept="image/*" style="cursor: pointer;">
                            </div>
                        </div>
                        <div class="col-12 mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                                Simpan Produk <i class="bi bi-check-lg ms-2"></i>
                            </button>
                            <a href="{{ route('admin.ProductHome') }}" class="btn btn-light rounded-pill px-4 py-2 text-muted small border">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    const imageInput = document.getElementById('image-input');
    const previewContainer = document.getElementById('preview-container');
    const imagePreview = document.getElementById('image-preview');
    const fileName = document.getElementById('file-name');
    const uploadPlaceholder = document.getElementById('upload-placeholder');

    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            
            fileName.textContent = file.name;
            previewContainer.classList.remove('d-none');
            uploadPlaceholder.classList.add('d-none');
            
            reader.onload = function(e) {
                imagePreview.setAttribute('src', e.target.result);
            }
            
            reader.readAsDataURL(file);
        } else {
            previewContainer.classList.add('d-none');
            uploadPlaceholder.classList.remove('d-none');
        }
    });
</script>
@endpush

<style>
    .border-dashed { border-style: dashed !important; }
</style>
@endsection