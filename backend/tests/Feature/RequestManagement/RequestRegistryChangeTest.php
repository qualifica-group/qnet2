<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * The client select of the work panel (decisione utente 2026-11-09, spec 0371).
 */
uses(RefreshDatabase::class);

function registryChangeActor(bool $canUpdate = true): User
{
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("request-management.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.viewAll']);

    if ($canUpdate) {
        $user->givePermissionTo('request-management.update');
    }

    return $user;
}

/**
 * @param  array<string, mixed>  $opportunityAttributes
 */
function registryChangeQuote(array $opportunityAttributes = []): Quote
{
    return Quote::factory()->for(Opportunity::factory()->create($opportunityAttributes))->create();
}

function registryChangeClient(?string $taxCode = null): Registry
{
    $registry = Registry::factory()->create();
    $registry->personalData()->create([
        'type' => 'individual',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'tax_code' => $taxCode,
    ]);

    return $registry;
}

it('AC-001: moves the request onto the chosen client', function () {
    $quote = registryChangeQuote();
    $client = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $client->id])
        ->assertOk()
        ->assertJsonPath('data.registry.id', $client->id);

    expect($quote->opportunity->fresh()->registry_id)->toBe($client->id);
});

it('AC-002: applies to every offer of the opportunity', function () {
    $quote = registryChangeQuote();
    $sibling = Quote::factory()->for($quote->opportunity)->create();
    $client = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $client->id])->assertOk();

    expect($sibling->fresh()->opportunity->registry_id)->toBe($client->id);
});

it('AC-003: the client cannot be cleared', function () {
    $quote = registryChangeQuote();
    $original = $quote->opportunity->registry_id;
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => null])
        ->assertStatus(422)
        ->assertJsonValidationErrors('registry_id');

    expect($quote->opportunity->fresh()->registry_id)->toBe($original);
});

it('AC-004: a contract on any offer of the opportunity freezes the client', function () {
    $quote = registryChangeQuote();
    $sibling = Quote::factory()->for($quote->opportunity)->create();
    Contract::factory()->for($sibling)->create();
    $original = $quote->opportunity->registry_id;
    $client = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $client->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('registry_id');

    expect($quote->opportunity->fresh()->registry_id)->toBe($original);
});

it('AC-005: a registry inherited from a lead stays frozen', function () {
    $registry = registryChangeClient();
    $lead = Lead::factory()->create(['registry_id' => $registry->id]);
    $quote = registryChangeQuote(['registry_id' => $registry->id, 'lead_id' => $lead->id]);
    $client = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $client->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('registry_id');

    expect($quote->opportunity->fresh()->registry_id)->toBe($registry->id);
});

it('AC-006: resending the current client is not a change, even on a frozen record', function () {
    $quote = registryChangeQuote();
    Contract::factory()->for($quote)->create();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $quote->opportunity->registry_id])->assertOk();
});

it('AC-007: the panel says why the client is frozen', function () {
    $quote = registryChangeQuote();
    Sanctum::actingAs(registryChangeActor());

    $this->getJson("/api/request-management/{$quote->id}")->assertJsonPath('data.registry_lock_reason', null);

    Contract::factory()->for($quote)->create();

    $this->getJson("/api/request-management/{$quote->id}")->assertJsonPath('data.registry_lock_reason', 'contract');
});

it('AC-008: refuses the old client blocks alongside a change of client', function () {
    $quote = registryChangeQuote();
    $client = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", [
        'registry_id' => $client->id,
        'client_contacts' => [['type' => 'email', 'value' => 'old@example.test', 'is_primary' => true]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('client_contacts');

    expect($quote->opportunity->fresh()->registry_id)->not->toBe($client->id);
});

it('AC-009: a referent the new client does not share is cleared, a shared one stays', function () {
    $referent = Referent::factory()->create();
    $quote = registryChangeQuote(['referent_id' => $referent->id]);
    $unrelated = registryChangeClient();
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => $unrelated->id])->assertOk();
    expect($quote->opportunity->fresh()->referent_id)->toBeNull();

    $sharing = registryChangeClient();
    $sharing->referents()->attach($referent->id);
    $other = registryChangeQuote(['referent_id' => $referent->id]);

    $this->patchJson("/api/request-management/{$other->id}", ['registry_id' => $sharing->id])->assertOk();
    expect($other->opportunity->fresh()->referent_id)->toBe($referent->id);
});

it('AC-010: a positive close in the same save reads the fiscal identity of the new client', function () {
    $quote = registryChangeQuote();
    $closedWon = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::ClosedWon, 'sort_order' => 99]);
    Sanctum::actingAs(registryChangeActor());

    $this->patchJson("/api/request-management/{$quote->id}", [
        'registry_id' => registryChangeClient()->id,
        'quote_workflow_status_id' => $closedWon->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('registry_id');

    $this->patchJson("/api/request-management/{$quote->id}", [
        'registry_id' => registryChangeClient('RSSMRA80A01H501U')->id,
        'quote_workflow_status_id' => $closedWon->id,
    ])->assertOk();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($closedWon->id);
});

it('AC-011: an actor without update cannot change the client', function () {
    $quote = registryChangeQuote();
    $original = $quote->opportunity->registry_id;
    Sanctum::actingAs(registryChangeActor(canUpdate: false));

    $this->patchJson("/api/request-management/{$quote->id}", ['registry_id' => registryChangeClient()->id])->assertForbidden();

    expect($quote->opportunity->fresh()->registry_id)->toBe($original);
});
