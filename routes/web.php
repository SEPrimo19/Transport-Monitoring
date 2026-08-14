<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgAdminController;
use App\Http\Controllers\OrgProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteGeometryController;
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
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::get('/operations/data', [OperationsController::class, 'data'])->name('operations.data');
    Route::resource('vehicles', VehicleController::class)->except(['show']);
    Route::resource('drivers', DriverController::class)->except(['show']);
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

    // Polled by the header bell so a new passenger report shows up without a refresh.
    Route::get('notifications/pending', [NotificationController::class, 'pending'])
        ->name('notifications.pending');

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
    Route::get('organization', [OrgProfileController::class, 'edit'])->name('organization.edit');
    Route::put('organization', [OrgProfileController::class, 'update'])->name('organization.update');

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
    });
});
