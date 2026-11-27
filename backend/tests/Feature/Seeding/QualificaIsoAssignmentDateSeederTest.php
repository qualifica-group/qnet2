<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\QualificaIsoAssignmentDateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Every ISO / [OLD] ISO commessa with a Responsabile and no "Data di assegnazione" takes
 * the legacy's assignment date; a date already there is never changed.
 */
uses(RefreshDatabase::class);

const ISO_ASSIGNMENT_DATE_CODE = 'data_inizio_lavorazione';

beforeEach(function () {
    Carbon::setTestNow('2026-11-27 12:00:00');
    // The date lives on "[OLD] ISO" and its subcategories inherit it, as "[OLD] 37001".
    $this->oldIso = ProductCategory::factory()->create(['name' => '[OLD] ISO', 'parent_id' => null]);
    categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => ISO_ASSIGNMENT_DATE_CODE], $this->oldIso);
    $this->subcategory = ProductCategory::factory()->create(['name' => '[OLD] 37001', 'parent_id' => $this->oldIso->id]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A migrated commessa of the category, with its creation and last change days.
 */
function isoAssignmentWorkOrder(ProductCategory $category, int $legacyId, array $overrides = [], bool $withSupervisor = true): WorkOrder
{
    $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $workOrder->forceFill([
        'old_id' => $legacyId,
        'attribute_values' => null,
        'created_at' => '2025-05-05 10:00:00',
        'updated_at' => '2026-11-18 10:00:00',
        ...$overrides,
    ])->saveQuietly();

    if ($withSupervisor) {
        $workOrder->supervisors()->sync([User::factory()->create()->id]);
    }

    return $workOrder;
}

function runIsoAssignmentDateSeeder(array $items): void
{
    fakeLegacyRealign('work-orders', [], [], $items);

    test()->seed(QualificaIsoAssignmentDateSeeder::class);
}

function isoAssignmentDateOf(WorkOrder $workOrder): ?string
{
    return $workOrder->fresh()->attribute_values[ISO_ASSIGNMENT_DATE_CODE] ?? null;
}

it('writes the legacy assignment date on a commessa of a subcategory, quietly', function () {
    $workOrder = isoAssignmentWorkOrder($this->subcategory, 70, ['attribute_values' => ['scopo' => 'Consulenza']]);
    Activity::query()->delete();

    runIsoAssignmentDateSeeder([['id' => 70, 'assignment_date' => '2025-05-05']]);

    $fresh = $workOrder->fresh();
    expect($fresh->attribute_values)->toBe(['scopo' => 'Consulenza', ISO_ASSIGNMENT_DATE_CODE => '2025-05-05'])
        ->and($fresh->updated_at->toDateTimeString())->toBe('2026-11-18 10:00:00')
        ->and(Activity::query()->count())->toBe(0);
});

it('takes an old commessa of the root category, whenever it was created or changed', function () {
    $workOrder = isoAssignmentWorkOrder($this->oldIso, 71, ['created_at' => '2022-11-24 09:00:00', 'updated_at' => '2022-11-24 09:00:00']);

    runIsoAssignmentDateSeeder([['id' => 71, 'assignment_date' => '2022-11-24']]);

    expect(isoAssignmentDateOf($workOrder))->toBe('2022-11-24');
});

it('never changes an existing date', function () {
    $workOrder = isoAssignmentWorkOrder($this->subcategory, 72, ['attribute_values' => [ISO_ASSIGNMENT_DATE_CODE => '2026-06-10']]);

    runIsoAssignmentDateSeeder([['id' => 72, 'assignment_date' => '2026-11-03']]);

    expect(isoAssignmentDateOf($workOrder))->toBe('2026-06-10');
});

it('leaves out a commessa without Responsabile and one outside ISO', function () {
    $withoutSupervisor = isoAssignmentWorkOrder($this->subcategory, 73, withSupervisor: false);
    $projects = ProductCategory::factory()->create(['name' => '[OLD] PROGETTI-', 'parent_id' => null]);
    $projects->attributes()->attach($this->oldIso->attributes()->firstOrFail()->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::WorkOrder->value]);
    $notIso = isoAssignmentWorkOrder($projects, 75);

    runIsoAssignmentDateSeeder([
        ['id' => 73, 'assignment_date' => '2026-11-05'],
        ['id' => 75, 'assignment_date' => '2026-11-05'],
    ]);

    expect(isoAssignmentDateOf($withoutSupervisor))->toBeNull()
        ->and(isoAssignmentDateOf($notIso))->toBeNull();
});

it('leaves the date empty when the legacy has none', function () {
    $workOrder = isoAssignmentWorkOrder($this->subcategory, 76);

    runIsoAssignmentDateSeeder([['id' => 76, 'assignment_date' => null]]);

    expect(isoAssignmentDateOf($workOrder))->toBeNull();
});
