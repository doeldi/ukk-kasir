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