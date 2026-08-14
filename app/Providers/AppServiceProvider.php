<?php

namespace App\Providers;

use App\Models\Organization;
use App\Support\AdminContext;
use App\Support\PendingReportFeed;
use Illuminate\Support\Facades\Cache;
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
        $this->app->scoped(AdminContext::class, fn () => new AdminContext);
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

        // Resolve the tenant's name, brand colour and logo for the shell chrome.
        // Runs once per render of any view that extends layouts.app; no
        // per-controller wiring.
        View::composer('layouts.app', function ($view): void {
            $ctx = $this->app->make(AdminContext::class);

            // Cached: this is chrome (name, colour, logo) on every single page, and the
            // database is ~400 ms away. An org profile changes about never, and
            // OrgProfileController::update() forgets this key the moment it does, so a
            // rebrand still lands on the next request rather than 60 s later.
            $org = $ctx->organizationId
                ? Cache::remember(
                    self::orgChromeCacheKey($ctx->organizationId),
                    now()->addMinutes(10),
                    fn () => Organization::find($ctx->organizationId)
                )
                : null;

            // Sidebar identity is per-TENANT: an org_admin sees its own org name +
            // logo; a super_admin has no single org, so it reads "Super Admin" with
            // a platform mark (no org logo).
            $isSuper = $ctx->isSuperAdmin();
            $label = $isSuper ? 'Super Admin' : ($org?->name ?: config('app.name', 'AMOEC Admin'));

            // Two-letter monogram fallback when there's no logo image.
            $initials = collect(preg_split('/\s+/', trim($label)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn ($w) => mb_substr($w, 0, 1))
                ->implode('');

            $view->with('orgName', $org?->name);
            $view->with('brandColor', $org && $org->primary_color ? $org->primary_color : '#0d6efd');
            $view->with('brandLabel', $label);
            $view->with('brandMark', mb_strtoupper($initials ?: 'A'));
            $view->with('orgLogoUrl', $isSuper ? null : ($org?->logo_url ?: null));

            // Notification bell: passenger reports still awaiting triage. Reports are
            // the only event admins must ACT on that arrives from outside the console,
            // so an unattended one is the thing worth surfacing on every screen.
            //
            // This is FIRST PAINT only. The layout then polls
            // NotificationController@pending — which reads the same PendingReportFeed —
            // so the bell stays current without a page refresh. Both paths must keep
            // sharing that class or the two can drift apart.
            //
            // Guarded on isAdmin() so this never queries for an unauthenticated render.
            $view->with('notifications', $ctx->isAdmin()
                ? $this->app->make(PendingReportFeed::class)->payload()
                : ['count' => 0, 'items' => []]);
        });
    }

    /**
     * Cache key for a tenant's shell chrome. Call
     * `Cache::forget(AppServiceProvider::orgChromeCacheKey($id))` after any write that
     * changes an org's name, colour or logo, or the sidebar will show the old branding
     * until the entry lapses.
     */
    public static function orgChromeCacheKey(string $organizationId): string
    {
        return 'org_chrome:'.$organizationId;
    }
}
