<?php

use App\Models\Attribute;
use App\Models\CommissionConfiguration;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\RecordMerge;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// AC-002 — links move, duplicate gone, trace written

it('moves every link to the survivor, deletes the duplicate and traces the merge', function () {
    $actor = mergeSuperAdmin();
    Sanctum::actingAs($actor);
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $duplicate->forceFill(['old_id' => 777])->save();

    $opportunity = Opportunity::factory()->create(['registry_id' => $duplicate->id]);
    $lead = Lead::factory()->create(['registry_id' => $duplicate->id]);
    $task = Task::factory()->create(['registry_id' => $duplicate->id]);
    $timeEntry = TimeEntry::factory()->create(['registry_id' => $duplicate->id]);
    $product = Product::factory()->create();
    $supplied = ProductSupplier::factory()->create(['product_id' => $product->id, 'supplier_id' => $duplicate->id]);
    $commission = CommissionConfiguration::factory()->create(['recipient_type' => 'registry', 'recipient_id' => $duplicate->id]);
    activity()->performedOn($duplicate)->event('updated')->log('touched');

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $survivor->id);

    expect(Registry::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and($opportunity->fresh()->registry_id)->toBe($survivor->id)
        ->and($lead->fresh()->registry_id)->toBe($survivor->id)
        ->and($task->fresh()->registry_id)->toBe($survivor->id)
        ->and($timeEntry->fresh()->registry_id)->toBe($survivor->id)
        ->and($supplied->fresh()->supplier_id)->toBe($survivor->id)
        ->and($commission->fresh()->recipient_id)->toBe($survivor->id)
        ->and(Activity::query()->where('subject_type', 'registry')->where('subject_id', $survivor->id)->where('description', 'touched')->exists())->toBeTrue();

    $merge = RecordMerge::query()->sole();
    expect($merge->entity_type)->toBe('registry')
        ->and($merge->survivor_id)->toBe($survivor->id)
        ->and($merge->merged_id)->toBe($duplicate->id)
        ->and($merge->merged_old_id)->toBe(777)
        ->and($merge->merged_name)->toBe('Acme S.r.l.')
        ->and($merge->merged_by)->toBe($actor->id)
        ->and($merge->snapshot['fields'])->not->toBeEmpty()
        ->and(collect($merge->snapshot['linked'])->firstWhere('key', 'opportunities')['count'])->toBe(1);

    $event = Activity::query()->where('event', 'merged')->sole();
    expect($event->subject_id)->toBe($survivor->id)
        ->and($event->getExtraProperty('merged'))->toBe([['id' => $duplicate->id, 'name' => 'Acme S.r.l.']]);
});

it('rewrites relation attribute values and relation custom fields to the survivor', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $other = mergeCompany('Other');

    Attribute::factory()->create(['code' => 'host_registry', 'type' => 'relation', 'relation_target' => ['entity_type' => 'registries', 'cardinality' => 'one']]);
    Attribute::factory()->create(['code' => 'plain_text', 'type' => 'text']);
    $asInt = Product::factory()->create();
    $asString = Product::factory()->create();
    $untouched = Product::factory()->create();
    DB::table('products')->where('id', $asInt->id)->update(['attribute_values' => json_encode(['host_registry' => $duplicate->id, 'plain_text' => (string) $duplicate->id])]);
    DB::table('products')->where('id', $asString->id)->update(['attribute_values' => json_encode(['host_registry' => (string) $duplicate->id])]);
    DB::table('products')->where('id', $untouched->id)->update(['attribute_values' => json_encode(['host_registry' => $other->id])]);

    CustomFieldDefinition::factory()->create(['entity_type' => 'referents', 'key' => 'favourite_registry', 'type' => 'relation', 'relation_target' => ['entity_type' => 'registries', 'cardinality' => 'many', 'for_select_resource' => 'registries']]);
    CustomFieldValue::query()->create(['entity_type' => 'referents', 'entity_id' => 1, 'values' => ['favourite_registry' => [$duplicate->id, $survivor->id, $other->id]]]);

    $linked = collect($this->postJson('/api/registries/merge/preview', ['ids' => [$survivor->id, $duplicate->id]])->json('data.linked'))->firstWhere('key', 'attribute_values');
    expect($linked['count'])->toBe(4);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect(json_decode(DB::table('products')->where('id', $asInt->id)->value('attribute_values'), true))->toBe(['host_registry' => $survivor->id, 'plain_text' => (string) $duplicate->id])
        ->and(json_decode(DB::table('products')->where('id', $asString->id)->value('attribute_values'), true))->toBe(['host_registry' => (string) $survivor->id])
        ->and(json_decode(DB::table('products')->where('id', $untouched->id)->value('attribute_values'), true))->toBe(['host_registry' => $other->id])
        ->and(CustomFieldValue::query()->where('entity_type', 'referents')->sole()->values['favourite_registry'])->toBe([$survivor->id, $other->id]);
});

it('keeps the survivor custom fields and fills its empty ones from the duplicates', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl');
    $first = mergeCompany('Dup One');
    $second = mergeCompany('Dup Two');
    CustomFieldValue::query()->create(['entity_type' => 'registries', 'entity_id' => $survivor->id, 'values' => ['kept' => 'mine', 'empty' => '']]);
    CustomFieldValue::query()->create(['entity_type' => 'registries', 'entity_id' => $first->id, 'values' => ['kept' => 'theirs', 'empty' => 'from-first', 'extra' => 'x']]);
    CustomFieldValue::query()->create(['entity_type' => 'registries', 'entity_id' => $second->id, 'values' => ['empty' => 'from-second', 'extra' => 'y']]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$second->id, $first->id]])->assertOk();

    expect(CustomFieldValue::query()->where('entity_type', 'registries')->sole()->values)
        ->toBe(['kept' => 'mine', 'empty' => 'from-first', 'extra' => 'x']);
});

// AC-003 — field choices

it('takes the value of the chosen record, combines long texts and re-derives the name', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl', ['general_notes' => 'Note A', 'employee_count' => 5]);
    $duplicate = mergeCompany('Acme S.r.l.', ['general_notes' => 'Note B', 'employee_count' => 50], ['vat_number' => null]);
    $third = mergeCompany('Acme Spa', ['general_notes' => 'Note A']);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id, $third->id],
        'fields' => ['company_name' => $duplicate->id, 'general_notes' => 'combine', 'employee_count' => $duplicate->id],
    ])->assertOk()->assertJsonPath('data.name', 'Acme S.r.l.');

    $survivor->refresh();
    expect($survivor->name)->toBe('Acme S.r.l.')
        ->and($survivor->personalData->company_name)->toBe('Acme S.r.l.')
        ->and($survivor->general_notes)->toBe("Note A\n\nNote B")
        ->and($survivor->employee_count)->toBe(50);
});

it('keeps the survivor value for fields missing from the payload', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl', ['employee_count' => 5]);
    $duplicate = mergeCompany('Other', ['employee_count' => 50]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect($survivor->fresh()->employee_count)->toBe(5)
        ->and($survivor->fresh()->name)->toBe('Acme Srl');
});

it('rejects a card that is not valid for its type and changes nothing', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl', [], ['first_name' => null, 'last_name' => null]);
    $person = mergeReferentLikeIndividualRegistry();
    Opportunity::factory()->create(['registry_id' => $person->id]);

    $this->postJson('/api/registries/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$person->id],
        'fields' => ['type' => $person->id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['fields.first_name', 'fields.last_name']);

    expect(Registry::query()->whereKey($person->id)->exists())->toBeTrue()
        ->and(RecordMerge::query()->count())->toBe(0)
        ->and(Opportunity::query()->where('registry_id', $survivor->id)->count())->toBe(0);
});

function mergeReferentLikeIndividualRegistry(): Registry
{
    return Registry::factory()
        ->withPersonalData(static fn ($factory) => $factory->individual()->state(['first_name' => null, 'last_name' => null]))
        ->create(['name' => '']);
}
