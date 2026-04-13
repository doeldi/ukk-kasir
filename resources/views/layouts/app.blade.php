<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .alert {
            border: none;
            border-radius: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.625rem 0.875rem;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

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