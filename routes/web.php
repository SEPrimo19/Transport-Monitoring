<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\RouteGeometryController;
use App\Http\Controllers\TerminalController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Guest auth — Supabase GoTrue password grant → access token in the session.
Route::get('/login', [AuthController::class, 'show'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Everything below requires an authenticated, active org_admin / super_admin.
// The middleware verifies the session/bearer token and fills AdminContext.
Route::middleware('supabase.admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('vehicles', VehicleController::class)->except(['show']);
    Route::resource('drivers', DriverController::class)->except(['show']);
    Route::resource('terminals', TerminalController::class)->except(['show']);
    Route::resource('routes', RouteController::class)->except(['show']);
    // Polyline map editor for a route (org-scoped via the {route} binding).
    Route::get('routes/{route}/geometry', [RouteGeometryController::class, 'edit'])->name('routes.geometry.edit');
    Route::put('routes/{route}/geometry', [RouteGeometryController::class, 'update'])->name('routes.geometry.update');
    Route::delete('routes/{route}/geometry', [RouteGeometryController::class, 'destroy'])->name('routes.geometry.destroy');
    Route::resource('assignments', AssignmentController::class)->except(['show']);
    Route::resource('reports', ReportController::class)->only(['index', 'show', 'update']);

    // Platform tier — super_admin ONLY. Tenants are never deleted (suspend instead).
    Route::middleware('super.admin')->group(function () {
        Route::resource('organizations', OrganizationController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);
    });
});
