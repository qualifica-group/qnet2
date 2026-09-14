<?php

use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Permissions and navigation entry of the `time-entries` resource (spec 0122,
| D-8, AC-027)
|--------------------------------------------------------------------------
*/

it('AC-027: permissions:sync creates exactly the 9 time-entries.* permissions of D-8', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
        expect(Permission::query()->where('name', "time-entries.{$ability}")->exists())
            ->toBeTrue("missing permission time-entries.{$ability}");
    }

    // No `import`/`viewActivity`: the module never uses either (BasePolicy's
    // abilities() override in TimeEntryPolicy).
    expect(Permission::query()->where('name', 'like', 'time-entries.%')->count())->toBe(9);
});

it('AC-027: the time-entries navigation entry requires time-entries.viewAny', function () {
    $item = collect(config('navigation.items'))->firstWhere('key', 'time-entries');

    expect($item)->not->toBeNull()
        ->and($item['permission'])->toBe('time-entries.viewAny')
        ->and($item['route'])->toBe('/time-entries');

    expect(app(NavigationService::class)->permissions())->toContain('time-entries.viewAny');
});
