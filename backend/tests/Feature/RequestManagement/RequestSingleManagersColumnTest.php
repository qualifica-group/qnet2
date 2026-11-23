<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0406: Gestione Richieste and Gestione Iscritti carry one Gestore
 * Account column only, "Gestori account" with the whole team, in every tab.
 * Supersedes spec 0382 (one column per position, "Altri gestori account").
 */
uses(RefreshDatabase::class);

if (! function_exists('singleManagersActor')) {
    function singleManagersActor(string $module = 'request-management'): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(["{$module}.viewAny", "{$module}.update", "{$module}.viewAll"]);

        return $user;
    }
}

if (! function_exists('singleManagersQuote')) {
    /**
     * A request with the given team, filed under $category when given; a
     * validated one (an Iscritto, spec 0130) for the enrollee module.
     *
     * @param  array<int, int>  $slots  position => user id
     */
    function singleManagersQuote(array $slots, ?ProductCategory $category = null, string $module = 'request-management'): Quote
    {
        $validated = $module === 'enrollee-management'
            ? QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', 'validated')->value('id')
                ?? QuoteWorkflowStatus::factory()->global()->system('validated')->create()->id
            : null;

        $quote = Quote::factory()->for(Opportunity::factory())->create([
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
            ...($validated === null ? [] : ['quote_workflow_status_id' => $validated]),
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
        }

        if ($category !== null) {
            OpportunityProductLine::factory()->create([
                'opportunity_id' => $quote->opportunity_id,
                'product_category_id' => $category->id,
            ]);
        }

        return $quote;
    }
}

if (! function_exists('singleManagersColumnIds')) {
    /**
     * The ids of the Gestore Account columns a tab's config carries.
     *
     * @return array<int, string>
     */
    function singleManagersColumnIds(string $query = '', string $module = 'request-management'): array
    {
        return collect(test()->getJson("/api/tables/{$module}/columns{$query}")->assertOk()->json('data.columns'))
            ->filter(static fn (array $column): bool => $column['id'] === 'managers' || preg_match('/^(operator|manager)_ga\d+$/', $column['id']) === 1)
            ->map(static fn (array $column): string => "{$column['id']}:{$column['label']}:".($column['visible'] ? 'visible' : 'hidden'))
            ->values()->all();
    }
}

it('AC-001/AC-005: every tab carries the single "Gestori account" column and no position column', function (string $module): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore', '5' => 'Operatore APL']]);
    $child = ProductCategory::factory()->create(['parent_id' => $category->id]);
    singleManagersQuote([1 => User::factory()->create()->id, 5 => User::factory()->create()->id], $child);
    Sanctum::actingAs(singleManagersActor($module));

    $expected = ['managers:requestManagement.columns.managers:visible'];

    expect(singleManagersColumnIds('', $module))->toBe($expected)
        ->and(singleManagersColumnIds("?product_category_id={$category->id}", $module))->toBe($expected)
        ->and(singleManagersColumnIds("?product_category_branch_id={$category->id}", $module))->toBe($expected);
})->with(['request-management', 'enrollee-management']);

it('AC-002: in a category with labels the row carries the whole team, and no position values', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore']]);
    $tutor = User::factory()->create(['name' => 'Tutor Person']);
    $partner = User::factory()->create(['name' => 'Partner Person']);
    $quote = singleManagersQuote([1 => $tutor->id, 3 => $partner->id], $category);
    Sanctum::actingAs(singleManagersActor());

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id])
        ->assertOk()->json('items'))->keyBy('id')[$quote->id];

    expect(array_column($row['managers'], 'id'))->toBe([$tutor->id, $partner->id])
        ->and(array_column($row['managers'], 'position'))->toBe([1, 3])
        ->and($row)->not->toHaveKeys(['operator_ga2', 'manager_ga1', 'manager_ga3', 'assignment_category_ids']);
});

it('AC-002: in a category with labels the filter and the distinct values read every position', function (): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor']]);
    $anna = User::factory()->create(['name' => 'Anna']);
    $zeno = User::factory()->create(['name' => 'Zeno']);
    $annaAsTutor = singleManagersQuote([1 => $anna->id], $category);
    $annaAsOther = singleManagersQuote([3 => $anna->id, 1 => $zeno->id], $category);
    singleManagersQuote([], $category);
    Sanctum::actingAs(singleManagersActor());

    $ids = collect($this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'productCategoryId' => $category->id,
        'filterModel' => ['managers' => ['filterType' => 'set', 'values' => ['Anna']]],
    ])->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$annaAsTutor->id, $annaAsOther->id]);

    $this->postJson('/api/tables/request-management/values', ['columnId' => 'managers', 'productCategoryId' => $category->id])
        ->assertOk()->assertJsonPath('data.values', [null, 'Anna', 'Zeno']);
});

it('AC-003: the cell writes the whole team in a labelled tab, and position 2 still updates operator_id', function (string $module): void {
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Tutor', '2' => 'Operatore']]);
    $tutor = User::factory()->create();
    $operator = User::factory()->create();
    $newcomer = User::factory()->create();
    $quote = singleManagersQuote([1 => $tutor->id, 2 => $operator->id], $category, $module);
    Sanctum::actingAs(singleManagersActor($module));

    $row = $this->patchJson("/api/tables/{$module}/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$newcomer->id, $operator->id],
        'product_category_id' => $category->id,
    ])->assertOk()->json('data');

    $positions = $quote->fresh()->managers()->get()->mapWithKeys(fn (User $user): array => [$user->pivot->position => $user->id])->all();

    // The Tutor leaves, the Operatore keeps slot 2, the newcomer takes the freed slot 1.
    expect($positions)->toBe([1 => $newcomer->id, 2 => $operator->id])
        ->and($quote->fresh()->operator_id)->toBe($operator->id)
        ->and(array_column($row['managers'], 'id'))->toBe([$newcomer->id, $operator->id]);

    $this->patchJson("/api/tables/{$module}/rows/{$quote->id}", [
        'column' => 'managers',
        'value' => [$newcomer->id],
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBeNull();
})->with(['request-management', 'enrollee-management']);

it('AC-004: a PATCH on a removed position column is refused as not editable', function (string $column): void {
    $quote = singleManagersQuote([]);
    Sanctum::actingAs(singleManagersActor());

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => $column,
        'value' => User::factory()->create()->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('column');

    expect($quote->fresh()->managers()->count())->toBe(0);
})->with(['operator_ga2', 'manager_ga1', 'manager_ga5']);
