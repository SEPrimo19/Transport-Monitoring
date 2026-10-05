{{--
    Where "go back" should point depends on whether the visitor still holds a session.
    Sending a signed-out visitor to the dashboard only bounces them through the auth
    middleware to the login screen; sending a signed-in one to login would look like
    they had been logged out. The session key is read directly rather than through the
    AdminContext, which an error page cannot assume was ever populated.
--}}
@if (session()->has('supabase_token'))
    <a href="{{ route('dashboard') }}" class="btn btn-primary px-4">
        <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Back to dashboard
    </a>
@else
    <a href="{{ route('login') }}" class="btn btn-primary px-4">
        <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Go to sign in
    </a>
@endif
