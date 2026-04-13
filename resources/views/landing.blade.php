<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store</title>
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
        h1 {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
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
                    <h1>Kelola Store Anda Lebih Cepat</h1>
                    <p class="hero-description">
                        Solusi manajemen kasir pintar untuk bisnis Anda. Pantau stok, catat penjualan, dan cetak laporan hanya dalam satu aplikasi yang elegan.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="{{ route('login') }}" class="btn btn-primary">
                            Mulai Sekarang <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>