<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * REQUIREMENT CHANGED (spec 0377, supersedes spec 0083 AC-018): a status in use
 * can no longer be deleted, alone or with its workflow.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteStatusGuardActor')) {
    function quoteStatusGuardActor(): User
    {
        $abilities = ['viewAny', 'view', 'update', 'delete'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("quote-workflows.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(fn (string $ability): string => "quote-workflows.{$ability}", $abilities));

        return $user;
    }
}

if (! function_exists('quoteOnStatus')) {
    function quoteOnStatus(QuoteWorkflowStatus $status): Quote
    {
        return Quote::factory()->create([
            'opportunity_id' => Opportunity::factory()->create()->id,
            'quote_workflow_status_id' => $status->id,
        ]);
    }
}

beforeEach(function (): void {
    $this->workflow = QuoteWorkflow::factory()->create();
    $this->open = QuoteWorkflowStatus::factory()->system('open')->create(['quote_workflow_id' => $this->workflow->id]);
    $this->used = QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $this->workflow->id, 'name' => 'Stato usato']);
    $this->unused = QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => $this->workflow->id, 'name' => 'Stato libero']);
    Sanctum::actingAs(quoteStatusGuardActor());
});

it('refuses to delete a workflow with a status in use, leaving everything as it was (AC-001)', function (): void {
    $quote = quoteOnStatus($this->used);

    $this->deleteJson("/api/quote-workflows/{$this->workflow->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Stato usato (1)'));

    expect($quote->fresh()->quote_workflow_status_id)->toBe($this->used->id)
        ->and($this->workflow->fresh()->is_active)->toBeTrue();
});

it('keeps a workflow with a status in use out of a bulk delete (AC-002)', function (): void {
    $quote = quoteOnStatus($this->used);

    $response = $this->postJson('/api/tables/quote-workflows/bulk-delete', ['ids' => [$this->workflow->id]])->assertOk();

    expect($response->json('data.deleted'))->toBe(0)
        ->and(QuoteWorkflow::query()->find($this->workflow->id))->not->toBeNull()
        ->and($quote->fresh()->quote_workflow_status_id)->toBe($this->used->id);
});

it('refuses a save that drops a status in use and writes nothing of it (AC-003)', function (): void {
    quoteOnStatus($this->used);

    $this->patchJson("/api/quote-workflows/{$this->workflow->id}", [
        'statuses' => [['id' => $this->unused->id, 'name' => 'Rinominato', 'group' => 'open']],
    ])->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Stato usato (1)'));

    expect($this->used->fresh())->not->toBeNull()
        ->and($this->unused->fresh()->name)->toBe('Stato libero');
});

it('drops a custom status nobody uses (AC-004)', function (): void {
    $this->patchJson("/api/quote-workflows/{$this->workflow->id}", [
        'statuses' => [['id' => $this->used->id, 'name' => 'Stato usato', 'group' => 'open']],
    ])->assertOk();

    expect($this->unused->fresh())->toBeNull();
});

it('deletes a workflow with no status in use (AC-005)', function (): void {
    $this->deleteJson("/api/quote-workflows/{$this->workflow->id}")->assertNoContent();

    expect(QuoteWorkflow::query()->find($this->workflow->id))->toBeNull()
        ->and($this->used->fresh())->toBeNull();
});

it('refuses to drop a default status in use (AC-007)', function (): void {
    $default = QuoteWorkflowStatus::factory()->create(['quote_workflow_id' => null, 'name' => 'Default usato']);
    quoteOnStatus($default);

    $this->putJson('/api/quote-workflows/default-statuses', [
        'statuses' => [['name' => 'Altro', 'group' => 'open']],
    ])->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Default usato (1)'));

    expect($default->fresh())->not->toBeNull();
});

it('names the quotes on the status in use by code', function (): void {
    $first = quoteOnStatus($this->used);
    $second = quoteOnStatus($this->used);

    $this->deleteJson("/api/quote-workflows/{$this->workflow->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, "Stato usato (2): {$first->code}, {$second->code}"));
});

it('lists at most ten quote codes per status and counts the rest', function (): void {
    $quotes = collect(range(1, 12))->map(fn (): Quote => quoteOnStatus($this->used));

    $message = $this->deleteJson("/api/quote-workflows/{$this->workflow->id}")
        ->assertStatus(409)
        ->json('message');

    expect($message)->toContain('Stato usato (12): '.$quotes->take(10)->pluck('code')->implode(', ').' and 2 more')
        ->and($message)->not->toContain($quotes->last()->code);
});
