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
        'general_notes' => "Cliente storico\n\nNote riservate:\nSconto max 10%", 'status_label' => 'Chiusa',
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
        'manager_user_ids' => [3, 603], 'source_id' => 4, 'operational_site_id' => 5,
    ]]);

    $run = runOpportunitiesMigration();
    $opportunity = Opportunity::query()->where('old_id', 904)->with('managers')->firstOrFail();

    expect($run->created_rows)->toBe(1)
        ->and($opportunity->managers->pluck('pivot.position', 'id')->all())->toBe([$manager->id => 2])
        ->and(collect($run->report)->pluck('message')->all())->toEqualCanonicalizing([
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

// REQUIREMENT CHANGED (principle 2026-10-13): the quote-lines fallback is applied
// by the legacy, which sends a single `product_category_ids`.
it('creates the product lines from the product_category_ids the legacy sends', function () {
    opportunitiesSourceRegistry(14);
    [$first, $second] = ProductCategory::factory()->count(2)->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $first->forceFill(['old_id' => 41])->save();
    $second->forceFill(['old_id' => 42])->save();
    fakeLegacyOpportunities([
        ['id' => 906, 'registry_id' => 14, 'product_category_ids' => [41]],
        ['id' => 907, 'registry_id' => 14, 'product_category_ids' => [42]],
    ]);

    $run = runOpportunitiesMigration();
    $lines = fn (int $oldId): array => Opportunity::query()->where('old_id', $oldId)->firstOrFail()
        ->productLines()->pluck('product_category_id')->all();

    expect($run->created_rows)->toBe(2)
        ->and($run->report ?? [])->toBe([])
        ->and($lines(906))->toBe([$first->id])
        ->and($lines(907))->toBe([$second->id]);
});

it('creates the product lines on a migrated category that is not active, history only (user directive 2026-10-13)', function () {
    opportunitiesSourceRegistry(14);
    $inactive = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()->create()->id, 'is_active' => false]);
    $inactive->forceFill(['old_id' => 41])->save();
    fakeLegacyOpportunities([['id' => 906, 'registry_id' => 14, 'product_category_ids' => [41]]]);

    $run = runOpportunitiesMigration();

    expect($run->created_rows)->toBe(1)
        ->and($run->report ?? [])->toBe([])
        ->and(Opportunity::query()->where('old_id', 906)->firstOrFail()->productLines()->pluck('product_category_id')->all())->toBe([$inactive->id]);
});

// REQUIREMENT CHANGED (user decision 2026-10-14): a product category not
// migrated fails the opportunity instead of dropping the line with a warning.
it('fails an opportunity whose product category is not migrated, and one without a customer', function () {
    opportunitiesSourceRegistry(15);
    fakeLegacyOpportunities([
        ['id' => 908, 'registry_id' => 15, 'product_category_ids' => [43]],
        ['id' => 911, 'registry_id' => null],
    ]);

    $run = runOpportunitiesMigration();

    expect($run->failed_rows)->toBe(2)
        ->and($run->created_rows)->toBe(0)
        ->and(Opportunity::query()->whereIn('old_id', [908, 911])->count())->toBe(0)
        ->and(collect($run->report)->where('level', 'error')->pluck('message')->all())->toBe([
            'Failed to import the record: Product category (legacy id 43) not migrated; the opportunity is not imported.',
            'Failed to import the record: The opportunity has no customer in the legacy; it is not imported.',
        ]);
});

it('back-fills the product lines of an opportunity imported without any on a re-run, never overwriting', function () {
    $registry = opportunitiesSourceRegistry(15);
    [$own, $late] = ProductCategory::factory()->count(2)->create(['business_function_id' => BusinessFunction::factory()->create()->id]);
    $own->forceFill(['old_id' => 44])->save();
    $late->forceFill(['old_id' => 43])->save();
    Opportunity::factory()->create(['registry_id' => $registry->id])->forceFill(['old_id' => 908])->save();
    fakeLegacyOpportunities([
        ['id' => 908, 'registry_id' => 15, 'product_category_ids' => [43]],
        ['id' => 909, 'registry_id' => 15, 'product_category_ids' => [44]],
    ]);

    $first = runOpportunitiesMigration();
    $second = runOpportunitiesMigration();
    $lines = fn (int $oldId): array => Opportunity::query()->where('old_id', $oldId)->firstOrFail()
        ->productLines()->pluck('product_category_id')->all();

    expect($first->created_rows)->toBe(1)
        ->and($first->skipped_rows)->toBe(1)
        ->and(collect($first->report)->pluck('message')->all())->toBe(['Missing product categories back-filled.'])
        ->and($lines(908))->toBe([$late->id])
        ->and($lines(909))->toBe([$own->id])
        ->and($second->skipped_rows)->toBe(2)
        ->and($second->report ?? [])->toBe([]);
});

it('writes the general notes the legacy composed, Info preliminari blocks included', function () {
    opportunitiesSourceRegistry(16);
    fakeLegacyOpportunities([[
        'id' => 910, 'registry_id' => 16,
        'general_notes' => "Cliente storico\n\nInfo Preliminari APL-PAL:\nIn attesa di atto di concessione",
        'preliminary_info_blocks' => ["Info Preliminari APL-PAL:\nIn attesa di atto di concessione"],
    ]]);

    runOpportunitiesMigration();

    expect(Opportunity::query()->where('old_id', 910)->value('general_notes'))
        ->toBe("Cliente storico\n\nInfo Preliminari APL-PAL:\nIn attesa di atto di concessione");
});

it('back-fills the missing Info preliminari notes of an imported opportunity on a re-run, once', function () {
    opportunitiesSourceRegistry(17);
    fakeLegacyOpportunities([[
        'id' => 911, 'registry_id' => 17, 'general_notes' => 'Cliente storico', 'updated_at' => '2020-07-01 18:30:00',
        'preliminary_info_blocks' => ["Info Preliminari Soa:\nClassifica III", "Info Preliminari Gara:\nScadenza a marzo"],
    ]]);
    runOpportunitiesMigration();
    // As imported before the card notes existed, the SOA one already written by hand.
    Opportunity::query()->where('old_id', 911)->toBase()->update(['general_notes' => "Cliente storico\n\nInfo Preliminari Soa:\nClassifica III"]);

    $second = runOpportunitiesMigration();
    $third = runOpportunitiesMigration();
    $opportunity = Opportunity::query()->where('old_id', 911)->firstOrFail();

    expect($opportunity->general_notes)
        ->toBe("Cliente storico\n\nInfo Preliminari Soa:\nClassifica III\n\nInfo Preliminari Gara:\nScadenza a marzo")
        ->and($opportunity->updated_at->format('Y-m-d H:i:s'))->toBe('2020-07-01 18:30:00')
        ->and(collect($second->report)->pluck('message')->all())->toBe(['Missing preliminary info notes back-filled.'])
        ->and($third->report ?? [])->toBe([]);
});

it('refuses a lead on the import path', function () {
    $data = new CreateOpportunityData(
        registryId: Registry::factory()->create()->id, referentId: null, commercialId: null, reporterId: null,
        supervisorId: null, sourceId: null, leadId: 1, managerSlots: null, productLines: null, startDate: null,
        estimatedValue: null, expectedCloseDate: null, successProbability: null,
    );

    expect(fn () => app(OpportunityService::class)->import($data))->toThrow(InvalidArgumentException::class);
});
