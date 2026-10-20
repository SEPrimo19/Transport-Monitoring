<?php

namespace Tests\Feature;

use App\Models\Route;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Tests\TestCase;

/**
 * Regression: "Add route" returned a 500 and created the row anyway.
 *
 * routes.id is `uuid primary key default gen_random_uuid()`, so Postgres mints the
 * key. Eloquent only reads a generated key back for an INCREMENTING model —
 * performInsert() calls insertAndSetId() for those and a bare insert for everything
 * else — so Route::create() returned a model whose getKey() was null while the row
 * sat happily in the database.
 *
 * Nothing noticed, because every other store() in the console redirects to a
 * parameterless *.index. RouteController::store() redirects to routes.geometry.edit,
 * whose URI is routes/{route}/geometry: the insert committed, URL generation threw
 * UrlGenerationException on the null key, and the admin got an error page for a
 * route that had been saved — skipping the polyline editor that redirect exists to
 * force, which is why the row showed "none" for its polyline.
 *
 * These tests need no database. The null key was a PHP-side fact, and so is the fix.
 */
class RouteKeyIsGeneratedBeforeInsertTest extends TestCase
{
    public function test_a_route_mints_its_own_key_instead_of_waiting_for_postgres(): void
    {
        $route = new Route;

        $this->assertNull($route->getKey(), 'a bare model should start with no key');
        $this->assertTrue(
            $route->usesUniqueIds(),
            'Route must generate its key in PHP; without this the key is only known to Postgres '
            .'and store() cannot build a URL from the model it just created.'
        );

        // Exactly what Model::performInsert() does immediately before the INSERT.
        $route->setUniqueIds();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $route->getKey(),
            'the generated key has to be a real UUID — the column type will reject anything else'
        );
    }

    public function test_the_redirect_that_store_performs_can_be_built_from_a_new_route(): void
    {
        $route = new Route;
        $route->setUniqueIds();

        $this->assertSame(
            '/routes/'.$route->getKey().'/geometry',
            route('routes.geometry.edit', $route, absolute: false)
        );
    }

    public function test_a_keyless_route_is_what_used_to_break_that_redirect(): void
    {
        // Pins the original failure, so removing the key generation fails loudly here
        // rather than quietly in production on the next "Add route".
        $this->expectException(UrlGenerationException::class);

        route('routes.geometry.edit', new Route);
    }
}
