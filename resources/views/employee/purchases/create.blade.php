@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Tambah Penjualan</h4>
        <p class="text-muted small">Pilih produk dan tentukan jumlahnya untuk memulai transaksi.</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        @if (session('failed'))
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>{{ session('failed') }}</div>
            </div>
        @endif

        <div class="row g-4">
            @foreach ($product as $data)
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card h-100 border-0 shadow-sm hover-shadow-lg transition-all">
                        <p hidden class="product_id">{{ $data['id'] }}</p>
                        <div class="position-relative">
                            @if ($data['image'])
                                <img src="{{ asset('storage/' . $data['image']) }}"
                                    class="card-img-top p-3 rounded-5" alt="{{ $data['name'] }}" 
                                    style="height: 180px; object-fit: cover;">
                            @else
                                <div class="bg-light d-flex align-items-center justify-content-center rounded-top" style="height: 180px;">
                                    <i class="bi bi-image text-muted fs-1"></i>
                                </div>
                            @endif
                            <div class="position-absolute top-0 end-0 m-3">
                                <span class="badge {{ $data['stock'] < 10 ? 'bg-danger' : 'bg-success' }} rounded-pill px-3 shadow-sm">
                                    Stok: <span class="product_stock">{{ $data['stock'] }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-4 pt-0 text-center">
                            <h6 class="card-title fw-bold text-dark mb-1">{{ $data['name'] }}</h6>
                            <p class="text-primary fw-bold mb-3 product_price">
                                Rp {{ number_format($data->price, 0, ',', '.') }}
                            </p>

                            <div class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 rounded-pill p-1 mb-3 mx-auto" style="max-width: 140px;">
                                <button type="button" class="btn btn-white bg-white rounded-circle shadow-sm p-0 d-flex align-items-center justify-content-center product_min" style="width: 32px; height: 32px; border: none;">
                                    <i class="bi bi-dash text-primary"></i>
                                </button>
                                <span class="mx-3 fw-bold text-primary product_sum">0</span>
                                <button type="button" class="btn btn-white bg-white rounded-circle shadow-sm p-0 d-flex align-items-center justify-content-center product_plus" style="width: 32px; height: 32px; border: none;">
                                    <i class="bi bi-plus text-primary"></i>
                                </button>
                            </div>
                            
                            <div class="text-muted extra-small">
                                Subtotal: <span class="fw-bold text-dark sub_total">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <form action="{{ route('employee.SaleStore') }}" method="POST">
        @csrf
        <div id="hidden-inputs"></div>
        <div class="fixed-bottom bg-white border-top p-3 shadow-lg d-flex justify-content-center align-items-center" style="z-index: 1030;">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="d-none d-md-block">
                    <span class="text-muted small">Total Terpilih:</span>
                    <span id="total-items" class="fw-bold ms-1">0</span> Produk
                </div>
                <button class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm">
                    Lanjut Ke Pembayaran <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </form>
</div>

<div style="height: 100px;"></div> <!-- Spacer for fixed-bottom -->

@push('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(".product_plus, .product_min").click(function() {
            var card = $(this).closest(".card");
            var quantityElement = card.find(".product_sum");
            var stock = parseInt(card.find(".product_stock").text().trim());
            var price = parseFloat(card.find(".product_price").text().replace(/[^\d]/g, ''));
            var quantity = parseInt(quantityElement.text());
            var productId = card.find(".product_id").text().trim();
            var productName = card.find(".card-title").text().trim();

            if ($(this).hasClass("product_plus")) {
                if (quantity < stock) {
                    quantity++;
                } else {
                    alert("Stok tidak mencukupi!");
                    return;
                }
            } else if ($(this).hasClass("product_min") && quantity > 0) {
                quantity--;
            }

            quantityElement.text(quantity);
            var subtotal = quantity * price;
            card.find(".sub_total").text("Rp " + subtotal.toLocaleString('id-ID'));

            updateHiddenInputs(productId, productName, price, quantity, subtotal);
            updateTotalItems();
        });

        function updateHiddenInputs(productId, productName, price, quantity, totalPrice) {
            var hiddenInputsContainer = $("#hidden-inputs");
            var existingInput = hiddenInputsContainer.find("input[data-id='" + productId + "']");
            var inputValue = productId + ";" + productName + ";" + price + ";" + quantity + ";" + totalPrice;

            if (existingInput.length > 0) {
                if (quantity > 0) {
                    existingInput.val(inputValue);
                } else {
                    existingInput.remove();
                }
            } else if (quantity > 0) {
                hiddenInputsContainer.append('<input type="hidden" name="products[]" data-id="' + productId + '" value="' +
                    inputValue + '">');
            }
        }

        function updateTotalItems() {
            var total = 0;
            $(".product_sum").each(function() {
                total += parseInt($(this).text());
            });
            $("#total-items").text(total);
        }
    </script>
    <style>
        .hover-shadow-lg:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 3rem rgba(0,0,0,.175)!important;
        }
        .transition-all {
            transition: all 0.3s ease;
        }
        .extra-small {
            font-size: 0.75rem;
        }
    </style>
@endpush
@endsection