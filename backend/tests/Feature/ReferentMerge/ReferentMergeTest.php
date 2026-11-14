<?php

use App\Models\Campaign;
use App\Models\CommissionConfiguration;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\RecordMerge;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Reward;
use App\Models\Task;
use App\Models\User;
use App\Services\Merge\MergedRecordResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// AC-001 — preview

it('previews two referents with the referent fields and collections', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $first = mergeReferent('Ada', 'Lovelace', ['notes' => 'A']);
    $second = mergeReferent('Ada', 'Lovelace', ['notes' => 'B']);
    mergeContact($first, 'phone', '+39 02 1112223');
    mergeContact($second, 'phone', '+39 02 1112223');
    $registry = mergeCompany('Acme');
    $registry->referents()->attach($second);
    Opportunity::factory()->count(2)->create(['commercial_id' => $second->id]);

    $data = $this->postJson('/api/referents/merge/preview', ['ids' => [$first->id, $second->id]])->assertOk()->json('data');

    expect($data['suggested_survivor_id'])->toBe($second->id)
        ->and(collect($data['fields'])->pluck('key')->all())->toContain('referent_type_id', 'contact_scope', 'notes', 'user_id', 'supplier_registry_id')
        ->and(collect($data['fields'])->firstWhere('key', 'notes')['combinable'])->toBeTrue()
        ->and(collect($data['collections'])->pluck('key')->all())->toBe(['contacts', 'addresses', 'registries', 'notes'])
        ->and(collect(collect($data['collections'])->firstWhere('key', 'contacts')['items'])->countBy('match_key')->max())->toBe(2)
        ->and(collect(collect($data['collections'])->firstWhere('key', 'notes')['items'])->pluck('match_key')->filter()->all())->toBe([])
        ->and(collect($data['linked'])->firstWhere('key', 'opportunities')['count'])->toBe(2)
        ->and(collect($data['collections'])->firstWhere('key', 'registries')['items'][0]['label'])->toBe('Acme');
});

// AC-006 — merge

it('moves registries, roles, opportunities, tasks, rewards, projects, campaigns and commissions to the survivor', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace');
    $duplicate->forceFill(['old_id' => 55])->save();

    $registry = mergeCompany('Acme', ['commercial_id' => $duplicate->id, 'reporter_id' => $duplicate->id]);
    $registry->referents()->attach($duplicate);
    $registry->forceFill(['primary_referent_id' => $duplicate->id])->save();
    $opportunity = Opportunity::factory()->create(['referent_id' => $duplicate->id, 'commercial_id' => $duplicate->id, 'reporter_id' => $duplicate->id]);
    $task = Task::factory()->create(['referent_id' => $duplicate->id]);
    $project = Project::factory()->create(['partner_id' => $duplicate->id]);
    $campaign = Campaign::factory()->create(['partner_id' => $duplicate->id]);
    $reward = Reward::factory()->for($opportunity, 'source')->create(['referent_id' => $duplicate->id]);
    $commission = CommissionConfiguration::factory()->create(['recipient_type' => 'referent', 'recipient_id' => $duplicate->id]);

    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])
        ->assertOk()
        ->assertJsonPath('data.id', $survivor->id);

    $registry->refresh();
    expect(Referent::query()->whereKey($duplicate->id)->exists())->toBeFalse()
        ->and($registry->commercial_id)->toBe($survivor->id)
        ->and($registry->reporter_id)->toBe($survivor->id)
        ->and($registry->primary_referent_id)->toBe($survivor->id)
        ->and($registry->referents()->pluck('referents.id')->all())->toBe([$survivor->id])
        ->and($opportunity->fresh()->only(['referent_id', 'commercial_id', 'reporter_id']))->toBe(['referent_id' => $survivor->id, 'commercial_id' => $survivor->id, 'reporter_id' => $survivor->id])
        ->and($task->fresh()->referent_id)->toBe($survivor->id)
        ->and($project->fresh()->partner_id)->toBe($survivor->id)
        ->and($campaign->fresh()->partner_id)->toBe($survivor->id)
        ->and($reward->fresh()->referent_id)->toBe($survivor->id)
        ->and($commission->fresh()->recipient_id)->toBe($survivor->id);

    $merge = RecordMerge::query()->sole();
    expect($merge->entity_type)->toBe('referent')->and($merge->merged_old_id)->toBe(55);
});

it('drops a reward that duplicates one of the survivor and keeps it in the snapshot', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace');
    $opportunity = Opportunity::factory()->create();
    $mine = Reward::factory()->for($opportunity, 'source')->create(['referent_id' => $survivor->id]);
    $clash = Reward::factory()->for($opportunity, 'source')->create(['referent_id' => $duplicate->id, 'reward_type_id' => $mine->reward_type_id]);

    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect(Reward::query()->whereKey($clash->id)->exists())->toBeFalse()
        ->and(Reward::query()->where('referent_id', $survivor->id)->count())->toBe(1)
        ->and(RecordMerge::query()->sole()->snapshot['dropped'][0]['table'])->toBe('rewards');
});

it('keeps the chosen user link without breaking its uniqueness', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $account = User::factory()->create();
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace', ['user_id' => $account->id]);

    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id], 'fields' => ['user_id' => $duplicate->id, 'notes' => 'combine']])->assertOk();

    expect($survivor->fresh()->user_id)->toBe($account->id);
});

it('combines the notes and unites the registries without duplicates', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeReferent('Ada', 'Lovelace', ['notes' => 'One']);
    $duplicate = mergeReferent('Ada', 'Lovelace', ['notes' => 'Two']);
    [$shared, $own, $discarded] = [mergeCompany('Shared'), mergeCompany('Own'), mergeCompany('Dropped')];
    $shared->referents()->attach([$survivor->id, $duplicate->id]);
    $own->referents()->attach($duplicate);
    $discarded->referents()->attach($duplicate);

    $this->postJson('/api/referents/merge', [
        'survivor_id' => $survivor->id,
        'merged_ids' => [$duplicate->id],
        'fields' => ['notes' => 'combine'],
        'discard' => ['registries' => [$discarded->id]],
    ])->assertOk();

    expect($survivor->fresh()->notes)->toBe("One\n\nTwo")
        ->and($survivor->registries()->pluck('registries.id')->sort()->values()->all())->toBe([$shared->id, $own->id])
        ->and($discarded->fresh()->referents()->count())->toBe(0);
});

it('requires update on the survivor and delete on the duplicates, and validates the payload', function () {
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace');
    $payload = ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]];

    $this->postJson('/api/referents/merge', $payload)->assertUnauthorized();

    Sanctum::actingAs(mergeActorWith(['referents.update']));
    $this->postJson('/api/referents/merge', $payload)->assertForbidden();
    Sanctum::actingAs(mergeActorWith(['referents.view']));
    $this->postJson('/api/referents/merge/preview', ['ids' => [$survivor->id, $duplicate->id]])->assertOk();
    $this->postJson('/api/referents/merge', $payload)->assertForbidden();

    Sanctum::actingAs(mergeSuperAdmin());
    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$survivor->id]])->assertUnprocessable();
    $this->postJson('/api/referents/merge', [...$payload, 'discard' => ['sectors' => [1]]])->assertUnprocessable();
    $this->postJson('/api/referents/merge', [...$payload, 'fields' => ['vat_group' => $survivor->id]])->assertUnprocessable();
    expect(Referent::query()->whereKey($duplicate->id)->exists())->toBeTrue();
});

it('resolves a merged referent legacy id to its survivor', function () {
    Sanctum::actingAs(mergeSuperAdmin());
    $survivor = mergeReferent('Ada', 'Lovelace');
    $duplicate = mergeReferent('Ada', 'Lovelace');
    $duplicate->forceFill(['old_id' => 91])->save();

    $this->postJson('/api/referents/merge', ['survivor_id' => $survivor->id, 'merged_ids' => [$duplicate->id]])->assertOk();

    expect(MergedRecordResolver::survivorId(Referent::class, 91))->toBe($survivor->id)
        ->and(MergedRecordResolver::survivorId(Registry::class, 91))->toBeNull();
});
