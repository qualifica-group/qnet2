<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderQuoteAttributeCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0404 part D: the offer's flexible fields fill the work order
// (AC-018..AC-020).

uses(RefreshDatabase::class);

/**
 * A validated contract whose offer has one revenue line in a category that
 * gives the work order the text attribute `$code` (and the enum `$enumCode`).
 *
 * @return array{contract: Contract, quote: Quote, line: QuoteLine}
 */
function copyAttributeContract(string $code, string $enumCode): array
{
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => $code]);
    categoryTabAttribute(AttributeContext::WorkOrder, 'enum', ['code' => $enumCode], $category);
    $quote = categoryTabQuote([$category]);
    $contract = Contract::factory()->create([
        'quote_id' => $quote->id,
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);

    return ['contract' => $contract, 'quote' => $quote, 'line' => $quote->offerLines()->sole()];
}

function copyAttributeValues(Quote $quote, array $values): void
{
    $quote->forceFill(['attribute_values' => $values])->saveQuietly();
}

function copyAttributeProgrammer(): User
{
    foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'contracts.viewAll', 'work-orders.view'] as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create()->givePermissionTo(['contracts.program', 'contracts.viewAll', 'work-orders.view']);
}

it('AC-018: a work order programmed from the contract takes the offer values, skipping an option it does not have', function () {
    ['contract' => $contract, 'quote' => $quote, 'line' => $line] = copyAttributeContract('cpy_note', 'cpy_kind');
    copyAttributeValues($quote, ['cpy_note' => 'Corso serale', 'cpy_kind' => 'not-an-option']);
    Sanctum::actingAs(copyAttributeProgrammer());

    $workOrderId = $this->postJson("/api/contracts/{$contract->id}/work-orders", [...workOrderRequiredFields(),
        'title' => 'Lavorazione', 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertCreated()->json('data.id');

    expect(WorkOrder::query()->findOrFail($workOrderId)->attribute_values)->toBe(['cpy_note' => 'Corso serale']);
});

it('AC-018: a value sent with the programming wins over the offer', function () {
    ['contract' => $contract, 'quote' => $quote, 'line' => $line] = copyAttributeContract('cpy_note', 'cpy_kind');
    copyAttributeValues($quote, ['cpy_note' => 'Dall\'offerta']);
    Sanctum::actingAs(copyAttributeProgrammer());

    $workOrderId = $this->postJson("/api/contracts/{$contract->id}/work-orders", [...workOrderRequiredFields(),
        'title' => 'Lavorazione', 'type' => 'processing', 'quote_line_ids' => [$line->id],
        'attribute_values' => ['cpy_note' => 'Scritto a mano'],
    ])->assertCreated()->json('data.id');

    expect(WorkOrder::query()->findOrFail($workOrderId)->attribute_values['cpy_note'])->toBe('Scritto a mano');
});

it('AC-019: a multi-offer work order takes the first non-empty value in the given offer order', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'cpy_multi']);
    $first = categoryTabQuote([$category]);
    $second = categoryTabQuote([$category]);
    copyAttributeValues($first, ['cpy_multi' => '']);
    copyAttributeValues($second, ['cpy_multi' => 'Dalla seconda']);
    $workOrder = categoryTabMultiWorkOrder($first, $second);

    $copied = app(WorkOrderQuoteAttributeCopier::class)->fill($workOrder, [$first->id, $second->id]);

    expect($copied)->toBe(1)
        ->and($workOrder->fresh()->attribute_values['cpy_multi'])->toBe('Dalla seconda')
        ->and(app(WorkOrderQuoteAttributeCopier::class)->quoteIdsOf($workOrder))->toBe([$first->id, $second->id]);
});

it('AC-020: "Copia dati dall\'offerta" fills only the empty fields and reports how many', function () {
    ['quote' => $quote, 'line' => $line] = copyAttributeContract('cpy_note', 'cpy_kind');
    $option = Attribute::query()->where('code', 'cpy_kind')->sole()->options()->firstOrFail();
    copyAttributeValues($quote, ['cpy_note' => 'Dall\'offerta', 'cpy_kind' => $option->value]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($line->id);
    $workOrder->forceFill(['attribute_values' => ['cpy_note' => 'Gia\' scritto']])->saveQuietly();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson("/api/work-orders/{$workOrder->id}/copy-quote-attributes")
        ->assertOk()
        ->assertJsonPath('message', '1 field copied from the offer')
        ->assertJsonPath('data.attribute_values.cpy_kind', $option->value);

    expect($workOrder->fresh()->attribute_values['cpy_note'])->toBe('Gia\' scritto');

    $this->postJson("/api/work-orders/{$workOrder->id}/copy-quote-attributes")
        ->assertOk()
        ->assertJsonPath('message', '0 fields copied from the offer');
});

it('AC-020: the copy needs the right to edit the work order', function () {
    ['quote' => $quote] = copyAttributeContract('cpy_note', 'cpy_kind');
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->postJson("/api/work-orders/{$workOrder->id}/copy-quote-attributes")->assertForbidden();
});
