<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Guard test (user directive, requirement 2 — parity with the form): the
 * inline-edit engine authorizes a field purely via
 * AbstractResourceAuthorization::fieldPermissions() (the SAME choke point
 * every `Update<X>Request`'s EnforcesFieldPermissions trait already calls).
 * That equality is true BY CONSTRUCTION for any field a FormRequest gates
 * ONLY through EnforcesFieldPermissions — the risk is a field a FormRequest
 * ALSO locks through some OTHER mechanism (a `prohibited` rule, a
 * conditional lock tied to the record's state) that inline-edit knows
 * nothing about, which would let inline-edit write something the real form
 * rejects.
 *
 * This test exercises the REAL update endpoint for every domain activated by
 * spec 0053/0054, with an actor holding the full, UNRESTRICTED ceiling (no
 * role, so no role_field_permissions row can narrow anything — see
 * AbstractResourceAuthorization::fieldPermissions()), submitting EVERY
 * inline-editable field of that domain in one PATCH. Any field the real form
 * rejects surfaces as a validation error, listed by field so a future
 * divergence is never hidden behind an opaque assertion.
 *
 * Bounded to the domains/fields spec 0053/0054 activate; extend the map
 * below when a future column is activated for inline edit (mirrors
 * RelationValueScopeChecker's own bounded-and-documented pattern).
 * `request-management` is deliberately NOT in this list: both its editable
 * columns (`next_callback_at`, `workflow_status`) and its real update
 * endpoint (`UpdateRequestRequest`) already route through the IDENTICAL
 * `RequestManagementService::updateWork()` (spec 0054, D-4/D-5) — parity is
 * true by construction there, verified directly by
 * RequestManagementWorkflowStatusInlineEditTest/RequestManagementNextCallbackInlineEditTest.
 */
uses(RefreshDatabase::class);

it('every inline-editable field is also accepted by the real update endpoint (form parity)', function () {
    $divergences = [];

    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    $operationalSite = OperationalSite::factory()->withAddress()->create();
    $source = Source::factory()->create();
    $operator = User::factory()->create();

    $cases = [
        'opportunities' => [
            'model' => Opportunity::factory()->create(),
            'endpoint' => fn (Opportunity $model): string => "/api/opportunities/{$model->id}",
            'payload' => [
                'name' => 'Parity check',
                'estimated_value' => 1234.56,
                'success_probability' => 42,
                'start_date' => '2026-01-01',
                'expected_close_date' => '2026-02-01',
            ],
        ],
        'campaigns' => [
            'model' => Campaign::factory()->create(),
            'endpoint' => fn (Campaign $model): string => "/api/campaigns/{$model->id}",
            'payload' => [
                'name' => 'Parity check',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-01',
                'total_budget' => 1000,
                'target_lead' => 10,
            ],
        ],
        'projects' => [
            'model' => Project::factory()->create(),
            'endpoint' => fn (Project $model): string => "/api/projects/{$model->id}",
            'payload' => [
                'name' => 'Parity check',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-01',
                'total_budget' => 1000,
                'target_lead' => 10,
            ],
        ],
        'leads' => [
            'model' => Lead::factory()->create(),
            'endpoint' => fn (Lead $model): string => "/api/leads/{$model->id}",
            'payload' => [
                'registry_id' => $registry->id,
                'campaign_id' => $campaign->id,
                'operational_site_id' => $operationalSite->id,
                'source_id' => $source->id,
                'operator_id' => $operator->id,
            ],
        ],
    ];

    foreach ($cases as $resource => $case) {
        // Guard pinned explicitly to 'web' (config/auth.php default): once
        // Sanctum::actingAs() runs a request, Permission::findOrCreate()'s
        // own guard auto-detection drifts to 'sanctum' for every call AFTER
        // the first, so a later iteration's permission would silently land
        // on the wrong guard and never match the actor's — a real gotcha
        // this loop (uniquely, across many actingAs() calls) exposed.
        Permission::findOrCreate("{$resource}.viewAny", 'web');
        Permission::findOrCreate("{$resource}.update", 'web');

        // No role: role_field_permissions can never restrict this actor
        // (AbstractResourceAuthorization::fieldPermissions), so the ceiling
        // is the ONLY thing in play — the most permissive baseline for a
        // genuine parity comparison.
        $actor = User::factory()->create();
        $actor->givePermissionTo(["{$resource}.viewAny", "{$resource}.update"]);
        if ($resource === 'opportunities') {
            $actor->givePermissionTo(Permission::findOrCreate('opportunities.viewAll', 'web'));
        }
        Sanctum::actingAs($actor);

        $response = $this->patchJson(($case['endpoint'])($case['model']), $case['payload']);

        if ($response->status() !== 200) {
            $errors = $response->json('errors') ?? $response->json('message');
            $divergences[] = "{$resource}: real update endpoint rejected an inline-editable field set — status {$response->status()}, errors: ".json_encode($errors);
        }
    }

    expect($divergences)->toBe([]);
});
