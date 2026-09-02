<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/contracts/{contract}/work-orders (spec 0095, D-3/D-6/D-11):
 * AC-030..035. Delegates to WorkOrderService::create() (AC-035): the
 * generated commessa is shape-identical to POST /api/work-orders.
 */
uses(RefreshDatabase::class);

if (! function_exists('workOrderGenerationActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderGenerationActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'program'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }
        foreach (['viewAny', 'view', 'create'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('generationContract')) {
    function generationContract(): Contract
    {
        return Contract::factory()->create([
            'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
        ]);
    }
}

it('AC-030: 2 free lines create ONE work order with exactly those 2 lines and a sequential COM- code', function () {
    $contract = generationContract();
    $lineA = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Prima lavorazione', 'type' => 'processing', 'quote_line_ids' => [$lineA->id, $lineB->id],
    ])->assertCreated();

    expect($response->json('data.code'))->toBe('COM-0001')
        ->and($response->json('data.quote.id'))->toBe($contract->quote_id)
        ->and(collect($response->json('data.quote_lines'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$lineA->id, $lineB->id])->sort()->values()->all());

    $workOrderId = $response->json('data.id');
    $this->assertDatabaseHas('quote_line_work_order', ['work_order_id' => $workOrderId, 'quote_line_id' => $lineA->id]);
    $this->assertDatabaseHas('quote_line_work_order', ['work_order_id' => $workOrderId, 'quote_line_id' => $lineB->id]);
});

it('AC-031: repeating the action on the SAME contract with other lines creates a SECOND work order', function () {
    $contract = generationContract();
    $lineA = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $first = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Prima', 'type' => 'processing', 'quote_line_ids' => [$lineA->id],
    ])->assertCreated();

    $second = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Seconda', 'type' => 'project', 'quote_line_ids' => [$lineB->id],
    ])->assertCreated();

    expect($first->json('data.code'))->toBe('COM-0001')
        ->and($second->json('data.code'))->toBe('COM-0002')
        ->and(WorkOrder::query()->where('quote_id', $contract->quote_id)->count())->toBe(2);
});

it('AC-032: a line already programmed in another work order is 422, names the occupying commessa, creates nothing', function () {
    $contract = generationContract();
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $occupying = WorkOrder::factory()->create(['quote_id' => $contract->quote_id, 'code' => 'COM-0099']);
    $occupying->quoteLines()->attach($line->id);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $countBefore = WorkOrder::count();

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Doppione', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect($response->json('errors.quote_line_ids.0'))->toContain('COM-0099')
        ->and(WorkOrder::count())->toBe($countBefore);
});

it('AC-033: a line from ANOTHER offer, or a COST line, is 422', function () {
    $contract = generationContract();
    $otherQuote = Quote::factory()->create();
    $foreignLine = QuoteLine::factory()->create(['quote_id' => $otherQuote->id]);
    $costLine = QuoteLine::factory()->cost()->create(['quote_id' => $contract->quote_id]);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Riga estranea', 'type' => 'processing', 'quote_line_ids' => [$foreignLine->id],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Riga di costo', 'type' => 'processing', 'quote_line_ids' => [$costLine->id],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-034: quote_line_ids empty or absent is 422', function () {
    $contract = generationContract();
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Senza righe', 'type' => 'processing', 'quote_line_ids' => [],
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Senza chiave', 'type' => 'processing',
    ])->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-035: the generated work order has the same shape as one created via POST /api/work-orders', function () {
    $contract = generationContract();
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view', 'work-orders.create']);
    Sanctum::actingAs($actor);

    $viaContract = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Via contratto', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertCreated();

    $otherQuote = Quote::factory()->create();
    $viaDirect = $this->postJson('/api/work-orders', [
        'quote_id' => $otherQuote->id, 'title' => 'Via diretta', 'type' => 'processing',
    ])->assertCreated();

    expect(array_keys($viaContract->json('data')))->toBe(array_keys($viaDirect->json('data')))
        ->and($viaContract->json('data.code'))->toBe('COM-0001')
        ->and($viaDirect->json('data.code'))->toBe('COM-0002');
});

it('quote_id is server-derived from the contract, never accepted from the client', function () {
    $contract = generationContract();
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $otherQuote = Quote::factory()->create();
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Ignora quote_id client', 'type' => 'processing',
        'quote_line_ids' => [$line->id], 'quote_id' => $otherQuote->id,
    ])->assertCreated();

    expect($response->json('data.quote.id'))->toBe($contract->quote_id);
});

it('403 without contracts.program, 403 when the contract is not ClosedWon, 404 on an unknown contract', function () {
    $contract = generationContract();
    $line = QuoteLine::factory()->create(['quote_id' => $contract->quote_id]);
    $noAbility = workOrderGenerationActorWith(['work-orders.view']);
    Sanctum::actingAs($noAbility);

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [
        'title' => 'Negata', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertForbidden();

    $notProgrammable = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
    ]);
    $otherLine = QuoteLine::factory()->create(['quote_id' => $notProgrammable->quote_id]);
    $actor = workOrderGenerationActorWith(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/contracts/{$notProgrammable->id}/work-orders", [
        'title' => 'Stato errato', 'type' => 'processing', 'quote_line_ids' => [$otherLine->id],
    ])->assertForbidden();

    $this->postJson('/api/contracts/999999/work-orders', [
        'title' => 'Contratto inesistente', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertNotFound();

    expect(WorkOrder::count())->toBe(0);
});
