<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0130, AC-003 (activity portion) — GET /api/activity-log/enrollee-management/{id}
// is gated by EnrolleeManagementActivityAuthorizer (the minimal
// RequestManagementActivityAuthorizer subclass overriding only module()):
// 403 without `enrollee-management.viewActivity`, and 403 when the actor's
// only Offerta on the Opportunity is outside the D-2 status filter (even
// though the SAME Offerta would put the Opportunity in the request-management
// activity scope).

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

if (! function_exists('enrolleeActivityActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function enrolleeActivityActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewAll', 'viewActivity'] as $ability) {
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

it('denies the enrollee-management activity log without enrollee-management.viewActivity, even holding opportunities.viewActivity', function () {
    $actor = enrolleeActivityActorWith(['enrollee-management.viewAny', 'enrollee-management.view', 'enrollee-management.viewAll']);
    Permission::findOrCreate('opportunities.viewActivity');
    $actor->givePermissionTo('opportunities.viewActivity');

    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('closed_won'), 'operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/enrollee-management/{$opportunity->id}")->assertForbidden();
});

it('403s the FULL request-management.* set, no enrollee-management permissions at all', function () {
    $actor = enrolleeActivityActorWith([]);
    $actor->givePermissionTo(['request-management.viewAny', 'request-management.view', 'request-management.viewAll', 'request-management.viewActivity']);

    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('closed_won'), 'operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/enrollee-management/{$opportunity->id}")->assertForbidden();
});

it('403s when the actor\'s only Offerta on the Opportunity is outside validated/closed_won (D-2)', function () {
    $actor = enrolleeActivityActorWith(['enrollee-management.viewAny', 'enrollee-management.view', 'enrollee-management.viewActivity']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('open'), 'operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/enrollee-management/{$opportunity->id}")->assertForbidden();
});

it('200s when the actor operates a closed_won Offerta of the Opportunity (D-2 + D-5)', function () {
    $actor = enrolleeActivityActorWith(['enrollee-management.viewAny', 'enrollee-management.view', 'enrollee-management.viewActivity']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('closed_won'), 'operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/enrollee-management/{$opportunity->id}")->assertOk();
});

it('200s a viewAll actor on a validated Offerta operated by someone else', function () {
    $actor = enrolleeActivityActorWith(['enrollee-management.viewAny', 'enrollee-management.view', 'enrollee-management.viewAll', 'enrollee-management.viewActivity']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['quote_workflow_status_id' => enrolleeStatusId('validated')]); // someone else's

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/enrollee-management/{$opportunity->id}")->assertOk();
});
