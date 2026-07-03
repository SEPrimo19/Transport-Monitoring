<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Support\AdminContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(AdminContext $ctx): View
    {
        // Vehicle::count() runs through OrganizationScope: an org_admin sees only
        // their own fleet, a super_admin sees every org. The number on the card is
        // live proof the tenant scope is active in the UI — the same guarantee the
        // throwaway /admin/ping route demonstrated, now behind a real screen.
        $stats = [
            'vehicles' => Vehicle::count(),
        ];

        return view('dashboard', [
            'stats' => $stats,
        ]);
    }
}
