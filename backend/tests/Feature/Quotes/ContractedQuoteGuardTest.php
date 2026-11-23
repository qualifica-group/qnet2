<?php

declare(strict_types=1);

use App\Models\Quote;
use App\Models\Referent;
use App\Models\User;
use App\Services\Quotes\ContractedQuoteGuard;
use App\Services\RequestManagement\RequestManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Spec 0404 part E: an offer under contract is frozen (AC-021..AC-023).

uses(RefreshDatabase::class);

function contractedEditor(bool $mayEditContracted): User
{
    $actor = quoteTableUserWith(['viewAny', 'view', 'update', ...($mayEditContracted ? ['updateContracted'] : [])]);

    foreach (['contracts.view', 'contracts.update', 'contracts.viewAll'] as $permission) {
        $actor->givePermissionTo(Permission::findOrCreate($permission));
    }

    return $actor;
}

it('AC-021: without the permission every channel refuses a change to an offer under contract, nothing is written', function () {
    ['contract' => $contract, 'quote' => $quote] = inForceContract();
    $title = $quote->title;
    Sanctum::actingAs(contractedEditor(false));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Nuovo titolo'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Quote under contract: editing is not allowed.');
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'title', 'value' => 'Dalla cella'])->assertForbidden();
    $this->patchJson("/api/contracts/{$contract->id}", ['title' => 'Dal contratto'])->assertForbidden();

    expect($quote->fresh()->title)->toBe($title);
});

it('AC-021: the system status of an offer under contract is frozen too', function () {
    ['quote' => $quote] = inForceContract();
    $statusId = $quote->quote_workflow_status_id;
    Sanctum::actingAs(contractedEditor(false));
    $lost = Quote::query()->getConnection()->table('quote_system_statuses')->where('system_key', 'closed_lost')->value('id');

    $this->patchJson("/api/quotes/{$quote->id}", ['quote_system_status_id' => $lost])->assertForbidden();

    expect($quote->fresh()->quote_workflow_status_id)->toBe($statusId);
});

it('AC-021/E-D3: team, next callback and operational site stay free; a value resent unchanged is no change', function () {
    ['quote' => $quote] = inForceContract();
    $actor = contractedEditor(false);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => $quote->title, 'quote_date' => $quote->quote_date->format('Y-m-d')])->assertOk();

    $panel = app(RequestManagementService::class)->updateWork($quote, $actor, ['next_callback_at' => '2026-12-01 10:00']);

    expect($panel['quote']->next_callback_at?->format('Y-m-d H:i'))->toBe('2026-12-01 10:00');
});

it('AC-021: Gestione Richieste refuses a commercial field of an offer under contract', function () {
    ['quote' => $quote] = inForceContract();
    $actor = contractedEditor(false);
    $commercial = Referent::factory()->create();

    expect(fn () => app(RequestManagementService::class)->updateWork($quote, $actor, ['commercial_id' => $commercial->id]))
        ->toThrow(HttpException::class);
    expect($quote->fresh()->commercial_id)->not->toBe($commercial->id);
});

it('AC-022: with the permission the first try asks for a confirmation, the confirmed one writes and is logged', function () {
    ['quote' => $quote] = inForceContract();
    Sanctum::actingAs(contractedEditor(true));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Nuovo titolo'])
        ->assertStatus(409)
        ->assertJsonPath('code', ContractedQuoteGuard::CONFIRMATION_CODE);
    expect($quote->fresh()->title)->not->toBe('Nuovo titolo');

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Nuovo titolo', ...contractedEditConfirmed()])->assertOk();

    $entry = Activity::query()->where('subject_id', $quote->id)->where('event', ContractedQuoteGuard::EVENT)->sole();
    expect($quote->fresh()->title)->toBe('Nuovo titolo')
        ->and($entry->properties['fields'])->toBe(['title']);
});

it('AC-022: the grid cell follows the same confirmation', function () {
    ['quote' => $quote] = inForceContract();
    Sanctum::actingAs(contractedEditor(true));

    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'title', 'value' => 'Dalla cella'])->assertStatus(409);
    $this->patchJson("/api/tables/quotes/rows/{$quote->id}", ['column' => 'title', 'value' => 'Dalla cella', ...contractedEditConfirmed()])->assertOk();

    expect($quote->fresh()->title)->toBe('Dalla cella');
});

it('AC-023: an offer without a contract changes as before, and the resources carry the flags', function () {
    $quote = Quote::factory()->create();
    Sanctum::actingAs(contractedEditor(false));

    $this->patchJson("/api/quotes/{$quote->id}", ['title' => 'Libera'])
        ->assertOk()
        ->assertJsonPath('data.is_contracted', false)
        ->assertJsonPath('data.can_update_contracted', false);

    ['quote' => $contracted] = inForceContract();
    $this->getJson("/api/quotes/{$contracted->id}")->assertOk()->assertJsonPath('data.is_contracted', true);
});
