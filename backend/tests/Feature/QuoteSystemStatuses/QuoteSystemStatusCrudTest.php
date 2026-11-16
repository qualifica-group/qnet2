<?php

declare(strict_types=1);

use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteSystemStatus;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0404 part A: the quote system statuses and their configurator
// (AC-001..AC-004).

uses(RefreshDatabase::class);

function systemStatusUserWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        Permission::findOrCreate("quote-system-statuses.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("quote-system-statuses.{$ability}");
    }

    return $user;
}

function systemStatusByKey(string $key): QuoteSystemStatus
{
    return QuoteSystemStatus::query()->where('system_key', $key)->sole();
}

it('AC-001: seeds the five system statuses and links every working status by behavior', function () {
    expect(QuoteSystemStatus::query()->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Aperto', 'In attesa', 'Vinto provvisorio', 'Vinto', 'Perso'])
        ->and(QuoteSystemStatus::query()->whereNotNull('system_key')->orderBy('sort_order')->pluck('system_key')->all())
        ->toBe(['open', 'closed_won', 'closed_lost']);

    $unlinked = QuoteWorkflowStatus::query()->with('systemStatus')->get()
        ->reject(static fn (QuoteWorkflowStatus $status): bool => $status->systemStatus?->behavior === $status->group);

    expect(QuoteWorkflowStatus::query()->count())->toBeGreaterThan(0)
        ->and($unlinked)->toBeEmpty()
        ->and(QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'closed_won')->sole()->quote_system_status_id)
        ->toBe(systemStatusByKey('closed_won')->id);
});

it('links a working status written with a bare group to the first system status with that behavior', function () {
    $status = QuoteWorkflowStatus::factory()->global()->create(['group' => WorkflowStatusGroup::Validated]);

    expect($status->fresh()->systemStatus->name)->toBe('Vinto provvisorio');
});

it('AC-002: creates, shows, updates and reorders with the permission', function () {
    Sanctum::actingAs(systemStatusUserWith(['create', 'view', 'update']));

    $id = $this->postJson('/api/quote-system-statuses', ['name' => 'Sospeso', 'color' => 'slate', 'behavior' => 'pending'])
        ->assertCreated()
        ->assertJsonPath('data.behavior', 'pending')
        ->assertJsonPath('data.system_key', null)
        ->assertJsonPath('data.workflow_statuses_count', 0)
        ->json('data.id');

    $this->getJson("/api/quote-system-statuses/{$id}")->assertOk()->assertJsonPath('data.name', 'Sospeso');
    $this->patchJson("/api/quote-system-statuses/{$id}", ['name' => 'Sospesa'])->assertOk()->assertJsonPath('data.name', 'Sospesa');

    $customIds = QuoteSystemStatus::query()->whereNull('system_key')->orderByDesc('sort_order')->pluck('id')->all();
    $this->postJson('/api/quote-system-statuses/reorder', ['ordered_ids' => $customIds])->assertOk();

    expect(QuoteSystemStatus::query()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([systemStatusByKey('open')->id, ...$customIds, systemStatusByKey('closed_won')->id, systemStatusByKey('closed_lost')->id]);
});

it('AC-002: refuses every write without the permission', function () {
    Sanctum::actingAs(systemStatusUserWith([]));
    $custom = QuoteSystemStatus::query()->where('behavior', 'pending')->sole();

    $this->postJson('/api/quote-system-statuses', ['name' => 'X', 'color' => 'slate', 'behavior' => 'pending'])->assertForbidden();
    $this->patchJson("/api/quote-system-statuses/{$custom->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/quote-system-statuses/{$custom->id}")->assertForbidden();
    $this->postJson('/api/tables/quote-system-statuses/rows', ['startRow' => 0, 'endRow' => 10])->assertForbidden();
});

it('AC-002: refuses to delete a system row or a linked one, deletes a free one', function () {
    Sanctum::actingAs(systemStatusUserWith(['create', 'delete']));
    $linked = QuoteSystemStatus::query()->where('behavior', 'pending')->sole();
    QuoteWorkflowStatus::factory()->global()->create(['name' => 'Attesa documenti', 'quote_system_status_id' => $linked->id]);

    $this->deleteJson('/api/quote-system-statuses/'.systemStatusByKey('closed_lost')->id)->assertUnprocessable();
    $this->deleteJson("/api/quote-system-statuses/{$linked->id}")->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Attesa documenti'));

    $free = QuoteSystemStatus::query()->create(['name' => 'Libero', 'color' => 'slate', 'behavior' => 'pending']);
    $this->deleteJson("/api/quote-system-statuses/{$free->id}")->assertNoContent();

    expect(QuoteSystemStatus::query()->whereKey($free->id)->exists())->toBeFalse();
});

it('AC-003: a system row keeps its behavior, a linked normal row propagates it without touching contracts', function () {
    Sanctum::actingAs(systemStatusUserWith(['update']));
    $won = systemStatusByKey('closed_won');

    $this->patchJson("/api/quote-system-statuses/{$won->id}", ['behavior' => 'pending'])->assertUnprocessable();
    $this->patchJson("/api/quote-system-statuses/{$won->id}", ['name' => 'Chiuso vinto', 'behavior' => 'closed_won'])->assertOk();

    $provisional = QuoteSystemStatus::query()->where('behavior', 'validated')->sole();
    $working = QuoteWorkflowStatus::factory()->global()->create(['quote_system_status_id' => $provisional->id]);
    $quote = Quote::factory()->create(['quote_workflow_status_id' => $working->id]);

    $this->patchJson("/api/quote-system-statuses/{$provisional->id}", ['behavior' => 'closed_won'])->assertOk();

    expect($working->fresh()->group)->toBe(WorkflowStatusGroup::ClosedWon)
        ->and(Contract::query()->where('quote_id', $quote->id)->exists())->toBeFalse();
});

it('serves the configurator grid and the for-select', function () {
    Sanctum::actingAs(systemStatusUserWith(['viewAny', 'view', 'delete']));

    $rows = collect($this->postJson('/api/tables/quote-system-statuses/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'));
    $open = $rows->firstWhere('system_key', 'open');
    $pending = $rows->firstWhere('behavior', 'pending');

    expect($rows)->toHaveCount(5)
        ->and($open['workflow_statuses_count'])->toBeGreaterThan(0)
        ->and($open['actions'])->not->toContain('delete')
        ->and($pending['actions'])->toContain('delete');

    $this->getJson('/api/quote-system-statuses/for-select')->assertOk()
        ->assertJsonPath('items.0.label', 'Aperto')
        ->assertJsonPath('items.0.meta.system_key', 'open')
        ->assertJsonPath('items.2.meta.behavior', 'validated');
});
