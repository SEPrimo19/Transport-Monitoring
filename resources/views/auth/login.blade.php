<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name', 'AMOEC Admin') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @php
        // No org is known yet at sign-in (auth precedes tenant resolution), so
        // $brandColor isn't actually shared with this view today — but this
        // validates it the same way layouts/app.blade.php does, so the moment
        // a controller starts passing it here, an unvalidated DB value (bare
        // "165935", malformed, etc.) can't silently make --amoec-brand invalid
        // CSS, which would render .btn-primary / .login-icon white-on-nothing.
        $rawLoginBrand = strtolower(ltrim(trim((string) ($brandColor ?? '')), '#'));
        $loginBrand = preg_match('/^[0-9a-f]{6}$/', $rawLoginBrand) ? '#' . $rawLoginBrand : '#0d6efd';
        $loginHex = ltrim($loginBrand, '#');
        $loginBrandRgb = hexdec(substr($loginHex, 0, 2)) . ', ' . hexdec(substr($loginHex, 2, 2)) . ', ' . hexdec(substr($loginHex, 4, 2));
    @endphp
    <style>
        :root { --amoec-brand: {{ $loginBrand }}; --amoec-brand-rgb: {{ $loginBrandRgb }}; }

        body {
            background: linear-gradient(160deg, #1f2937 0%, #111827 100%);
            min-height: 100vh; display: flex; align-items: center;
        }
        @supports (color: color-mix(in srgb, red, blue)) {
            body {
                background: linear-gradient(160deg,
                    color-mix(in srgb, var(--amoec-brand) 80%, #111827) 0%,
                    #111827 100%);
            }
        }
        .login-card { max-width: 420px; width: 100%; }
        .login-icon {
            width: 3.25rem; height: 3.25rem; border-radius: .85rem;
            background: var(--amoec-brand); color: #fff;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin: 0 auto;
        }
        .btn-primary {
            --bs-btn-bg: var(--amoec-brand);
            --bs-btn-border-color: var(--amoec-brand);
            --bs-btn-hover-bg: var(--amoec-brand);
            --bs-btn-hover-border-color: var(--amoec-brand);
            --bs-btn-active-bg: var(--amoec-brand);
            --bs-btn-active-border-color: var(--amoec-brand);
            --bs-btn-focus-shadow-rgb: var(--amoec-brand-rgb);
        }
        .btn-primary:hover { filter: brightness(.93); }
        .btn-primary:active, .btn-primary:focus-visible { filter: brightness(.86); }
        .form-control:focus {
            border-color: var(--amoec-brand);
            box-shadow: 0 0 0 .25rem rgba(var(--amoec-brand-rgb), .2);
        }
        a:focus-visible, .btn:focus-visible {
            outline: 2px solid var(--amoec-brand); outline-offset: 2px; border-radius: .25rem;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-card mx-auto">
        <div class="card shadow-lg border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <span class="login-icon"><i class="bi bi-bus-front-fill"></i></span>
                    <h1 class="h4 mt-3 mb-0">{{ config('app.name', 'AMOEC Admin') }}</h1>
                    <p class="text-muted small mb-0">Sign in to the admin console</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
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
