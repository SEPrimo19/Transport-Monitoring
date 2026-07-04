<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name', 'AMOEC Admin') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --amoec-brand: {{ $brandColor ?? '#0d6efd' }}; }
        body { background: #f6f8fa; }
        .sidebar { min-height: 100vh; background: #fff; border-right: 1px solid #e5e7eb; }
        .sidebar .nav-link { color: #374151; border-radius: .5rem; margin-bottom: .125rem; }
        .sidebar .nav-link:hover { background: #f1f5f9; }
        .sidebar .nav-link.active { background: var(--amoec-brand); color: #fff; }
        .sidebar .nav-link.disabled { color: #9ca3af; }
        .brand-dot { width: .85rem; height: .85rem; border-radius: 50%; background: var(--amoec-brand); display: inline-block; }
        .btn-brand { background: var(--amoec-brand); border-color: var(--amoec-brand); color: #fff; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-12 col-md-3 col-lg-2 sidebar p-3">
            <div class="d-flex align-items-center mb-4">
                <span class="brand-dot me-2"></span>
                <strong class="text-truncate">{{ config('app.name', 'AMOEC Admin') }}</strong>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link @yield('nav_dashboard')" href="{{ route('dashboard') }}">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                </li>
                @if ($ctx->isSuperAdmin())
                    <li class="nav-item">
                        <a class="nav-link @yield('nav_organizations')" href="{{ route('organizations.index') }}">
                            <i class="bi bi-buildings me-2"></i>Organizations
                        </a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link @yield('nav_vehicles')" href="{{ route('vehicles.index') }}">
                        <i class="bi bi-bus-front me-2"></i>Vehicles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_drivers')" href="{{ route('drivers.index') }}">
                        <i class="bi bi-person-badge me-2"></i>Drivers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_terminals')" href="{{ route('terminals.index') }}">
                        <i class="bi bi-pin-map me-2"></i>Terminals
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_routes')" href="{{ route('routes.index') }}">
                        <i class="bi bi-signpost-2 me-2"></i>Routes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @yield('nav_reports')" href="{{ route('reports.index') }}">
                        <i class="bi bi-flag me-2"></i>Reports
                    </a>
                </li>
            </ul>
        </nav>

        <main class="col-12 col-md-9 col-lg-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-0">@yield('heading', 'Dashboard')</h1>
                    <small class="text-muted">{{ $orgName ?? 'All organizations' }}</small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>{{ $ctx->fullName ?? $ctx->email }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-muted">{{ $ctx->email }}</span></li>
                        <li><span class="dropdown-item-text small"><span class="badge text-bg-secondary">{{ $ctx->role }}</span></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i>{{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
