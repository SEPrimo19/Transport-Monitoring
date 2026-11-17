<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AMOEC Track · Know where your bus is before you leave')</title>
    <meta name="description" content="@yield('description', 'AMOEC Track shows passengers where their bus is and when it will arrive, helps drivers share their trip, and gives cooperatives a console to run the service.')">
    <meta name="theme-color" content="#165935">
    <meta property="og:title" content="AMOEC Track">
    <meta property="og:description" content="Know where your bus is before you leave. Live bus tracking for passengers, drivers and cooperatives.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('images/landing/card.jpg') }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    {{-- Subresource Integrity: the browser refuses these files if the CDN ever serves different bytes. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"
          integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="{{ asset('css/landing.css') }}" rel="stylesheet">
    {{-- Tells the stylesheet that scroll animations may hide content until it is revealed. --}}
    <script>document.documentElement.classList.add('js');</script>
    @stack('head')
</head>
<body>
    <a class="visually-hidden-focusable skip-link" href="#main">Skip to content</a>

    {{-- Top bar. On the privacy page the section links point back to the landing page. --}}
    <nav class="navbar navbar-expand-lg fixed-top site-nav @yield('navclass')" id="siteNav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                <img src="{{ asset('images/amoec-logo.png') }}" alt="" width="34" height="34">
                <span>AMOEC Track</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                    aria-controls="navMenu" aria-expanded="false" aria-label="Open the menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#screens">Screens</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#download">Download</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#faq">FAQ</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        @if ($hasAdminSession ?? false)
                            <a class="btn btn-light btn-sm px-3" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Open dashboard</a>
                        @else
                            <a class="btn btn-light btn-sm px-3" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-1"></i>Administrator sign-in</a>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main id="main">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ asset('images/amoec-logo.png') }}" alt="" width="34" height="34">
                        <strong>AMOEC Track</strong>
                    </div>
                    <p class="small mb-1">A Transport Monitoring and Passenger Information System.</p>
                    <p class="small opacity-75 mb-0">A capstone project of Group G23, Bachelor of Science in Information Technology,
                        Northwest Samar State University.</p>
                </div>
                <div class="col-6 col-lg-3">
                    <h2 class="h6">Explore</h2>
                    <ul class="list-unstyled small mb-0">
                        <li><a href="{{ route('home') }}#about">About</a></li>
                        <li><a href="{{ route('home') }}#features">Features</a></li>
                        <li><a href="{{ route('home') }}#download">Download the app</a></li>
                        <li><a href="{{ asset($app['manual_path']) }}">User manual (PDF)</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h2 class="h6">More</h2>
                    <ul class="list-unstyled small mb-0">
                        <li><a href="{{ route('privacy') }}">Privacy</a></li>
                        <li><a href="{{ route('login') }}">Administrator sign-in</a></li>
                        <li><a href="{{ $app['issues_url'] }}" rel="noopener" target="_blank">Report a problem</a></li>
                        <li><a href="{{ $app['repo_url'] }}" rel="noopener" target="_blank">Project on GitHub</a></li>
                    </ul>
                </div>
            </div>
            <hr class="border-light opacity-25 my-4">
            <p class="small opacity-75 mb-0">&copy; {{ date('Y') }} AMOEC Track. Version {{ $app['version'] }}.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="{{ asset('js/landing.js') }}" defer></script>
</body>
</html>
