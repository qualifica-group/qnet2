<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Services\Quotes\QuoteAttributeValueWriter;
use App\WorkOrders\WorkOrderAttributeLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0425: a field synced with the offer has one value on the offer and
// every work order it feeds (AC-003..AC-009).

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->category = syncedAttributeCategory('sync_registry');
    $this->quote = categoryTabQuote([$this->category]);
});

function syncedValuesOf(Quote|WorkOrder $record): array
{
    return $record->fresh()->attribute_values ?? [];
}

it('AC-003: an edit of the offer reaches every work order it feeds, and only the synced code', function () {
    // One line per work order per renewal: the multi-offer takes renewal 0.
    $multi = categoryTabMultiWorkOrder($this->quote, categoryTabQuote([$this->category]));
    $mono = syncedAttributeWorkOrder($this->quote, renewal: 1);
    $renewal = syncedAttributeWorkOrder($this->quote, renewal: 2);
    $mono->forceFill(['attribute_values' => ['sync_registry_work_order_only' => 'own']])->saveQuietly();
    Sanctum::actingAs(quoteScopedUserWith(['view', 'update', 'viewAll']));

    $this->patchJson("/api/quotes/{$this->quote->id}", ['attribute_values' => [
        'sync_registry' => 'Mario Rossi', 'sync_registry_quote_only' => 'offer only',
    ]])->assertOk();

    expect(syncedValuesOf($mono))->toEqual(['sync_registry_work_order_only' => 'own', 'sync_registry' => 'Mario Rossi'])
        ->and(syncedValuesOf($renewal))->toBe(['sync_registry' => 'Mario Rossi'])
        ->and(syncedValuesOf($multi))->toBe(['sync_registry' => 'Mario Rossi']);
});

it('AC-004: an edit of the work order reaches its offers and their other work orders', function () {
    $other = categoryTabQuote([$this->category]);
    $multi = categoryTabMultiWorkOrder($this->quote, $other);
    $sibling = syncedAttributeWorkOrder($this->quote, renewal: 1);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$multi->id}", ['attribute_values' => ['sync_registry' => 'Anna Bianchi']])->assertOk();

    expect(syncedValuesOf($this->quote))->toBe(['sync_registry' => 'Anna Bianchi'])
        ->and(syncedValuesOf($other))->toBe(['sync_registry' => 'Anna Bianchi'])
        ->and(syncedValuesOf($sibling))->toBe(['sync_registry' => 'Anna Bianchi']);
});

it('AC-005: emptying the value on one side empties it on the other', function () {
    $workOrder = syncedAttributeWorkOrder($this->quote);
    $this->quote->forceFill(['attribute_values' => ['sync_registry' => 'Mario Rossi']])->saveQuietly();
    $workOrder->forceFill(['attribute_values' => ['sync_registry' => 'Mario Rossi']])->saveQuietly();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['attribute_values' => ['sync_registry' => null]])->assertOk();

    expect(syncedValuesOf($this->quote))->not->toHaveKey('sync_registry');
});

it('AC-006: without the flag, or on a code of one side only, the other side stays as it is', function () {
    $category = syncedAttributeCategory('plain_registry', synced: false);
    $quote = categoryTabQuote([$category]);
    $workOrder = syncedAttributeWorkOrder($quote);
    Sanctum::actingAs(quoteScopedUserWith(['view', 'update', 'viewAll']));

    $this->patchJson("/api/quotes/{$quote->id}", ['attribute_values' => ['plain_registry' => 'Mario Rossi']])->assertOk();

    expect(syncedValuesOf($workOrder))->toBe([]);
});

it('AC-007: a work order programmed from the contract takes the offer value, or sends its own back to the offer', function () {
    $this->quote->forceFill(['attribute_values' => ['sync_registry' => 'Dall\'offerta']])->saveQuietly();
    $contract = Contract::factory()->create([
        'quote_id' => $this->quote->id,
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $line = $this->quote->offerLines()->sole();
    foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'contracts.viewAll', 'work-orders.view'] as $permission) {
        Permission::findOrCreate($permission);
    }
    Sanctum::actingAs(workOrderUserWith(['view'])->givePermissionTo(['contracts.program', 'contracts.viewAll']));
    $program = fn (int $renewal, array $values) => $this->postJson("/api/contracts/{$contract->id}/work-orders", [...workOrderRequiredFields(),
        'title' => 'Lavorazione', 'type' => 'processing', 'quote_line_ids' => [$line->id], 'attribute_values' => $values,
    ])->assertCreated()->json('data.id');

    $taken = WorkOrder::query()->findOrFail($program(0, []));

    expect($taken->attribute_values['sync_registry'])->toBe('Dall\'offerta');

    $taken->quoteLines()->detach();
    $sent = WorkOrder::query()->findOrFail($program(0, ['sync_registry' => 'Scritto in commessa']));

    expect($sent->attribute_values['sync_registry'])->toBe('Scritto in commessa')
        ->and(syncedValuesOf($this->quote)['sync_registry'])->toBe('Scritto in commessa');
});

it('AC-008: a code an integration owns on the work order takes nothing from the offer', function () {
    $workOrder = syncedAttributeWorkOrder($this->quote);
    app()->instance('test.sync-lock', new class implements WorkOrderAttributeLock
    {
        public function lockedCodes(array $categoryIds): array
        {
            return ['sync_registry'];
        }

        public function source(): string
        {
            return 'Integration';
        }
    });
    app()->tag(['test.sync-lock'], 'work-orders.attribute-locks');
    Sanctum::actingAs(quoteScopedUserWith(['view', 'update', 'viewAll']));

    $this->patchJson("/api/quotes/{$this->quote->id}", ['attribute_values' => ['sync_registry' => 'Mario Rossi']])->assertOk();

    expect(syncedValuesOf($workOrder))->toBe([]);
});

it('AC-009: any surface writing through the offer writer syncs (grid cells, Gestione Richieste)', function () {
    $workOrder = syncedAttributeWorkOrder($this->quote);
    $changed = [];
    $old = [];

    app(QuoteAttributeValueWriter::class)->apply($this->quote, ['sync_registry' => 'Dalla cella'], $changed, $old);
    $this->quote->save();

    expect(syncedValuesOf($workOrder))->toBe(['sync_registry' => 'Dalla cella']);
});
