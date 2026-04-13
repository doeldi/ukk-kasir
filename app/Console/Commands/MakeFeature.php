<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeFeature extends Command
{
    protected $signature = 'make:feature {name? : Nama fitur (singular) - kosongkan untuk generate app base} {--role=admin : admin atau employee} {--type=feature : feature atau app} {--fields= : Kolom database (format: name:string,price:integer)} {--soft-deletes : Tambahkan support soft deletes} {--reset : Reset project ke default (Hapus semua yang pernah di-generate)}';
    protected $description = 'Generate fitur lengkap atau APP BASE (Kasir UKK) otomatis';

    protected $help = 'Gunakan --type=app untuk membangun pondasi project UKK Kasir (Landing, Login, Dashboard, User, Produk, Pelanggan, Transaksi).';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        if ($this->option('reset')) {
            $this->resetProject();
            return;
        }

        $name = trim($this->argument('name') ?? '');
        $role = $this->option('role');
        $type = $this->option('type');
        $fieldsInput = $this->option('fields');
        $useSoftDeletes = $this->option('soft-deletes');

        if ($type === 'app') {
            if (empty($name)) {
                $this->generateAppBase();
                return;
            } else {
                $this->error('Untuk type=app, name harus kosong!');
                return;
            }
        }

        if (empty($name)) {
            $this->error('Name fitur harus diisi untuk type=feature!');
            return;
        }

        if (!in_array($role, ['admin', 'employee'])) {
            $this->error('Role harus admin atau employee!');
            return;
        }

        $fields = $this->parseFields($fieldsInput);

        $singular = Str::singular($name);
        $plural = Str::plural($name);
        $modelName = Str::studly($singular);
        $tableName = Str::snake($plural);
        $viewFolder = Str::kebab($singular);
        $singularLower = lcfirst($singular);

        $this->info("Generating {$role} feature: {$modelName}");

        // 1. Migration
        $this->generateMigration($tableName, $fields, $useSoftDeletes);
        $this->info('✓ Migration created with fields');

        // 2. Model
        $this->call('make:model', [
            'name' => $modelName,
        ]);
        $this->info('✓ Model created');

        // 3. Controller
        $this->call('make:controller', [
            'name' => "{$modelName}Controller",
        ]);
        $this->info('✓ Controller created');

        // 4. Views folder
        $viewPath = resource_path("views/{$role}/{$viewFolder}");
        if (!$this->files->exists($viewPath)) {
            $this->files->makeDirectory($viewPath, 0755, true);
        }

        // 5. Create views
        if ($role === 'admin') {
            $this->createIndexView($viewPath, $modelName, $singularLower, $plural, $fields);
            $this->createCreateView($viewPath, $modelName, $singular, $role, $fields);
            $this->createEditView($viewPath, $modelName, $singular, $fields);
        } else {
            // Employee: index dengan filter note, create
            $this->createEmployeeIndexView($viewPath, $modelName, $singularLower, $plural, $fields);
            $this->createCreateView($viewPath, $modelName, $singular, $role, $fields);
            $this->createPaymentView($viewPath, $modelName, $singularLower);
            $this->createMemberView($viewPath, $modelName, $singularLower);
            $this->createPrintView($viewPath, $modelName, $singularLower);
            $this->createExportPdfView($viewPath, $modelName, $singularLower);
        }
        $this->info('✓ Views created');

        // 6. Update Model
        $this->updateModel($modelName, $fields, $useSoftDeletes);
        $this->info('✓ Model fillable updated');

        // 7. Update Controller
        $this->updateController($modelName, $viewFolder, $singularLower, $plural, $role, $fields);
        $this->info('✓ Controller methods updated');

        // 8. Update Routes
        $this->updateRoutes($modelName, $viewFolder, $singular, $role);
        $this->info('✓ Routes added to web.php');

        // 9. Update Sidebar
        $this->updateSidebar($modelName, $role, $viewFolder);
        $this->info('✓ Sidebar updated');

        $this->info("\nFeature {$modelName} ({$role}) berhasil dibuat!");
        $this->line('Langkah selanjutnya:');
        $this->line('1. Cek migration di: database/migrations/');
        $this->line("2. Cek model di: app/Models/{$modelName}.php");
        $this->line("3. Cek controller di: app/Http/Controllers/{$modelName}Controller.php");
        $this->line("4. Cek views di: resources/views/{$role}/{$viewFolder}/");
        $this->line('5. Jalankan: php artisan migrate');
    }

    protected function parseFields($input): array
    {
        if (empty($input)) {
            return [['name' => 'name', 'type' => 'string']];
        }

        $fields = [];
        foreach (explode(',', $input) as $field) {
            $parts = explode(':', $field);
            $fields[] = [
                'name' => trim($parts[0]),
                'type' => trim($parts[1] ?? 'string'),
            ];
        }
        return $fields;
    }

    protected function generateMigration(string $tableName, array $fields, bool $softDeletes): void
    {
        $this->call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);

        // Find the newly created migration file
        $migrationFiles = $this->files->glob(database_path('migrations/*.php'));
        $migrationPath = end($migrationFiles);

        $content = $this->files->get($migrationPath);
        
        $fieldsContent = "";
         foreach ($fields as $field) {
             $nullable = (isset($field['nullable']) && $field['nullable']) ? '->nullable()' : '';
             $fieldsContent .= "            \$table->{$field['type']}('{$field['name']}'){$nullable};\n";
         }
        
        if ($softDeletes) {
            $fieldsContent .= "            \$table->softDeletes();\n";
        }

        $content = str_replace(
            "\$table->id();\n            \$table->timestamps();",
            "\$table->id();\n{$fieldsContent}            \$table->timestamps();",
            $content
        );

        $this->files->put($migrationPath, $content);
    }


    protected function createIndexView(string $path, string $modelName, string $singular, string $plural, array $fields): void
    {
        $headers = "";
        $columns = "";
        foreach ($fields as $field) {
            $label = ucfirst($field['name']);
            $headers .= "                    <th>{$label}</th>\n";
            $columns .= "                    <td>{{ \${$singular}->{$field['name']} }}</td>\n";
        }

        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-6">
                <h2>Daftar __MODEL__</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="{{ route('admin.__MODEL__Create') }}" class="btn btn-primary">Tambah __MODEL__</a>
            </div>
        </div>

@if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
@endif

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
__HEADERS__                    <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
@forelse($__PLURAL__ as $__SINGULAR__)
                    <tr>
                        <td>{{ $__SINGULAR__->id }}</td>
__COLUMNS__                    <td>
                            <a href="{{ route('admin.__MODEL__Edit', $__SINGULAR__->id) }}" class="btn btn-sm btn-warning">Edit</a>
                            <form action="{{ route('admin.__MODEL__Delete', $__SINGULAR__->id) }}" method="POST" style="display:inline;">
@csrf
@method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
@empty
                    <tr>
                        <td colspan="__COL_COUNT__" class="text-center">Tidak ada data</td>
                    </tr>
@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $__PLURAL__->links() }}
        </div>
    </div>
</div>
@endsection
PHP;

        $content = str_replace(
            ['__MODEL__', '__SINGULAR__', '__PLURAL__', '__HEADERS__', '__COLUMNS__', '__COL_COUNT__'],
            [$modelName, $singular, $plural, $headers, $columns, count($fields) + 2],
            $content
        );
        $this->files->put("{$path}/index.blade.php", $content);
    }

    protected function createPaymentView(string $path, string $modelName, string $singular): void
    {
        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="page-breadcrumb">
        <div class="row align-items-center">
            <div class="col-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 d-flex align-items-center">
                        <li class="breadcrumb-item">
                            <a href="{{ route('employee.dashboard') }}" class="link">
                                <i class="mdi mdi-home-outline fs-4"></i>
                            </a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('employee.__MODEL__Index') }}" class="link">__MODEL__</a>
                        </li>
                        <li class="breadcrumb-item active text-dark" aria-current="page">Pembayaran</li>
                    </ol>
                </nav>
                <h1 class="mb-0 fw-bold">Pembayaran</h1>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body mt-3">
                        <form action="{{ route('employee.__MODEL__PaymentProcess') }}" method="POST" class="row g-3">
@csrf
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <h4 class="fw-bold mb-4">Produk yang dipilih</h4>
@foreach($products as $item)
                                        <div class="d-flex justify-content-between align-items-start mt-2">
                                            <div>
                                                <div class="fw-medium">{{ $item['name'] }}</div>
                                                <div class="text-muted small">{{ number_format($item['price'], '0', ',' , '.') }} x {{ $item['quantity'] }}</div>
                                            </div>
                                            <div class="fw-bold">{{ number_format($item['sub_total'], '0', ',', '.') }}</div>
                                        </div>
                                        <input type="hidden" name="shop[]" value="{{ $item['product_id'] . ';' . $item['name'] . ';' . $item['price'] . ';' . $item['quantity'] . ';' . $item['sub_total'] }}">
@endforeach
                                    <div class="d-flex justify-content-between mt-4">
                                        <div class="fw-bold fs-5">Total</div>
                                        <div class="fw-bold fs-5">{{ number_format($total, '0', ',', '.') }}</div>
                                    </div>
                                    <input type="hidden" name="total" value="{{ $total }}">
                                </div>
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label class="form-label">Member Status</label>
                                        <select name="customer" id="customer" class="form-select" onchange="memberDetect()">
                                            <option value="Non-Member">Non-Member</option>
                                            <option value="Member">Member</option>
                                        </select>
                                    </div>
                                    <div id="member-wrap" class="d-none mb-3">
                                        <label class="form-label">Nomor Telepon Member</label>
                                        <input type="text" name="phone" id="phone" class="form-control">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Total Pembayaran</label>
                                        <input type="text" id="total_payment" name="total_payment" class="form-control" required>
                                        <small id="warningMessage" class="text-danger d-none">Jumlah bayar kurang.</small>
                                    </div>
                                    <div class="text-end mt-4">
                                        <button type="submit" id="submitButton" class="btn btn-primary px-4">Bayar Sekarang</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function memberDetect() {
        const detectElement = document.getElementById('customer');
        const phone = document.getElementById('member-wrap');
        if (detectElement.value == 'Member') {
            phone.classList.remove('d-none');
        } else {
            phone.classList.add('d-none');
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const total = {{ $total }};
        const paymentInput = document.getElementById("total_payment");
        const warning = document.getElementById("warningMessage");
        const submitButton = document.getElementById("submitButton");

        function formatRupiah(angka) {
            return angka.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateValidation() {
            const bayar = parseInt(paymentInput.value.replace(/[^0-9]/g, '')) || 0;
            if (bayar < total) {
                warning.classList.remove("d-none");
                submitButton.disabled = true;
            } else {
                warning.classList.add("d-none");
                submitButton.disabled = false;
            }
            paymentInput.value = "Rp. " + formatRupiah(bayar.toString());
        }

        paymentInput.addEventListener("input", updateValidation);
        updateValidation();
    });
</script>
@endsection
PHP;
        $content = str_replace(['__MODEL__'], [$modelName], $content);
        $this->files->put("{$path}/payment.blade.php", $content);
    }

    protected function createMemberView(string $path, string $modelName, string $singular): void
    {
        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0">Member Info - __MODEL__ #{{ $sale->id }}</h4>
        </div>
        <div class="card-body">
            <form action="{{ route('employee.__MODEL__MemberUpdate', $sale->id) }}" method="POST">
@csrf
                <input type="hidden" name="sale_id" value="{{ $sale->id }}">
                <input type="hidden" name="customer_id" value="{{ $sale->customer_id }}">
                
                <div class="mb-3">
                    <label class="form-label">Nama Member</label>
                    <input type="text" class="form-control" name="name" value="{{ $sale->customer->name ?? '' }}" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Poin Member Saat Ini</label>
                    <input type="text" class="form-control" value="{{ $sale->customer->point }}" readonly>
                </div>

@if($sale->customer->point > 0)
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="check_point" value="Ya" id="usePoint">
                        <label class="form-check-label" for="usePoint">
                            Gunakan Poin untuk Diskon?
                        </label>
                    </div>
                </div>
@endif

                <button type="submit" class="btn btn-primary">Selesai</button>
            </form>
        </div>
    </div>
</div>
@endsection
PHP;
        $content = str_replace(['__MODEL__'], [$modelName], $content);
        $this->files->put("{$path}/member.blade.php", $content);
    }

    protected function createPrintView(string $path, string $modelName, string $singular): void
    {
        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Invoice __MODEL__</h4>
        <p class="text-muted small">Transaksi #TRX-{{ $sale->id }} berhasil diselesaikan.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <a href="{{ route('employee.__MODEL__ExportPDF', $sale->id) }}" class="btn btn-primary rounded-start-pill px-4 shadow-sm">
                <i class="bi bi-download me-2"></i> Unduh PDF
            </a>
            <a href="{{ route('employee.__MODEL__Index') }}" class="btn btn-outline-primary rounded-end-pill px-4">
                Daftar __MODEL__ <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <!-- Header Invoice -->
                <div class="bg-primary bg-opacity-10 p-4 border-bottom border-primary border-opacity-10">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3 mb-md-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-primary">STRUK PENJUALAN</h5>
                                    <div class="text-muted extra-small">ID: #TRX-{{ $sale->id }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d F Y') }}</div>
                            <div class="text-muted small">Waktu: {{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }} WIB</div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="row mb-3 g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Detail Pelanggan</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                @if ($sale->customer)
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                        <div class="fw-bold text-dark">{{ $sale->customer->name }}</div>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-telephone me-2"></i>{{ $sale->customer->phone }}
                                    </div>
                                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">Member Store</div>
                                @else
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person-x text-muted"></i>
                                        </div>
                                        <div class="fw-bold text-muted">NON-MEMBER</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Informasi Kasir</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                        <i class="bi bi-person-badge text-primary"></i>
                                    </div>
                                    <div class="fw-bold text-dark">{{ $sale->user->name ?? $sale->user->email ?? '-' }}</div>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-clock me-2"></i>Selesai pada {{ $sale->updated_at->format('H:i:s') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr class="extra-small text-uppercase text-muted">
                                    <th class="ps-3">Produk</th>
                                    <th class="text-center">Harga Satuan</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-3">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detail_sale as $data)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">{{ $data->product->name }}</div>
                                        </td>
                                        <td class="text-center text-muted">
                                            Rp {{ number_format($data->product->price, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border rounded-pill px-3">{{ $data->quantity }}</span>
                                        </td>
                                        <td class="text-end pe-3 fw-bold text-dark">
                                            Rp {{ number_format($data->sub_total, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="bg-light rounded-4 p-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Subtotal</span>
                                    <span class="fw-bold text-dark">Rp {{ number_format($sale->total_price + ($sale->used_point ?? 0), 0, ',', '.') }}</span>
                                </div>
                                @if($sale->used_point > 0)
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span class="small">Potongan Poin</span>
                                    <span class="fw-bold">- Rp {{ number_format($sale->used_point, 0, ',', '.') }}</span>
                                </div>
                                @endif
                                <hr class="my-3 opacity-10">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold text-primary">TOTAL AKHIR</span>
                                    <span class="fw-bold text-primary fs-4">Rp {{ number_format($sale->total_price, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted extra-small">DIBAYAR</span>
                                    <span class="fw-medium text-dark small">Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted extra-small">KEMBALIAN</span>
                                    <span class="fw-bold text-success small">Rp {{ number_format($sale->change, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-4 text-center border-top">
                    <p class="text-muted small mb-0">Terima kasih telah berbelanja di toko kami. Simpan struk ini sebagai bukti transaksi yang sah.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
</style>
@endsection
PHP;
        $content = str_replace(['__MODEL__'], [$modelName], $content);
        $this->files->put("{$path}/print.blade.php", $content);
    }

    protected function createExportPdfView(string $path, string $modelName, string $singular): void
    {
        $content = <<<'PHP'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Struk __MODEL__ #{{ $sale->id }}</title>
  <style>
    body { font-family: 'Arial', sans-serif; font-size: 12px; background: #f8f9fa; }
    #receipt { background: #fff; padding: 25px; margin: 0 auto; width: 600px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); border-radius: 8px; }
    h2 { font-size: 1.5rem; margin: 0; text-align: center; color: #333; }
    small { font-size: 11px; color: #555; }
    .info { display: flex; justify-content: space-between; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 8px 10px; text-align: right; }
    th { background-color: #e9ecef; font-size: 11px; color: #333; border: 1px solid #dee2e6; }
    td { font-size: 11px; border: 1px solid #dee2e6; }
    .total-row td { font-weight: bold; background-color: #f1f3f5; }
    #legalcopy { text-align: center; margin-top: 30px; }
    .legal { font-size: 11px; color: #333; }
    .highlight { color: #007bff; font-weight: bold; }
    .logo { text-align: center; margin-bottom: 20px; }
    .store-info { text-align: center; font-size: 12px; margin-top: 15px; }
  </style>
</head>
<body>
@php
        $imagePath = public_path('assets/images/store.jpeg');
        $src = '';
        if (file_exists($imagePath)) {
            $imageData = base64_encode(file_get_contents($imagePath));
            $src = 'data:' . mime_content_type($imagePath) . ';base64,' . $imageData;
        }
@endphp
    <div id="receipt">
        <div class="logo">@if($src)<img src="{{ $src }}" width="100">@endif</div>
        <h2>Store</h2>
        <div class="store-info">
            <small>Telp: 081234098765<br>Jl. Raya Puncak</small>
        </div>
        <div class="info">
            <div>
                <small>Status: <span class="highlight">{{ $sale->customer ? 'Member' : 'Non-Member' }}</span><br>
                Poin: {{ $sale->customer ? $sale->customer->point : '-' }}</small>
            </div>
            <div>
                <small>Kasir: {{ $sale->user->name ?? $sale->user->email ?? '-' }}<br>
                Tanggal: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</small>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
@foreach($detail_sale as $item)
                <tr>
                    <td style="text-align: left;">{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rp {{ number_format($item->product->price, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                </tr>
@endforeach
                <tr class="total-row"><td colspan="3">Total</td><td>Rp {{ number_format($sale->total_price + $sale->used_point, 0, ',', '.') }}</td></tr>
@if($sale->used_point > 0)<tr class="total-row"><td colspan="3">Poin Digunakan</td><td>Rp {{ number_format($sale->used_point, 0, ',', '.') }}</td></tr>@endif
                <tr class="total-row"><td colspan="3">Total Bayar</td><td>Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td colspan="3">Kembalian</td><td>Rp {{ number_format($sale->change, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
        <div id="legalcopy">
            <p class="legal">Invoice: #{{ $sale->id }}</p>
            <p class="legal">~ Terima kasih ~</p>
        </div>
    </div>
</body>
</html>
PHP;
        $content = str_replace(['__MODEL__'], [$modelName], $content);
        $this->files->put("{$path}/exportpdf.blade.php", $content);
    }

    protected function createCreateView(string $path, string $modelName, string $singular, string $role, array $fields): void
    {
        if ($role === 'employee') {
            $this->createEmployeeCreateView($path, $modelName, $singular);
            return;
        }

        $inputs = "";
        foreach ($fields as $field) {
            $label = ucfirst($field['name']);
            $inputType = ($field['type'] === 'integer' || $field['type'] === 'decimal') ? 'number' : 'text';
            $inputs .= "                <div class=\"mb-3\">\n";
            $inputs .= "                    <label for=\"{$field['name']}\" class=\"form-label\">{$label}</label>\n";
            $inputs .= "                    <input type=\"{$inputType}\" class=\"form-control @error('{$field['name']}') is-invalid @enderror\" id=\"{$field['name']}\" name=\"{$field['name']}\" value=\"{{ old('{$field['name']}') }}\" required>\n";
            $inputs .= "                    @error('{$field['name']}')\n";
            $inputs .= "                        <span class=\"invalid-feedback\">{{ \$message }}</span>\n";
            $inputs .= "                    @enderror\n";
            $inputs .= "                </div>\n";
        }

        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <h2>Tambah __MODEL__</h2>

                <form action="{{ route('__ROLE__.__MODEL__Store') }}" method="POST">
@csrf

__INPUTS__
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <a href="{{ route('__ROLE__.__MODEL__Home') }}" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
PHP;

        $content = str_replace(['__MODEL__', '__SINGULAR__', '__ROLE__', '__INPUTS__'], [$modelName, $singular, $role, $inputs], $content);
        $this->files->put("{$path}/create.blade.php", $content);
    }

    protected function createEmployeeCreateView(string $path, string $modelName, string $singular): void
    {
        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
    <div class="page-wrapper">
        <div class="page-breadcrumb">
            <div class="row align-items-center">
                <div class="col-6">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 d-flex align-items-center">
                            <li class="breadcrumb-item">
                                <a href="{{ route('employee.dashboard') }}" class="link">
                                    <i class="mdi mdi-home-outline fs-4"></i>
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="{{ route('employee.__MODEL__Index') }}" class="link">__MODEL__</a>
                            </li>
                            <li class="breadcrumb-item active text-dark" aria-current="page">Tambah __MODEL__</li>
                        </ol>
                    </nav>
                    <h1 class="mb-0 fw-bold">Tambah __MODEL__</h1>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
@if(session('failed'))
                        <div class="alert alert-danger">{{ session('failed') }}</div>
@endif

                    <div class="card">
                        <div class="card-body">
                            <div class="text-center container">
                                <div class="row">
@foreach($product as $data)
                                        <div class="col-lg-4 col-md-6">
                                            <div class="card">
                                                <p hidden class="product_id">{{ $data['id'] }}</p>
                                                <div class="bg-image">
@if($data['image'])
                                                    <img src="{{ asset('storage/' . $data['image']) }}" class="w-50 mt-3" alt="">
@else
                                                    <div class="bg-light py-5">No Image</div>
@endif
                                                </div>
                                                <div class="card-body">
                                                    <div class="card-title mb-3">{{ $data['name'] }}</div>
                                                    <p>Stock <span class="product_stock">{{ $data['stock'] }}</span></p>
                                                    <h6 class="mb-3 product_price">Rp. {{ number_format($data->price, 0, '.', '.') }}</h6>

                                                    <center>
                                                        <table>
                                                            <tbody>
                                                                <tr>
                                                                    <td style="padding: 0px 10px 0px 10px; cursor: pointer;" class="product_min">
                                                                        <b>-</b>
                                                                    </td>
                                                                    <td style="padding: 0px 10px 0px 10px;" class="product_sum">0</td>
                                                                    <td style="padding: 0px 10px 0px 10px; cursor: pointer;" class="product_plus">
                                                                        <b>+</b>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </center>
                                                    <p class="mt-3">
                                                        Sub Total
                                                        <b class="sub_total">Rp. 0 ,-</b>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
@endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form action="{{ route('employee.__MODEL__Store') }}" method="POST">
@csrf
                    <div id="hidden-inputs"></div>
                    <div class="fixed-bottom bg-white shadow p-3 border-top border-warning w-100 d-flex justify-content-center">
                        <button class="btn btn-primary w-20">Selanjutnya</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
                    alert("Stock is not enough!");
                    return;
                }
            } else if ($(this).hasClass("product_min") && quantity > 0) {
                quantity--;
            }

            quantityElement.text(quantity);
            var subtotal = quantity * price;
            card.find(".sub_total").text("Rp. " + subtotal.toLocaleString() + " ,-");

            updateHiddenInputs(productId, productName, price, quantity, subtotal);
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
                hiddenInputsContainer.append('<input type="hidden" name="products[]" data-id="' + productId + '" value="' + inputValue + '">');
            }
        }
    </script>
@endpush
@endsection
PHP;
        $content = str_replace(['__MODEL__'], [$modelName], $content);
        $this->files->put("{$path}/create.blade.php", $content);
    }

    protected function createEditView(string $path, string $modelName, string $singular, array $fields): void
    {
        $inputs = "";
        foreach ($fields as $field) {
            $label = ucfirst($field['name']);
            $inputType = ($field['type'] === 'integer' || $field['type'] === 'decimal') ? 'number' : 'text';
            $inputs .= "                <div class=\"mb-3\">\n";
            $inputs .= "                    <label for=\"{$field['name']}\" class=\"form-label\">{$label}</label>\n";
            $inputs .= "                    <input type=\"{$inputType}\" class=\"form-control @error('{$field['name']}') is-invalid @enderror\" id=\"{$field['name']}\" name=\"{$field['name']}\" value=\"{{ old('{$field['name']}', \${$singular}->{$field['name']}) }}\" required>\n";
            $inputs .= "                    @error('{$field['name']}')\n";
            $inputs .= "                        <span class=\"invalid-feedback\">{{ \$message }}</span>\n";
            $inputs .= "                    @enderror\n";
            $inputs .= "                </div>\n";
        }

        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <h2>Edit __MODEL__</h2>

                <form action="{{ route('admin.__MODEL__Update', $__SINGULAR__->id) }}" method="POST">
@csrf
@method('PATCH')

__INPUTS__
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <a href="{{ route('admin.__MODEL__Home') }}" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
PHP;

        $content = str_replace(['__MODEL__', '__SINGULAR__', '__INPUTS__'], [$modelName, $singular, $inputs], $content);
        $this->files->put("{$path}/edit.blade.php", $content);
    }

    protected function createEmployeeIndexView(string $path, string $modelName, string $singular, string $plural, array $fields): void
    {
        $headers = "";
        $columns = "";
        foreach ($fields as $field) {
            $label = ucfirst($field['name']);
            $headers .= "                    <th>{$label}</th>\n";
            $columns .= "                    <td>{{ \${$singular}->{$field['name']} }}</td>\n";
        }

        $content = <<<'PHP'
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-6">
                <h2>Daftar __MODEL__</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="{{ route('employee.__MODEL__Create') }}" class="btn btn-primary">Tambah __MODEL__</a>
            </div>
        </div>

@if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
@endif

        <!-- Filter: daily, weekly, monthly, yearly -->
        <div class="mb-3">
            <form method="GET" class="row g-2">
                <div class="col-auto">
                    <select name="filter" class="form-select">
                        <option value="">Semua</option>
                        <option value="daily" {{ request('filter') === 'daily' ? 'selected' : '' }}>Hari ini</option>
                        <option value="weekly" {{ request('filter') === 'weekly' ? 'selected' : '' }}>Minggu ini</option>
                        <option value="monthly" {{ request('filter') === 'monthly' ? 'selected' : '' }}>Bulan ini</option>
                        <option value="yearly" {{ request('filter') === 'yearly' ? 'selected' : '' }}>Tahun ini</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
__HEADERS__                    <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
@forelse($__PLURAL__ as $__SINGULAR__)
                    <tr>
                        <td>{{ $__SINGULAR__->id }}</td>
__COLUMNS__                    <td>{{ $__SINGULAR__->created_at?->format('d/m/Y') ?? '-' }}</td>
                        <td>
                            <a href="{{ route('employee.__MODEL__Print', $__SINGULAR__->id) }}" class="btn btn-sm btn-info">Print</a>
                        </td>
                    </tr>
@empty
                    <tr>
                        <td colspan="__COL_COUNT__" class="text-center">Tidak ada data</td>
                    </tr>
@endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            {{ $__PLURAL__->links() }}
        </div>
    </div>
</div>
@endsection
PHP;

        $content = str_replace(
            ['__MODEL__', '__SINGULAR__', '__PLURAL__', '__HEADERS__', '__COLUMNS__', '__COL_COUNT__'],
            [$modelName, $singular, $plural, $headers, $columns, count($fields) + 3],
            $content
        );
        $this->files->put("{$path}/index.blade.php", $content);
    }

    protected function updateModel(string $modelName, array $fields, bool $softDeletes): void
    {
        $modelPath = app_path("Models/{$modelName}.php");

        if (!$this->files->exists($modelPath)) {
            return;
        }

        $content = $this->files->get($modelPath);
        
        $fillable = [];
        foreach ($fields as $field) {
            $fillable[] = "'{$field['name']}'";
        }
        $fillableStr = implode(', ', $fillable);

        $traits = "use HasFactory;";
        $imports = "use Illuminate\Database\Eloquent\Factories\HasFactory;";
        
        if ($softDeletes) {
            $traits .= "\n    use SoftDeletes;";
            $imports .= "\nuse Illuminate\Database\Eloquent\SoftDeletes;";
        }

        $content = str_replace(
            "use Illuminate\Database\Eloquent\Factories\HasFactory;",
            $imports,
            $content
        );

        $replacement = "{$traits}\n\n    protected \$fillable = [{$fillableStr}];";
        $content = preg_replace('/use HasFactory;/', $replacement, $content, 1);

        $this->files->put($modelPath, $content);
    }

    protected function updateController(string $modelName, string $viewFolder, string $singularLower, string $plural, string $role, array $fields): void
    {
        $controllerPath = app_path("Http/Controllers/{$modelName}Controller.php");
        if (!$this->files->exists($controllerPath)) {
            return;
        }

        $validationRules = [];
        $fieldNames = [];
        foreach ($fields as $field) {
            $rules = ($field['type'] === 'string') ? 'required|string|min:3' : 'required|numeric';
            $validationRules[] = "            '{$field['name']}' => '{$rules}',";
            $fieldNames[] = "'{$field['name']}'";
        }
        $validationRulesStr = implode("\n", $validationRules);
        $fieldNamesStr = implode(", ", $fieldNames);

        if ($role === 'admin') {
            $content = <<<'EOC'
<?php

namespace App\Http\Controllers;

use App\Models\__MODEL__;
use Illuminate\Http\Request;

class __MODEL__Controller extends Controller
{
    public function index()
    {
        $__PLURAL__ = __MODEL__::paginate(10);
        return view('admin.__VIEW_FOLDER__.index', compact('__PLURAL__'));
    }

    public function create()
    {
        return view('admin.__VIEW_FOLDER__.create');
    }

    public function store(Request $request)
    {
        $request->validate([
__VALIDATION__
        ]);

        __MODEL__::create($request->only([__FIELDS__]));

        return redirect()->route('admin.__MODEL__Home')->with('success', '__MODEL__ berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $__SINGULAR__ = __MODEL__::findOrFail($id);
        return view('admin.__VIEW_FOLDER__.edit', compact('__SINGULAR__'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
__VALIDATION__
        ]);

        $__SINGULAR__ = __MODEL__::findOrFail($id);
        $__SINGULAR__->update($request->only([__FIELDS__]));

        return redirect()->route('admin.__MODEL__Home')->with('success', '__MODEL__ berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $__SINGULAR__ = __MODEL__::findOrFail($id);
        $__SINGULAR__->delete();

        return redirect()->route('admin.__MODEL__Home')->with('success', '__MODEL__ berhasil dihapus!');
    }
}
EOC;
        } else {
            // Employee controller
            $content = <<<'EOC'
<?php

namespace App\Http\Controllers;

use App\Models\__MODEL__;
use App\Models\Product;
use App\Models\Customers;
use App\Models\Detail_sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class __MODEL__Controller extends Controller
{
    public function index(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $__PLURAL__ = __MODEL__::with(['customer', 'user']);

        if ($start_date && $end_date) {
            $__PLURAL__->whereBetween('created_at', [
                Carbon::parse($start_date)->startOfDay(),
                Carbon::parse($end_date)->endOfDay()
            ]);
        }

        $__PLURAL__ = $__PLURAL__->latest()->paginate(10);
        return view('employee.__VIEW_FOLDER__.index', compact('__PLURAL__', 'start_date', 'end_date'));
    }

    public function create()
    {
        $product = Product::all();
        return view('employee.__VIEW_FOLDER__.create', compact('product'));
    }

    public function store(Request $request)
    {
        $products = $request->products;
        if (empty($products)) {
            return redirect()->back()->with('failed', 'Pilih minimal 1 produk.');
        }

        $data['products'] = [];
        $data['total'] = 0;

        foreach ($products as $product) {
            $product = explode(';', $product);
            $id = $product[0];
            $name = $product[1];
            $price = (float) str_replace(['Rp', '.', ','], '', $product[2]);
            $quantity = (int) $product[3];
            $subtotal = $price * $quantity;

            $data['products'][] = [
                'product_id' => $id,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ];
            $data['total'] += $subtotal;
        }

        return view('employee.__VIEW_FOLDER__.payment', $data);
    }

    public function paymentProcess(Request $request)
    {
        $products = $request->shop;
        $sale_product = [];
        $total_pay = (int)str_replace(['Rp. ', '.'], '', $request->total_payment);
        $total = (int)str_replace(['Rp. ', '.'], '', $request->total);
        $customer_id = null;

        if ($request->customer == 'Member') {
            $phone = $request->phone;
            $member_name = $request->member_name;
            $existCustomer = Customers::where('phone', $phone)->first();

            if ($existCustomer) {
                $updateData = ['point' => $existCustomer->point + ($total / 100)];
                if ($member_name) {
                    $updateData['name'] = $member_name;
                }
                $existCustomer->update($updateData);
                $customer_id = $existCustomer->id;
            } else {
                $newCustomer = Customers::create([
                    'name' => $member_name ?? 'Member Baru',
                    'phone' => $phone,
                    'point' => 0, // Pembelian pertama tidak dapet poin
                ]);
                $customer_id = $newCustomer->id;
            }
        }

        $sale = __MODEL__::create([
            'sale_date' => now(),
            'customer_id' => $customer_id,
            'total_price' => $total,
            'total_payment' => $total_pay,
            'change' => $total_pay - $total,
            'user_id' => Auth::user()->id,
            'sale_product' => '',
            'used_point' => 0
        ]);

        foreach ($products as $product) {
            $product = explode(';', $product);
            $id = $product[0];
            $name = $product[1];
            $price = number_format($product[2], 0, ',', '.');
            $quantity = (int)$product[3];
            $subtotal = (int)$product[4];

            $sale_product[] = "{$name} ( {$quantity} : Rp. {$price} )";

            $productModel = Product::find($id);
            if ($productModel) {
                $productModel->update(['stock' => $productModel->stock - $quantity]);
            }

            Detail_sale::create([
                'sale_id' => $sale->id,
                'product_id' => $id,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ]);
        }

        $sale->update(['sale_product' => implode(' , ', $sale_product)]);

        if ($request->customer == 'Member') {
            return redirect()->route('employee.__MODEL__Member', $sale->id);
        }

        return redirect()->route('employee.__MODEL__Print', $sale->id)->with('success', 'Transaksi Berhasil!');
    }

    public function member($id)
    {
        $sale = __MODEL__::with(['customer', 'user'])->findOrFail($id);
        return view('employee.__VIEW_FOLDER__.member', compact('sale'));
    }

    public function memberUpdate(Request $request, $id)
    {
        $customer = Customers::findOrFail($request->customer_id);
        $customer->update(['name' => $request->name]);

        $sale = __MODEL__::findOrFail($id);

        if ($request->check_point == 'Ya') {
            $used_point = $customer->point;
            $customer->update(['point' => 0]);

            $sale->used_point = $used_point;
            $sale->total_price -= $used_point;
            $sale->change = $sale->total_payment - $sale->total_price;
        }

        $sale->save();

        return redirect()->route('employee.__MODEL__Print', $sale->id)->with('success', 'Data Member Berhasil Diperbarui!');
    }

    public function print($id)
    {
        $sale = __MODEL__::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        return view('employee.__VIEW_FOLDER__.print', compact('sale', 'detail_sale'));
    }

    public function exportPDF($id)
    {
        $sale = __MODEL__::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();

        $pdf = PDF::loadView('employee.__VIEW_FOLDER__.exportpdf', compact('sale', 'detail_sale'));
        return $pdf->download('receipt-' . $sale->id . '.pdf');
    }
}
EOC;
        }

        $content = str_replace(
            ['__MODEL__', '__SINGULAR__', '__PLURAL__', '__VIEW_FOLDER__', '__VALIDATION__', '__FIELDS__'],
            [$modelName, $singularLower, $plural, $viewFolder, $validationRulesStr, $fieldNamesStr],
            $content
        );

        $this->files->put($controllerPath, $content);
    }


    protected function updateRoutes(string $modelName, string $viewFolder, string $singular, string $role): void
    {
        $routePath = base_path('routes/web.php');
        if (!$this->files->exists($routePath)) {
            return;
        }

        $content = $this->files->get($routePath);

        // Jika route sudah ada, jangan tambah lagi
        if (strpos($content, "{$role}.{$modelName}Home") !== false) {
            return;
        }

        // Import controller
        $import = "use App\\Http\\Controllers\\{$modelName}Controller;";
        if (strpos($content, $import) === false) {
            $content = str_replace(
                "use App\\Http\\Controllers\\SaleController;",
                "use App\\Http\\Controllers\\SaleController;\nuse App\\Http\\Controllers\\{$modelName}Controller;",
                $content
            );
        }

        // Buat route block
        $routeBlock = "\n\n    //{$viewFolder}\n    Route::prefix('/{$viewFolder}')->group(function() {\n";
        $routeBlock .= "        Route::get('/', [{$modelName}Controller::class, 'index'])->name('{$modelName}Home');\n";
        $routeBlock .= "        Route::get('/create', [{$modelName}Controller::class, 'create'])->name('{$modelName}Create');\n";
        $routeBlock .= "        Route::post('/store', [{$modelName}Controller::class, 'store'])->name('{$modelName}Store');\n";

        if ($role === 'admin') {
            $routeBlock .= "        Route::get('/{id}', [{$modelName}Controller::class, 'edit'])->name('{$modelName}Edit');\n";
            $routeBlock .= "        Route::patch('/{id}', [{$modelName}Controller::class, 'update'])->name('{$modelName}Update');\n";
            $routeBlock .= "        Route::delete('/{id}', [{$modelName}Controller::class, 'destroy'])->name('{$modelName}Delete');\n";
        } else {
            $routeBlock .= "        Route::post('/payment-process', [{$modelName}Controller::class, 'paymentProcess'])->name('{$modelName}PaymentProcess');\n";
            $routeBlock .= "        Route::get('/member/{id}', [{$modelName}Controller::class, 'member'])->name('{$modelName}Member');\n";
            $routeBlock .= "        Route::post('/member/{id}', [{$modelName}Controller::class, 'memberUpdate'])->name('{$modelName}MemberUpdate');\n";
            $routeBlock .= "        Route::get('/print/{id}', [{$modelName}Controller::class, 'print'])->name('{$modelName}Print');\n";
            $routeBlock .= "        Route::get('/export-pdf/{id}', [{$modelName}Controller::class, 'exportPDF'])->name('{$modelName}ExportPDF');\n";
        }

        $routeBlock .= "    });\n";

        // Cari posisi untuk insert - sebelum "Route::middleware('IsLogin', 'IsEmployee')"
        $pos = strpos($content, "Route::middleware('IsLogin', 'IsEmployee')->prefix('employee')->name('employee.')->group(function() {");

        if ($pos !== false && $role === 'admin') {
            // Insert route block sebelum employee routes
            $content = substr_replace($content, $routeBlock, $pos, 0);
        } else {
            // Fallback: tambah di akhir file sebelum closing
            $content = rtrim($content, "\n") . "\n" . $routeBlock . "\n";
        }

        $this->files->put($routePath, $content);
    }

    protected function updateSidebar(string $modelName, string $role, string $viewFolder): void
    {
        $navbarPath = resource_path('views/components/navbar.blade.php');
        if (!$this->files->exists($navbarPath)) {
            return;
        }

        $content = $this->files->get($navbarPath);
        $icon = ($role === 'admin') ? 'mdi mdi-settings' : 'mdi mdi-checkbox-marked-circle-outline';
        $label = ucfirst($modelName);

        $menuItem = "                                <li class=\"sidebar-item\">\n";
        $menuItem .= "                                    <a class=\"sidebar-link waves-effect waves-dark sidebar-link\" href=\"{{ route('{$role}.{$modelName}Home') }}\" aria-expanded=\"false\">\n";
        $menuItem .= "                                        <i class=\"{$icon}\"></i>\n";
        $menuItem .= "                                        <span class=\"hide-menu\">{$label}</span>\n";
        $menuItem .= "                                    </a>\n";
        $menuItem .= "                                </li>\n";

        if ($role === 'admin') {
            // Find the last admin item before @else
            $search = '<span class="hide-menu">User</span>';
            if (strpos($content, $search) !== false) {
                $pos = strpos($content, '</li>', strpos($content, $search));
                $content = substr_replace($content, "</li>\n" . $menuItem, $pos, 5);
            }
        } else {
            // Find the last employee item before @endif
            $search = '<span class="hide-menu">Penjualan</span>';
            // Find the one after @else
            $posElse = strpos($content, '@else');
            $posSearch = strpos($content, $search, $posElse);
            if ($posSearch !== false) {
                $pos = strpos($content, '</li>', $posSearch);
                $content = substr_replace($content, "</li>\n" . $menuItem, $pos, 5);
            }
        }

        $this->files->put($navbarPath, $content);
    }

    protected function generateAppBase(): void
    {
        $this->info("Generating complete app base: all views, controllers, routes");

        // 1. Create UserController if not exists
        $userControllerPath = app_path('Http/Controllers/UserController.php');
        if (!$this->files->exists($userControllerPath)) {
            $this->call('make:controller', ['name' => 'UserController']);
            $this->updateUserController();
            $this->info('✓ UserController created');
        }

        // 2. Create ProductController if not exists
        $productControllerPath = app_path('Http/Controllers/ProductController.php');
        if (!$this->files->exists($productControllerPath)) {
            $this->call('make:controller', ['name' => 'ProductController']);
            $this->updateProductController();
            $this->info('✓ ProductController created');
        }

        // 3. Create SaleController if not exists
        $saleControllerPath = app_path('Http/Controllers/SaleController.php');
        if (!$this->files->exists($saleControllerPath)) {
            $this->call('make:controller', ['name' => 'SaleController']);
            $this->updateSaleController();
            $this->info('✓ SaleController created');
        }

        // 4. Create CustomersController if not exists
        $customersControllerPath = app_path('Http/Controllers/CustomersController.php');
        if (!$this->files->exists($customersControllerPath)) {
            $this->call('make:controller', ['name' => 'CustomersController']);
            $this->updateCustomersController();
            $this->info('✓ CustomersController created');
        }

        // 5. Create Models
        $this->generateBaseModels();
        $this->info('✓ Base models created with relationships');

        // 5. Create views directories
        $viewDirs = [
            'landing', 'login', 'admin/dashboard', 'employee/dashboard', 'error',
            'admin/product', 'admin/user', 'admin/purchases',
            'employee/product', 'employee/purchases',
            'components'
        ];
        foreach ($viewDirs as $dir) {
            $path = resource_path("views/{$dir}");
            if (!$this->files->exists($path)) {
                $this->files->makeDirectory($path, 0755, true);
            }
        }

        // 5. Create all views
        $this->createAllViews();
        $this->info('✓ All views created');

        // 6. Create Middlewares
        $this->generateMiddlewares();
        $this->info('✓ Middlewares created and registered');

        // 7. Create Layouts
        $this->createLayouts();
        $this->info('✓ Layouts created');

        // 8. Create Base Migrations
        $this->createBaseMigrations();
        $this->info('✓ Base migrations created');

        // 9. Update User Migration & Model
        $this->updateUserBase();
        $this->info('✓ User model & migration updated');

        // 10. Create Seeders
        $this->generateBaseSeeders();
        $this->info('✓ Base seeders created');

        // 11. Create Exports
        $this->generateExports();
        $this->info('✓ Base exports created');

        // 12. Update routes
        $this->updateCompleteRoutes();
        $this->info('✓ Complete routes updated');

        $this->info('Complete app generation finished! All features ready.');
    }

    protected function generateBaseModels(): void
    {
        // Customers Model
        $this->call('make:model', ['name' => 'Customers']);
        $this->updateModel('Customers', [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'phone', 'type' => 'string'],
            ['name' => 'point', 'type' => 'integer'],
        ], false);

        // Product Model
        $this->call('make:model', ['name' => 'Product']);
        $this->updateModel('Product', [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'price', 'type' => 'integer'],
            ['name' => 'stock', 'type' => 'integer'],
            ['name' => 'image', 'type' => 'string'],
        ], false);

        // Add relationships to Product
        $productPath = app_path('Models/Product.php');
        $productContent = $this->files->get($productPath);
        $productRel = "\n    public function detail_sales()\n    {\n        return \$this->hasMany(Detail_sale::class, 'product_id');\n    }\n";
        $productContent = preg_replace('/}\s*$/', $productRel . "\n}", $productContent);
        $this->files->put($productPath, $productContent);

        // Sale Model
        $this->call('make:model', ['name' => 'Sale']);
        $this->updateModel('Sale', [
            ['name' => 'sale_date', 'type' => 'date'],
            ['name' => 'user_id', 'type' => 'integer'],
            ['name' => 'sale_product', 'type' => 'string'],
            ['name' => 'customer_id', 'type' => 'integer'],
            ['name' => 'total_price', 'type' => 'integer'],
            ['name' => 'total_payment', 'type' => 'integer'],
            ['name' => 'change', 'type' => 'integer'],
            ['name' => 'used_point', 'type' => 'integer'],
        ], false);

        // Add relationships to Sale
        $salePath = app_path('Models/Sale.php');
        $saleContent = $this->files->get($salePath);
        $saleRel = "\n    public function detail_sales()\n    {\n        return \$this->hasMany(Detail_sale::class, 'sale_id');\n    }\n\n    public function customer()\n    {\n        return \$this->belongsTo(Customers::class, 'customer_id');\n    }\n\n    public function user()\n    {\n        return \$this->belongsTo(User::class, 'user_id');\n    }\n";
        $saleContent = preg_replace('/}\s*$/', $saleRel . "\n}", $saleContent);
        $this->files->put($salePath, $saleContent);

        // Detail_sale Model
        $this->call('make:model', ['name' => 'Detail_sale']);
        $this->updateModel('Detail_sale', [
            ['name' => 'sale_id', 'type' => 'integer'],
            ['name' => 'product_id', 'type' => 'integer'],
            ['name' => 'quantity', 'type' => 'integer'],
            ['name' => 'sub_total', 'type' => 'integer'],
        ], false);

        // Add relationships to Detail_sale
        $dsPath = app_path('Models/Detail_sale.php');
        $dsContent = $this->files->get($dsPath);
        $dsRel = "\n    public function sale()\n    {\n        return \$this->belongsTo(Sale::class, 'sale_id');\n    }\n\n    public function product()\n    {\n        return \$this->belongsTo(Product::class, 'product_id');\n    }\n";
        $dsContent = preg_replace('/}\s*$/', $dsRel . "\n}", $dsContent);
        $this->files->put($dsPath, $dsContent);
    }

    protected function generateMiddlewares(): void
    {
        $middlewares = [
            'IsLogin' => 'if (!auth()->check()) { return redirect()->route("login"); } return $next($request);',
            'IsGuest' => 'if (auth()->check()) { return redirect()->route("landingpage"); } return $next($request);',
            'IsAdmin' => 'if (auth()->user()->role !== "admin") { return redirect()->route("error.permission"); } return $next($request);',
            'IsEmployee' => 'if (auth()->user()->role !== "employee") { return redirect()->route("error.permission"); } return $next($request);',
        ];

        foreach ($middlewares as $name => $logic) {
            $path = app_path("Http/Middleware/{$name}.php");
            $content = <<<PHP
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class {$name}
{
    public function handle(Request \$request, Closure \$next): Response
    {
        {$logic}
    }
}
PHP;
            $this->files->put($path, $content);
        }

        // Register in Kernel.php
        $kernelPath = app_path('Http/Kernel.php');
        if ($this->files->exists($kernelPath)) {
            $kernelContent = $this->files->get($kernelPath);
            $middlewareAliases = [
                "'IsLogin' => \\App\\Http\\Middleware\\IsLogin::class,",
                "'IsGuest' => \\App\\Http\\Middleware\\IsGuest::class,",
                "'IsAdmin' => \\App\\Http\\Middleware\\IsAdmin::class,",
                "'IsEmployee' => \\App\\Http\\Middleware\\IsEmployee::class,",
            ];

            foreach ($middlewareAliases as $alias) {
                if (!str_contains($kernelContent, $alias)) {
                    $kernelContent = str_replace(
                        'protected $middlewareAliases = [',
                        "protected \$middlewareAliases = [\n        " . $alias,
                        $kernelContent
                    );
                }
            }
            $this->files->put($kernelPath, $kernelContent);
        }
    }

    protected function createLayouts(): void
    {
        $path = resource_path('views/layouts');
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

                $content = <<<'Sc3a9f9b06fd4e307d74d546a016ce151'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store - Modern POS</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-color: #f9fafb;
            --card-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --card-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        body { 
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: #1f2937;
            -webkit-font-smoothing: antialiased;
        }

        /* Modern Navbar */
        .navbar {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 0;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
            letter-spacing: -0.025em;
        }
        .nav-link {
            color: #4b5563 !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: var(--primary-color) !important;
            background-color: #f3f4f6;
        }
        .nav-link.active {
            color: var(--primary-color) !important;
            background-color: #eef2ff;
        }

        /* Modern Cards */
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            transition: box-shadow 0.3s ease;
        }
        .card:hover {
            box-shadow: var(--card-shadow-lg);
        }
        .card-header {
            background-color: transparent;
            border-bottom: 1px solid #f3f4f6;
            padding: 1.25rem;
            font-weight: 600;
        }

        /* Modern Buttons */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        /* Tables */
        .table {
            --bs-table-bg: transparent;
            margin-bottom: 0;
        }
        .table thead th {
            background-color: #f9fafb;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 0.5rem;
        }

        /* Inputs */
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        /* Bar filter tanggal (penjualan admin/employee): samakan tinggi input & tombol */
        .sale-filter-form input[type="date"].form-control {
            height: 2.375rem;
            min-height: 2.375rem;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
            line-height: 1.25;
        }
        .sale-filter-form .btn.btn-sm {
            height: 2.375rem;
            min-height: 2.375rem;
            padding-top: 0;
            padding-bottom: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Sidebar/Wrapper fix */
        .page-wrapper {
            min-height: calc(100vh - 70px);
        }
    </style>
</head>
<body>
    <x-navbar />

    <main class="container py-5">
        @if(session('success'))
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div>{{ session('success') }}</div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div>{{ session('error') }}</div>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('script')
</body>
</html>
Sc3a9f9b06fd4e307d74d546a016ce151;
        $this->files->put("{$path}/app.blade.php", $content);
    }

    protected function createBaseMigrations(): void
    {
        // 1. Products (Independent)
        $this->generateMigration('products', [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'price', 'type' => 'integer'],
            ['name' => 'stock', 'type' => 'integer'],
            ['name' => 'image', 'type' => 'string', 'nullable' => true],
        ], false);

        sleep(1);

        // 2. Customers (Independent)
        $this->generateMigration('customers', [
            ['name' => 'name', 'type' => 'string', 'nullable' => true],
            ['name' => 'phone', 'type' => 'string', 'unique' => true],
            ['name' => 'point', 'type' => 'integer'],
        ], false);

        sleep(1);

        // 3. Sales (Depends on Customers)
        $this->generateMigration('sales', [
            ['name' => 'sale_date', 'type' => 'date'],
            ['name' => 'user_id', 'type' => 'bigInteger'],
            ['name' => 'sale_product', 'type' => 'string'],
            ['name' => 'customer_id', 'type' => 'bigInteger', 'nullable' => true],
            ['name' => 'total_price', 'type' => 'bigInteger'],
            ['name' => 'total_payment', 'type' => 'bigInteger'],
            ['name' => 'change', 'type' => 'bigInteger'],
            ['name' => 'used_point', 'type' => 'integer'],
        ], false);

        sleep(1);

        // 4. Detail Sales (Depends on Sales and Products)
        $this->generateMigration('detail_sales', [
            ['name' => 'sale_id', 'type' => 'foreignId'],
            ['name' => 'product_id', 'type' => 'foreignId'],
            ['name' => 'quantity', 'type' => 'integer'],
            ['name' => 'sub_total', 'type' => 'bigInteger'],
        ], false);

        // Update detail_sales and sales migration to add constrained()
        $migrationFiles = $this->files->glob(database_path('migrations/*.php'));
        foreach ($migrationFiles as $file) {
            if (str_contains($file, 'create_detail_sales_table')) {
                $content = $this->files->get($file);
                $content = str_replace(
                    "\$table->foreignId('sale_id');\n            \$table->foreignId('product_id');",
                    "\$table->foreignId('sale_id')->constrained()->onDelete('cascade');\n            \$table->foreignId('product_id')->constrained()->onDelete('cascade');",
                    $content
                );
                $this->files->put($file, $content);
            }
            if (str_contains($file, 'create_sales_table')) {
                $content = $this->files->get($file);
                $content = str_replace(
                    "\$table->bigInteger('customer_id')->nullable();",
                    "\$table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');",
                    $content
                );
                $this->files->put($file, $content);
            }
        }
    }

    protected function updateUserBase(): void
    {
        // Update Migration — satu blok: name + email + role (tanpa email_verified_at). Hindari str_replace
        // bertingkat yang menduplikasi enum('role').
        $migrationFiles = $this->files->glob(database_path('migrations/*.php'));
        $ukkUserColumns = "            \$table->string('name');\n            \$table->string('email')->unique();\n            \$table->enum('role', ['admin', 'employee']);\n            ";
        foreach ($migrationFiles as $file) {
            if (str_contains($file, 'create_users_table')) {
                $content = $this->files->get($file);
                $original = $content;

                // Laravel default: name + email.unique + (opsional) email_verified_at → name + email + role
                $content = preg_replace(
                    '/\$table->string\(\'name\'\);\s*\R\s*\$table->string\(\'email\'\)->unique\(\);\s*(?:\R\s*\$table->timestamp\(\'email_verified_at\'\)->nullable\(\);\s*)?/m',
                    $ukkUserColumns,
                    $content
                );

                // Skema tanpa kolom name (proyek lama): sisipkan name antara id() dan email
                if (str_contains($content, "Schema::create('users'")
                    && !str_contains($content, "\$table->string('name')")
                    && preg_match('/\$table->id\(\);\s*\R\s*\$table->string\(\'email\'\)->unique\(\);/m', $content)) {
                    $content = preg_replace(
                        '/(\$table->id\(\);)\s*\R\s*(\$table->string\(\'email\'\)->unique\(\);)/',
                        "\$1\n            \$table->string('name');\n            \$2",
                        $content,
                        1
                    );
                }

                // File rusak (dua enum role): kembalikan name + email + satu role
                $roleEnum = "\$table->enum('role', ['admin', 'employee'])";
                if (substr_count($content, $roleEnum) > 1) {
                    $content = preg_replace(
                        '/\$table->enum\(\'role\', \[\s*\'admin\',\s*\'employee\'\s*\]\);\s*\R\s*\$table->enum\(\'role\', \[\s*\'admin\',\s*\'employee\'\s*\]\);\s*/',
                        $ukkUserColumns,
                        $content
                    );
                }

                if ($content !== $original) {
                    $this->files->put($file, $content);
                }
            }
        }

        // Model: name, email, role, password
        $modelPath = app_path('Models/User.php');
        if ($this->files->exists($modelPath)) {
            $content = $this->files->get($modelPath);
            $original = $content;
            $ukkFillable = <<<'PHP'
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
    ];
PHP;
            $content = preg_replace(
                '/protected \$fillable = \[\s*\'name\',\s*\'email\',\s*\'password\',\s*\];/s',
                $ukkFillable,
                $content
            );
            $content = preg_replace(
                '/protected \$fillable = \[\s*\'email\',\s*\'role\',\s*\'password\',\s*\];/s',
                $ukkFillable,
                $content
            );
            if ($content !== $original) {
                $this->files->put($modelPath, $content);
            }
        }
    }

    protected function generateBaseSeeders(): void
    {
        // 1. Create UserSeeder
        $userSeederPath = database_path('seeders/UserSeeder.php');
        $userSeederContent = <<<PHP
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Petugas Kasir',
            'email' => 'petugas@example.com',
            'password' => Hash::make('petugas123'),
            'role' => 'employee',
        ]);
    }
}
PHP;
        $this->files->put($userSeederPath, $userSeederContent);

        // 1.5 Create CustomersSeeder
        $customersSeederPath = database_path('seeders/CustomersSeeder.php');
        $customersSeederContent = <<<PHP
<?php

namespace Database\Seeders;

use App\Models\Customers;
use Illuminate\Database\Seeder;

class CustomersSeeder extends Seeder
{
    public function run(): void
    {
        Customers::create([
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'point' => 0,
        ]);

        Customers::create([
            'name' => 'Siti Aminah',
            'phone' => '089876543210',
            'point' => 10,
        ]);
    }
}
PHP;
        $this->files->put($customersSeederPath, $customersSeederContent);

        // 2. Create ProductSeeder (Optional but helpful)
        $productSeederPath = database_path('seeders/ProductSeeder.php');
        $productSeederContent = <<<PHP
<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::create([
            'name' => 'Buku Tulis',
            'price' => 5000,
            'stock' => 50,
        ]);

        Product::create([
            'name' => 'Pensil 2B',
            'price' => 2000,
            'stock' => 100,
        ]);
    }
}
PHP;
        $this->files->put($productSeederPath, $productSeederContent);

        // 3. Register in DatabaseSeeder.php
        $this->patchDatabaseSeederForUkK();
    }

    /**
     * Ganti seluruh method run() di DatabaseSeeder agar memanggil seeder UKK.
     * Hindari str_replace parsial pada signature saja (menyisakan { bawaan Laravel → sintaks rusak).
     */
    protected function patchDatabaseSeederForUkK(): void
    {
        $dbSeederPath = database_path('seeders/DatabaseSeeder.php');
        if (!$this->files->exists($dbSeederPath)) {
            return;
        }
        $content = $this->files->get($dbSeederPath);
        if (str_contains($content, 'UserSeeder::class')) {
            return;
        }
        $needle = 'public function run(): void';
        $pos = strpos($content, $needle);
        if ($pos === false) {
            return;
        }
        $openBrace = strpos($content, '{', $pos);
        if ($openBrace === false) {
            return;
        }
        $depth = 0;
        $len = strlen($content);
        for ($i = $openBrace; $i < $len; $i++) {
            $ch = $content[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $lastNl = strrpos(substr($content, 0, $pos), "\n");
                    $lineStart = $lastNl === false ? 0 : $lastNl + 1;
                    $indent = substr($content, $lineStart, $pos - $lineStart);

                    $method = $indent . "public function run(): void\n"
                        . $indent . "{\n"
                        . $indent . "    \$this->call([\n"
                        . $indent . "        UserSeeder::class,\n"
                        . $indent . "        CustomersSeeder::class,\n"
                        . $indent . "        ProductSeeder::class,\n"
                        . $indent . "    ]);\n"
                        . $indent . "}\n";

                    $content = substr($content, 0, $pos) . $method . substr($content, $i + 1);
                    $this->files->put($dbSeederPath, $content);

                    return;
                }
            }
        }
    }

    protected function generateExports(): void
    {
        $path = app_path('Exports');
        if (!$this->files->exists($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }

        $exportPath = "{$path}/SalesExport.php";
        $content = <<<'S2be756560ea379b223d8515924d4ffad'
<?php

namespace App\Exports;

use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class SalesExport implements FromCollection, WithHeadings
{
    protected $filter;

    public function __construct($filter = null)
    {
        $this->filter = $filter;
    }

    public function collection()
    {
        $query = Sale::with(['customer', 'user']);

        if ($this->filter === 'daily') {
            $query->whereDate('sale_date', Carbon::today());
        } elseif ($this->filter === 'weekly') {
            $query->whereBetween('sale_date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($this->filter === 'monthly') {
            $query->whereMonth('sale_date', Carbon::now()->month);
        } elseif ($this->filter === 'yearly') {
            $query->whereYear('sale_date', Carbon::now()->year);
        }

        return $query->get()->map(function($sale) {
            return [
                'ID' => '#TRX-' . $sale->id,
                'Tanggal' => $sale->created_at->format('d M Y H:i'),
                'Customer' => $sale->customer->name ?? 'NON-MEMBER',
                'Produk' => $sale->sale_product,
                'Total Harga' => $sale->total_price,
                'Potongan Poin' => $sale->used_point,
                'Total Bayar' => $sale->total_payment,
                'Kembalian' => $sale->change,
                'Kasir' => $sale->user->name ?? $sale->user->email ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Tanggal & Waktu',
            'Customer',
            'Detail Produk (Qty : Harga)',
            'Total Harga (Rp)',
            'Potongan Poin (Rp)',
            'Total Bayar (Rp)',
            'Kembalian (Rp)',
            'Petugas Kasir',
        ];
    }
}
S2be756560ea379b223d8515924d4ffad;
        $this->files->put($exportPath, $content);
    }

    protected function createAllViews(): void
    {
        // Basic views
        $this->createLandingView();
        $this->createLoginView();
        $this->createAdminDashboardView();
        $this->createEmployeeDashboardView();
        $this->createErrorPermissionView();

        // Admin views
        $this->createAdminProductViews();
        $this->createAdminUserViews();
        $this->createAdminPurchasesViews();

        // Employee views
        $this->createEmployeeProductViews();
        $this->createEmployeePurchasesViews();

        // Components
        $this->createNavbarComponent();
    }

    protected function createAdminProductViews(): void
    {
        // Admin Product Index
                $content = <<<'S608bd62c4bf072a47d774299a9765e5d'
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
S608bd62c4bf072a47d774299a9765e5d;
        $this->files->put(resource_path('views/admin/product/index.blade.php'), $content);

        // Admin Product Create
                $content = <<<'Sd2a7700618f5490a4acc7fb8624b9307'
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
Sd2a7700618f5490a4acc7fb8624b9307;
        $this->files->put(resource_path('views/admin/product/create.blade.php'), $content);

        // Admin Product Edit
                $content = <<<'Sdc67a8511ffe4b65167a6901aa11aa2a'
@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Edit Produk</h4>
        <p class="text-muted small">Perbarui informasi produk: {{ $product->name }}</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold">Form Edit Produk</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('admin.ProductUpdate', $product->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Nama Produk</label>
                            <input type="text" class="form-control rounded-pill px-3 shadow-none" name="name" value="{{ $product->name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Harga Jual</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 rounded-start-pill px-3 text-muted">Rp</span>
                                <input type="number" class="form-control border-0 bg-light rounded-end-pill px-3 shadow-none" name="price" value="{{ $product->price }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">Stok</label>
                            <input type="number" class="form-control rounded-pill px-3 shadow-none" name="stock" value="{{ $product->stock }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">Gambar Produk</label>
                            <div class="bg-light rounded-4 p-4 text-center border-2 border-dashed border-muted position-relative overflow-hidden" id="drop-area">
                                <div id="preview-container" class="{{ $product->image ? '' : 'd-none' }} mb-3">
                                    <img id="image-preview" src="{{ $product->image ? asset('storage/' . $product->image) : '#' }}" alt="Preview" class="img-fluid rounded-3 shadow-sm mx-auto" style="max-height: 200px; object-fit: cover;">
                                    <div id="file-name" class="mt-2 fw-bold text-primary small">{{ $product->image ? 'Gambar saat ini' : '' }}</div>
                                </div>
                                <div id="upload-placeholder" class="{{ $product->image ? 'd-none' : '' }}">
                                    <i class="bi bi-cloud-arrow-up fs-2 text-primary"></i>
                                    <div class="mt-2 text-muted small">Klik atau tarik file untuk ganti gambar</div>
                                </div>
                                <input type="file" class="form-control position-absolute top-0 start-0 w-100 h-100 opacity-0" name="image" id="image-input" accept="image/*" style="cursor: pointer;">
                            </div>
                        </div>
                        <div class="col-12 mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm fw-bold">
                                Update Produk <i class="bi bi-check-lg ms-2"></i>
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
        }
    });
</script>
@endpush

<style>
    .border-dashed { border-style: dashed !important; }
    .extra-small { font-size: 0.75rem; }
</style>
@endsection
Sdc67a8511ffe4b65167a6901aa11aa2a;
        $this->files->put(resource_path('views/admin/product/edit.blade.php'), $content);
    }

    protected function createAdminUserViews(): void
    {
        // Admin User Index
        $content = <<<'ADMIN_USER_INDEX_BLADE'
@extends('layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Kelola User</h4>
        <p class="text-muted small">Total ada {{ $users->count() }} user terdaftar di sistem.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <a href="{{ route('admin.UserCreate') }}" class="btn btn-primary rounded-pill shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Tambah User
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
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users ?? [] as $index => $user)
                    <tr>
                        <td class="ps-4 text-muted small">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: 600;">
                                    {{ strtoupper(substr($user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $user->name }}</div>
                                    <div class="text-muted extra-small">ID: #USER-{{ $user->id }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="text-dark small">{{ $user->email }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $user->role == 'admin' ? 'bg-primary bg-opacity-10 text-primary' : 'bg-success bg-opacity-10 text-success' }} rounded-pill px-3 fw-bold">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="text-center pe-4">
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <a href="{{ route('admin.UserEdit', $user->id) }}" class="btn btn-sm btn-outline-warning rounded-circle d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px;" title="Edit User">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.UserDelete', $user->id) }}"
                                      onsubmit="return confirm('Yakin hapus user ini?')" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px;" title="Hapus User">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted small">Belum ada user terdaftar</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
ADMIN_USER_INDEX_BLADE;
        $this->files->put(resource_path('views/admin/user/index.blade.php'), $content);

        // Admin User Create
        $content = <<<EOC
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>➕ Tambah User Baru</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.UserStore') }}">
@csrf
                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-control" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="employee">Employee</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan User</button>
                            <a href="{{ route('admin.UserHome') }}" class="btn btn-secondary">Kembali</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOC;
        $this->files->put(resource_path('views/admin/user/create.blade.php'), $content);

        // Admin User Edit
        $content = <<<EOC
@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>✏️ Edit User</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.UserUpdate', \$user->id) }}">
@csrf
@method('PATCH')
                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" name="name" value="{{ \$user->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="{{ \$user->email }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-control" name="role" required>
                                    <option value="admin" {{ \$user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="employee" {{ \$user->role == 'employee' ? 'selected' : '' }}>Employee</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="{{ route('admin.UserHome') }}" class="btn btn-secondary">Kembali</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOC;
        $this->files->put(resource_path('views/admin/user/edit.blade.php'), $content);
    }

    protected function createAdminPurchasesViews(): void
    {
        // Admin Purchases Index
        $content = <<<'ADMIN_PURCHASES_INDEX_BLADE'
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
ADMIN_PURCHASES_INDEX_BLADE;
        $this->files->put(resource_path('views/admin/purchases/index.blade.php'), $content);

        // Admin Purchases Export PDF
        $content = <<<EOC
<!DOCTYPE html>
<html>
<head>
    <title>Struk Pembelian - {{ \$sale->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h2>STORE</h2>
        <p>Struk Pembelian</p>
    </div>

    <div class="details">
        <p><strong>No. Transaksi:</strong> {{ \$sale->id }}</p>
        <p><strong>Tanggal:</strong> {{ \$sale->created_at->format('d/m/Y H:i') }}</p>
        <p><strong>Customer:</strong> {{ \$sale->customer->name ?? 'NON-MEMBER' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>Harga</th>
                <th>Qty</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
@foreach(\$sale->detail_sales as \$detail)
            <tr>
                <td>{{ \$detail->product->name }}</td>
                <td>Rp {{ number_format(\$detail->price, 0, ',', '.') }}</td>
                <td>{{ \$detail->quantity }}</td>
                <td>Rp {{ number_format(\$detail->sub_total, 0, ',', '.') }}</td>
            </tr>
@endforeach
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="3">TOTAL</td>
                <td>Rp {{ number_format(\$sale->total_price, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 50px; text-align: center;">
        <p>Terima Kasih Telah Berbelanja di Store!</p>
    </div>
</body>
</html>
EOC;
        $this->files->put(resource_path('views/admin/purchases/exportpdf.blade.php'), $content);
    }

    protected function createEmployeeProductViews(): void
    {
        // Employee Product Index
        $content = <<<EOC
@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Daftar Produk</h4>
        <p class="text-muted small">Lihat informasi produk dan ketersediaan stok.</p>
    </div>
</div>

<div class="row g-4">
    @forelse(\$products ?? [] as \$product)
    <div class="col-lg-3 col-md-4 col-sm-6">
        <div class="card h-100 border-0 shadow-sm hover-shadow transition-all">
            <div class="position-relative">
                @if(\$product->image)
                    <img src="{{ asset('storage/' . \$product->image) }}" class="card-img-top p-3 rounded-5" alt="{{ \$product->name }}" style="height: 180px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center rounded-top" style="height: 180px;">
                        <i class="bi bi-image text-muted fs-1"></i>
                    </div>
                @endif
                <div class="position-absolute top-0 end-0 m-3">
                    <span class="badge {{ \$product->stock < 10 ? 'bg-danger' : 'bg-success' }} rounded-pill px-3 shadow-sm">
                        Stok: {{ \$product->stock }}
                    </span>
                </div>
            </div>
            <div class="card-body p-4 pt-0">
                <h6 class="card-title fw-bold text-dark mb-1">{{ \$product->name }}</h6>
                <div class="text-muted extra-small mb-3">ID: #PROD-{{ \$product->id }}</div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-primary fw-bold">
                        Rp {{ number_format(\$product->price, 0, ',', '.') }}
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
EOC;
        $this->files->put(resource_path('views/employee/product/index.blade.php'), $content);
    }

    protected function createEmployeePurchasesViews(): void
    {
        // Employee Purchases Index
                $content = <<<'S7f9a126abf02c0278989a5b2c09f1367'
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
S7f9a126abf02c0278989a5b2c09f1367;
        $this->files->put(resource_path('views/employee/purchases/index.blade.php'), $content);

        // Employee Purchases Create
                $content = <<<'S604e5617ed9f19c770de711c28da22b2'
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
S604e5617ed9f19c770de711c28da22b2;
        $this->files->put(resource_path('views/employee/purchases/create.blade.php'), $content);

        // Employee Purchases Payment
                $content = <<<'Sec79c884ce12594f8c5995c27a582ddf'
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
Sec79c884ce12594f8c5995c27a582ddf;
        $this->files->put(resource_path('views/employee/purchases/payment.blade.php'), $content);

        // Employee Purchases Member
                $content = <<<'S799318112da962f04df5e4c536591121'
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
S799318112da962f04df5e4c536591121;
        $this->files->put(resource_path('views/employee/purchases/member.blade.php'), $content);

        // Employee Purchases Print
                $content = <<<'S72a8b3288f3787cb39b02ae55aa15e3a'
@extends('layouts.app')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h4 class="fw-bold mb-1">Invoice Transaksi</h4>
        <p class="text-muted small">Transaksi #TRX-{{ $sale->id }} berhasil diselesaikan.</p>
    </div>
    <div class="col-md-6 text-md-end">
        <div class="btn-group">
            <a href="{{ route('employee.ExportPDF', $sale->id) }}" class="btn btn-primary rounded-start-pill px-4 shadow-sm">
                <i class="bi bi-download me-2"></i> Unduh PDF
            </a>
            <a href="{{ route('employee.SaleIndex') }}" class="btn btn-outline-primary rounded-end-pill px-4">
                Daftar Penjualan <i class="bi bi-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-body p-0">
                <!-- Header Invoice -->
                <div class="bg-primary bg-opacity-10 p-4 border-bottom border-primary border-opacity-10">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-3 mb-md-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="bi bi-receipt fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-primary">STRUK PENJUALAN</h5>
                                    <div class="text-muted extra-small">ID: #TRX-{{ $sale->id }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($sale->sale_date)->format('d F Y') }}</div>
                            <div class="text-muted small">Waktu: {{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }} WIB</div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="row mb-3 g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Detail Pelanggan</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                @if ($sale->customer)
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                        <div class="fw-bold text-dark">{{ $sale->customer->name }}</div>
                                    </div>
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-telephone me-2"></i>{{ $sale->customer->phone }}
                                    </div>
                                    <div class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3">Member Store</div>
                                @else
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                            <i class="bi bi-person-x text-muted"></i>
                                        </div>
                                        <div class="fw-bold text-muted">NON-MEMBER</div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2 d-block">Informasi Kasir</label>
                            <div class="bg-light rounded-4 p-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-white rounded-circle p-2 me-2 shadow-sm">
                                        <i class="bi bi-person-badge text-primary"></i>
                                    </div>
                                    <div class="fw-bold text-dark">{{ $sale->user->name ?? $sale->user->email ?? '-' }}</div>
                                </div>
                                <div class="text-muted small">
                                    <i class="bi bi-clock me-2"></i>Selesai pada {{ $sale->updated_at->format('H:i:s') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr class="extra-small text-uppercase text-muted">
                                    <th class="ps-3">Produk</th>
                                    <th class="text-center">Harga Satuan</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end pe-3">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detail_sale as $data)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">{{ $data->product->name }}</div>
                                        </td>
                                        <td class="text-center text-muted">
                                            Rp {{ number_format($data->product->price, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border rounded-pill px-3">{{ $data->quantity }}</span>
                                        </td>
                                        <td class="text-end pe-3 fw-bold text-dark">
                                            Rp {{ number_format($data->sub_total, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="row justify-content-end">
                        <div class="col-md-5">
                            <div class="bg-light rounded-4 p-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Subtotal</span>
                                    <span class="fw-bold text-dark">Rp {{ number_format($sale->total_price + ($sale->used_point ?? 0), 0, ',', '.') }}</span>
                                </div>
                                @if($sale->used_point > 0)
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span class="small">Potongan Poin</span>
                                    <span class="fw-bold">- Rp {{ number_format($sale->used_point, 0, ',', '.') }}</span>
                                </div>
                                @endif
                                <hr class="my-3 opacity-10">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-bold text-primary">TOTAL AKHIR</span>
                                    <span class="fw-bold text-primary fs-4">Rp {{ number_format($sale->total_price, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted extra-small">DIBAYAR</span>
                                    <span class="fw-medium text-dark small">Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted extra-small">KEMBALIAN</span>
                                    <span class="fw-bold text-success small">Rp {{ number_format($sale->change, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-light p-4 text-center border-top">
                    <p class="text-muted small mb-0">Terima kasih telah berbelanja di toko kami. Simpan struk ini sebagai bukti transaksi yang sah.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .extra-small { font-size: 0.75rem; }
</style>
@endsection
S72a8b3288f3787cb39b02ae55aa15e3a;
        $this->files->put(resource_path('views/employee/purchases/print.blade.php'), $content);

        // Employee Purchases Export PDF
                $content = <<<'S9bd8acc38665b3de05ec54d54574a15f'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Struk Penjualan</title>
  <style>
    body { font-family: 'Arial', sans-serif; font-size: 12px; background: #f8f9fa; }
    #receipt { background: #fff; padding: 25px; margin: 0 auto; width: 600px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); border-radius: 8px; }
    h2 { font-size: 1.5rem; margin: 0; text-align: center; color: #333; }
    small { font-size: 11px; color: #555; }
    .info { display: flex; justify-content: space-between; margin: 20px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 8px 10px; text-align: right; }
    th { background-color: #e9ecef; font-size: 11px; color: #333; border: 1px solid #dee2e6; }
    td { font-size: 11px; border: 1px solid #dee2e6; }
    .total-row td { font-weight: bold; background-color: #f1f3f5; }
    #legalcopy { text-align: center; margin-top: 30px; }
    .legal { font-size: 11px; color: #333; }
    .highlight { color: #007bff; font-weight: bold; }
    .logo { text-align: center; margin-bottom: 20px; }
    .store-info { text-align: center; font-size: 12px; margin-top: 15px; }
  </style>
</head>
<body>
    @php
        $imagePath = public_path('assets/images/store.jpeg');
        $src = '';
        if (file_exists($imagePath)) {
            $imageData = base64_encode(file_get_contents($imagePath));
            $src = 'data:' . mime_content_type($imagePath) . ';base64,' . $imageData;
        }
    @endphp
    <div id="receipt">
        <div class="logo">@if($src)<img src="{{ $src }}" width="100">@endif</div>
        <h2>Store</h2>
        <div class="store-info">
            <small>Telp: 081234098765<br>Jl. Raya Puncak</small>
        </div>
        <div class="info">
            <div>
                <small>Status: <span class="highlight">{{ $sale->customer ? 'Member' : 'Non-Member' }}</span><br>
                Poin: {{ $sale->customer ? $sale->customer->point : '-' }}</small>
            </div>
            <div>
                <small>Kasir: {{ $sale->user->name ?? $sale->user->email ?? '-' }}<br>
                Tanggal: {{ \Carbon\Carbon::parse($sale->sale_date)->format('d M Y') }}</small>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Item</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($detail_sale as $item)
                <tr>
                    <td style="text-align: left;">{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rp {{ number_format($item->product->price, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($item->sub_total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-row"><td colspan="3">Total</td><td>Rp {{ number_format($sale->total_price + $sale->used_point, 0, ',', '.') }}</td></tr>
                @if($sale->used_point > 0)<tr class="total-row"><td colspan="3">Poin Digunakan</td><td>Rp {{ number_format($sale->used_point, 0, ',', '.') }}</td></tr>@endif
                <tr class="total-row"><td colspan="3">Total Bayar</td><td>Rp {{ number_format($sale->total_payment, 0, ',', '.') }}</td></tr>
                <tr class="total-row"><td colspan="3">Kembalian</td><td>Rp {{ number_format($sale->change, 0, ',', '.') }}</td></tr>
            </tbody>
        </table>
        <div id="legalcopy">
            <p class="legal">Invoice: #{{ $sale->id }}</p>
            <p class="legal">~ Terima kasih ~</p>
        </div>
    </div>
</body>
</html>
S9bd8acc38665b3de05ec54d54574a15f;
        $this->files->put(resource_path('views/employee/purchases/exportpdf.blade.php'), $content);
    }

    protected function createNavbarComponent(): void
    {
                $content = <<<'Sfa28630c93f02d7fb618d66ec3e99213'
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/">
            <div class="bg-primary text-white rounded-3 p-2 me-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="bi bi-shop fs-5"></i>
            </div>
            <span>Store</span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto ms-lg-4">
                @auth
                    @if(Auth::user()->role == 'admin')
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('admin.ProductHome*') ? 'active' : '' }}" href="{{ route('admin.ProductHome') }}">
                                <i class="bi bi-box-seam me-1"></i> Produk
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('admin.SaleHome*') ? 'active' : '' }}" href="{{ route('admin.SaleHome') }}">
                                <i class="bi bi-receipt me-1"></i> Penjualan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('admin.UserHome*') ? 'active' : '' }}" href="{{ route('admin.UserHome') }}">
                                <i class="bi bi-people me-1"></i> User
                            </a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('employee.dashboard') ? 'active' : '' }}" href="{{ route('employee.dashboard') }}">
                                <i class="bi bi-speedometer2 me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('employee.ProductIndex*') ? 'active' : '' }}" href="{{ route('employee.ProductIndex') }}">
                                <i class="bi bi-box-seam me-1"></i> Produk
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ Route::is('employee.SaleIndex*') ? 'active' : '' }}" href="{{ route('employee.SaleIndex') }}">
                                <i class="bi bi-cart3 me-1"></i> Penjualan
                            </a>
                        </li>
                    @endif
                @endauth
            </ul>

            <ul class="navbar-nav align-items-lg-center">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center bg-light rounded-pill px-3 py-2" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px; font-size: 0.75rem;">
                                {{ strtoupper(substr(Auth::user()->name ?? '?', 0, 1)) }}
                            </div>
                            <span class="text-truncate d-inline-block" style="max-width: 10rem;" title="{{ Auth::user()->email }}">{{ Auth::user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <div class="small text-muted">Role</div>
                                <div class="fw-bold text-primary small">{{ ucfirst(Auth::user()->role) }}</div>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 text-danger" href="{{ route('logout') }}">
                                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="btn btn-primary rounded-pill px-4" href="{{ route('login') }}">Login</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
Sfa28630c93f02d7fb618d66ec3e99213;
        $this->files->put(resource_path('views/components/navbar.blade.php'), $content);
    }

    protected function updateProductController(): void
    {
        $controllerPath = app_path('Http/Controllers/ProductController.php');
        $content = $this->files->get($controllerPath);

        // Add necessary imports
        $imports = [
            "use App\Models\Product;",
            "use Illuminate\Support\Facades\Storage;",
            "use Illuminate\Support\Facades\Auth;",
        ];

        foreach ($imports as $import) {
            if (!str_contains($content, $import)) {
                $content = str_replace(
                    "use Illuminate\Http\Request;",
                    "use Illuminate\Http\Request;\n" . $import,
                    $content
                );
            }
        }

        $methods = <<<'Sab7580302ccb259f48e954d0a65c0664'
    // Admin methods
    public function index()
    {
        $products = Product::all();
        return view('admin.product.index', compact('products'));
    }

    public function create()
    {
        return view('admin.product.create');
    }

    public function store(Request $request)
    {
        $removeRP = str_replace(['RP. ', '.'], '', $request->price);
        $request->merge(['price' => $removeRP]);

        $request->validate([
            'name' => 'required|min:3',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif',
            'price' => 'required|numeric|min:1',
            'stock' => 'required|numeric|min:1'
        ]);

        Product::create([
            'name' => $request->name,
            'image' => $request->hasFile('image') ? $request->file('image')->store('product-images', 'public') : null,
            'price' => $request->price,
            'stock' => $request->stock
        ]);

        return redirect()->route('admin.ProductHome')->with('success', 'Product added successfully!');
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view('admin.product.edit', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $removeRP = str_replace(['RP. ', '.'], '', $request->price);
        $request->merge(['price' => $removeRP]);

        $request->validate([
            'name' => 'required|min:3',
            'image' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:5120',
            'price' => 'required|numeric|min:1',
        ]);

        $product = Product::findOrFail($id);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $product->image = $request->file('image')->store('product-images', 'public');
        }

        $product->name = $request->name;
        $product->price = $request->price;
        $product->save();

        return redirect()->route('admin.ProductHome')->with('success', 'Product edited successfully!');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        
        // In current project, it checks if product is listed in detail_sales
        if ($product->detail_sales()->exists()) {
            return redirect()->back()->with('failed', 'Product is already listed with purchase!');
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();
        return redirect()->route('admin.ProductHome')->with('success', 'Product deleted successfully!');
    }

    public function updateStock(Request $request, $id)
    {
        $request->validate([
            'stock' => 'required|integer|min:0'
        ]);

        $product = Product::findOrFail($id);
        $product->update(['stock' => $request->stock]);

        return redirect()->route('admin.ProductHome')->with('success', 'Stok berhasil diupdate!');
    }

    // Employee methods
    public function employeeIndex()
    {
        $products = Product::all();
        return view('employee.product.index', compact('products'));
    }
Sab7580302ccb259f48e954d0a65c0664;

        // Insert methods before closing brace
        $content = str_replace('}', $methods . "\n}", $content);
        $this->files->put($controllerPath, $content);
    }

    protected function updateSaleController(): void
    {
        $controllerPath = app_path('Http/Controllers/SaleController.php');
        $content = $this->files->get($controllerPath);

        // Add necessary imports
        $imports = [
            "use App\Models\Sale;",
            "use App\Models\Product;",
            "use App\Models\Detail_sale;",
            "use App\Models\Customers;",
            "use Carbon\Carbon;",
            "use Maatwebsite\Excel\Facades\Excel;",
            "use App\Exports\SalesExport;",
            "use Illuminate\Support\Facades\Auth;",
            "use Barryvdh\DomPDF\Facade\Pdf;",
        ];

        foreach ($imports as $import) {
            if (!str_contains($content, $import)) {
                $content = str_replace(
                    "use Illuminate\Http\Request;",
                    "use Illuminate\Http\Request;\n" . $import,
                    $content
                );
            }
        }

        $methods = <<<'S0ba810714a55681a5e596a0fe42d8b16'
    // Admin methods
    public function adminIndex(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $query = Sale::with(['customer', 'user']);

        if ($start_date && $end_date) {
            $query->whereBetween('created_at', [
                Carbon::parse($start_date)->startOfDay(),
                Carbon::parse($end_date)->endOfDay()
            ]);
        }

        $sales = $query->latest()->get();
        $detail_sales = Detail_sale::with(['product'])->get();
        return view('admin.purchases.index', compact('sales', 'detail_sales', 'start_date', 'end_date'));
    }

    public function exportPDFAd($id)
    {
        $sale = Sale::with(['customer', 'user', 'detail_sales.product'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        $data = ['sale' => $sale, 'detail_sale' => $detail_sale];
        $pdf = Pdf::loadView('admin.purchases.exportpdf', $data);
        return $pdf->download('receipt.pdf');
    }

    // Employee methods
    public function SaleIndex(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
     
        $query = Sale::with(['customer', 'user']);
     
        if ($start_date && $end_date) {
            $query->whereBetween('created_at', [
                Carbon::parse($start_date)->startOfDay(),
                Carbon::parse($end_date)->endOfDay()
            ]);
        }
     
        $sale = $query->latest()->get();
        $detail_sale = Detail_sale::with('product')->get();
     
        return view('employee.purchases.index', compact('sale', 'detail_sale', 'start_date', 'end_date'));
    }

    public function create()
    {
        $product = Product::where('stock', '>', 0)->get();
        return view('employee.purchases.create', compact('product'));
    }

    public function store(Request $request)
    {
        $products = $request->products;
        if (empty($products)) {
            return redirect()->back()->with('failed', 'Please choose product at least 1.');
        }
    
        $data['products'] = [];
        $data['total'] = 0;
    
        foreach ($products as $productStr) {
            $parts = explode(';', $productStr);
            $id = $parts[0];
            $name = $parts[1];
            $price = (float) str_replace(['Rp', '.', ','], '', $parts[2]);
            $quantity = (int) $parts[3];
            $subtotal = $price * $quantity;
    
            $data['products'][] = [
                'product_id' => $id,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ];
            $data['total'] += $subtotal;
        }
    
        return view('employee.purchases.payment', $data);
    }

    public function paymentProcess(Request $request)
    {
        $products = $request->shop;
        $sale_product = [];
        $total_pay = (int)str_replace(['Rp. ', '.'], '', $request->total_payment);
        $total = (int)str_replace(['Rp. ', '.'], '', $request->total);
        $customer_id = null;

        if ($request->customer == 'Member') {
            $phone = $request->phone;
            $member_name = $request->member_name;
            $existCustomer = Customers::where('phone', $phone)->first();

            if ($existCustomer) {
                $updateData = ['point' => $existCustomer->point + ($total / 100)];
                if ($member_name) {
                    $updateData['name'] = $member_name;
                }
                $existCustomer->update($updateData);
                $customer_id = $existCustomer->id;
            } else {
                $newCustomer = Customers::create([
                    'name' => $member_name ?? 'Member Baru',
                    'phone' => $phone,
                    'point' => 0, // Pembelian pertama tidak dapet poin
                ]);
                $customer_id = $newCustomer->id;
            }
        }

        $sale = Sale::create([
            'sale_date' => now(),
            'customer_id' => $customer_id,
            'total_price' => $total,
            'total_payment' => $total_pay,
            'change' => $total_pay - $total,
            'user_id' => Auth::user()->id,
            'sale_product' => '',
            'used_point' => 0
        ]);

        foreach ($products as $productStr) {
            $parts = explode(';', $productStr);
            $id = $parts[0];
            $name = $parts[1];
            $price = number_format($parts[2], 0, ',', '.');
            $quantity = (int)$parts[3];
            $subtotal = (int)$parts[4];

            $sale_product[] = "{$name} ( {$quantity} : Rp. {$price} )";

            $productModel = Product::find($id);
            if ($productModel) {
                $productModel->decrement('stock', $quantity);
            }

            Detail_sale::create([
                'sale_id' => $sale->id,
                'product_id' => $id,
                'quantity' => $quantity,
                'sub_total' => $subtotal,
            ]);
        }

        $sale->update(['sale_product' => implode(' , ', $sale_product)]);

        if ($request->customer == 'Member') {
            return redirect()->route('employee.EditMember', $sale->id);
        }

        return redirect()->route('employee.DetPrint', $sale->id)->with('success', 'Transaksi Berhasil!');
    }

    public function EditMember($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        return view('employee.purchases.member', compact('sale'));
    }

    public function member(Request $request, $id)
    {
        $customer = Customers::findOrFail($request->customer_id);
        $customer->update(['name' => $request->name]);

        $sale = Sale::findOrFail($id);

        if ($request->check_point == 'Ya') {
            $used_point = $customer->point;
            $customer->update(['point' => 0]);

            $sale->used_point = $used_point;
            $sale->total_price -= $used_point;
            $sale->change = $sale->total_payment - $sale->total_price;
        }

        $sale->save();

        return redirect()->route('employee.DetPrint', $sale->id)->with('success', 'Data Member Berhasil Diperbarui!');
    }

    public function print($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        return view('employee.purchases.print', compact('sale', 'detail_sale'));
    }

    public function exportPDF($id)
    {
        $sale = Sale::with(['customer', 'user'])->findOrFail($id);
        $detail_sale = Detail_sale::where('sale_id', $sale->id)->with('product')->get();
        $data = ['sale' => $sale, 'detail_sale' => $detail_sale];
        $pdf = Pdf::loadView('employee.purchases.exportpdf', $data);
        return $pdf->download('receipt.pdf');
    }

    public function Excel(Request $request)
    {
        return Excel::download(new SalesExport($request->filter), 'sale_export.xlsx');
    }
S0ba810714a55681a5e596a0fe42d8b16;

        // Insert methods before closing brace
        $content = str_replace('}', $methods . "\n}", $content);
        $this->files->put($controllerPath, $content);
    }

    protected function updateCompleteRoutes(): void
    {
        $routePath = base_path('routes/web.php');

        // Untuk type=app, kita timpa seluruh file routes/web.php
        // agar route default Laravel (Welcome) hilang dan diganti dengan route Kasir kita.
                $completeRoutes = <<<'S722614130dc4015672c96c761979b1f7'
<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('IsGuest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');

    Route::post('/login', [UserController::class, 'loginAuth'])->name('login.auth');
});

Route::get('/', function () {
    return view('landing');
})->name('landingpage');

Route::get('/logout', [UserController::class, 'logout'])->name('logout');

Route::get('/error-permission', function() {
    return view('error.permission');
})->name('error.permission');

//admin
Route::middleware('IsLogin', 'IsAdmin')->prefix('admin')->name('admin.')->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboardAdmin'])->name('dashboard');

    //product
    Route::prefix('/product')->group(function() {
        Route::get('/', [ProductController::class, 'index'])->name('ProductHome');
        Route::get('/create', [ProductController::class, 'create'])->name('ProductCreate');
        Route::post('/store', [ProductController::class, 'store'])->name('ProductStore');
        Route::get('/{id}', [ProductController::class, 'edit'])->name('ProductEdit');
        Route::patch('/{id}', [ProductController::class, 'update'])->name('ProductUpdate');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->name('ProductDelete');
        Route::put('/stock/{id}', [ProductController::class, 'updateStock'])->name('ProductStock');
    });

    //user
    Route::prefix('/user')->group(function() {
        Route::get('/', [UserController::class, 'index'])->name('UserHome');
        Route::get('/create', [UserController::class, 'create'])->name('UserCreate');
        Route::post('/store', [UserController::class, 'store'])->name('UserStore');
        Route::get('/{id}', [UserController::class, 'edit'])->name('UserEdit');
        Route::patch('/{id}', [UserController::class, 'update'])->name('UserUpdate');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('UserDelete');
    });

    //purchases
    Route::prefix('/sale')->group(function() {
        Route::get('/', [SaleController::class, 'adminIndex'])->name('SaleHome');
        Route::get('/print/{id}', [SaleController::class, 'exportPDFAd'])->name('exportPDFAd');
        Route::get('/excel', [SaleController::class, 'Excel'])->name('Excel');
    });
});

//employee
Route::middleware('IsLogin', 'IsEmployee')->prefix('employee')->name('employee.')->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboardEmployee'])->name('dashboard');

    //product
    Route::prefix('/product')->group(function() {
        Route::get('/', [ProductController::class, 'employeeIndex'])->name('ProductIndex');
    });

    //purchases
    Route::prefix('/sale')->group(function() {
        Route::get('/', [SaleController::class, 'SaleIndex'])->name('SaleIndex');
        Route::get('/create', [SaleController::class, 'create'])->name('SaleCreate');
        Route::post('/store', [SaleController::class, 'store'])->name('SaleStore');
        Route::post('/payment-proses', [SaleController::class, 'paymentProcess'])->name('paymentProcess');
        Route::get('/member-edit/{id}', [SaleController::class, 'EditMember'])->name('EditMember');
        Route::post('/member/{id}', [SaleController::class, 'member'])->name('Member');
        Route::get('/detail-print/{id}', [SaleController::class, 'print'])->name('DetPrint');
        Route::get('/print/{id}', [SaleController::class, 'exportPDF'])->name('ExportPDF');
        Route::get('/excel', [SaleController::class, 'Excel'])->name('Excel');
    });
});
S722614130dc4015672c96c761979b1f7;

        $this->files->put($routePath, $completeRoutes);
    }

    protected function updateUserController(): void
    {
        $controllerPath = app_path('Http/Controllers/UserController.php');
        $content = $this->files->get($controllerPath);

        // Add necessary imports
        $imports = [
            "use Illuminate\Support\Facades\Auth;",
            "use App\Models\User;",
        ];

        foreach ($imports as $import) {
            if (!str_contains($content, $import)) {
                $content = str_replace(
                    "use Illuminate\Http\Request;",
                    "use Illuminate\Http\Request;\n" . $import,
                    $content
                );
            }
        }

        $methods = <<<'S9ed7e430b3cd6914bd3455ddebf7e0dc'
    public function loginAuth(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            if ($user->role == 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role == 'employee') {
                return redirect()->route('employee.dashboard');
            }
        }

        return redirect()->back()->with('error', 'Email atau password salah.');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('landingpage');
    }

    public function dashboardAdmin()
    {
        return view('admin.dashboard.index');
    }

    public function dashboardEmployee()
    {
        return view('employee.dashboard.index');
    }

    public function index()
    {
        $users = User::all();
        return view('admin.user.index', compact('users'));
    }

    public function create()
    {
        return view('admin.user.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,employee'
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role
        ]);

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil ditambahkan!');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        return view('admin.user.edit', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,employee'
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role
        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil diupdate!');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('admin.UserHome')->with('success', 'User berhasil dihapus!');
    }
S9ed7e430b3cd6914bd3455ddebf7e0dc;

        // Add methods to controller
        if (!str_contains($content, 'public function loginAuth')) {
            $content = preg_replace('/}\s*$/', $methods . "\n}", $content);
        }

        $this->files->put($controllerPath, $content);
    }

    protected function updateCustomersController(): void
    {
        $controllerPath = app_path('Http/Controllers/CustomersController.php');
        $content = $this->files->get($controllerPath);

        $methods = <<<'S9e6987e04d94e20fd00a86fd183b6767'
    public function create()
    {
        return view('employee.purchases.member');
    }
S9e6987e04d94e20fd00a86fd183b6767;

        // Insert methods before closing brace
        $content = preg_replace('/}\s*$/', $methods . "\n}", $content);
        $this->files->put($controllerPath, $content);
    }

    protected function createLandingView(): void
    {
                $content = <<<'Sa31069b3aa18af1b6d650e0a5f941824'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store - Modern POS System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: #1f2937;
            margin: 0;
            overflow-x: hidden;
        }
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background: radial-gradient(circle at top right, #eef2ff 0%, #ffffff 50%);
            position: relative;
        }
        .hero-content {
            z-index: 2;
        }
        .badge-new {
            background-color: #eef2ff;
            color: #4f46e5;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }
        .text-gradient {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-description {
            font-size: 1.25rem;
            color: #4b5563;
            margin-bottom: 2.5rem;
            max-width: 540px;
        }
        .btn-primary-custom {
            background-color: #4f46e5;
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
        }
        .btn-primary-custom:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.4);
        }
        .hero-image-container {
            position: relative;
        }
        .hero-card-ui {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            transform: rotate(-2deg);
            border: 1px solid #f3f4f6;
        }
        .floating-icon {
            position: absolute;
            width: 64px;
            height: 64px;
            background: white;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            font-size: 1.5rem;
            animation: floating 3s ease-in-out infinite;
        }
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top bg-white bg-opacity-75 backdrop-blur">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-shop me-2"></i> Store
            </a>
            <div class="ms-auto">
                <a href="{{ route('login') }}" class="btn btn-outline-primary rounded-pill px-4 fw-600">Masuk</a>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="badge-new">POS System Version 2.0</div>
                    <h1>Kelola Store Anda Lebih <span class="text-gradient">Cepat & Modern</span></h1>
                    <p class="hero-description">
                        Solusi manajemen kasir pintar untuk bisnis Anda. Pantau stok, catat penjualan, dan cetak laporan hanya dalam satu aplikasi yang elegan.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="{{ route('login') }}" class="btn-primary-custom">
                            Mulai Sekarang <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="hero-image-container">
                        <div class="hero-card-ui">
                            <div class="d-flex justify-content-between mb-4">
                                <div class="h-4 bg-light rounded w-25"></div>
                                <div class="h-4 bg-primary bg-opacity-10 rounded w-25"></div>
                            </div>
                            <div class="space-y-3">
                                <div class="bg-light rounded-3 p-3 mb-3 d-flex align-items-center">
                                    <div class="bg-white rounded-2 p-2 me-3 shadow-sm"><i class="bi bi-cart text-primary"></i></div>
                                    <div class="flex-grow-1 bg-white bg-opacity-50 h-2 rounded"></div>
                                </div>
                                <div class="bg-light rounded-3 p-3 mb-3 d-flex align-items-center">
                                    <div class="bg-white rounded-2 p-2 me-3 shadow-sm"><i class="bi bi-graph-up text-success"></i></div>
                                    <div class="flex-grow-1 bg-white bg-opacity-50 h-2 rounded"></div>
                                </div>
                                <div class="bg-light rounded-3 p-3 d-flex align-items-center">
                                    <div class="bg-white rounded-2 p-2 me-3 shadow-sm"><i class="bi bi-people text-warning"></i></div>
                                    <div class="flex-grow-1 bg-white bg-opacity-50 h-2 rounded"></div>
                                </div>
                            </div>
                        </div>
                        <div class="floating-icon" style="top: -20px; right: 20px; color: #4f46e5;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div class="floating-icon" style="bottom: -20px; left: 40px; color: #10b981; animation-delay: 1s;">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
Sa31069b3aa18af1b6d650e0a5f941824;

        $this->files->put(resource_path('views/landing.blade.php'), $content);
    }

    protected function createLoginView(): void
    {
                $content = <<<'Se78188304d79ee45ceed9895e86f526e'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-card {
            background: white;
            border-radius: 1.25rem;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
        }
        .brand-logo {
            width: 48px;
            height: 48px;
            background: #4f46e5;
            color: white;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.5rem;
        }
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
        }
        .form-control:focus {
            background-color: white;
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        .btn-login {
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-card text-center">
        <div class="brand-logo">
            <i class="bi bi-shop"></i>
        </div>
        <h4 class="fw-bold mb-1">Selamat Datang</h4>
        <p class="text-muted small mb-4">Silakan masuk ke akun Anda</p>

        @if(session('error'))
            <div class="alert alert-danger border-0 small py-2 mb-4">
                <i class="bi bi-exclamation-circle me-2"></i> {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.auth') }}" class="text-start">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control shadow-none" name="email" placeholder="nama@email.com" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" class="form-control shadow-none" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-login">Masuk ke Sistem</button>
        </form>
        
        <div class="mt-4 pt-2">
            <a href="/" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>
Se78188304d79ee45ceed9895e86f526e;

        $this->files->put(resource_path('views/login.blade.php'), $content);
    }

    protected function createAdminDashboardView(): void
    {
                                $content = <<<'Sdc47a642e41e68d8361e1e78da9f641b'
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
Sdc47a642e41e68d8361e1e78da9f641b;

        $this->files->put(resource_path('views/admin/dashboard/index.blade.php'), $content);
    }

    protected function createEmployeeDashboardView(): void
    {
        $content = <<<EOC
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
                        <h3 class="fw-bold mb-0">{{ \\App\\Models\\Sale::whereDate('created_at', today())->count() }}</h3>
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
                        <h3 class="fw-bold mb-0">Rp {{ number_format(\\App\\Models\\Sale::whereDate('created_at', today())->sum('total_price'), 0, ',', '.') }}</h3>
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
EOC;

        $this->files->put(resource_path('views/employee/dashboard/index.blade.php'), $content);
    }

    protected function createErrorPermissionView(): void
    {
        $content = <<<EOC
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h4>Access Denied</h4>
                </div>
                <div class="card-body text-center">
                    <h5>Anda tidak memiliki izin untuk mengakses halaman ini.</h5>
                    <a href="{{ route('landingpage') }}" class="btn btn-primary">Kembali ke Beranda</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
EOC;

        $this->files->put(resource_path('views/error/permission.blade.php'), $content);
    }

    protected function updateAppRoutes(): void
    {
        $routePath = base_path('routes/web.php');
        $content = $this->files->get($routePath);

        // Add imports if not exist
        $imports = [
            "use App\\Http\\Controllers\\UserController;",
            "use Illuminate\\Support\\Facades\\Route;",
        ];

        foreach ($imports as $import) {
            if (strpos($content, $import) === false) {
                $content = $import . "\n" . $content;
            }
        }

        // Add basic routes if not exist
        $basicRoutes = <<<EOC

Route::middleware('IsGuest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');

    Route::post('/login', [UserController::class, 'loginAuth'])->name('login.auth');
});

Route::get('/', function () {
    return view('landing');
})->name('landingpage');

Route::get('/logout', [UserController::class, 'logout'])->name('logout');

Route::get('/error-permission', function() {
    return view('error.permission');
})->name('error.permission');

//admin
Route::middleware('IsLogin', 'IsAdmin')->prefix('admin')->name('admin.')->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboardAdmin'])->name('dashboard');
});

//employee
Route::middleware('IsLogin', 'IsEmployee')->prefix('employee')->name('employee.')->group(function() {
    Route::get('/dashboard', [UserController::class, 'dashboardEmployee'])->name('dashboard');
});

EOC;

        // Only add if routes don't exist
        if (strpos($content, "Route::get('/', function ()") === false) {
            $content .= "\n" . $basicRoutes;
        }

        $this->files->put($routePath, $content);
    }

    protected function resetProject(): void
    {
        if (!$this->confirm('Apakah Anda yakin ingin mereset project? Semua file yang di-generate akan dihapus!')) {
            return;
        }

        $this->info("Resetting project to default...");

        // 1. Delete Controllers
        $controllers = ['UserController.php', 'ProductController.php', 'SaleController.php', 'CustomersController.php'];
        foreach ($controllers as $controller) {
            $path = app_path("Http/Controllers/{$controller}");
            if ($this->files->exists($path)) {
                $this->files->delete($path);
                $this->info("✓ Deleted controller: {$controller}");
            }
        }

        // 2. Delete Models
        $models = ['Product.php', 'Sale.php', 'Customers.php', 'Detail_sale.php'];
        foreach ($models as $model) {
            $path = app_path("Models/{$model}");
            if ($this->files->exists($path)) {
                $this->files->delete($path);
                $this->info("✓ Deleted model: {$model}");
            }
        }

        // 3. Delete Views
        $viewDirs = ['admin', 'employee', 'layouts', 'components', 'landing', 'login', 'error'];
        foreach ($viewDirs as $dir) {
            $path = resource_path("views/{$dir}");
            if ($this->files->exists($path)) {
                $this->files->deleteDirectory($path);
                $this->info("✓ Deleted view directory: {$dir}");
            }
        }

        // 4. Delete Middlewares
        $middlewares = ['IsLogin.php', 'IsGuest.php', 'IsAdmin.php', 'IsEmployee.php'];
        foreach ($middlewares as $mw) {
            $path = app_path("Http/Middleware/{$mw}");
            if ($this->files->exists($path)) {
                $this->files->delete($path);
                $this->info("✓ Deleted middleware: {$mw}");
            }
        }

        // 5. Delete Seeders
        $seeders = ['UserSeeder.php'];
        foreach ($seeders as $seeder) {
            $path = database_path("seeders/{$seeder}");
            if ($this->files->exists($path)) {
                $this->files->delete($path);
                $this->info("✓ Deleted seeder: {$seeder}");
            }
        }

        // 6. Delete Exports
        $exportsPath = app_path('Exports');
        if ($this->files->exists($exportsPath)) {
            $this->files->deleteDirectory($exportsPath);
            $this->info("✓ Deleted Exports directory");
        }

        // 7. Delete Migrations
        $migrationFiles = $this->files->files(database_path('migrations'));
        $targets = ['_products_table', '_sales_table', '_customers_table', '_detail_sales_table'];
        foreach ($migrationFiles as $file) {
            foreach ($targets as $target) {
                if (str_contains($file->getFilename(), $target)) {
                    $this->files->delete($file->getPathname());
                    $this->info("✓ Deleted migration: " . $file->getFilename());
                }
            }
        }

        // 8. Delete Storage Link
        $storageLink = public_path('storage');
        if ($this->files->exists($storageLink)) {
            if (is_link($storageLink)) {
                unlink($storageLink);
            } else {
                $this->files->deleteDirectory($storageLink);
            }
            $this->info("✓ Deleted storage link");
        }

        // 9. Reset web.php
        $webPath = base_path('routes/web.php');
        $cleanWeb = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n";
        $this->files->put($webPath, $cleanWeb);
        $this->info("✓ Reverted routes/web.php to default");

        // 10. Revert Kernel.php
        $kernelPath = app_path('Http/Kernel.php');
        if ($this->files->exists($kernelPath)) {
            $kernelContent = $this->files->get($kernelPath);
            $middlewareAliases = ["'IsLogin'", "'IsGuest'", "'IsAdmin'", "'IsEmployee'"];
            $lines = explode("\n", $kernelContent);
            $newLines = [];
            foreach ($lines as $line) {
                $shouldSkip = false;
                foreach ($middlewareAliases as $alias) {
                    if (str_contains($line, $alias)) {
                        $shouldSkip = true;
                        break;
                    }
                }
                if (!$shouldSkip) {
                    $newLines[] = $line;
                }
            }
            $this->files->put($kernelPath, implode("\n", $newLines));
            $this->info("✓ Cleaned middleware aliases from Kernel.php");
        }

        $this->info("✨ Project reset successfully!");
    }
}