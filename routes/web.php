<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgAdminController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteGeometryController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\TripScheduleController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

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

    // Platform tier — super_admin ONLY. Tenants are never deleted (suspend instead).
    Route::middleware('super.admin')->group(function () {
        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        // Promote a passenger to org_admin of a tenant / demote back to passenger.
        Route::get('org-admins', [OrgAdminController::class, 'index'])->name('org-admins.index');
        Route::get('org-admins/create', [OrgAdminController::class, 'create'])->name('org-admins.create');
        Route::post('org-admins', [OrgAdminController::class, 'store'])->name('org-admins.store');
        Route::delete('org-admins/{profile}', [OrgAdminController::class, 'destroy'])->name('org-admins.destroy');
    });
});
