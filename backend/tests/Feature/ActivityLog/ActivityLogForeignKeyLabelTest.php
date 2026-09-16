<?php

use App\Models\Address;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * FK-label extension of spec 0034 (client-reported: `registry_id: — → 206`
 * instead of the registry's name; noise rows like
 * `operational_site_id: — → —` for fields left null at creation). Mirrors the
 * other ActivityLog test files' self-contained `activityLogActor` helper.
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('fkLabelActor')) {
    function fkLabelActor(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("{$resource}.{$ability}");
        }

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// created: populated FK fields get a label, null-null fields are dropped
// ---------------------------------------------------------------------------

it('a created lead resolves FK labels for populated fields and drops null-null noise', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $registry = Registry::factory()->create(['name' => 'Jane Registry']);
    $campaign = Campaign::factory()->create(['name' => 'Spring Campaign']);
    Sanctum::actingAs($actor);

    // operational_site_id/source_id/operator_id stay null (LeadFactory default),
    // exactly the customer-reported noise ("operational_site_id: — → —").
    $lead = Lead::factory()->create([
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'notes' => 'Inbound call',
    ]);

    $items = collect($this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk()->json('data.items'));
    $created = $items->firstWhere('event', 'created');
    $byField = collect($created['changes'])->keyBy('field');

    expect($byField->keys()->all())->not->toContain('operational_site_id', 'source_id', 'operator_id');

    expect($byField['registry_id']['old_display'])->toBeNull()
        ->and($byField['registry_id']['new_display'])->toBe('Jane Registry')
        ->and($byField['campaign_id']['new_display'])->toBe('Spring Campaign');

    // A non-FK field never gets a display, FK or not.
    expect($byField['notes']['old_display'])->toBeNull()
        ->and($byField['notes']['new_display'])->toBeNull();
});

// ---------------------------------------------------------------------------
// updated: both sides of an FK change carry their label
// ---------------------------------------------------------------------------

it('an updated FK field resolves both old_display and new_display', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $registryA = Registry::factory()->create(['name' => 'Registry A']);
    $registryB = Registry::factory()->create(['name' => 'Registry B']);
    $lead = Lead::factory()->create(['registry_id' => $registryA->id]);
    Sanctum::actingAs($actor);

    $lead->update(['registry_id' => $registryB->id]);

    $items = collect($this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk()->json('data.items'));
    $updated = $items->firstWhere('event', 'updated');
    $change = collect($updated['changes'])->firstWhere('field', 'registry_id');

    expect($change['old_value'])->toBe($registryA->id)
        ->and($change['new_value'])->toBe($registryB->id)
        ->and($change['old_display'])->toBe('Registry A')
        ->and($change['new_display'])->toBe('Registry B');
});

// ---------------------------------------------------------------------------
// FK target since deleted: display falls back to null, raw id is untouched
// ---------------------------------------------------------------------------

it('an FK pointing to a since-deleted record resolves to a null display, keeping the raw id', function () {
    $actor = fkLabelActor('business-functions', ['view', 'viewActivity']);
    $manager = User::factory()->create(['name' => 'Former Manager']);
    $function = BusinessFunction::factory()->create(['manager_id' => $manager->id]);
    Sanctum::actingAs($actor);

    $before = collect($this->getJson("/api/activity-log/business-functions/{$function->id}")->assertOk()->json('data.items'));
    $beforeChange = collect($before->firstWhere('event', 'created')['changes'])->firstWhere('field', 'manager_id');
    expect($beforeChange['new_display'])->toBe('Former Manager');

    $manager->delete(); // business_functions.manager_id is nullOnDelete: allowed, no FK violation

    $after = collect($this->getJson("/api/activity-log/business-functions/{$function->id}")->assertOk()->json('data.items'));
    $afterChange = collect($after->firstWhere('event', 'created')['changes'])->firstWhere('field', 'manager_id');

    expect($afterChange['new_value'])->toBe($manager->id)
        ->and($afterChange['new_display'])->toBeNull();
});

// ---------------------------------------------------------------------------
// No N+1: one label query per related class, regardless of entry/field count
// ---------------------------------------------------------------------------

it('resolves FK labels with one batched query per related class, never per row/field', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $registries = Registry::factory()->count(3)->create();
    $campaign = Campaign::factory()->create();
    $lead = Lead::factory()->create(['registry_id' => $registries[0]->id, 'campaign_id' => $campaign->id]);
    Sanctum::actingAs($actor);

    // 3 more registry_id updates -> 4 distinct registry ids across 4 activity
    // rows (1 created + 3 updated), all resolved by a SINGLE `registries` query.
    foreach ([1, 2, 0] as $index) {
        $lead->update(['registry_id' => $registries[$index]->id]);
    }

    DB::enableQueryLog();
    $this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $labelQueries = fn (string $table): int => $queries->filter(
        fn (array $query): bool => str_contains($query['query'], "from \"{$table}\"") && str_contains($query['query'], ' in (')
    )->count();

    expect($labelQueries('registries'))->toBe(1)
        ->and($labelQueries('campaigns'))->toBe(1);
});

// ---------------------------------------------------------------------------
// Explicit entries (request management, D-9): Quote-level FKs logged on the
// Opportunity, which has no relation of its own for them
// ---------------------------------------------------------------------------

it('an explicit opportunity entry resolves Quote-level FKs through the configured foreign-key map', function () {
    $actor = fkLabelActor('opportunities', ['view', 'viewActivity']);
    $opportunity = Opportunity::factory()->create();
    $statusFrom = QuoteWorkflowStatus::factory()->create(['name' => 'Da contattare']);
    $statusTo = QuoteWorkflowStatus::factory()->create(['name' => 'Contattato']);
    $operatorFrom = User::factory()->create(['name' => 'Rosa Operatrice']);
    $operatorTo = User::factory()->create(['name' => 'Fabrizio Operatore']);
    Sanctum::actingAs($actor);

    activity($opportunity->getTable())
        ->performedOn($opportunity)
        ->causedBy($actor)
        ->event('updated')
        ->withProperties([
            'attributes' => ['quote_workflow_status_id' => $statusTo->id, 'operator_id' => $operatorTo->id],
            'old' => ['quote_workflow_status_id' => $statusFrom->id, 'operator_id' => $operatorFrom->id],
        ])
        ->log('Request management work update');

    $items = collect($this->getJson("/api/activity-log/opportunities/{$opportunity->id}")->assertOk()->json('data.items'));
    $entry = $items->first(fn (array $item): bool => collect($item['changes'])->contains('field', 'quote_workflow_status_id'));
    $byField = collect($entry['changes'])->keyBy('field');

    expect($byField['quote_workflow_status_id']['old_display'])->toBe('Da contattare')
        ->and($byField['quote_workflow_status_id']['new_display'])->toBe('Contattato')
        ->and($byField['operator_id']['old_display'])->toBe('Rosa Operatrice')
        ->and($byField['operator_id']['new_display'])->toBe('Fabrizio Operatore');
});

it('an explicit opportunity entry resolves id-list fields to their joined labels', function () {
    $actor = fkLabelActor('opportunities', ['view', 'viewActivity']);
    $opportunity = Opportunity::factory()->create();
    $categoryA = ProductCategory::factory()->create(['name' => 'Corsi']);
    $categoryB = ProductCategory::factory()->create(['name' => 'Master']);
    $manager = User::factory()->create(['name' => 'Gino Manager']);
    Sanctum::actingAs($actor);

    activity($opportunity->getTable())
        ->performedOn($opportunity)
        ->causedBy($actor)
        ->event('updated')
        ->withProperties([
            'attributes' => ['product_lines' => [$categoryA->id, $categoryB->id], 'manager_slots' => [null, $manager->id]],
            'old' => ['product_lines' => [$categoryA->id], 'manager_slots' => []],
        ])
        ->log('Request management work update');

    $items = collect($this->getJson("/api/activity-log/opportunities/{$opportunity->id}")->assertOk()->json('data.items'));
    $entry = $items->first(fn (array $item): bool => collect($item['changes'])->contains('field', 'product_lines'));
    $byField = collect($entry['changes'])->keyBy('field');

    expect($byField['product_lines']['old_display'])->toBe('Corsi')
        ->and($byField['product_lines']['new_display'])->toBe('Corsi, Master')
        ->and($byField['manager_slots']['old_display'])->toBe('—')
        ->and($byField['manager_slots']['new_display'])->toBe('—, Gino Manager');
});

// ---------------------------------------------------------------------------
// Related models with no `name` column: composed labels
// ---------------------------------------------------------------------------

it('an operational site FK resolves to its primary-address label', function () {
    $actor = fkLabelActor('leads', ['view', 'viewActivity']);
    $siteFrom = OperationalSite::factory()->create();
    $siteTo = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteFrom, 'addressable')->create(['line1' => 'Via Roma 1']);
    Address::factory()->primary()->for($siteTo, 'addressable')->create(['line1' => 'Via Milano 2']);
    $lead = Lead::factory()->create(['operational_site_id' => $siteFrom->id]);
    Sanctum::actingAs($actor);

    $lead->update(['operational_site_id' => $siteTo->id]);

    $items = collect($this->getJson("/api/activity-log/leads/{$lead->id}")->assertOk()->json('data.items'));
    $change = collect($items->firstWhere('event', 'updated')['changes'])->firstWhere('field', 'operational_site_id');

    expect($change['old_display'])->toBe('Via Roma 1')
        ->and($change['new_display'])->toBe('Via Milano 2');
});
