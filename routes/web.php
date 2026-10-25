<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\DriverPerformanceController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrgAdminController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteGeometryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripScheduleController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

// Route::redirect, not a closure: `php artisan route:cache` serializes the route
// table and cannot serialize a Closure action — one closure here disables route
// caching for the entire application.
Route::redirect('/', '/dashboard');

// Guest auth — Supabase GoTrue password grant → access token in the session.
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:8,1') // slow brute-force / credential-stuffing (8/min per IP)
    ->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Everything below requires an authenticated, active org_admin / super_admin.
// The middleware verifies the session/bearer token and fills AdminContext.
Route::middleware('supabase.admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Tenant data: org_admin ONLY, super_admin deliberately excluded ──────────
    // A platform admin manages cooperatives, not what happens inside them. Passenger
    // reports name drivers and carry photo or video evidence; driver records hold licence
    // and phone numbers; trips are location history about identifiable people. None of
    // that was collected so a platform operator could read it.
    //
    // The gate is on the ROUTES, not just the sidebar. Hiding a link while the URL still
    // answers is decoration, not access control.
    Route::middleware('tenant.admin')->group(function () {
        Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
        Route::get('/operations/data', [OperationsController::class, 'data'])->name('operations.data');
        Route::resource('vehicles', VehicleController::class)->except(['show']);
        Route::resource('drivers', DriverController::class)->except(['show']);
        // Create a driver login outright (no self-registration) and reset a
        // driver's password — both proxy the driver-account Supabase Edge
        // Function. Kept off the drivers resource's own verbs/URIs. Throttled like
        // account.update below: each is a privileged write against GoTrue, and
        // unmetered would let a session holder hammer the Edge Function.
        Route::post('drivers/login', [DriverController::class, 'storeLogin'])
            ->middleware('throttle:6,1')
            ->name('drivers.login.store');
        Route::put('drivers/{driver}/reset-password', [DriverController::class, 'resetPassword'])
            ->middleware('throttle:6,1')
            ->name('drivers.reset-password');
        Route::resource('terminals', TerminalController::class)->except(['show']);
        Route::resource('routes', RouteController::class)->except(['show']);
        // Polyline map editor for a route (org-scoped via the {route} binding).
        Route::get('routes/{route}/geometry', [RouteGeometryController::class, 'edit'])->name('routes.geometry.edit');
        Route::put('routes/{route}/geometry', [RouteGeometryController::class, 'update'])->name('routes.geometry.update');
        Route::delete('routes/{route}/geometry', [RouteGeometryController::class, 'destroy'])->name('routes.geometry.destroy');
        Route::resource('schedules', TripScheduleController::class)->except(['show']);
        Route::resource('assignments', AssignmentController::class)->except(['show']);
        Route::resource('trips', TripController::class)->only(['index', 'show']);
        Route::resource('reports', ReportController::class)->only(['index', 'show', 'update']);

        // One box over all eight tenant screens. INSIDE this group on purpose: it reads
        // every tenant table at once, so it belongs behind the same gate as the screens
        // it searches -- a super_admin must not reach it, for the reason stated above.
        Route::get('search', [SearchController::class, 'index'])->name('search');

        // Per-driver performance. HR data about named people, so it sits behind the same gate
        // as the rest of the tenant data and a super_admin never reaches it.
        Route::get('analytics/drivers', [DriverPerformanceController::class, 'index'])->name('analytics.drivers.index');
        Route::get('report-center/drivers', [DriverPerformanceController::class, 'report'])->name('report-center.drivers');
        // export BEFORE {driver}: registered after it, the word export would be looked up as a driver id.
        Route::get('analytics/drivers/export', [DriverPerformanceController::class, 'export'])->name('analytics.drivers.export');
        Route::get('analytics/drivers/{driver}', [DriverPerformanceController::class, 'show'])->whereUuid('driver')->name('analytics.drivers.show');

        // Polled by the header bell so a new passenger report shows up without a refresh.
        // Inside the tenant gate: the count itself is about one cooperative's reports.
        Route::get('notifications/pending', [NotificationController::class, 'pending'])
            ->name('notifications.pending');
    }); // end tenant.admin

    // Account-administration history. Deliberately in NEITHER tier group: both roles may
    // open it and the SCOPE differs rather than the access -- an org_admin reads their own
    // cooperative's events, a super_admin reads platform-tier ones only. Enforced by
    // AccountAdminEvent::scopeVisibleTo, because this app connects as the database owner
    // and so bypasses the RLS policy in migration 037.
    Route::get('activity', [AdminActivityController::class, 'index'])->name('activity.index');

    // Self-service for the signed-in admin (both tiers): change display name / password.
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    // Throttled: this re-authenticates against GoTrue with a password grant, so an
    // unmetered endpoint is a credential-verification oracle for anyone who already
    // holds a session (audit L-9). POST /login is already throttled at 8,1.
    Route::put('account', [AccountController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('account.update');

    // A cooperative's own profile, branding and data-retention setting. org_admin
    // edits ITS OWN org (id comes from AdminContext, never the request); a
    // super_admin manages every org through the Organizations screens instead.
    Route::middleware('tenant.admin')->group(function () {
        Route::get('organization', [OrgProfileController::class, 'edit'])->name('organization.edit');
        Route::put('organization', [OrgProfileController::class, 'update'])->name('organization.update');
    });

    // Platform tier — super_admin ONLY. Tenants are never deleted (suspend instead).
    Route::middleware('super.admin')->group(function () {
        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        // One-flow onboarding: create a tenant + promote its first org_admin.
        Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
        Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

        // Promote a passenger to org_admin of a tenant / demote back to passenger.
        Route::get('org-admins', [OrgAdminController::class, 'index'])->name('org-admins.index');
        Route::get('org-admins/create', [OrgAdminController::class, 'create'])->name('org-admins.create');
        Route::post('org-admins', [OrgAdminController::class, 'store'])->name('org-admins.store');
        Route::delete('org-admins/{profile}', [OrgAdminController::class, 'destroy'])->name('org-admins.destroy');
        // Create an org-admin login outright (no passenger self-registration required)
        // and reset one's password — both proxy the driver-account Supabase Edge
        // Function's create_org_admin / reset_org_admin_password actions, mirroring
        // drivers.login.store / drivers.reset-password above. Kept off the org-admins
        // resource's own verbs/URIs and throttled the same way: each is a privileged
        // write against GoTrue.
        Route::post('org-admins/login', [OrgAdminController::class, 'storeLogin'])
            ->middleware('throttle:6,1')
            ->name('org-admins.login.store');
        Route::put('org-admins/{profile}/reset-password', [OrgAdminController::class, 'resetPassword'])
            ->middleware('throttle:6,1')
            ->name('org-admins.reset-password');
    });
});
