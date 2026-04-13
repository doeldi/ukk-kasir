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
            <ul class="navbar-nav me-auto ms-lg-4 d-flex gap-1">
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