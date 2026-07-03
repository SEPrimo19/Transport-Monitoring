<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps public.terminals (schema owned by the Android repo's migrations). Org-scoped:
 * a route's origin/destination must be terminals in the SAME organization.
 */
class Terminal extends Model
{
    use BelongsToOrganization;

    protected $table = 'terminals';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'latitude',
        'longitude',
        'address',
        'is_active',
        'organization_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** "ALN — Allen Terminal" for select options. */
    public function label(): string
    {
        return "{$this->code} — {$this->name}";
    }
}
