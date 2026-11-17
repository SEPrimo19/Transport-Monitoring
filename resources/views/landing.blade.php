{{-- Public landing page: introduces the project to passengers, drivers, administrators and visitors. --}}
@extends('layouts.public')

@section('content')
    {{-- ============================== HERO ============================== --}}
    <section class="hero" id="home">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="container position-relative">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 reveal">
                    <span class="eyebrow"><i class="bi bi-broadcast-pin me-1"></i>Live bus tracking · Northern Samar</span>
                    <h1 class="display-4 fw-bold mt-3">Know where your bus is <span class="accent">before you leave.</span></h1>
                    <p class="lead mt-3 mb-4">AMOEC Track shows passengers where their bus is and when it will arrive, helps drivers share their trip,
                        and gives cooperatives one console to run the service.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-warning btn-lg px-4 fw-semibold" id="heroDownload" href="#download" data-apk="{{ $app['apk_url'] }}"><i class="bi bi-android2 me-2"></i>Download Android app</a>
                        @if ($hasAdminSession)
                            <a class="btn btn-outline-light btn-lg px-4" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Open dashboard</a>
                        @else
                            <a class="btn btn-outline-light btn-lg px-4" href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right me-2"></i>Administrator sign-in</a>
                        @endif
                    </div>
                    <p class="hero-note small mt-3 mb-0" id="heroNote">Android app &middot; {{ $app['apk_size'] }} &middot; {{ $app['min_android'] }}</p>
                    <ul class="hero-points list-unstyled mt-3 mb-0">
                        <li><i class="bi bi-check-circle-fill"></i> Free for passengers</li>
                        <li><i class="bi bi-check-circle-fill"></i> Works with weak signal</li>
                        <li><i class="bi bi-check-circle-fill"></i> Anonymous reporting</li>
                    </ul>
                </div>

                <div class="col-lg-6 reveal">
                    {{-- The story of one ride, drawn in SVG and moved by landing.js: a passenger waits at their stop, the bus approaches while
                         the arrival time counts down, the passenger boards, and the bus carries on to the destination.
                         The bus is the same top-down marker the app and the console draw on their maps. --}}
                    <div class="route-stage" id="routeStage">
                        <button type="button" class="motion-toggle" id="motionToggle" aria-pressed="false" aria-label="Pause the animation">
                            <i class="bi bi-pause-fill" aria-hidden="true"></i><span>Pause</span></button>
                        <svg viewBox="0 0 600 400" class="route-svg" role="img"
                             aria-label="A passenger waits at a stop on the route from Allen to Calbayog. A bus approaches while its arrival time counts down, the passenger boards, and the bus continues to Calbayog.">
                            <defs>
                                <linearGradient id="routeGrad" x1="0" x2="1">
                                    <stop offset="0" stop-color="#ffd54f"/>
                                    <stop offset="1" stop-color="#ffffff"/>
                                </linearGradient>
                            </defs>
                            <path id="routePath" d="M40 330 C 140 300, 150 170, 250 195 S 400 305, 470 160 S 550 80, 565 60"
                                  fill="none" stroke="rgba(255,255,255,.28)" stroke-width="10" stroke-linecap="round"/>
                            <path id="routeDone" d="M40 330 C 140 300, 150 170, 250 195 S 400 305, 470 160 S 550 80, 565 60"
                                  fill="none" stroke="url(#routeGrad)" stroke-width="4" stroke-linecap="round" stroke-dasharray="0 2000"/>
                            <g class="stop"><circle cx="40" cy="330" r="9"/><text x="40" y="362" text-anchor="middle">Allen</text></g>
                            <g class="stop"><circle cx="565" cy="60" r="9"/><text x="588" y="94" text-anchor="end">Calbayog</text></g>

                            {{-- the waiting passenger (placed on the route by landing.js) --}}
                            <g id="waiter">
                                <circle class="wait-ring" r="20"/>
                                <circle r="15" fill="#ffc107" stroke="#fff" stroke-width="2.5"/>
                                <circle cx="0" cy="-5.2" r="4.3" fill="#165935"/>
                                <path d="M-7.4 8.2 C-7.4 0.6 7.4 0.6 7.4 8.2 Z" fill="#165935"/>
                                <g class="wait-tag"><rect x="-47" y="-48" width="94" height="22" rx="11" fill="#fff"/><text x="0" y="-33" text-anchor="middle">You wait here</text></g>
                            </g>

                            {{-- the bus: the same marker as the app and the console (body = live green, operator stripe across the roof) --}}
                            <g id="busMarker">
                                <g class="bus-shadow"><ellipse rx="16" ry="26" fill="rgba(0,0,0,.28)" transform="translate(4 5)"/></g>
                                <g transform="scale(1.25) translate(-27 -27)">
                                    <rect x="15.93" y="15.81" width="2.97" height="1.62" rx=".81" fill="#0b5a28"/>
                                    <rect x="35.1" y="15.81" width="2.97" height="1.62" rx=".81" fill="#0b5a28"/>
                                    <rect x="18.9" y="10.26" width="16.2" height="33.48" rx="4.46" fill="#15A04A" stroke="#0b5a28" stroke-width="1.1"/>
                                    <rect x="20.68" y="12.6" width="12.64" height="5.03" rx="1.2" fill="#1C2B33" opacity=".92"/>
                                    <rect x="21.65" y="37.04" width="10.7" height="4.36" rx="1.2" fill="#1C2B33" opacity=".92"/>
                                    <rect x="21.01" y="20.3" width="11.98" height="14.07" rx="1.1" fill="#F2F4F5" opacity=".95"/>
                                    <rect x="21.01" y="26.36" width="11.98" height="1.94" fill="#165935"/>
                                </g>
                            </g>
                            <g id="etaBubble">
                                <rect id="etaBox" x="-62" y="-60" width="124" height="30" rx="15" fill="#fff"/>
                                <text id="etaText" x="0" y="-40" text-anchor="middle">ETA 6 min</text>
                            </g>
                        </svg>
                        <ol class="route-steps" id="routeSteps" aria-hidden="true">
                            <li data-step="1"><i class="bi bi-person-standing"></i><span>Wait at your stop</span></li>
                            <li data-step="2"><i class="bi bi-clock-history"></i><span>Bus approaches</span></li>
                            <li data-step="3"><i class="bi bi-person-check-fill"></i><span>Board and ride</span></li>
                            <li data-step="4"><i class="bi bi-flag-fill"></i><span>Arrive</span></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== ABOUT ============================== --}}
    <section class="section" id="about">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 reveal">
                    <h2 class="section-title">Why AMOEC Track</h2>
                    <p>Waiting along the highway without knowing when the bus will come costs passengers time, and gives drivers and
                        cooperatives no clear picture of the service. AMOEC Track fixes that with one system for everyone.</p>
                    <p>It was built for the buses that run between Allen, Calbayog and Catarman, and it is made so that any transport
                        cooperative can join: each cooperative sees only its own data.</p>
                    <ul class="list-unstyled check-list mt-3">
                        <li><i class="bi bi-geo-alt-fill"></i>See the bus on the map and how far away it is.</li>
                        <li><i class="bi bi-clock-history"></i>Get an arrival time, even when the signal drops.</li>
                        <li><i class="bi bi-people-fill"></i>Know if the bus has seats before you flag it down.</li>
                    </ul>
                </div>
                <div class="col-lg-6 reveal">
                    <div class="stat-grid">
                        <div class="stat"><strong>3</strong><span>kinds of users: passengers, drivers, administrators</span></div>
                        <div class="stat"><strong>2</strong><span>apps that work together: Android app and web console</span></div>
                        <div class="stat"><strong>Live</strong><span>positions updated within seconds</span></div>
                        <div class="stat"><strong>Offline</strong><span>ETA keeps working in dead zones</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== HOW IT WORKS ============================== --}}
    <section class="section section-tint" id="how">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">How it works</h2>
                <p class="text-muted mb-0">Three steps from the driver&rsquo;s seat to your screen.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 reveal">
                    <div class="step-card"><span class="step-no">1</span><i class="bi bi-broadcast"></i>
                        <h3>The driver shares the trip</h3>
                        <p>The driver starts a trip in the app. The phone shares the bus position and the driver sets how full the bus is.</p></div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-card"><span class="step-no">2</span><i class="bi bi-cpu"></i>
                        <h3>The system works out the arrival</h3>
                        <p>Positions go to the cloud. The system follows the route and calculates distance and arrival time, even if the signal is lost.</p></div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-card"><span class="step-no">3</span><i class="bi bi-phone"></i>
                        <h3>The passenger sees it live</h3>
                        <p>Passengers watch the bus move on the map and read the ETA and seats. Administrators follow every bus on one screen.</p></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== FEATURES ============================== --}}
    <section class="section" id="features">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <h2 class="section-title">What you can do</h2>
            </div>
            <div class="row g-4">
                @php
                    $features = [
                        ['bi-geo-alt', 'Live map', 'Watch the buses of your route move in near real time, with distance and arrival time.'],
                        ['bi-wifi-off', 'Offline ETA', 'In a dead zone the app keeps estimating from the last speed and direction, and says so openly.'],
                        ['bi-people', 'Seat status', 'Drivers set Vacant, Limited Seats, Standing Only or Full, so you know before you flag the bus.'],
                        ['bi-calendar3', 'Schedules', 'Read the timetable of each route and the next departure.'],
                        ['bi-flag', 'Report a problem', 'Report unsafe or poor service with a photo or video, and send it anonymously if you want.'],
                        ['bi-building-gear', 'Cooperative console', 'Manage vehicles, drivers, routes and assignments, review reports and print reports.'],
                    ];
                @endphp
                @foreach ($features as [$icon, $title, $text])
                    <div class="col-md-6 col-lg-4 reveal">
                        <div class="feature-card h-100"><i class="bi {{ $icon }}"></i><h3>{{ $title }}</h3><p class="mb-0">{{ $text }}</p></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== WHO USES IT ============================== --}}
    <section class="section section-tint" id="users">
        <div class="container">
            <div class="text-center mb-5 reveal"><h2 class="section-title">Made for three kinds of users</h2></div>
            <div class="row g-4">
                <div class="col-lg-4 reveal">
                    <div class="user-card h-100">
                        <span class="badge text-bg-success mb-3">Passengers</span>
                        <h3>Plan your ride</h3>
                        <ul><li>Free Android app</li><li>Live map, ETA and seats</li><li>Schedules and reports</li></ul>
                        <a href="#download" class="btn btn-success btn-sm">Get the app</a>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="user-card h-100">
                        <span class="badge text-bg-warning mb-3">Drivers</span>
                        <h3>Share your trip</h3>
                        <ul><li>Start and end a trip in a few taps</li><li>Location shared automatically</li><li>Account from your cooperative</li></ul>
                        <a href="#download" class="btn btn-warning btn-sm">Get the app</a>
                    </div>
                </div>
                <div class="col-lg-4 reveal">
                    <div class="user-card h-100">
                        <span class="badge text-bg-dark mb-3">Administrators</span>
                        <h3>Run the service</h3>
                        <ul><li>Live operations map</li><li>Fleet, routes, schedules, assignments</li><li>Reports and driver performance</li></ul>
                        <a href="{{ $hasAdminSession ? route('dashboard') : route('login') }}" class="btn btn-dark btn-sm">{{ $hasAdminSession ? 'Open dashboard' : 'Sign in' }}</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== SCREENS ============================== --}}
    <section class="section" id="screens">
        <div class="container">
            <div class="text-center mb-4 reveal">
                <h2 class="section-title">See it in action</h2>
                <p class="text-muted mb-0">Move your mouse over a screen, or drag the phone, to turn it.</p>
            </div>

            <div class="row align-items-center gy-4 mb-5 reveal">
                <div class="col-lg-6 text-center">
                    <div class="phone-scene" id="phoneScene">
                        <div class="phone" id="phone">
                            <div class="face front"><img id="phoneImg" src="{{ asset('images/landing/live.jpg') }}" alt="Live map with the closest bus, its distance and its arrival time" width="310" height="690"></div>
                            <div class="face back"><img src="{{ asset('images/logo-white.svg') }}" alt="" width="96" height="96"><span>AMOEC Track</span></div>
                            <div class="side side-l"></div><div class="side side-r"></div><div class="side side-t"></div><div class="side side-b"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h3 class="h4 mb-3">Choose a screen</h3>
                    <div class="d-grid gap-2" id="phoneTabs">
                        @php
                            $screens = [
                                ['live', 'bi-geo-alt-fill', 'Live map and ETA', 'Closest bus, distance, arrival time and seats', 'Live map with the closest bus, its distance and its arrival time'],
                                ['route', 'bi-signpost-split-fill', 'Choose your route', 'Pick where you board and where you go', 'The route picker with the From and To terminals'],
                                ['schedules', 'bi-calendar3', 'Schedules', 'Timetables and the next departure', 'Schedules with the next departure of each route'],
                                ['report', 'bi-flag-fill', 'Report a problem', 'With a photo or video, even anonymously', 'The report form with evidence and the anonymous option'],
                                ['start', 'bi-person-badge-fill', 'Driver: start a trip', 'Checks, direction and depart', 'The driver&rsquo;s Start New Trip screen'],
                                ['progress', 'bi-broadcast', 'Driver: trip in progress', 'Location shared, seat status one tap away', 'The driver&rsquo;s Trip in Progress screen with the occupancy buttons'],
                            ];
                        @endphp
                        @foreach ($screens as $i => [$img, $icon, $title, $sub, $alt])
                            <button type="button" class="screen-tab {{ $i ? '' : 'active' }}" aria-pressed="{{ $i ? 'false' : 'true' }}"
                                    data-src="{{ asset('images/landing/'.$img.'.jpg') }}" data-alt="{{ $alt }}">
                                <i class="bi {{ $icon }}"></i><span><strong>{{ $title }}</strong><small>{{ $sub }}</small></span></button>
                        @endforeach
                    </div>
                </div>
            </div>

            <h3 class="h4 text-center mb-4 reveal">The administrator console</h3>
            <div class="row g-4">
                @php
                    $web = [
                        ['dashboard', 'Dashboard with fleet, trips and reports at a glance'],
                        ['liveops', 'Live operations map of every bus on a trip'],
                        ['editor', 'Route editor that draws the path along real roads'],
                        ['reportdetail', 'Report review with photo evidence and status'],
                    ];
                @endphp
                @foreach ($web as [$img, $label])
                    <div class="col-md-6 reveal">
                        <figure class="browser tilt" data-tilt>
                            <div class="browser-bar"><i></i><i></i><i></i><span>amoec-track-admin.onrender.com</span></div>
                            <img src="{{ asset('images/landing/'.$img.'.jpg') }}" alt="{{ $label }}" loading="lazy" width="1318" height="630">
                            <figcaption>{{ $label }}</figcaption>
                        </figure>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== DOWNLOAD ============================== --}}
    <section class="section section-dark" id="download">
        <div class="container">
            <div class="row gy-5 align-items-center">
                <div class="col-lg-6 reveal">
                    <h2 class="section-title text-white">Download the Android app</h2>
                    <p class="opacity-75">Free for passengers and drivers. Version {{ $app['version'] }} &middot; {{ $app['apk_size'] }} &middot; {{ $app['min_android'] }}.</p>
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <a class="btn btn-warning btn-lg fw-semibold px-4" href="{{ $app['apk_url'] }}" rel="noopener"><i class="bi bi-download me-2"></i>Download APK</a>
                        <a class="btn btn-outline-light btn-lg px-4" href="{{ asset($app['manual_path']) }}"><i class="bi bi-file-earmark-pdf me-2"></i>User manual (PDF)</a>
                    </div>
                    {{-- Shown by landing.js on iPhone and iPad: the app is Android only for now. --}}
                    <div class="ios-note" id="iosNote" hidden>
                        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                        <span><strong>AMOEC Track is Android only for now.</strong> A version for iPhone is planned for later.
                            Administrators can still sign in to the console from any browser.</span>
                    </div>
                    <p class="small opacity-75 mb-1">Checksum (SHA-256), to check the file is genuine:</p>
                    <button type="button" class="checksum" id="checksum" data-hash="{{ $app['sha256'] }}" aria-describedby="copyNote"
                            title="Click to copy the checksum">{{ $app['sha256'] }}</button>
                    <span class="copy-note" id="copyNote" role="status" aria-live="polite"></span>
                    <p class="small mt-2"><a class="link-light" href="{{ $app['release_url'] }}" rel="noopener" target="_blank">All releases and notes on GitHub</a></p>
                </div>
                <div class="col-lg-6 reveal">
                    <div class="install-card">
                        {{-- For visitors on a computer: scan to download on the phone. Hidden on phones, where it is no use. --}}
                        <div class="qr-box d-none d-md-flex">
                            <img src="{{ asset('images/landing/download-qr.svg') }}" width="132" height="132" loading="lazy"
                                 alt="QR code that downloads the AMOEC Track Android app">
                            <div><strong>On a computer?</strong><span>Point your phone camera at this code to download the app.</span></div>
                        </div>
                        <h3 class="h5 mb-3">Install in four steps</h3>
                        <ol class="install-steps">
                            <li><strong>Download</strong> the APK to your phone and open it.</li>
                            <li>If Android asks, <strong>allow the installation</strong> from this source.</li>
                            <li>Tap <strong>Install</strong>, then <strong>Open</strong>.</li>
                            <li>Allow <strong>location</strong> and <strong>notifications</strong>, then sign in or tap Sign Up.</li>
                        </ol>
                        <p class="small mb-0 opacity-75">Drivers: use the employee code and password your cooperative gave you.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== FAQ ============================== --}}
    <section class="section" id="faq">
        <div class="container" style="max-width: 820px;">
            <div class="text-center mb-4 reveal"><h2 class="section-title">Questions people ask</h2></div>
            @php
                $faq = [
                    ['Do I need an account?', 'Passengers can sign up in the app with an email or with Google. Drivers and administrators receive their accounts from their cooperative.'],
                    ['Why is my bus not on the map?', 'A bus appears only while its driver is on a trip. Check that you chose the right cooperative and route, and refresh.'],
                    ['How exact is the arrival time?', 'The status on the bus card tells you how fresh the data is. When there is no recent signal the app shows Offline ETA Mode, which is an estimate.'],
                    ['What happens when there is no signal?', 'Drivers’ phones keep saving their position and send it when the connection returns. Passengers see the last position and an estimated arrival time.'],
                    ['Is my location shared?', 'Passengers’ locations stay on the phone and are sent only inside a report you submit. Drivers share the bus location during a trip. See the Privacy page for the full list.'],
                    ['How do I report a problem?', 'Open Menu, tap Report misconduct, choose the bus and the issue, add a photo if you have one, and send it. You can follow it under My reports.'],
                    ['Is there an iPhone app?', 'Not yet. AMOEC Track is Android only for now, and a version for iPhone is planned for later. Cooperative administrators can use the console from any browser.'],
                    ['Can my cooperative use AMOEC Track?', 'Yes. The system is built for many cooperatives; each one sees only its own vehicles, drivers and reports. Use the contact link in the footer to ask.'],
                ];
            @endphp
            <div class="accordion faq" id="faqList">
                @foreach ($faq as $i => [$q, $a])
                    <div class="accordion-item reveal">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="faq{{ $i }}">{{ $q }}</button>
                        </h3>
                        <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faqList">
                            <div class="accordion-body">{{ $a }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
