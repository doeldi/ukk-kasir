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