<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'supabase.admin' => \App\Http\Middleware\AuthenticateSupabaseAdmin::class,
            'super.admin' => \App\Http\Middleware\RequireSuperAdmin::class,
        ]);

        // Baseline security response headers on every web response (audit L-3).
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // AuthenticateSupabaseAdmin fills AdminContext, which the tenant
        // OrganizationScope reads. It MUST run before SubstituteBindings, or
        // route-model binding for {vehicle} etc. resolves with an empty context —
        // the scope then fails closed (WHERE 1=0) and every bound route 404s.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\AuthenticateSupabaseAdmin::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
