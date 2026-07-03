<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps public.drivers (schema owned by the Android repo's migrations — do NOT
 * create a Laravel migration for it).
 *
 * A "driver" is really three rows sharing one id:
 *   - this drivers row  → employment fields (employee_code, license_number, phone)
 *   - its profiles row  → full_name, role, is_active  (via {@see profile()})
 *   - its auth.users row → email                       (read separately)
 * The PK is profile_id, which equals profiles.id = auth.users.id.
 *
 * is_active here means "may currently drive" (suspend/reactivate). It is distinct
 * from profiles.is_active (account enabled), which gates login.
 */
class Driver extends Model
{
    use BelongsToOrganization;

    protected $table = 'drivers';
    protected $primaryKey = 'profile_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'profile_id',
        'employee_code',
        'license_number',
        'phone',
        'is_active',
        'organization_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id', 'id');
    }

    public function statusBadge(): string
    {
        return $this->is_active ? 'text-bg-success' : 'text-bg-secondary';
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Suspended';
    }
}
