<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Spec 0130 D-2/D-5 — AC-004: RequestManagementScope evaluates the D-2
// row-state filter and the D-5 perimeter (operatore proprio / viewSite sedi
// / viewAll) on `enrollee-management.*` permissions ONLY, never on
// `request-management.*`, and RequestModule::Requests keeps behaving exactly
// as before the refactor (parity).

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

if (! function_exists('enrolleeActor')) {
    /**
     * @param  array<int, string>  $enrolleeAbilities
     */
    function enrolleeActor(array $enrolleeAbilities, ?OperationalSite $site = null): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewSite', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($enrolleeAbilities as $ability) {
            $user->givePermissionTo("enrollee-management.{$ability}");
        }

        if ($site !== null) {
            EmploymentProfile::factory()->physicalSite($site)->create(['user_id' => $user->id]);
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// D-2 — row-state filter, query shape (scopeToActor)
// ---------------------------------------------------------------------------

it('scopeToActor(Enrollees) keeps only validated/closed_won rows, even for a viewAll actor', function () {
    $actor = enrolleeActor(['viewAny', 'viewAll']);

    $open = enrolleeQuote('open');
    $pending = enrolleeQuote('open', ['title' => 'pending-ish']);
    $closedLost = enrolleeQuote('closed_lost');
    $validated = enrolleeQuote('validated');
    $closedWon = enrolleeQuote('closed_won');

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all();

    expect($ids)->toContain($validated->id, $closedWon->id)
        ->not->toContain($open->id, $pending->id, $closedLost->id);
});

it('scopeToActor(Requests) applies no state filter at all (parity)', function () {
    $actor = enrolleeActor([]);
    $actor->givePermissionTo('request-management.viewAll');

    $open = enrolleeQuote('open');
    $closedLost = enrolleeQuote('closed_lost');

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor)->pluck('id')->all();

    expect($ids)->toContain($open->id, $closedLost->id);
});

// ---------------------------------------------------------------------------
// D-5 — perimeter, evaluated ONLY on enrollee-management.* (AC-004)
// ---------------------------------------------------------------------------

it('viewAny alone (no viewAll/viewSite) sees only the actor\'s own rows (AC-004)', function () {
    $actor = enrolleeActor(['viewAny']);

    $mine = enrolleeQuote('validated', ['operator_id' => $actor->id]);
    $colleague = enrolleeQuote('validated');

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all();

    expect($ids)->toBe([$mine->id]);
});

it('viewSite widens to the actor\'s own Sede (AC-004)', function () {
    $site = OperationalSite::factory()->create();
    $actor = enrolleeActor(['viewAny', 'viewSite'], $site);

    $inMySite = enrolleeQuote('closed_won', ['operational_site_id' => $site->id]);
    $elsewhere = enrolleeQuote('closed_won', ['operational_site_id' => OperationalSite::factory()->create()->id]);

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all();

    expect($ids)->toContain($inMySite->id)->not->toContain($elsewhere->id);
});

it('request-management.viewAll does NOT widen the enrollee-management perimeter (AC-004)', function () {
    $actor = enrolleeActor(['viewAny']);
    $actor->givePermissionTo('request-management.viewAll');

    $mine = enrolleeQuote('validated', ['operator_id' => $actor->id]);
    $someoneElse = enrolleeQuote('validated');

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all();

    expect($ids)->toBe([$mine->id])
        ->and($ids)->not->toContain($someoneElse->id);
});

it('enrollee-management.viewAll sees every row in scope, request-management permissions do not leak in (AC-004)', function () {
    $actor = enrolleeActor(['viewAny', 'viewAll']);

    $anyOperator = enrolleeQuote('closed_won');

    $ids = RequestManagementScope::scopeToActor(Quote::query(), $actor, RequestModule::Enrollees)->pluck('id')->all();

    expect($ids)->toContain($anyOperator->id);
});

// ---------------------------------------------------------------------------
// assertInScope — record shape, D-2 and D-5 combined (AC-005 groundwork)
// ---------------------------------------------------------------------------

it('assertInScope(Enrollees) 403s on a quote in perimeter but out of the status filter', function () {
    $actor = enrolleeActor(['viewAny', 'viewAll']);
    $quote = enrolleeQuote('open');

    expect(fn () => (new RequestManagementScope)->assertInScope($actor, $quote, RequestModule::Enrollees))
        ->toThrow(HttpException::class);
});

it('assertInScope(Enrollees) 403s on a quote in the status filter but out of perimeter', function () {
    $actor = enrolleeActor(['viewAny']);
    $quote = enrolleeQuote('closed_won');

    expect(fn () => (new RequestManagementScope)->assertInScope($actor, $quote, RequestModule::Enrollees))
        ->toThrow(HttpException::class);
});

it('assertInScope(Enrollees) passes on a quote both validated/closed_won and in perimeter', function () {
    $actor = enrolleeActor(['viewAny', 'viewAll']);
    $quote = enrolleeQuote('closed_won');

    (new RequestManagementScope)->assertInScope($actor, $quote, RequestModule::Enrollees);
})->throwsNoExceptions();

it('assertInScope(Requests) ignores the state filter entirely (parity)', function () {
    $actor = enrolleeActor([]);
    $actor->givePermissionTo('request-management.viewAll');
    $quote = enrolleeQuote('open');

    (new RequestManagementScope)->assertInScope($actor, $quote);
})->throwsNoExceptions();
