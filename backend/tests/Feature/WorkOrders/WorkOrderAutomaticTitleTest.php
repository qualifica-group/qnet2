<?php

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Automatic work order title (spec 0215, D-2/D-5): `<code> - <products>` until
 * the user types their own, flagged `title_is_manual` (twin of
 * QuoteEditableTitleTest).
 */
uses(RefreshDatabase::class);

function autoTitleLine(Quote $quote, string $productName, int $sortOrder): QuoteLine
{
    return QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['name' => $productName])->id,
        'sort_order' => $sortOrder,
    ]);
}

/** Creates an automatic commessa on $quote with the given lines through the API. */
function autoTitleWorkOrder($test, Quote $quote, array $lines, array $extra = []): int
{
    return $test->postJson('/api/work-orders', [
        'quote_id' => $quote->id,
        'type' => 'processing',
        'start_date' => '2026-11-02',
        'supervisor_ids' => [User::factory()->create()->id],
        'quote_line_ids' => collect($lines)->pluck('id')->all(),
        ...$extra,
    ])->assertCreated()->json('data.id');
}

it('AC-009: changing quote_line_ids re-derives an automatic title and leaves a manual one alone', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'create', 'update']));
    $quote = Quote::factory()->create();
    $lineA = autoTitleLine($quote, 'Alfa', 1);
    $lineB = autoTitleLine($quote, 'Beta', 2);
    $lineC = autoTitleLine($quote, 'Gamma', 3);
    $automaticId = autoTitleWorkOrder($this, $quote, [$lineA]);
    $manualId = autoTitleWorkOrder($this, $quote, [$lineC], ['title' => 'Mio titolo']);
    $code = WorkOrder::findOrFail($automaticId)->code;

    $this->patchJson("/api/work-orders/{$automaticId}", ['quote_line_ids' => [$lineA->id, $lineB->id]])
        ->assertOk()
        ->assertJsonPath('data.title', $code.' - Alfa + Beta')
        ->assertJsonPath('data.title_is_manual', false);

    $this->patchJson("/api/work-orders/{$manualId}", ['quote_line_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.title', 'Mio titolo')
        ->assertJsonPath('data.title_is_manual', true);

    $this->patchJson("/api/work-orders/{$automaticId}", ['title' => 'Scelto io'])
        ->assertOk()->assertJsonPath('data.title_is_manual', true);

    $this->patchJson("/api/work-orders/{$automaticId}", ['title' => null])
        ->assertOk()
        ->assertJsonPath('data.title', $code.' - Alfa + Beta')
        ->assertJsonPath('data.title_is_manual', false);

    $this->patchJson("/api/work-orders/{$automaticId}", ['title' => 'Scelto io']);
    $this->patchJson("/api/work-orders/{$automaticId}", ['title' => $code.' - Alfa + Beta'])
        ->assertOk()->assertJsonPath('data.title_is_manual', false);
});

it('AC-009: clearing the title cell of the grid brings the commessa back to the automatic title', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'create', 'update']));
    $quote = Quote::factory()->create();
    $line = autoTitleLine($quote, 'Alfa', 1);
    $id = autoTitleWorkOrder($this, $quote, [$line], ['title' => 'Manuale']);
    $code = WorkOrder::findOrFail($id)->code;

    $this->patchJson("/api/tables/work-orders/rows/{$id}", ['column' => 'title', 'value' => ''])
        ->assertOk()
        ->assertJsonPath('data.title', $code.' - Alfa');

    expect(WorkOrder::findOrFail($id)->title_is_manual)->toBeFalse();
});

it('AC-010: POST /api/work-orders without title is 201 with the automatic title', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'create']));
    $quote = Quote::factory()->create();
    $line = autoTitleLine($quote, 'Alfa', 1);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'type' => 'processing', 'start_date' => '2026-11-02',
        'supervisor_ids' => [User::factory()->create()->id], 'quote_line_ids' => [$line->id],
    ])->assertCreated();

    expect($response->json('data.title'))->toBe($response->json('data.code').' - Alfa')
        ->and($response->json('data.title_is_manual'))->toBeFalse();
});

it('AC-010: the single contract endpoint without title is 201 automatic, with a title stays manual', function () {
    foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'work-orders.view'] as $name) {
        Permission::findOrCreate($name);
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['contracts.program', 'work-orders.view']);
    Sanctum::actingAs($actor);
    $contract = Contract::factory()->create([
        'contract_status_id' => ContractStatus::where('system_key', 'validated')->sole()->id,
    ]);
    $lineA = autoTitleLine($contract->quote, 'Alfa', 1);
    $lineB = autoTitleLine($contract->quote, 'Beta', 2);
    $base = ['type' => 'processing', ...workOrderRequiredFields()];

    $automatic = $this->postJson("/api/contracts/{$contract->id}/work-orders", [...$base, 'quote_line_ids' => [$lineA->id]])->assertCreated();
    $manual = $this->postJson("/api/contracts/{$contract->id}/work-orders", [...$base, 'title' => 'Mio', 'quote_line_ids' => [$lineB->id]])->assertCreated();

    expect($automatic->json('data.title'))->toBe($automatic->json('data.code').' - Alfa')
        ->and($automatic->json('data.title_is_manual'))->toBeFalse()
        ->and($manual->json('data.title'))->toBe('Mio')
        ->and($manual->json('data.title_is_manual'))->toBeTrue();
});

it('AC-011: the migration flags every existing commessa manual keeping its title, and down() drops the column', function () {
    $migration = 'database/migrations/2026_10_15_120000_add_title_is_manual_to_work_orders_table.php';
    $workOrder = WorkOrder::factory()->create(['title' => 'Titolo storico']);

    Artisan::call('migrate:rollback', ['--path' => $migration]);
    expect(Schema::hasColumn('work_orders', 'title_is_manual'))->toBeFalse();

    Artisan::call('migrate', ['--path' => $migration]);

    $fresh = WorkOrder::findOrFail($workOrder->id);
    expect(Schema::hasColumn('work_orders', 'title_is_manual'))->toBeTrue()
        ->and($fresh->title_is_manual)->toBeTrue()
        ->and($fresh->title)->toBe('Titolo storico');
});

it('AC-012: titles:recalculate re-derives automatic commesse only', function () {
    $quote = Quote::factory()->create();
    $line = autoTitleLine($quote, 'Alfa', 1);
    $automatic = WorkOrder::factory()->create(['quote_id' => $quote->id, 'title' => 'obsoleto', 'title_is_manual' => false]);
    $manual = WorkOrder::factory()->create(['quote_id' => $quote->id, 'title' => 'Mio titolo', 'title_is_manual' => true]);
    $automatic->quoteLines()->attach($line->id);
    $manual->quoteLines()->attach(autoTitleLine($quote, 'Beta', 2)->id);

    Artisan::call('titles:recalculate');

    expect($automatic->fresh()->title)->toBe($automatic->code.' - Alfa')
        ->and($manual->fresh()->title)->toBe('Mio titolo');
});
