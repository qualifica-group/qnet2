<?php

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\BusinessFunction;
use App\Models\MigrationRun;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// OpportunitiesSource (spec 0189) — legacy opportunities via OpportunityService::import()
// ---------------------------------------------------------------------------

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function fakeLegacyOpportunities(array $items): void
{
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/opportunities*' => Http::response(['items' => $items, 'pagination' => ['total' => count($items)]]),
    ]);
}

function runOpportunitiesMigration(?User $actor = null): MigrationRun
{
    $actor ??= migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'opportunities']);

    runMigrationJobFor($run);

    return $run->fresh();
}

function opportunitiesSourceRegistry(int $oldId): Registry
{
    $registry = Registry::factory()->create();
    $registry->forceFill(['old_id' => $oldId])->save();

    return $registry;
}

it('imports an opportunity with every field remapped, without notifications or activity log', function () {
    Notification::fake();
    $registry = opportunitiesSourceRegistry(10);
    [$referent, $commercial, $reporter] = Referent::factory()->count(3)->create();
    $referent->forceFill(['old_id' => 801])->save();
    $commercial->forceFill(['old_id' => 802])->save();
    $reporter->forceFill(['old_id' => 803])->save();
    [$supervisor, $manager] = User::factory()->count(2)->create();
    $supervisor->forceFill(['old_id' => 601])->save();
    $manager->forceFill(['old_id' => 602])->save();
    $source = Source::factory()->create();
    $source->forceFill(['old_id' => 5])->save();
    $site = OperationalSite::factory()->create();
    $site->forceFill(['old_id' => 7])->save();
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $category->forceFill(['old_id' => 33])->save();
    $actor = migrationsSuperAdminActor();
    $activityRows = DB::table('activity_log')->count();

    fakeLegacyOpportunities([[
        'id' => 900, 'registry_id' => 10, 'title' => '  Rinnovo certificazione  ', 'referent_id' => 801,
        'commercial_referent_id' => 802, 'reporter_referent_id' => 803, 'supervisor_user_id' => 601,
        'manager_user_ids' => [602], 'source_id' => 5, 'operational_site_id' => 7, 'product_category_ids' => [33],
        'start_date' => '2020-01-15', 'expected_close_date' => '2020-06-30', 'estimated_value' => 12500.5,
        'notes' => 'Cliente storico', 'reserved_notes' => 'Sconto max 10%', 'status' => 3, 'status_label' => 'Chiusa',
        'created_at' => '2020-01-10 09:00:00', 'updated_at' => '2020-07-01 18:30:00',
    ]]);

    $run = runOpportunitiesMigration($actor);

    $opportunity = Opportunity::query()->where('old_id', 900)->with('managers', 'productLines')->firstOrFail();

    expect($run->created_rows)->toBe(1)
        ->and($run->report ?? [])->toBe([])
        ->and($opportunity->registry_id)->toBe($registry->id)
        ->and($opportunity->name)->toBe('Rinnovo certificazione')
        ->and($opportunity->referent_id)->toBe($referent->id)
        ->and($opportunity->commercial_id)->toBe($commercial->id)
        ->and($opportunity->reporter_id)->toBe($reporter->id)
        ->and($opportunity->supervisor_id)->toBe($supervisor->id)
        ->and($opportunity->managers->pluck('pivot.position', 'id')->all())->toBe([$manager->id => 1])
        ->and($opportunity->source_id)->toBe($source->id)
        ->and($opportunity->operational_site_id)->toBe($site->id)
        ->and($opportunity->productLines->pluck('product_category_id')->all())->toBe([$category->id])
        ->and($opportunity->start_date->toDateString())->toBe('2020-01-15')
        ->and($opportunity->expected_close_date->toDateString())->toBe('2020-06-30')
        ->and($opportunity->estimated_value)->toBe('12500.50')
        ->and($opportunity->success_probability)->toBeNull()
        ->and($opportunity->general_notes)->toBe("Cliente storico\n\nNote riservate:\nSconto max 10%")
        ->and($opportunity->created_at->format('Y-m-d H:i:s'))->toBe('2020-01-10 09:00:00')
        ->and($opportunity->updated_at->format('Y-m-d H:i:s'))->toBe('2020-07-01 18:30:00');

    Notification::assertNothingSent();
    expect(DB::table('activity_log')->count())->toBe($activityRows);
});

it('imports several open opportunities on the same registry, bypassing the one-open guard', function () {
    $registry = opportunitiesSourceRegistry(11);
    fakeLegacyOpportunities([
        ['id' => 901, 'registry_id' => 11, 'title' => null],
        ['id' => 902, 'registry_id' => 11, 'title' => ''],
    ]);

    $run = runOpportunitiesMigration();

    expect($run->created_rows)->toBe(2)
        ->and($registry->opportunities()->count())->toBe(2)
        ->and(Opportunity::query()->where('old_id', 901)->value('name_is_manual'))->toBeFalsy()
        ->and(fn () => app(RegistryOpenOpportunityGuard::class)->assertNoOpenOpportunity($registry->id))->toThrow(Exception::class);
});

it('fails the row when the registry was not migrated', function () {
    fakeLegacyOpportunities([['id' => 903, 'registry_id' => 4040]]);

    $run = runOpportunitiesMigration();

    expect($run->failed_rows)->toBe(1)
        ->and(Opportunity::query()->where('old_id', 903)->exists())->toBeFalse()
        ->and(collect($run->report)->firstWhere('level', 'error')['message'])
        ->toBe('Failed to import the record: Registry (legacy id 4040) not migrated.');
});

it('warns on unresolved optional references and keeps manager positions', function () {
    opportunitiesSourceRegistry(12);
    $manager = User::factory()->create();
    $manager->forceFill(['old_id' => 603])->save();
    fakeLegacyOpportunities([[
        'id' => 904, 'registry_id' => 12, 'referent_id' => 1, 'supervisor_user_id' => 2,
        'manager_user_ids' => [3, 603], 'source_id' => 4, 'operational_site_id' => 5, 'product_category_ids' => [6],
    ]]);

    $run = runOpportunitiesMigration();
    $opportunity = Opportunity::query()->where('old_id', 904)->with('managers')->firstOrFail();

    expect($run->created_rows)->toBe(1)
        ->and($opportunity->managers->pluck('pivot.position', 'id')->all())->toBe([$manager->id => 2])
        ->and(collect($run->report)->pluck('message')->all())->toEqualCanonicalizing([
            'Unresolved product_category_ids (legacy id 6).',
            'Unresolved referent_id (legacy id 1).',
            'Unresolved supervisor_user_id (legacy id 2).',
            'Unresolved source_id (legacy id 4).',
            'Unresolved manager_user_ids (legacy id 3).',
            'Unresolved operational_site_id (legacy id 5).',
        ]);
});

it('skips already imported opportunities on a second run', function () {
    opportunitiesSourceRegistry(13);
    fakeLegacyOpportunities([['id' => 905, 'registry_id' => 13]]);

    runOpportunitiesMigration();
    $second = runOpportunitiesMigration();

    expect($second->skipped_rows)->toBe(1)
        ->and(Opportunity::query()->where('old_id', 905)->count())->toBe(1);
});

it('refuses a lead on the import path', function () {
    $data = new CreateOpportunityData(
        registryId: Registry::factory()->create()->id, referentId: null, commercialId: null, reporterId: null,
        supervisorId: null, sourceId: null, leadId: 1, managerSlots: null, productLines: null, startDate: null,
        estimatedValue: null, expectedCloseDate: null, successProbability: null,
    );

    expect(fn () => app(OpportunityService::class)->import($data))->toThrow(InvalidArgumentException::class);
});
