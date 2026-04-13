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
            background: #4a81e7;
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
            <div class="d-flex justify-content-center">
                <button type="submit" class="btn btn-primary">Masuk ke Sistem</button>
            </div>
        </form>
        
        <div class="mt-4 pt-2">
            <a href="/" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>