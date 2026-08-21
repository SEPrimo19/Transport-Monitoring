<?php

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Middleware\RequireSuperAdmin;
use App\Http\Middleware\RequireTenantAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
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
        //
    })->create();
