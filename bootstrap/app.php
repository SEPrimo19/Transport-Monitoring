<?php

use App\Http\Middleware\AuthenticateSupabaseAdmin;
use App\Http\Middleware\RequireSuperAdmin;
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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
