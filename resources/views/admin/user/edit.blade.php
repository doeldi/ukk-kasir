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
                        <form method="POST" action="{{ route('admin.UserUpdate', $user->id) }}">
@csrf
@method('PATCH')
                            <div class="mb-3">
                                <label class="form-label">Nama</label>
                                <input type="text" class="form-control" name="name" value="{{ $user->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="{{ $user->email }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                                <input type="password" class="form-control" name="password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-control" name="role" required>
                                    <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                                    <option value="employee" {{ $user->role == 'employee' ? 'selected' : '' }}>Employee</option>
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