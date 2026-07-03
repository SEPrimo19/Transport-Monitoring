<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps public.profiles.
 *
 * INTENTIONALLY NOT org-scoped (no BelongsToOrganization trait): passengers are
 * org-less (organization_id IS NULL), and the promote-to-driver flow must find a
 * passenger by exact email across the whole table. There is deliberately NO
 * profile-listing screen in the admin — the only reads are:
 *   (a) a driver's own profile via Driver::profile() (already org-bounded because
 *       the Driver model IS org-scoped), and
 *   (b) the single, guarded exact-email lookup in DriverController::store().
 * Do NOT add a profiles index without first scoping this model, or you'd expose
 * the whole rider base to every org admin.
 */
class Profile extends Model
{
    protected $table = 'profiles';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'full_name',
        'role',
        'is_active',
        'organization_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
