<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/contracts/{contract}/work-orders/batch (spec 0215, AC-001..008):
 * several commesse from groups of the offer's lines, all or nothing.
 */
uses(RefreshDatabase::class);

function batchProgramActor(array $abilities = ['contracts.program', 'work-orders.view']): User
{
    foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'work-orders.viewAny', 'work-orders.view', 'work-orders.create'] as $name) {
        Permission::findOrCreate($name);
    }

    $user = User::factory()->create();
    $user->givePermissionTo($abilities);

    return $user;
}

function batchProgramContract(): Contract
{
    return Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
}

/**
 * @return array<int, QuoteLine>
 */
function batchProgramLines(Contract $contract, int $count): array
{
    return collect(range(1, $count))->map(fn (int $position): QuoteLine => QuoteLine::factory()->create([
        'quote_id' => $contract->quote_id,
        'product_id' => Product::factory()->create(['name' => "Prodotto {$position}"])->id,
        'sort_order' => $position,
    ]))->all();
}

/**
 * @param  array<int, QuoteLine>  $lines
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function batchProgramGroup(array $lines, array $overrides = []): array
{
    return [
        'title' => null,
        'type' => 'processing',
        'start_date' => '2026-11-02',
        'supervisor_ids' => [User::factory()->create()->id],
        'task_template_id' => null,
        'quote_line_ids' => collect($lines)->pluck('id')->all(),
        ...$overrides,
    ];
}

it('AC-001: two groups create two commesse with consecutive codes, each linked only to its own lines, in group order', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 5);
    $template = TaskTemplate::factory()->create();
    $groupOne = batchProgramGroup(array_slice($lines, 0, 3), ['type' => 'processing', 'task_template_id' => $template->id]);
    $groupTwo = batchProgramGroup(array_slice($lines, 3, 2), ['type' => 'project', 'start_date' => '2026-12-01']);
    Sanctum::actingAs(batchProgramActor());

    $response = $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [$groupOne, $groupTwo]])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Created');

    $data = $response->json('data');
    expect($data)->toHaveCount(2)
        ->and($data[0]['code'])->toBe('COM-0001')
        ->and($data[1]['code'])->toBe('COM-0002')
        ->and($data[0]['type'])->toBe('processing')
        ->and($data[1]['type'])->toBe('project')
        ->and($data[0]['task_template']['id'])->toBe($template->id)
        ->and($data[1]['task_template'])->toBeNull()
        ->and(collect($data[0]['quote_lines'])->pluck('id')->sort()->values()->all())->toBe(collect($groupOne['quote_line_ids'])->sort()->values()->all())
        ->and(collect($data[1]['quote_lines'])->pluck('id')->sort()->values()->all())->toBe(collect($groupTwo['quote_line_ids'])->sort()->values()->all());

    $second = WorkOrder::findOrFail($data[1]['id']);
    expect($second->start_date->toDateString())->toBe('2026-12-01')
        ->and($second->supervisors->pluck('id')->all())->toBe($groupTwo['supervisor_ids'])
        ->and($second->quote_id)->toBe($contract->quote_id);
});

it('AC-002: a blank title is automatic ("<code> - <products in line order>"), a typed one is manual', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 3);
    Sanctum::actingAs(batchProgramActor());

    $data = $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup([$lines[1], $lines[0]], ['title' => null]),
        batchProgramGroup([$lines[2]], ['title' => 'Titolo mio']),
    ]])->assertCreated()->json('data');

    expect($data[0]['title'])->toBe('COM-0001 - Prodotto 1 + Prodotto 2')
        ->and($data[0]['title_is_manual'])->toBeFalse()
        ->and($data[1]['title'])->toBe('Titolo mio')
        ->and($data[1]['title_is_manual'])->toBeTrue();
});

it('AC-003: a line already programmed elsewhere in the 2nd group is 422 on groups.1.quote_line_ids and nothing is created', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 3);
    $occupying = WorkOrder::factory()->create(['quote_id' => $contract->quote_id, 'code' => 'COM-0099']);
    $occupying->quoteLines()->attach($lines[2]->id);
    Sanctum::actingAs(batchProgramActor());

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup([$lines[0]]),
        batchProgramGroup([$lines[1], $lines[2]]),
    ]])->assertUnprocessable()->assertJsonValidationErrors('groups.1.quote_line_ids');

    expect(WorkOrder::count())->toBe(1)
        ->and(WorkOrder::query()->max('code'))->toBe('COM-0099');

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [batchProgramGroup([$lines[0]])]])
        ->assertCreated()
        ->assertJsonPath('data.0.code', 'COM-0100');
});

it('AC-004: the same line in two groups is 422 on groups.1.quote_line_ids and nothing is created', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 2);
    Sanctum::actingAs(batchProgramActor());

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup([$lines[0]]),
        batchProgramGroup([$lines[1], $lines[0]]),
    ]])->assertUnprocessable()->assertJsonValidationErrors('groups.1.quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-005: a line of another offer or a COST line is 422 on its group and nothing is created', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 1);
    $foreign = QuoteLine::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    $cost = QuoteLine::factory()->cost()->create(['quote_id' => $contract->quote_id]);
    Sanctum::actingAs(batchProgramActor());

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup($lines),
        batchProgramGroup([$foreign]),
    ]])->assertUnprocessable()->assertJsonValidationErrors('groups.1.quote_line_ids');

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [batchProgramGroup([$cost])]])
        ->assertUnprocessable()->assertJsonValidationErrors('groups.0.quote_line_ids');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-006: field validation reports the right groups.{i}.field key', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 1);
    Sanctum::actingAs(batchProgramActor());
    $url = "/api/contracts/{$contract->id}/work-orders/batch";

    $this->postJson($url, ['groups' => []])->assertUnprocessable()->assertJsonValidationErrors('groups');

    $many = array_fill(0, 51, batchProgramGroup($lines));
    $this->postJson($url, ['groups' => $many])->assertUnprocessable()->assertJsonValidationErrors('groups');

    $this->postJson($url, ['groups' => [
        batchProgramGroup($lines, ['supervisor_ids' => []]),
        batchProgramGroup($lines, ['type' => 'nope']),
        (function () use ($lines) {
            $group = batchProgramGroup($lines);
            unset($group['start_date']);

            return $group;
        })(),
        batchProgramGroup($lines, ['title' => str_repeat('x', 192)]),
    ]])->assertUnprocessable()->assertJsonValidationErrors([
        'groups.0.supervisor_ids', 'groups.1.type', 'groups.2.start_date', 'groups.3.title',
    ]);

    expect(WorkOrder::count())->toBe(0);
});

it('AC-007: 403 without contracts.program and 403 on a non-programmable contract, nothing created; 404 on unknown contract', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 1);
    $payload = ['groups' => [batchProgramGroup($lines)]];

    Sanctum::actingAs(batchProgramActor(['work-orders.view']));
    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", $payload)->assertForbidden();

    $notProgrammable = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('name', 'Programmato')->sole()->id,
    ]);
    $otherLines = batchProgramLines($notProgrammable, 1);
    Sanctum::actingAs(batchProgramActor());
    $this->postJson("/api/contracts/{$notProgrammable->id}/work-orders/batch", ['groups' => [batchProgramGroup($otherLines)]])->assertForbidden();

    $this->postJson('/api/contracts/999999/work-orders/batch', $payload)->assertNotFound();

    expect(WorkOrder::count())->toBe(0);
});

it('AC-008: the task template generates tasks only on its own group commessa, and a generation failure rolls back the whole batch', function () {
    $contract = batchProgramContract();
    $lines = batchProgramLines($contract, 2);
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Task modello']);
    Sanctum::actingAs(batchProgramActor());

    $data = $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup([$lines[0]], ['task_template_id' => $template->id]),
        batchProgramGroup([$lines[1]]),
    ]])->assertCreated()->json('data');

    expect(Task::query()->where('work_order_id', $data[0]['id'])->count())->toBe(1)
        ->and(Task::query()->where('work_order_id', $data[1]['id'])->count())->toBe(0);

    WorkOrder::query()->delete();

    Task::creating(static fn () => throw new RuntimeException('generation failed'));

    $this->postJson("/api/contracts/{$contract->id}/work-orders/batch", ['groups' => [
        batchProgramGroup([$lines[0]]),
        batchProgramGroup([$lines[1]], ['task_template_id' => $template->id]),
    ]])->assertStatus(500);

    expect(WorkOrder::count())->toBe(0);
});
