<?php

use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\RequestManagementNotable;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementScope;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0373: in Gestione Richieste and Gestione Iscritti the "own rows" tier is
// the request's team (Supervisore + every Gestore account), not the Operatore alone.

uses(RefreshDatabase::class);

if (! function_exists('teamVisibilityActor')) {
    /**
     * @param  array<int, string>  $abilities  `{module}.*` suffixes
     */
    function teamVisibilityActor(string $module, array $abilities = ['viewAny', 'view']): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        return User::factory()->create()->givePermissionTo(
            array_map(static fn (string $ability): string => "{$module}.{$ability}", $abilities),
        );
    }
}

if (! function_exists('teamVisibilityQuote')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function teamVisibilityQuote(string $systemKey = 'validated', array $attributes = []): Quote
    {
        $statusId = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', $systemKey)->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;

        return Quote::factory()->create([...$attributes, 'quote_workflow_status_id' => $statusId]);
    }
}

if (! function_exists('teamVisibilityRows')) {
    /**
     * @return array<int, int>
     */
    function teamVisibilityRows(string $table): array
    {
        return collect(test()->postJson("/api/tables/{$table}/rows", ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
            ->pluck('id')->all();
    }
}

dataset('request modules', ['request-management', 'enrollee-management']);

it('a Gestore account who is not the Operatore sees and opens the request (AC-001)', function (string $module) {
    $actor = teamVisibilityActor($module);
    $quote = teamVisibilityQuote();
    $quote->managers()->attach($actor->id, ['position' => ManagerPositions::OPERATOR + 1]);
    teamVisibilityQuote();

    Sanctum::actingAs($actor);

    expect(teamVisibilityRows($module))->toBe([$quote->id]);
    $this->getJson("/api/{$module}/{$quote->id}")->assertOk();
})->with('request modules');

it('the Supervisore who is not the Operatore sees and opens the request (AC-002)', function (string $module) {
    $actor = teamVisibilityActor($module);
    $quote = teamVisibilityQuote(attributes: ['supervisor_id' => $actor->id]);

    Sanctum::actingAs($actor);

    expect(teamVisibilityRows($module))->toBe([$quote->id]);
    $this->getJson("/api/{$module}/{$quote->id}")->assertOk();
})->with('request modules');

it('the Operatore keeps the request even without a pivot row (D-3)', function (string $module) {
    $actor = teamVisibilityActor($module);
    $quote = teamVisibilityQuote(attributes: ['operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    expect(teamVisibilityRows($module))->toBe([$quote->id]);
    $this->getJson("/api/{$module}/{$quote->id}")->assertOk();
})->with('request modules');

it('an actor outside the team sees nothing and gets 403 (AC-003)', function (string $module) {
    $actor = teamVisibilityActor($module);
    $quote = teamVisibilityQuote();
    $quote->managers()->attach(User::factory()->create()->id, ['position' => ManagerPositions::OPERATOR + 1]);

    Sanctum::actingAs($actor);

    expect(teamVisibilityRows($module))->toBe([]);
    $this->getJson("/api/{$module}/{$quote->id}")->assertForbidden();
})->with('request modules');

it('Gestione Iscritti keeps its status filter for a team member (AC-004)', function () {
    $actor = teamVisibilityActor('enrollee-management');
    $open = teamVisibilityQuote('open');
    $open->managers()->attach($actor->id, ['position' => ManagerPositions::OPERATOR + 1]);

    Sanctum::actingAs($actor);

    expect(teamVisibilityRows('enrollee-management'))->toBe([]);
    $this->getJson("/api/enrollee-management/{$open->id}")->assertForbidden();
});

it('being on the team grants no write without the update ability (AC-005)', function (string $module) {
    $actor = teamVisibilityActor($module);
    $quote = teamVisibilityQuote();
    $quote->managers()->attach($actor->id, ['position' => ManagerPositions::OPERATOR + 1]);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/{$module}/{$quote->id}", ['next_callback_at' => null])->assertForbidden();
})->with('request modules');

it('the team disjunction never escapes a pre-scoped query (AC-006)', function () {
    $actor = teamVisibilityActor('request-management');
    $mine = teamVisibilityQuote();
    $mine->managers()->attach($actor->id, ['position' => ManagerPositions::OPERATOR + 1]);
    $foreign = teamVisibilityQuote();

    expect(RequestManagementScope::reachesOpportunity($actor, $mine->opportunity_id, RequestModule::Requests))->toBeTrue()
        ->and(RequestManagementScope::reachesOpportunity($actor, $foreign->opportunity_id, RequestModule::Requests))->toBeFalse();
});

it('team members are mentionable in the request notes (AC-007)', function () {
    $manager = teamVisibilityActor('request-management');
    $supervisor = teamVisibilityActor('request-management');
    $outsider = teamVisibilityActor('request-management');
    $noPermission = User::factory()->create();

    $quote = teamVisibilityQuote(attributes: ['supervisor_id' => $supervisor->id]);
    $quote->managers()->attach([
        $manager->id => ['position' => ManagerPositions::OPERATOR + 1],
        $noPermission->id => ['position' => ManagerPositions::OPERATOR + 2],
    ]);

    $ids = app(RequestManagementNotable::class)->mentionableUsersQuery($quote->opportunity)->pluck('id')->all();

    expect($ids)->toContain($manager->id, $supervisor->id)
        ->and($ids)->not->toContain($outsider->id, $noPermission->id);
});

it('the notes row action reaches a team member on the Offerte and Opportunita grids (AC-008)', function (string $table, string $resource) {
    $actor = teamVisibilityActor('request-management');

    foreach (['viewAny', 'view', 'viewAll'] as $ability) {
        $actor->givePermissionTo(Permission::findOrCreate("{$resource}.{$ability}"));
    }

    $quote = teamVisibilityQuote();
    $quote->managers()->attach($actor->id, ['position' => ManagerPositions::OPERATOR + 1]);
    $foreign = teamVisibilityQuote();

    $rowIds = $table === 'quotes' ? [$quote->id, $foreign->id] : [$quote->opportunity_id, $foreign->opportunity_id];

    Sanctum::actingAs($actor);

    $items = collect($this->postJson("/api/tables/{$table}/rows", ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'));

    expect($items->firstWhere('id', $rowIds[0])['actions'])->toContain('notes')
        ->and($items->firstWhere('id', $rowIds[1])['actions'])->not->toContain('notes');
})->with([
    'Offerte' => ['quotes', 'quotes'],
    'Opportunita' => ['opportunities', 'opportunities'],
]);
