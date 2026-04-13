@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Konfirmasi Pembayaran</h4>
        <p class="text-muted small">Tinjau pesanan dan selesaikan proses pembayaran.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Ringkasan Pesanan</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="bg-light">
                            <tr class="extra-small text-uppercase text-muted">
                                <th class="ps-4">Produk</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end pe-4">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $item)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $item['name'] }}</div>
                                    <div class="text-muted extra-small">Rp {{ number_format($item['price'], 0, ',', '.') }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border rounded-pill px-3">{{ $item['quantity'] }}</span>
                                </td>
                                <td class="text-end pe-4 fw-bold text-primary">
                                    Rp {{ number_format($item['sub_total'], 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-light bg-opacity-50">
                            <tr>
                                <td colspan="2" class="ps-4 py-3 fw-bold text-dark fs-5">Total Bayar</td>
                                <td class="pe-4 py-3 text-end fw-bold text-primary fs-5">
                                    Rp {{ number_format($total, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
        <a href="{{ route('employee.SaleCreate') }}" class="btn btn-light rounded-pill px-4 shadow-none border">
            <i class="bi bi-arrow-left me-2"></i> Kembali Pilih Produk
        </a>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold">Detail Pembayaran</h6>
            </div>
            <div class="card-body p-3">
                <form action="{{ route('employee.paymentProcess') }}" method="POST">
                    @csrf
                    @foreach ($products as $item)
                        <input type="hidden" name="shop[]" value="{{ $item['product_id'] . ';' . $item['name'] . ';' . $item['price'] . ';' . $item['quantity'] . ';' . $item['sub_total'] }}">
                    @endforeach
                    <input type="hidden" name="total" value="{{ $total }}">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-1">Status Pelanggan</label>
                        <div class="d-flex gap-2">
                            <div class="flex-fill">
                                <input type="radio" class="btn-check" name="customer" id="non-member" value="Non-Member" checked onchange="memberDetect()">
                                <label class="btn btn-sm btn-outline-primary w-100 rounded-pill py-2" for="non-member">Non-Member</label>
                            </div>
                            <div class="flex-fill">
                                <input type="radio" class="btn-check" name="customer" id="is-member" value="Member" onchange="memberDetect()">
                                <label class="btn btn-sm btn-outline-primary w-100 rounded-pill py-2" for="is-member">Member</label>
                            </div>
                        </div>
                    </div>

                    <div id="member-wrap" class="d-none mb-3 animate__animated animate__fadeIn">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-1">Nomor Telepon Member</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 rounded-start-pill px-3">
                                    <i class="bi bi-phone text-muted"></i>
                                </span>
                                <input type="text" name="phone" id="phone" class="form-control border-start-0 rounded-end-pill py-2 shadow-none" placeholder="Contoh: 08123456789">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-1">Nama Member (Opsional)</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 rounded-start-pill px-3">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input type="text" name="member_name" id="member_name" class="form-control border-start-0 rounded-end-pill py-2 shadow-none" placeholder="Masukkan nama member">
                            </div>
                            <small class="text-muted extra-small">Isi jika ini member baru atau ingin mengubah nama.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase mb-1">Jumlah Uang Diterima</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-primary text-white border-0 rounded-start-pill px-3">Rp</span>
                            <input type="text" id="total_payment" name="total_payment" class="form-control py-2 fw-bold fs-5 border-primary rounded-end-pill shadow-none" required>
                        </div>
                        <div id="warningMessage" class="alert alert-danger d-none mt-2 py-2 small border-0">
                            <i class="bi bi-exclamation-circle me-1"></i> Jumlah bayar kurang.
                        </div>
                    </div>

                    <div class="bg-light rounded-3 p-2 mb-3 d-flex justify-content-between align-items-center">
                        <div class="text-muted extra-small fw-bold text-uppercase">Kembalian</div>
                        <div id="change-amount" class="fw-bold fs-5 text-dark">Rp 0</div>
                    </div>

                    <button type="submit" id="submitButton" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">
                        Proses Transaksi <i class="bi bi-check2-circle ms-2"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    function memberDetect() {
        const isMember = document.getElementById('is-member').checked;
        const phoneWrap = document.getElementById('member-wrap');
        if (isMember) {
            phoneWrap.classList.remove('d-none');
        } else {
            phoneWrap.classList.add('d-none');
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const total = {{ $total }};
        const paymentInput = document.getElementById("total_payment");
        const warning = document.getElementById("warningMessage");
        const submitButton = document.getElementById("submitButton");
        const changeDisplay = document.getElementById("change-amount");
        const phoneInput = document.getElementById("phone");
        const memberNameInput = document.getElementById("member_name");

        function formatRupiah(angka) {
            return angka.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateValidation() {
            const rawValue = paymentInput.value.replace(/[^0-9]/g, '');
            const bayar = parseInt(rawValue) || 0;
            
            if (bayar < total && rawValue !== '') {
                warning.classList.remove("d-none");
                submitButton.disabled = true;
                changeDisplay.innerText = "Rp 0";
                changeDisplay.classList.remove('text-success');
            } else {
                warning.classList.add("d-none");
                submitButton.disabled = bayar < total;
                const kembalian = Math.max(0, bayar - total);
                changeDisplay.innerText = "Rp " + formatRupiah(kembalian.toString());
                if (kembalian > 0) changeDisplay.classList.add('text-success');
                else changeDisplay.classList.remove('text-success');
            }
            
            if (rawValue !== '') {
                paymentInput.value = formatRupiah(bayar.toString());
            }
        }

        let debounceTimer;
        phoneInput.addEventListener("input", function() {
            clearTimeout(debounceTimer);
            const phone = this.value.trim();
            
            if (phone.length > 0) {
                debounceTimer = setTimeout(() => {
                    fetch(`/employee/customer/phone/${phone}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.data) {
                                memberNameInput.value = data.data.name;
                                memberNameInput.classList.add('border-success');
                            } else {
                                memberNameInput.value = '';
                                memberNameInput.classList.remove('border-success');
                            }
                        })
                        .catch(error => {
                            console.log('Customer tidak ditemukan');
                            memberNameInput.value = '';
                            memberNameInput.classList.remove('border-success');
                        });
                }, 500);
            } else {
                memberNameInput.value = '';
                memberNameInput.classList.remove('border-success');
            }
        });

        paymentInput.addEventListener("input", updateValidation);
        updateValidation();
    });
</script>
<style>
    .extra-small { font-size: 0.75rem; }
    .backdrop-blur { backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
</style>
@endpush
@endsection