<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\TripMetric;
use Tests\TestCase;

/**
 * trip_metrics holds per-driver performance figures, which are HR data. These pin the three
 * properties that keep one cooperative's drivers out of another's console and keep this
 * model from ever becoming a write path. DB-free: .env points at the live Supabase.
 */
class TripMetricIsolationTest extends TestCase
{
    public function test_it_is_organization_scoped(): void
    {
        $this->assertContains(BelongsToOrganization::class, class_uses_recursive(TripMetric::class),
            'Without OrganizationScope an org_admin would see every cooperative\'s drivers.');
    }

    public function test_it_cannot_be_mass_assigned(): void
    {
        $metric = new TripMetric;

        $this->assertFalse($metric->isFillable('driver_profile_id'));
        $this->assertFalse($metric->isFillable('punctuality'));
        $this->assertTrue($metric->totallyGuarded(),
            'Rows are written only by rollup_trip_metrics() in the database.');
    }

    public function test_the_model_carries_no_write_path(): void
    {
        $source = file_get_contents((new \ReflectionClass(TripMetric::class))->getFileName());

        foreach (['::create(', '->create(', '->update(', '->save(', '->delete(', '->insert('] as $write) {
            $this->assertStringNotContainsString($write, $source, "TripMetric must stay read-only ($write).");
        }
    }

    public function test_the_key_is_the_trip_id(): void
    {
        $metric = new TripMetric;

        $this->assertSame('trip_id', $metric->getKeyName());
        $this->assertFalse($metric->getIncrementing());
        $this->assertSame('string', $metric->getKeyType());
    }
}
