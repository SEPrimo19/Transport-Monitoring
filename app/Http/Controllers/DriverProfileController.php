<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Driver;
use App\Support\AdminContext;
use App\Support\AssignmentStatus;
use App\Support\DriverAccountEmail;
use App\Support\FleetReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One driver's profile: who they are, what they are assigned to, how they are performing and what
 * was reported about their conduct, on one page for the cooperative's administrators.
 *
 * It is read-only and shows nothing the same administrators cannot already see elsewhere: the
 * licence and phone are on the Drivers list, the figures are the performance detail's. The
 * performance numbers and the complaints come from DriverPerformanceController::detail(), the one
 * place that join is written, pinned to the signed-in organization.
 *
 *  - TENANT ISOLATION. The route sits in tenant.admin, and `{driver}` is bound through the scoped
 *    Driver model, so another cooperative's driver is a 404 before this method runs. The account
 *    lookup below is keyed on that already-bound driver. DriverProfileIsolationTest pins the
 *    type-hint, the scope escapes and that Profile is never queried directly.
 *  - PRIVACY. The complainant is never read here, masked or not, and neither is a report's text.
 *    Only conduct and safety reports are listed, because a vehicle or route report is not about
 *    the driver.
 */
class DriverProfileController extends Controller
{
    public function __construct(private DriverPerformanceController $performance) {}

    public function show(Driver $driver, Request $request, AdminContext $ctx): View
    {
        $id = (string) $driver->profile_id;

        $full = (string) ($driver->profile?->full_name ?? '');
        $code = (string) ($driver->employee_code ?? '');
        $name = $full !== '' ? $full : ($code !== '' ? $code : 'Unnamed driver');

        return view('drivers.show', $this->performance->detail($driver, $request, $ctx, 10) + [
            'name' => $name,
            'signIn' => $this->signIn($id, $code),
            'assignments' => $this->assignments($id),
        ]);
    }

    /**
     * What the driver types to sign in. An admin-created login is a username (the account address
     * is a synthetic one derived from it, which is an implementation detail); a driver promoted
     * from a passenger account signs in with their own email. Read the way the Drivers list reads
     * it, through the scoped Driver model's join, keyed on this driver.
     *
     * @return array{kind: 'username'|'email'|'none', value: ?string}
     */
    private function signIn(string $id, string $code): array
    {
        $email = Driver::query()
            ->whereKey($id)
            ->leftJoin('auth.users', 'auth.users.id', '=', 'drivers.profile_id')
            ->value('auth.users.email');

        if (DriverAccountEmail::isUsernameLogin($email)) {
            return ['kind' => 'username', 'value' => $code !== '' ? $code : null];
        }

        return is_string($email) && $email !== ''
            ? ['kind' => 'email', 'value' => $email]
            : ['kind' => 'none', 'value' => null];
    }

    /** @return array<int, array<string, mixed>> */
    private function assignments(string $id): array
    {
        $now = CarbonImmutable::now();

        return Assignment::query()
            ->where('driver_profile_id', $id)
            ->with(['vehicle', 'route'])
            ->orderByDesc('starts_at')
            ->limit(10)
            ->get()
            ->map(static function (Assignment $a) use ($now): array {
                $vehicle = $a->vehicle;
                $label = $vehicle === null ? '—' : FleetReportBuilder::label($vehicle->fleet_code, $vehicle->plate_number);
                $plate = (string) ($vehicle?->plate_number ?? '');
                $route = (string) ($a->route?->code ?? '');

                return [
                    'vehicle' => $label,
                    'plate' => $plate !== '' && $plate !== $label ? $plate : '',
                    'route' => $route !== '' ? $route : '—',
                    'starts_at' => $a->starts_at,
                    'ends_at' => $a->ends_at,
                    'status' => AssignmentStatus::for((bool) $a->is_active, $a->starts_at, $a->ends_at, $now),
                ];
            })->all();
    }
}
