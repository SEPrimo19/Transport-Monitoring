<?php

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\RequireTenantAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'supabase.admin' => AuthenticateSupabaseAdmin::class,
            'super.admin' => RequireSuperAdmin::class,
            'tenant.admin' => RequireTenantAdmin::class,
        ]);

        // Render terminates TLS at its edge and forwards plain HTTP to this
        // container. Without this the framework believes every request arrived over
        // http, from Render's own internal address. Three things followed, and the
        // third one broke sign-in on the deployed console:
        //
        //  * Strict-Transport-Security was never sent. SecurityHeaders only emits it
        //    on an already-secure request, and no request ever looked secure.
        //
        //  * `throttle:8,1` on POST /login keyed every attempt to the proxy's address
        //    rather than the visitor's, so every admin in the world shared one bucket
        //    of eight attempts a minute.
        //
        //  * AuthenticateSupabaseAdmin::deny() sends guests away with
        //    redirect()->guest(), which stores $request->fullUrl() as the intended
        //    destination. fullUrl() reads the REQUEST's scheme, so it is not covered
        //    by the URL::forceScheme('https') in AppServiceProvider — it stored
        //    http://…/dashboard. A successful sign-in then replayed that absolute
        //    http:// URL verbatim through redirect()->intended(), and Chrome checks a
        //    form submission's redirect target against `form-action 'self'`: a
        //    different scheme is a different origin, so the navigation was refused.
        //    The POST had already succeeded, so the button sat on "Signing in…" until
        //    the admin reloaded by hand and arrived at a dashboard they were already
        //    signed in to.
        //
        // '*' trusts the immediate peer only (see TrustProxies), which is the right
        // reading here: the container is not reachable except through Render's edge,
        // so the peer is always theirs. X-Forwarded-Host is deliberately left out —
        // nothing needs it, and trusting it would let a spoofed Host header poison
        // every generated URL.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Baseline security response headers on every web response (audit L-3).
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // AuthenticateSupabaseAdmin fills AdminContext, which the tenant
        // OrganizationScope reads. It MUST run before SubstituteBindings, or
        // route-model binding for {vehicle} etc. resolves with an empty context —
        // the scope then fails closed (WHERE 1=0) and every bound route 404s.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            AuthenticateSupabaseAdmin::class,
        );

        // RequireTenantAdmin/RequireSuperAdmin MUST also run before SubstituteBindings.
        // Otherwise a bound route like reports.show resolves {report} first: for a
        // super_admin, OrganizationScope applies no filter (by design, mirroring
        // migration 016), so a foreign cooperative's row is SELECTed and hydrated
        // before the gate ever runs — only to be refused after the read already
        // happened. The tier gate must precede the model lookup it is meant to guard.
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            RequireTenantAdmin::class,
        );
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            RequireSuperAdmin::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // POST /login is throttled at 8/min per IP. Exceeding it threw a raw 429, which
        // with APP_DEBUG on rendered a stack trace on the public sign-in page. Repeated
        // attempts are exactly what someone who has forgotten their password makes, so
        // hitting the limit is ordinary use, not abuse, and deserves a sentence.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->routeIs('login.attempt')) {
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Too many sign-in attempts. Please wait a minute and try again.');
            }

            return null; // every other route keeps the framework's own 429
        });
    })->create();
