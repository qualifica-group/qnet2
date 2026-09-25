<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubCustomFilterRuleTableDefinition;

uses(RefreshDatabase::class);

/**
 * Generic custom filter rules engine (spec 0158): validation (allow-list,
 * operator matrix, value shapes) and query application (AND/OR semantics,
 * negatives-include-blanks, day-portable date operators) against the
 * domain-agnostic stub — the tasks-specific AC-001/AC-003 live in
 * tests/Feature/Tasks/TaskCustomFilterRulesTest.php.
 */
if (! function_exists('userWithBusinessFunctionAbilities')) {
    function userWithBusinessFunctionAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('customFilterRuleNames')) {
    function customFilterRuleNames(array $rules): array
    {
        $items = test()->postJson('/api/tables/stub-custom-filter-rules/rows', [
            'startRow' => 0, 'endRow' => 50, 'customFilterRules' => $rules,
        ])->assertOk()->json('items');

        return collect($items)->pluck('name')->sort()->values()->all();
    }
}

beforeEach(function () {
    config(['tables.definitions' => ['stub-custom-filter-rules' => StubCustomFilterRuleTableDefinition::class]]);
    Sanctum::actingAs(userWithBusinessFunctionAbilities(['viewAny']));
});

afterEach(function () {
    Carbon::setTestNow();
});

// ---------------------------------------------------------------------------
// AND / OR semantics
// ---------------------------------------------------------------------------

it('AND: matches only rows satisfying every and-rule', function () {
    BusinessFunction::factory()->create(['name' => 'Alfa', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Alfa service', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Beta', 'is_business_unit' => true]);

    $names = customFilterRuleNames([
        'and' => [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'Alfa'],
            ['field' => 'is_business_unit', 'operator' => 'is', 'value' => true],
        ],
    ]);

    expect($names)->toBe(['Alfa']);
});

it('OR: matches rows satisfying any or-rule', function () {
    BusinessFunction::factory()->create(['name' => 'Alfa']);
    BusinessFunction::factory()->create(['name' => 'Beta']);
    BusinessFunction::factory()->create(['name' => 'Gamma']);

    $names = customFilterRuleNames([
        'or' => [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'Alfa'],
            ['field' => 'name', 'operator' => 'equals', 'value' => 'Beta'],
        ],
    ]);

    expect($names)->toBe(['Alfa', 'Beta']);
});

it('(AND) OR (OR): the two groups combine as (all of and) OR (any of or)', function () {
    BusinessFunction::factory()->create(['name' => 'Match-and', 'is_business_unit' => true]);
    BusinessFunction::factory()->create(['name' => 'Match-and-wrong-type', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Match-or', 'is_business_unit' => false]);
    BusinessFunction::factory()->create(['name' => 'Match-neither', 'is_business_unit' => false]);

    $names = customFilterRuleNames([
        'and' => [
            ['field' => 'name', 'operator' => 'contains', 'value' => 'Match-and'],
            ['field' => 'is_business_unit', 'operator' => 'is', 'value' => true],
        ],
        'or' => [
            ['field' => 'name', 'operator' => 'equals', 'value' => 'Match-or'],
        ],
    ]);

    expect($names)->toBe(['Match-and', 'Match-or']);
});

// ---------------------------------------------------------------------------
// Validation (AC-002 + edge cases)
// ---------------------------------------------------------------------------

it('422: a field outside the filterable allow-list (AC-002)', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [['field' => 'ghost', 'operator' => 'equals', 'value' => 'x']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and.0.field');
});

it('422: an operator not allowed for the column type', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [['field' => 'name', 'operator' => 'gt', 'value' => 'x']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and.0.operator');
});

it('422: an invalid value for the type', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [['field' => 'id', 'operator' => 'equals', 'value' => 'not-a-number']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and.0.value');
});

it('422: zero rules in total', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [], 'or' => []],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules');
});

it('422: blank rejected on a column with hasFilterValues false', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [['field' => 'score', 'operator' => 'blank']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and.0.operator');
});

it('422: a column with no usable filterType cannot be used in a rule', function () {
    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => [['field' => 'weird', 'operator' => 'contains', 'value' => 'x']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and.0.field');
});

it('422: more than 20 rules in one group', function () {
    $rules = array_fill(0, 21, ['field' => 'name', 'operator' => 'contains', 'value' => 'x']);

    $this->postJson('/api/tables/stub-custom-filter-rules/rows', [
        'startRow' => 0, 'endRow' => 50,
        'customFilterRules' => ['and' => $rules],
    ])->assertUnprocessable()->assertJsonValidationErrors('customFilterRules.and');
});

// ---------------------------------------------------------------------------
// Negatives include blank rows
// ---------------------------------------------------------------------------

it('text not_equals excludes the matching value but INCLUDES blank rows', function () {
    BusinessFunction::factory()->create(['name' => 'Excluded']);
    BusinessFunction::factory()->create(['name' => 'Kept']);
    BusinessFunction::factory()->create(['name' => '']); // blank

    $names = customFilterRuleNames([
        'and' => [['field' => 'name', 'operator' => 'not_equals', 'value' => 'Excluded']],
    ]);

    expect($names)->toBe(['', 'Kept']);
});

it('set not_in excludes the matching related manager but INCLUDES rows with no manager', function () {
    $manager = User::factory()->create(['name' => 'Excluded Manager']);
    BusinessFunction::factory()->create(['name' => 'Has excluded manager', 'manager_id' => $manager->id]);
    BusinessFunction::factory()->create(['name' => 'No manager', 'manager_id' => null]);

    $names = customFilterRuleNames([
        'and' => [['field' => 'manager', 'operator' => 'not_in', 'value' => ['Excluded Manager']]],
    ]);

    expect($names)->toBe(['No manager']);
});

it('set in matches by related manager name; blank included via a null value entry', function () {
    $manager = User::factory()->create(['name' => 'Wanted Manager']);
    BusinessFunction::factory()->create(['name' => 'Has wanted manager', 'manager_id' => $manager->id]);
    BusinessFunction::factory()->create(['name' => 'No manager', 'manager_id' => null]);
    BusinessFunction::factory()->create(['name' => 'Has other manager', 'manager_id' => User::factory()->create(['name' => 'Other'])->id]);

    $names = customFilterRuleNames([
        'and' => [['field' => 'manager', 'operator' => 'in', 'value' => ['Wanted Manager']]],
    ]);

    expect($names)->toBe(['Has wanted manager']);
});

// ---------------------------------------------------------------------------
// Date operators — day-portable on a real DATETIME column
// ---------------------------------------------------------------------------

it('date equals/lt/gt/between are day-portable on a DATETIME column (not just midnight)', function () {
    BusinessFunction::factory()->create(['name' => 'Day1-late', 'created_at' => Carbon::parse('2026-09-10 23:59:59')]);
    BusinessFunction::factory()->create(['name' => 'Day2-early', 'created_at' => Carbon::parse('2026-09-11 00:00:01')]);
    BusinessFunction::factory()->create(['name' => 'Day3', 'created_at' => Carbon::parse('2026-09-12 12:00:00')]);

    expect(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'equals', 'value' => '2026-09-10']]]))
        ->toBe(['Day1-late'])
        // "gt 2026-09-10" must mean the day AFTER, so a same-day 23:59:59 row
        // must NOT match (raw '>' would wrongly include it).
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'gt', 'value' => '2026-09-10']]]))
        ->toBe(['Day2-early', 'Day3'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'lt', 'value' => '2026-09-11']]]))
        ->toBe(['Day1-late'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'between', 'value' => ['2026-09-11', '2026-09-12']]]]))
        ->toBe(['Day2-early', 'Day3']);
});

it('date lte/gte are day-portable (NOT gt / NOT lt)', function () {
    BusinessFunction::factory()->create(['name' => 'Day1-late', 'created_at' => Carbon::parse('2026-09-10 23:59:59')]);
    BusinessFunction::factory()->create(['name' => 'Day2-early', 'created_at' => Carbon::parse('2026-09-11 00:00:01')]);

    expect(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'lte', 'value' => '2026-09-10']]]))
        ->toBe(['Day1-late'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'gte', 'value' => '2026-09-11']]]))
        ->toBe(['Day2-early']);
});

it('date today/this_week/this_month/last_n_days resolve relative to the current moment', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00')); // a Friday

    BusinessFunction::factory()->create(['name' => 'Today', 'created_at' => Carbon::parse('2026-09-25 08:00:00')]);
    BusinessFunction::factory()->create(['name' => 'MondayThisWeek', 'created_at' => Carbon::parse('2026-09-21 08:00:00')]);
    BusinessFunction::factory()->create(['name' => 'LastWeek', 'created_at' => Carbon::parse('2026-09-13 08:00:00')]);
    BusinessFunction::factory()->create(['name' => 'EarlierThisMonth', 'created_at' => Carbon::parse('2026-09-02 08:00:00')]);
    BusinessFunction::factory()->create(['name' => 'LastMonth', 'created_at' => Carbon::parse('2026-08-31 08:00:00')]);

    expect(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'today']]]))
        ->toBe(['Today'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'this_week']]]))
        ->toBe(['MondayThisWeek', 'Today'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'this_month']]]))
        ->toBe(['EarlierThisMonth', 'LastWeek', 'MondayThisWeek', 'Today'])
        ->and(customFilterRuleNames(['and' => [['field' => 'created_at', 'operator' => 'last_n_days', 'value' => 3]]]))
        ->toBe(['Today']);
});
