{{-- Public privacy page. Written from how the system really works; keep it in step with the code when data handling changes. --}}
@extends('layouts.public')

@section('title', 'Privacy · AMOEC Track')
@section('description', 'What AMOEC Track collects, why, who can see it, where it is stored and how long it is kept.')
@section('navclass', 'show-bg')

@section('content')
    <header class="page-head">
        <div class="container">
            <h1 class="display-6 fw-bold mb-2">Privacy</h1>
            <p class="mb-0 opacity-75">How AMOEC Track handles your information. Last updated {{ $app['privacy_updated'] }}.</p>
        </div>
    </header>

    <div class="container py-5">
        <article class="prose">
            <p class="note">AMOEC Track is a student capstone project. This page describes how the system works today, in plain words.
                It covers the Android app and the web administration console.</p>

            <h2>What we collect</h2>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light"><tr><th>Who</th><th>What</th><th>Why</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Everyone with an account</strong></td>
                            <td>Name, email or username, and role. Your password is handled by the sign-in service and is stored in a protected form; we never see it. If you sign in with Google, we receive your name and email from Google.</td>
                            <td>To sign you in and show the right screens.</td></tr>
                        <tr><td><strong>Passengers</strong></td>
                            <td>Nothing about where you are while you only look at the map. When you send a report: its category, your description, your location and the time, the photo or video you attach (optional), and whether you chose to be anonymous.</td>
                            <td>So the cooperative can check where and when a problem happened.</td></tr>
                        <tr><td><strong>Drivers</strong></td>
                            <td>The position, speed and heading of the bus while a trip is running, the start and end of the trip, the seat status you set, and your assignment.</td>
                            <td>To show the bus to passengers and to measure the service.</td></tr>
                        <tr><td><strong>Administrators</strong></td>
                            <td>Their actions on the vehicles, drivers, routes, schedules and reports they manage. Some account changes, such as creating or changing an administrator, are kept in an activity log.</td>
                            <td>To keep the service accountable.</td></tr>
                        <tr><td><strong>The app on your phone</strong></td>
                            <td>Technical crash information (for example the phone model, the Android version and the error) sent through Firebase Crashlytics when the app stops unexpectedly.</td>
                            <td>To find and fix bugs.</td></tr>
                    </tbody>
                </table>
            </div>
            <p>The app uses the location of your phone to show you on the map and to work out the distance to the bus.
                For passengers this stays on the phone. It is sent to our servers only inside a report that you submit.</p>
            <p>We do not sell your information and the app has no advertising.</p>

            <h2>Who can see it</h2>
            <ul>
                <li><strong>Passengers</strong> see the buses of the cooperative they chose: position, route and seat status. They see their own reports.</li>
                <li><strong>Cooperative administrators</strong> see the vehicles, drivers, trips and reports of their own cooperative only. Each cooperative is kept apart from the others by rules in the database.</li>
                <li><strong>Anonymous reports:</strong> if you switch on <em>Submit anonymously</em>, the administrators do not see your name on the report. The report still appears in your own list of reports.</li>
                <li><strong>Platform administrators</strong> run the system itself, for example creating cooperatives and their first administrators.</li>
            </ul>

            <h2>Where it is stored and which services we use</h2>
            <ul>
                <li><strong>Supabase</strong> holds the database, the sign-in service and the files attached to reports.</li>
                <li><strong>Google</strong> is used only if you choose Continue with Google.</li>
                <li><strong>Firebase Crashlytics</strong> receives the crash information described above.</li>
                <li><strong>OpenFreeMap</strong> supplies the map pictures; the map area you look at is requested from its servers.</li>
                <li><strong>Render</strong> hosts this website and the administration console.</li>
            </ul>
            <p>Connections to the website and the services are encrypted. On the phone, your sign-in is kept in encrypted storage.</p>

            <h2>How long we keep it</h2>
            <ul>
                <li><strong>Location history</strong> of trips is deleted after the number of days each cooperative sets (from 1 to 3650 days). If a cooperative sets no limit, the history is kept until it is deleted. The deletion runs once a day.</li>
                <li>The <strong>current position</strong> of a bus is one record that is replaced at every update.</li>
                <li><strong>Trip summaries</strong> (counts, durations and punctuality, without raw positions) stay so that reports remain possible.</li>
                <li><strong>Reports and their attachments</strong> are kept while the cooperative needs them to review the service. Ask the cooperative to remove one if you want it gone.</li>
            </ul>

            <h2>Your choices</h2>
            <ul>
                <li>You can turn the location permission off in the phone settings. You can still see the buses on the map, but the app cannot place you, and a report cannot be sent because it needs a location.</li>
                <li>You can send reports anonymously and sign out at any time from the Menu.</li>
                <li>To correct or delete your account or your reports, ask the administrator of your cooperative. If you cannot reach one, open an issue at the project page below.</li>
            </ul>

            <h2>Changes and contact</h2>
            <p>When the system changes how it handles information, this page is updated and the date at the top changes.
                Questions can be sent through <a href="{{ $app['issues_url'] }}" rel="noopener" target="_blank">the project page on GitHub</a>.</p>
            <p><a href="{{ route('home') }}" class="btn btn-outline-success"><i class="bi bi-arrow-left me-1"></i>Back to the home page</a></p>
        </article>
    </div>
@endsection
