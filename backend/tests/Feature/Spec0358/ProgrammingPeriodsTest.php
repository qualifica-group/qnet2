<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0358, MT-02: programming per contract renewal period (D-3/D-4/D-5),
 * through the real endpoints (AC-008..AC-013).
 */
uses(RefreshDatabase::class);

function periodsActor(): User
{
    foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'contracts.viewAll', 'work-orders.viewAny', 'work-orders.view', 'work-orders.create', 'work-orders.update', 'work-orders.viewAll', 'quotes.view'] as $name) {
        Permission::findOrCreate($name);
    }

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::query()->pluck('name')->all());

    return $user;
}

function periodsContract(int $lines = 2): array
{
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $quoteLines = collect(range(1, $lines))->map(fn (int $n): QuoteLine => QuoteLine::factory()->create([
        'quote_id' => $contract->quote_id, 'sort_order' => $n,
    ]))->all();

    return [$contract, $quoteLines];
}

function periodsPivot(WorkOrder $workOrder, QuoteLine $line): ?int
{
    $value = DB::table('quote_line_work_order')
        ->where('work_order_id', $workOrder->id)->where('quote_line_id', $line->id)->value('renewal_number');

    return $value === null ? null : (int) $value;
}

it('AC-008: after the renewal every line is free again in programmable-lines', function () {
    [$contract, $lines] = periodsContract();
    foreach ($lines as $line) {
        attachLineInPeriod(WorkOrder::factory()->create(['quote_id' => $contract->quote_id]), $line, 0);
    }
    Sanctum::actingAs(periodsActor());

    $before = $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertOk()->json('data');
    renewContractTo($contract, 1);
    $after = $this->getJson("/api/contracts/{$contract->id}/programmable-lines")->assertOk()->json('data');

    expect(collect($before)->pluck('work_order')->filter()->count())->toBe(2)
        ->and(collect($after)->pluck('work_order')->filter()->count())->toBe(0);
});

it('AC-009: programming in period 1 writes renewal_number 1 and leaves period 0 untouched', function () {
    [$contract, $lines] = periodsContract();
    $original = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($original, $lines[0], 0);
    attachLineInPeriod($original, $lines[1], 0);
    renewContractTo($contract, 1);
    Sanctum::actingAs(periodsActor());

    $id = $this->postJson("/api/contracts/{$contract->id}/work-orders", [...workOrderRequiredFields(),
        'type' => 'processing', 'quote_line_ids' => [$lines[0]->id, $lines[1]->id],
    ])->assertCreated()->json('data.id');

    $created = WorkOrder::findOrFail($id);
    expect(periodsPivot($created, $lines[0]))->toBe(1)
        ->and(periodsPivot($created, $lines[1]))->toBe(1)
        ->and(periodsPivot($original, $lines[0]))->toBe(0);
});

it('AC-010: a second commessa on a line already used in the same period is 422 naming the occupant (single and batch)', function () {
    [$contract, $lines] = periodsContract();
    renewContractTo($contract, 1);
    $occupant = WorkOrder::factory()->create(['quote_id' => $contract->quote_id, 'code' => 'COM-A']);
    attachLineInPeriod($occupant, $lines[0], 1);
    Sanctum::actingAs(periodsActor());
    $before = WorkOrder::count();
    $payload = [...workOrderRequiredFields(), 'type' => 'processing'];

    $this->postJson("/api/contracts/{$contract->id}/work-orders", [...$payload, 'quote_line_ids' => [$lines[0]->id]])
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids')
        ->assertJsonFragment(['quote_line_ids' => [__('work_orders.line_already_programmed', ['code' => 'COM-A'])]]);

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        [...$payload, 'title' => null, 'task_template_id' => null, 'quote_line_ids' => [$lines[1]->id]],
        [...$payload, 'title' => null, 'task_template_id' => null, 'quote_line_ids' => [$lines[0]->id]],
    ]])->assertStatus(422)->assertJsonValidationErrors('groups.1.quote_line_ids');

    expect(WorkOrder::count())->toBe($before);
});

it('AC-011: PATCH of a period-0 commessa adds a free line in period 0; an occupied one is 422', function () {
    [$contract, $lines] = periodsContract(3);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($workOrder, $lines[0], 0);
    $other = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($other, $lines[2], 0);
    renewContractTo($contract, 1);
    Sanctum::actingAs(periodsActor());

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$lines[0]->id, $lines[1]->id]])->assertOk();
    expect(periodsPivot($workOrder, $lines[1]))->toBe(0)
        ->and(periodsPivot($workOrder, $lines[0]))->toBe(0);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$lines[0]->id, $lines[1]->id, $lines[2]->id]])
        ->assertStatus(422)->assertJsonValidationErrors('quote_line_ids');
});

it('AC-012: for-select evaluates availability on the period of the commessa or the current one', function () {
    [$contract, $lines] = periodsContract(2);
    $old = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($old, $lines[0], 0);
    renewContractTo($contract, 1);
    $current = WorkOrder::factory()->create(['quote_id' => $contract->quote_id]);
    attachLineInPeriod($current, $lines[1], 1);
    Sanctum::actingAs(periodsActor());
    $ids = fn (string $query): array => collect($this->getJson("/api/quote-offer-lines/for-select?quote_id={$contract->quote_id}{$query}")->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids(''))->toBe([$lines[0]->id])
        ->and($ids("&except_work_order_id={$old->id}"))->toBe([$lines[0]->id, $lines[1]->id]);
});

it('AC-013: a multi-offer commessa takes the period of each contract; eligibility follows the current period', function () {
    [$contractA, $lineA] = multiQuoteProgramContract();
    $product = Product::factory()->create(['category_id' => $lineA->product->category_id]);
    [$contractB, $lineB] = multiQuoteProgramContract($product);
    renewContractTo($contractA, 1);
    attachLineInPeriod(WorkOrder::factory()->create(['quote_id' => $contractA->quote_id]), $lineA, 0);
    Sanctum::actingAs(multiQuoteProgramActor());

    $lines = $this->getJson('/api/contracts/multi-programmable-lines?'.http_build_query(['contract_ids' => [$contractA->id, $contractB->id]]))
        ->assertOk()->json('data.lines');
    expect(collect($lines)->firstWhere('id', $lineA->id)['multi_quote_eligible'])->toBeTrue();

    $id = $this->postJson('/api/contracts/multi-quote-work-orders', multiQuoteProgramPayload([$contractA->id, $contractB->id], [$lineA->id, $lineB->id]))
        ->assertCreated()->json('data.id');
    $created = WorkOrder::findOrFail($id);

    expect(periodsPivot($created, $lineA))->toBe(1)
        ->and(periodsPivot($created, $lineB))->toBe(0);
});
