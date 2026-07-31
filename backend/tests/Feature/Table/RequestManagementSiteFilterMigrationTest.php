<?php

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use App\Models\User;
use App\Models\UserTableFilter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Coverage for the data migration that drops the stale, free-text
 * `operational_site` advanced filter of the `request-management` domain (user
 * directive 2026-07-31: the filter became an id-based picker).
 *
 * The migration is destructive and irreversible, so what it must NOT touch is
 * asserted as explicitly as what it removes: the other advanced filters of the
 * same row, and the same filter name saved for a DIFFERENT domain (where it is
 * still a free-text needle).
 */
uses(RefreshDatabase::class);

function siteFilterMigration(): Migration
{
    return require database_path('migrations/2026_08_01_110000_drop_stale_request_management_site_advanced_filter.php');
}

it('drops the stale operational_site needle from the applied filter state, keeping every other filter', function () {
    $state = UserTableFilter::create([
        'user_id' => User::factory()->create()->id,
        'domain' => 'request-management',
        'filters' => [],
        'advanced_filters' => ['operational_site' => 'Milano', 'registry' => [7]],
    ]);

    siteFilterMigration()->up();

    expect($state->fresh()->advanced_filters)->toBe(['registry' => [7]]);
});

it('drops the stale needle from a saved filter view too', function () {
    $view = TableFilterView::create([
        'user_id' => User::factory()->create()->id,
        'domain' => 'request-management',
        'name' => 'Milano',
        'filters' => [],
        'visibility' => FilterViewVisibility::Private,
        'advanced_filters' => ['operational_site' => 'Milano'],
    ]);

    siteFilterMigration()->up();

    expect($view->fresh()->advanced_filters)->toBe([]);
});

it('leaves another domain\'s operational_site filter untouched', function () {
    $state = UserTableFilter::create([
        'user_id' => User::factory()->create()->id,
        'domain' => 'opportunities',
        'filters' => [],
        'advanced_filters' => ['operational_site' => 'Milano'],
    ]);

    siteFilterMigration()->up();

    expect($state->fresh()->advanced_filters)->toBe(['operational_site' => 'Milano']);
});
