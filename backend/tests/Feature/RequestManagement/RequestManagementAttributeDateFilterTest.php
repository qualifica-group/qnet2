<?php

use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

// Spec 0064, §M2, coordinator follow-up (post-review): `filterType: "date"`
// on `attr.<code>` columns of type `date`/`datetime` mounts the frontend's
// `agDateColumnFilter`, which sends `{dateFrom, dateTo, type}` — a shape
// `AppliesTextFilter` silently ignored (AC-012 regression). Covers
// AttributeDateFilterApplier: equals/inRange/lessThan/greaterThan and the
// dateTo-absent case, for both `date` and `datetime` attributes, including
// the inclusive-end-of-day pitfall the coordinator flagged explicitly.

uses(RefreshDatabase::class);

if (! function_exists('dateFilterUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function dateFilterUserWith(array $abilities): User
    {
        foreach (['viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('dateFilterCategory')) {
    /**
     * @return array{0: ProductCategory, 1: Attribute}
     */
    function dateFilterCategory(string $type, string $code): array
    {
        $category = ProductCategory::factory()->create();
        $attribute = Attribute::factory()->ofType($type)->create(['code' => $code]);

        $category->attributes()->attach($attribute->id, [
            'is_required' => false,
            'sort_order' => 0,
            'context' => 'opportunity',
        ]);

        return [$category, $attribute];
    }
}

if (! function_exists('dateFilterOpportunity')) {
    function dateFilterOpportunity(ProductCategory $category, string $code, mixed $value): Opportunity
    {
        $opportunity = Opportunity::factory()->create(['attribute_values' => [$code => $value]]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'product_category_id' => $category->id,
        ]);

        return $opportunity;
    }
}

if (! function_exists('dateFilterRows')) {
    /**
     * @return array<int, int>
     */
    function dateFilterRows(TestCase $test, ProductCategory $category, string $columnId, array $filter): array
    {
        $rows = $test->postJson('/api/tables/request-management/rows', [
            'startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id,
            'filterModel' => [$columnId => $filter],
        ])->assertOk()->json('items');

        return array_column($rows, 'id');
    }
}

// ---------------------------------------------------------------------------
// `date` attribute (Y-m-d stored, no time component)
// ---------------------------------------------------------------------------

it('date filter: equals matches only the exact stored date', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('date', 'data_corso');

    $matching = dateFilterOpportunity($category, 'data_corso', '2026-03-10');
    $other = dateFilterOpportunity($category, 'data_corso', '2026-03-11');

    Sanctum::actingAs($actor);

    $ids = dateFilterRows($this, $category, 'attr.data_corso', [
        'filterType' => 'date', 'type' => 'equals', 'dateFrom' => '2026-03-10',
    ]);

    expect($ids)->toBe([$matching->id])
        ->and($ids)->not->toContain($other->id);
});

it('date filter: inRange matches dates within the bounds (inclusive)', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('date', 'data_corso');

    $before = dateFilterOpportunity($category, 'data_corso', '2026-03-09');
    $lowerBound = dateFilterOpportunity($category, 'data_corso', '2026-03-10');
    $inside = dateFilterOpportunity($category, 'data_corso', '2026-03-12');
    $upperBound = dateFilterOpportunity($category, 'data_corso', '2026-03-15');
    $after = dateFilterOpportunity($category, 'data_corso', '2026-03-16');

    Sanctum::actingAs($actor);

    $ids = dateFilterRows($this, $category, 'attr.data_corso', [
        'filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-03-10', 'dateTo' => '2026-03-15',
    ]);

    expect($ids)->toContain($lowerBound->id)
        ->and($ids)->toContain($inside->id)
        ->and($ids)->toContain($upperBound->id)
        ->and($ids)->not->toContain($before->id)
        ->and($ids)->not->toContain($after->id);
});

it('date filter: lessThan/greaterThan exclude the boundary date itself', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('date', 'data_corso');

    $earlier = dateFilterOpportunity($category, 'data_corso', '2026-03-09');
    $boundary = dateFilterOpportunity($category, 'data_corso', '2026-03-10');
    $later = dateFilterOpportunity($category, 'data_corso', '2026-03-11');

    Sanctum::actingAs($actor);

    $lessThanIds = dateFilterRows($this, $category, 'attr.data_corso', [
        'filterType' => 'date', 'type' => 'lessThan', 'dateFrom' => '2026-03-10',
    ]);
    expect($lessThanIds)->toBe([$earlier->id]);

    $greaterThanIds = dateFilterRows($this, $category, 'attr.data_corso', [
        'filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-03-10',
    ]);
    expect($greaterThanIds)->toBe([$later->id]);
});

// ---------------------------------------------------------------------------
// `datetime` attribute (Y-m-d\TH:i stored) — the flagged end-of-day pitfall
// ---------------------------------------------------------------------------

it('datetime filter: inRange with a date-only dateTo INCLUDES a value late on that last day (no silent midnight truncation)', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('datetime', 'orario_corso');

    $lateOnLastDay = dateFilterOpportunity($category, 'orario_corso', '2026-03-15T23:45');
    $nextDay = dateFilterOpportunity($category, 'orario_corso', '2026-03-16T00:01');

    Sanctum::actingAs($actor);

    $ids = dateFilterRows($this, $category, 'attr.orario_corso', [
        'filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-03-10', 'dateTo' => '2026-03-15',
    ]);

    expect($ids)->toContain($lateOnLastDay->id)
        ->and($ids)->not->toContain($nextDay->id);
});

it('datetime filter: equals matches any time within the whole day', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('datetime', 'orario_corso');

    $morning = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T08:00');
    $midnight = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T00:00');
    $lateNight = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T23:59');
    $otherDay = dateFilterOpportunity($category, 'orario_corso', '2026-03-11T08:00');

    Sanctum::actingAs($actor);

    $ids = dateFilterRows($this, $category, 'attr.orario_corso', [
        'filterType' => 'date', 'type' => 'equals', 'dateFrom' => '2026-03-10',
    ]);

    expect($ids)->toContain($morning->id)
        ->and($ids)->toContain($midnight->id)
        ->and($ids)->toContain($lateNight->id)
        ->and($ids)->not->toContain($otherDay->id);
});

it('datetime filter: inRange with dateTo absent degrades to equals on dateFrom (whole day)', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('datetime', 'orario_corso');

    $matching = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T18:00');
    $other = dateFilterOpportunity($category, 'orario_corso', '2026-03-11T08:00');

    Sanctum::actingAs($actor);

    $ids = dateFilterRows($this, $category, 'attr.orario_corso', [
        'filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-03-10',
    ]);

    expect($ids)->toBe([$matching->id])
        ->and($ids)->not->toContain($other->id);
});

it('datetime filter: lessThan/greaterThan exclude every time of the boundary day', function () {
    $actor = dateFilterUserWith(['viewAny', 'viewAll']);
    [$category] = dateFilterCategory('datetime', 'orario_corso');

    $earlier = dateFilterOpportunity($category, 'orario_corso', '2026-03-09T23:59');
    $boundaryMorning = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T08:00');
    $boundaryNight = dateFilterOpportunity($category, 'orario_corso', '2026-03-10T23:00');
    $later = dateFilterOpportunity($category, 'orario_corso', '2026-03-11T00:01');

    Sanctum::actingAs($actor);

    $lessThanIds = dateFilterRows($this, $category, 'attr.orario_corso', [
        'filterType' => 'date', 'type' => 'lessThan', 'dateFrom' => '2026-03-10',
    ]);
    expect($lessThanIds)->toBe([$earlier->id]);

    $greaterThanIds = dateFilterRows($this, $category, 'attr.orario_corso', [
        'filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2026-03-10',
    ]);
    expect($greaterThanIds)->toBe([$later->id])
        ->and($greaterThanIds)->not->toContain($boundaryMorning->id)
        ->and($greaterThanIds)->not->toContain($boundaryNight->id);
});
