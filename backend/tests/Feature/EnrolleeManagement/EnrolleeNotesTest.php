<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, AC-003 (note portion) — GET /api/notes?entity_type=enrollee-management
// is gated by EnrolleeManagementNotable (the minimal RequestManagementNotable
// subclass overriding only module()): 403 without `enrollee-management.view`
// (even with the FULL request-management.* set), and 403 when none of the
// Opportunity's Offerte are in the Iscritti perimeter (D-2 status filter +
// D-5), even though the very same Offerta would put the Opportunity in scope
// under `request-management`.

uses(RefreshDatabase::class);

if (! function_exists('enrolleeStatusId')) {
    function enrolleeStatusId(string $systemKey): int
    {
        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('enrolleeNoteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function enrolleeNoteActor(array $abilities = []): User
    {
        foreach (['view', 'viewAll'] as $ability) {
            Permission::findOrCreate("enrollee-management.{$ability}");
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('enrolleeOperatedOpportunity')) {
    function enrolleeOperatedOpportunity(User $operator, string $systemKey): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create([
            'operator_id' => $operator->id,
            'quote_workflow_status_id' => enrolleeStatusId($systemKey),
        ]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — the permission sets never leak across modules
// ---------------------------------------------------------------------------

it('403s reading enrollee-management notes with the FULL request-management.* set but no enrollee-management.view', function () {
    $actor = enrolleeNoteActor([]);
    $actor->givePermissionTo(['request-management.view', 'request-management.viewAll']);
    $opportunity = enrolleeOperatedOpportunity($actor, 'closed_won');
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-003 — 403 when no Offerta of the Opportunity is in the Iscritti perimeter
// ---------------------------------------------------------------------------

it('403s when the actor\'s only Offerta on the Opportunity is outside validated/closed_won (D-2)', function () {
    $actor = enrolleeNoteActor(['enrollee-management.view']);
    $opportunity = enrolleeOperatedOpportunity($actor, 'open');
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertForbidden();
});

it('403s when the actor operates no Offerta of the Opportunity at all (D-5), even one that is closed_won', function () {
    $actor = enrolleeNoteActor(['enrollee-management.view']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('closed_won')]); // someone else's

    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-003 — 200 when the actor's own Offerta is validated/closed_won
// ---------------------------------------------------------------------------

it('reads the notes when the actor operates a closed_won Offerta of the Opportunity', function () {
    $actor = enrolleeNoteActor(['enrollee-management.view']);
    $opportunity = enrolleeOperatedOpportunity($actor, 'closed_won');
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertOk();
});

it('reads the notes when the actor operates a validated Offerta of the Opportunity', function () {
    $actor = enrolleeNoteActor(['enrollee-management.view']);
    $opportunity = enrolleeOperatedOpportunity($actor, 'validated');
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertOk();
});

it('enrollee-management.viewAll reads notes on any record, independent of request-management.viewAll', function () {
    $actor = enrolleeNoteActor(['enrollee-management.view', 'enrollee-management.viewAll']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('closed_won')]); // someone else's

    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=enrollee-management&entity_id={$opportunity->id}")
        ->assertOk();
});
