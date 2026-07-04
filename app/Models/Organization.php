<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps the existing Supabase `organizations` table (the TENANT ROOT).
 * Deliberately NOT org-scoped: super_admin lists/creates orgs; org metadata is
 * discoverable. Branding columns here are what the mobile app's Phase 3 theming
 * reads: primary_color, secondary_color, logo_url, region_center_lat/lng, map_style_url.
 */
class Organization extends Model
{
    protected $table = 'organizations';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'legal_name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'region_center_lat',
        'region_center_lng',
        'region_bbox',
        'map_style_url',
        'contact_email',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'region_bbox' => 'array',
        'region_center_lat' => 'float',
        'region_center_lng' => 'float',
    ];

    /** Matches the organizations_status_chk CHECK constraint. */
    public const STATUSES = [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return $this->status === 'active' ? 'text-bg-success' : 'text-bg-warning';
    }

    public function hasRegionCenter(): bool
    {
        return $this->region_center_lat !== null && $this->region_center_lng !== null;
    }
}
