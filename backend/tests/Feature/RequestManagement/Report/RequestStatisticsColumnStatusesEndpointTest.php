<?php

declare(strict_types=1);

use App\Enums\ReportStatusColumn;
use App\Enums\WorkflowStatusGroup;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowCriterion;
use App\Models\QuoteWorkflowStatus;
use App\Models\ReportColumnStatus;
use App\Models\User;
use App\RequestManagement\RequestModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET/PUT /api/request-management/report/column-statuses (spec 0391 AC-005..AC-008).

uses(RefreshDatabase::class);

const COLUMN_STATUSES_URL = '/api/request-management/report/column-statuses';

/**
 * @param  array<int, string>  $permissions
 */
function columnStatusesActor(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission));
    }

    return $user;
}

/**
 * Every column key present; $overrides replaces the chosen ids of some.
 *
 * @param  array<string, array<int, mixed>>  $overrides
 * @return array<string, array<int, mixed>>
 */
function columnStatusesPayload(array $overrides = []): array
{
    return [...array_fill_keys(ReportStatusColumn::values(), []), ...$overrides];
}

it('AC-005: GET returns the five columns with twin and chosen ids, then the default set first and the workflows with categories', function (): void {
    $defaultStatus = QuoteWorkflowStatus::factory()->global()->create(['name' => 'Aperta', 'group' => WorkflowStatusGroup::Open]);
    $zeta = QuoteWorkflow::factory()->create(['name' => 'Zeta flow']);
    $alpha = QuoteWorkflow::factory()->create(['name' => 'Alpha flow']);
    $alphaWon = QuoteWorkflowStatus::factory()->for($alpha, 'workflow')->create(['name' => 'Chiusa SI', 'group' => WorkflowStatusGroup::ClosedWon, 'color' => 'green']);
    $category = ProductCategory::factory()->create(['name' => 'GOL']);
    $branch = ProductCategory::factory()->create(['name' => 'Autoimpiego']);
    QuoteWorkflowCriterion::factory()->create(['quote_workflow_id' => $alpha->id, 'field' => 'product_category_id', 'value_id' => $category->id]);
    QuoteWorkflowCriterion::factory()->create(['quote_workflow_id' => $alpha->id, 'field' => 'product_category_branch_id', 'value_id' => $branch->id]);
    QuoteWorkflowCriterion::factory()->create(['quote_workflow_id' => $zeta->id, 'field' => 'source_id']); // not a category criterion
    ReportColumnStatus::query()->create(['column_key' => 'associati', 'quote_workflow_status_id' => $alphaWon->id]);
    ReportColumnStatus::query()->create(['column_key' => 'unhandled_new_contacts', 'quote_workflow_status_id' => $defaultStatus->id]);

    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_PERMISSION]));

    $response = $this->getJson(COLUMN_STATUSES_URL)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['message', 'data' => ['columns', 'workflows']]);

    $columns = collect($response->json('data.columns'));
    expect($columns->pluck('key')->all())->toBe(ReportStatusColumn::values())
        ->and($columns->pluck('twin_key')->all())->toBe(['nuovi_contatti', 'potenziali', null, null, null])
        ->and($columns->firstWhere('key', 'associati')['status_ids'])->toBe([$alphaWon->id])
        ->and($columns->firstWhere('key', 'unhandled_new_contacts')['status_ids'])->toBe([$defaultStatus->id])
        ->and($columns->firstWhere('key', 'current_potentials')['status_ids'])->toBe([]);

    $workflows = collect($response->json('data.workflows'));
    expect($workflows->pluck('name')->all())->toBe([__('request-management-report.default_statuses'), 'Alpha flow', 'Zeta flow'])
        ->and($workflows[0]['id'])->toBeNull()
        ->and(collect($workflows[0]['statuses'])->pluck('id')->all())->toContain($defaultStatus->id)
        ->and($workflows[1]['id'])->toBe($alpha->id)
        ->and($workflows[1]['is_active'])->toBeTrue()
        ->and($workflows[1]['categories'])->toBe(['Autoimpiego', 'GOL'])
        ->and($workflows[1]['statuses'])->toBe([['id' => $alphaWon->id, 'name' => 'Chiusa SI', 'group' => 'closed_won', 'color' => 'green']])
        ->and($workflows[2]['categories'])->toBe([]);
});

it('AC-006: PUT replaces the choice of every column and the re-read returns it', function (): void {
    $workflow = QuoteWorkflow::factory()->create();
    [$a, $b, $c] = QuoteWorkflowStatus::factory()->count(3)->for($workflow, 'workflow')->create()->all();
    ReportColumnStatus::query()->create(['column_key' => 'associati', 'quote_workflow_status_id' => $c->id]);
    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_PERMISSION, RequestModule::STATISTICS_CONFIGURE_PERMISSION]));

    $payload = columnStatusesPayload([
        'unhandled_new_contacts' => [$b->id, $a->id],
        'current_potentials' => [$a->id],
    ]);

    $this->putJson(COLUMN_STATUSES_URL, ['columns' => $payload])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.columns.0.status_ids', [$a->id, $b->id])
        ->assertJsonPath('data.columns.1.status_ids', [$a->id])
        ->assertJsonPath('data.columns.2.status_ids', []);

    $this->getJson(COLUMN_STATUSES_URL)
        ->assertOk()
        ->assertJsonPath('data.columns.0.status_ids', [$a->id, $b->id])
        ->assertJsonPath('data.columns.2.status_ids', []);
    expect(ReportColumnStatus::query()->count())->toBe(3);

    $this->putJson(COLUMN_STATUSES_URL, ['columns' => columnStatusesPayload()])->assertOk();
    expect(ReportColumnStatus::query()->count())->toBe(0);
});

it('AC-007: a guest gets 401 on both verbs', function (): void {
    $this->getJson(COLUMN_STATUSES_URL)->assertUnauthorized();
    $this->putJson(COLUMN_STATUSES_URL, ['columns' => columnStatusesPayload()])->assertUnauthorized();
});

it('AC-007: GET without request-statistics.view is 403', function (): void {
    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_CONFIGURE_PERMISSION]));

    $this->getJson(COLUMN_STATUSES_URL)->assertForbidden();
});

it('AC-007: PUT without request-statistics.update is 403 and writes nothing, even with view', function (): void {
    $status = QuoteWorkflowStatus::factory()->create();
    ReportColumnStatus::query()->create(['column_key' => 'associati', 'quote_workflow_status_id' => $status->id]);
    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_PERMISSION]));

    $this->putJson(COLUMN_STATUSES_URL, ['columns' => columnStatusesPayload(['current_potentials' => [$status->id]])])
        ->assertForbidden();

    expect(ReportColumnStatus::query()->pluck('column_key')->all())->toBe(['associati']);
});

it('AC-008: PUT with an invalid payload is 422 and writes nothing', function (string $case): void {
    $status = QuoteWorkflowStatus::factory()->create();
    ReportColumnStatus::query()->create(['column_key' => 'associati', 'quote_workflow_status_id' => $status->id]);
    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_PERMISSION, RequestModule::STATISTICS_CONFIGURE_PERMISSION]));

    $columns = match ($case) {
        'missing key' => array_diff_key(columnStatusesPayload(), ['associati' => true]),
        'unknown key' => columnStatusesPayload(['bogus_column' => [$status->id]]),
        'non-existent status' => columnStatusesPayload(['associati' => [999999]]),
        'duplicated id' => columnStatusesPayload(['associati' => [$status->id, $status->id]]),
        'non-integer id' => columnStatusesPayload(['associati' => ['abc']]),
        'non-array column' => columnStatusesPayload(['associati' => $status->id]),
    };

    $this->putJson(COLUMN_STATUSES_URL, ['columns' => $columns])->assertUnprocessable();

    expect(ReportColumnStatus::query()->count())->toBe(1);
})->with(['missing key', 'unknown key', 'non-existent status', 'duplicated id', 'non-integer id', 'non-array column']);

it('AC-008: PUT without the columns object is 422', function (): void {
    Sanctum::actingAs(columnStatusesActor([RequestModule::STATISTICS_PERMISSION, RequestModule::STATISTICS_CONFIGURE_PERMISSION]));

    $this->putJson(COLUMN_STATUSES_URL, [])->assertUnprocessable();
});
