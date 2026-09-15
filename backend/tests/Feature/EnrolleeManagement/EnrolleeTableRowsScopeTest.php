<?php

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130 — `POST /api/tables/enrollee-management/rows` /
// `GET /api/tables/enrollee-management/columns`: AC-002 (D-2 row-state
// filter reaches the HTTP layer, not just RequestManagementScope in
// isolation) and the table-endpoint slice of AC-003 (permission sets of the
// two modules never leak into one another).

uses(RefreshDatabase::class);

if (! function_exists('enrolleeStatusId')) {
    /**
     * The GLOBAL row for a system status key, reused across tests the same
     * way QuoteFactory reuses 'open' — avoids the (quote_workflow_id,
     * system_key) unique constraint tripping over duplicate global rows.
     */
    function enrolleeStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('enrolleeQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function enrolleeQuote(string $systemKey, array $attributes = []): Quote
    {
        return Quote::factory()->create([
            ...$attributes,
            'quote_workflow_status_id' => enrolleeStatusId($systemKey),
        ]);
    }
}

if (! function_exists('enrolleeTableActorWith')) {
    /**
     * Registers BOTH permission prefixes (mirrors
     * `EnrolleeManagementScopeTest::enrolleeActor()`) so a leak between the
     * two modules would surface as a real over-grant, never a missing
     * `Permission` row masking it as a false negative.
     *
     * @param  array<int, string>  $enrolleeAbilities
     * @param  array<int, string>  $requestAbilities
     */
    function enrolleeTableActorWith(array $enrolleeAbilities, array $requestAbilities = []): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update', 'delete', 'export'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($enrolleeAbilities as $ability) {
            $user->givePermissionTo("enrollee-management.{$ability}");
        }

        foreach ($requestAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — only validated/closed_won rows surface
// ---------------------------------------------------------------------------

it('rows: only validated/closed_won quotes surface, an open/pending/closed_lost one never does (AC-002)', function () {
    $actor = enrolleeTableActorWith(['viewAny', 'viewAll']);

    $open = enrolleeQuote('open');
    $pending = enrolleeQuote('open', ['title' => 'pending-ish']);
    $closedLost = enrolleeQuote('closed_lost');
    $validated = enrolleeQuote('validated');
    $closedWon = enrolleeQuote('closed_won');

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids->all())->toEqualCanonicalizing([$validated->id, $closedWon->id])
        ->and($ids)->not->toContain($open->id, $pending->id, $closedLost->id);
});

it('columns: GET /api/tables/enrollee-management/columns 200s for an actor with only enrollee-management.viewAny', function () {
    $actor = enrolleeTableActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tables/enrollee-management/columns')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-003 (table-endpoint slice) — the two permission sets never leak
// ---------------------------------------------------------------------------

it('403s an actor with ONLY request-management.* on every enrollee-management table endpoint (AC-003)', function () {
    $actor = enrolleeTableActorWith([], ['viewAny', 'viewAll', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->getJson('/api/tables/enrollee-management/columns')->assertForbidden();
    $this->postJson('/api/tables/enrollee-management/values', ['columnId' => 'source'])->assertForbidden();
    $this->postJson('/api/tables/enrollee-management/bulk-delete', ['ids' => [1]])->assertForbidden();
});

it('403s an actor with ONLY enrollee-management.* on every request-management table endpoint (AC-003)', function () {
    $actor = enrolleeTableActorWith(['viewAny', 'viewAll', 'update', 'delete']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->getJson('/api/tables/request-management/columns')->assertForbidden();
    $this->postJson('/api/tables/request-management/values', ['columnId' => 'source'])->assertForbidden();
    $this->postJson('/api/tables/request-management/bulk-delete', ['ids' => [1]])->assertForbidden();
});

it('request-management.viewAll does not widen the enrollee-management rows scope (AC-004, HTTP layer)', function () {
    $actor = enrolleeTableActorWith(['viewAny'], ['viewAll']);
    $mine = enrolleeQuote('validated', ['operator_id' => $actor->id]);
    $someoneElse = enrolleeQuote('closed_won');

    Sanctum::actingAs($actor);

    $ids = collect($this->postJson('/api/tables/enrollee-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toBe([$mine->id])->and($ids)->not->toContain($someoneElse->id);
});

// ---------------------------------------------------------------------------
// bulk-delete: out-of-scope/out-of-state ids are reported not_found, never
// deleted (mirrors RequestManagementTableDefinition's own D-3 guard).
// ---------------------------------------------------------------------------

it('bulk-delete never deletes a quote outside the D-2 status filter, even in perimeter', function () {
    $actor = enrolleeTableActorWith(['viewAny', 'viewAll', 'delete']);
    $outOfState = enrolleeQuote('open');
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/enrollee-management/bulk-delete', ['ids' => [$outOfState->id]])
        ->assertOk()
        ->assertJsonPath('data.deleted', 0)
        ->assertJsonPath('data.failed.0.reason', 'not_found');

    expect(Quote::query()->whereKey($outOfState->id)->exists())->toBeTrue();
});
