<?php

namespace App\Providers;

use App\Models\Organization;
use App\Support\AdminContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One AdminContext per request; filled by AuthenticateSupabaseAdmin.
        $this->app->scoped(AdminContext::class, fn () => new AdminContext());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share the signed-in admin with every view. We share the (empty) scoped
        // instance now; the auth middleware fills that SAME object before any view
        // renders, so `$ctx` is populated by render time — and it reaches nested
        // Blade sections (unlike view composers), so screens can rely on it.
        View::share('ctx', $this->app->make(AdminContext::class));

        // Resolve the tenant's name + brand colour for the shell chrome. Runs once
        // per render of any view that extends layouts.app; no per-controller wiring.
        View::composer('layouts.app', function ($view): void {
            $ctx = $this->app->make(AdminContext::class);
            $org = $ctx->organizationId ? Organization::find($ctx->organizationId) : null;

            $view->with('orgName', $org?->name);
            $view->with('brandColor', $org && $org->primary_color ? $org->primary_color : '#0d6efd');
        });
    }
}
