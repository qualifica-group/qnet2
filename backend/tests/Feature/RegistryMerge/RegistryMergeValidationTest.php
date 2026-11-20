<?php

use App\Migrations\Sources\RegistriesSource;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\PurchaseInvoice;
use App\Models\RecordMerge;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-007 — authorization

it('requires update on the survivor and delete on every duplicate', function () {
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $payload = ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]];

    $this->postJson('/api/registries/merge', $payload)->assertUnauthorized();

    Sanctum::actingAs(mergeActorWith(['registries.delete']));
    $this->postJson('/api/registries/merge', $payload)->assertForbidden();

    Sanctum::actingAs(mergeActorWith(['registries.update']));
    $this->postJson('/api/registries/merge', $payload)->assertForbidden();

    expect(Registry::query()->whereKey($duplicate->id)->exists())->toBeTrue();

    Sanctum::actingAs(mergeActorWith(['registries.update', 'registries.delete']));
    $this->postJson('/api/registries/merge', $payload)->assertOk();
});

// AC-008 — validation

it('rejects malformed merge requests with 422', function (array $payload) {
    Sanctum::actingAs(mergeSuperAdmin());
    $records = collect([mergeCompany('A'), mergeCompany('B'), mergeCompany('C')]);
    [$survivor, $first, $second] = $records->all();

    $payload = $payload + ['survivor_id' => $survivor->id, 'merged_ids' => [$first->id]];

    $this->postJson('/api/registries/merge', array_map(
        static fn (mixed $value) => $value instanceof Closure ? $value($survivor, $first, $second) : $value,
        $payload,
    ))->assertUnprocessable();

    expect(RecordMerge::query()->count())->toBe(0)
        ->and(Registry::query()->count())->toBe(3);
})->with([
    'no duplicates' => [['merged_ids' => []]],
    'survivor among the duplicates' => [['merged_ids' => fn ($survivor) => [$survivor->id]]],
    'repeated duplicates' => [['merged_ids' => fn ($survivor, $first) => [$first->id, $first->id]]],
    'unknown duplicate' => [['merged_ids' => [99999]]],
    'unknown field' => [['fields' => ['nonexistent' => 1]]],
    'field from a stranger record' => [['fields' => ['company_name' => 99999]]],
    'combine on a short field' => [['fields' => ['company_name' => 'combine']]],
    'unknown collection' => [['discard' => ['unknown' => [1]]]],
    'discard of an element of nobody' => [['discard' => ['contacts' => [99999]]]],
]);

it('rejects more than 5 records in total', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $records = Registry::factory()->count(6)->create();

    $this->postJson('/api/registries/merge', ['survivor_id' => $records[0]->id, 'merged_ids' => $records->slice(1)->pluck('id')->all()])->assertUnprocessable();
});

it('refuses to discard an element of the survivor and changes nothing', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl');
    $duplicate = mergeCompany('Acme S.r.l.');
    $own = mergeContact($survivor, 'phone', '+39 02 1112223');
    Opportunity::factory()->create(['registry_id' => $duplicate->id]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id], 'discard' => ['contacts' => [$own->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discard.contacts']);

    expect($own->fresh())->not->toBeNull()
        ->and(Opportunity::query()->where('registry_id', $duplicate->id)->count())->toBe(1);
});

it('answers 422 and rolls everything back when a uniqueness rule would be broken mid-merge', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeCompany('Acme Srl', ['is_supplier' => true]);
    $duplicate = mergeCompany('Acme S.r.l.', ['is_supplier' => true]);
    $opportunity = Opportunity::factory()->create(['registry_id' => $duplicate->id]);
    $contact = mergeContact($duplicate, 'email', 'info@acme.test');
    $company = Company::factory()->create();
    $shared = ['company_id' => $company->id, 'document_number' => 'FT-1', 'document_date' => '2026-03-01'];
    PurchaseInvoice::factory()->create([...$shared, 'supplier_id' => $survivor->id]);
    $clashing = PurchaseInvoice::factory()->create([...$shared, 'supplier_id' => $duplicate->id]);

    $this->postJson('/api/registries/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['merged_ids']);

    expect($clashing->fresh()->supplier_id)->toBe($duplicate->id)
        ->and($opportunity->fresh()->registry_id)->toBe($duplicate->id)
        ->and($contact->fresh()->contactable_id)->toBe($duplicate->personalData->id)
        ->and(Registry::query()->whereKey($duplicate->id)->exists())->toBeTrue()
        ->and(RecordMerge::query()->count())->toBe(0);
});

// AC-009 — legacy migration lookups

it('resolves a merged registry legacy id to its survivor, also along a chain', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $first = mergeCompany('First', ['old_id' => 11]);
    $middle = mergeCompany('Middle');
    $middle->forceFill(['old_id' => 22])->save();
    $last = mergeCompany('Last');

    $source = app(RegistriesSource::class);
    $exists = fn (int $oldId): bool => (new ReflectionMethod($source, 'existsByOldId'))->invoke($source, Registry::class, $oldId);
    $resolve = fn (int $oldId): ?int => (new ReflectionMethod($source, 'resolveOldId'))->invoke($source, Registry::class, $oldId);

    expect($exists(22))->toBeTrue()->and($resolve(33))->toBeNull();

    $this->postJson('/api/registries/merge', ['survivor_id' => $middle->id, 'merged_ids' => [$first->id]])->assertOk();
    expect($exists(11))->toBeTrue()->and($resolve(11))->toBe($middle->id)->and($resolve(22))->toBe($middle->id);

    $this->postJson('/api/registries/merge', ['survivor_id' => $last->id, 'merged_ids' => [$middle->id]])->assertOk();
    expect($resolve(11))->toBe($last->id)
        ->and($resolve(22))->toBe($last->id)
        ->and($exists(22))->toBeTrue()
        ->and($resolve(999))->toBeNull();
});
