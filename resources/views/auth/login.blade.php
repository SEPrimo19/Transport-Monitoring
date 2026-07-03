<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name', 'AMOEC Admin') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); min-height: 100vh; display: flex; align-items: center; }
        .login-card { max-width: 420px; width: 100%; }
    </style>
</head>
<body>
<div class="container">
    <div class="login-card mx-auto">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <i class="bi bi-bus-front-fill text-primary" style="font-size: 2.5rem;"></i>
                    <h1 class="h4 mt-2 mb-0">{{ config('app.name', 'AMOEC Admin') }}</h1>
                    <p class="text-muted small mb-0">Sign in to the admin console</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success py-2">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger py-2">{{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                               class="form-control @error('email') is-invalid @enderror"
                               autofocus required autocomplete="username">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required autocomplete="current-password">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
                    </button>
                </form>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">
            Powered by Supabase &middot; {{ config('app.name', 'AMOEC Admin') }}
        </p>
    </div>
</div>
</body>
</html>
