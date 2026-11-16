<?php

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-001 — preview

it('previews two registries with fields, collections, links and the suggested survivor', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $older = mergeCompany('Acme Srl', ['employee_count' => 10]);
    $newer = mergeCompany('Acme S.r.l.', ['employee_count' => 20]);
    mergeContact($older, 'phone', '+39 02 1112223', true);
    mergeContact($newer, 'phone', '+39 02 1112223', true);
    mergeContact($newer, 'email', 'info@acme.test');
    Opportunity::factory()->count(2)->create(['registry_id' => $newer->id]);
    Opportunity::factory()->create(['registry_id' => $older->id]);

    $response = $this->postJson('/api/registries/merge/preview', ['ids' => [$newer->id, $older->id]])->assertOk();
    $data = $response->json('data');

    expect($response->json('success'))->toBeTrue()
        ->and($data['suggested_survivor_id'])->toBe($newer->id)
        ->and(array_column($data['records'], 'id'))->toBe([$older->id, $newer->id]);

    $employees = collect($data['fields'])->firstWhere('key', 'employee_count');
    expect($employees['type'])->toBe('number')
        ->and(array_column($employees['values'], 'value'))->toBe([10, 20]);
    expect(collect($data['fields'])->firstWhere('key', 'general_notes')['combinable'])->toBeTrue()
        ->and(collect($data['fields'])->firstWhere('key', 'company_name')['values'][1]['value'])->toBe('Acme S.r.l.');

    $contacts = collect($data['collections'])->firstWhere('key', 'contacts')['items'];
    expect($contacts)->toHaveCount(3)
        ->and(collect($contacts)->countBy('match_key')->max())->toBe(2)
        ->and(collect($contacts)->pluck('match_key')->filter(fn ($key) => str_starts_with((string) $key, 'email|'))->all())->toHaveCount(1);

    $opportunities = collect($data['linked'])->firstWhere('key', 'opportunities');
    expect($opportunities['count'])->toBe(3)
        ->and($opportunities['items'])->toHaveCount(3)
        ->and(collect($data['linked'])->pluck('key')->all())->toBe(['opportunities']);
});

it('counts and samples the links per record, so any survivor can be shown', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $busy = mergeCompany('Busy');
    $other = mergeCompany('Other');
    Opportunity::factory()->count(22)->create(['registry_id' => $busy->id]);
    Opportunity::factory()->count(2)->create(['registry_id' => $other->id]);

    $opportunities = collect($this->postJson('/api/registries/merge/preview', ['ids' => [$busy->id, $other->id]])
        ->assertOk()
        ->json('data.linked'))->firstWhere('key', 'opportunities');

    expect($opportunities['count'])->toBe(24)
        ->and($opportunities['counts'])->toBe([
            ['record_id' => $busy->id, 'count' => 22],
            ['record_id' => $other->id, 'count' => 2],
        ])
        ->and(collect($opportunities['items'])->countBy('record_id')->all())->toBe([$busy->id => 20, $other->id => 2]);
});

it('suggests the oldest record when the links tie', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $first = mergeCompany('One');
    $second = mergeCompany('Two');

    $this->postJson('/api/registries/merge/preview', ['ids' => [$second->id, $first->id]])
        ->assertOk()
        ->assertJsonPath('data.suggested_survivor_id', $first->id);
});

it('shows the readable name of relation fields', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $source = Source::factory()->create(['name' => 'Fiera 2026']);
    $first = mergeCompany('One', ['source_id' => $source->id]);
    $second = mergeCompany('Two');

    $field = collect($this->postJson('/api/registries/merge/preview', ['ids' => [$first->id, $second->id]])->json('data.fields'))->firstWhere('key', 'source_id');

    expect($field['type'])->toBe('relation')
        ->and($field['values'][0])->toMatchArray(['value' => $source->id, 'display' => 'Fiera 2026'])
        ->and($field['values'][1])->toMatchArray(['value' => null, 'display' => null]);
});

it('requires view on every record and authentication for the preview', function () {
    $first = mergeCompany('One');
    $second = mergeCompany('Two');

    $this->postJson('/api/registries/merge/preview', ['ids' => [$first->id, $second->id]])->assertUnauthorized();

    Sanctum::actingAs(mergeActorWith(['registries.update', 'registries.delete']));
    $this->postJson('/api/registries/merge/preview', ['ids' => [$first->id, $second->id]])->assertForbidden();

    Sanctum::actingAs(mergeActorWith(['registries.view']));
    $this->postJson('/api/registries/merge/preview', ['ids' => [$first->id, $second->id]])->assertOk();
});

it('rejects a preview of fewer than 2 or more than 5 records and unknown ids', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $records = Registry::factory()->count(6)->create();

    $this->postJson('/api/registries/merge/preview', ['ids' => [$records[0]->id]])->assertUnprocessable();
    $this->postJson('/api/registries/merge/preview', ['ids' => $records->pluck('id')->all()])->assertUnprocessable();
    $this->postJson('/api/registries/merge/preview', ['ids' => [$records[0]->id, 99999]])->assertUnprocessable();
    $this->postJson('/api/registries/merge/preview', ['ids' => [$records[0]->id, $records[0]->id]])->assertUnprocessable();
});
