<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Tables\RequestManagement\RequestManagerColumns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direttiva utente 2026-09-10 — the Operatore (GA2) cell editor was narrowed
 * to the row's Sede alone. It must offer the same operators the assignment
 * popup offers: the operators of the offer's `quotes.operational_site_id`
 * (spec 0113, D-4) COMPETENT for the categories its
 * `opportunity_product_lines` require (spec 0110), so the grid carries one
 * more non-visible row key the editor sends to `users/for-select`.
 */
uses(RefreshDatabase::class);

if (! function_exists('operatorPickerScopeActor')) {
    /** @param  array<int, string>  $abilities */
    function operatorPickerScopeActor(array $abilities = ['viewAny', 'viewAll']): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'operator-picker-scope-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('operatorPickerScopeCategory')) {
    function operatorPickerScopeCategory(): ProductCategory
    {
        return ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('operatorPickerScopeQuote')) {
    /**
     * An Offerta on $site whose Opportunity is classified with exactly
     * $categories (none at all when the array is empty).
     *
     * @param  array<int, ProductCategory>  $categories
     */
    function operatorPickerScopeQuote(?OperationalSite $site, array $categories): Quote
    {
        $opportunity = Opportunity::factory()->create();

        foreach ($categories as $category) {
            OpportunityProductLine::factory()->for($opportunity)->create([
                'business_function_id' => $category->business_function_id,
                'product_category_id' => $category->id,
            ]);
        }

        return Quote::factory()->for($opportunity)->create(['operational_site_id' => $site?->id]);
    }
}

if (! function_exists('operatorPickerScopeRow')) {
    /** @return array<string, mixed> */
    function operatorPickerScopeRow(): array
    {
        return test()->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
            ->assertOk()
            ->json('items.0');
    }
}

// ---------------------------------------------------------------------------
// The config the grid builds the editor from
// ---------------------------------------------------------------------------

it('declares the Operatore picker scope as {Sede of the offer, required categories}', function () {
    Sanctum::actingAs(operatorPickerScopeActor(['viewAny', 'update', 'viewAll']));

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['operator_ga2'];

    expect($column['relation']['scope'])->toBe([
        'operational_site_id' => 'operational_site',
        'competence_category_ids' => 'assignment_category_ids',
    ])
        // No `lockScope`, and declaring one would be INERT: the flag is
        // forwarded to the `multiselect` editor alone (cell-editor-registry),
        // and the "show everyone" escape it governs exists only there — this
        // single-value cell (RelationCellEditor) has no escape to lock, it
        // simply lists what the scoped `users/for-select` returns. Declaring
        // it here would leave behind a flag that looks like a protection and
        // is not. An escape on a single-value cell would be framework work
        // (RelationCellEditor + registry), never a column declaration.
        // Ineligible operators are refused by the domain writer
        // (RequestAttributionWriter::assertOperatorCovers, direttiva utente
        // 2026-09-10), covered by its own tests.
        ->and($column['relation'])->not->toHaveKey('lockScope')
        // Everything else about the column is untouched.
        ->and($column['label'])->toBe('requestManagement.columns.operator')
        ->and($column['type'])->toBe('text')
        ->and($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('relation')
        ->and($column['relation']['resource'])->toBe('users')
        ->and($column['sortable'])->toBeFalse()
        ->and($column['filterable'])->toBeFalse();

    // `editableField`/`nullable` are not part of the emitted config (the
    // write path reads them off the raw declaration): asserted at the source.
    $declared = collect(RequestManagerColumns::columns())->keyBy('id')['operator_ga2'];

    expect($declared['editableField'])->toBe('manager_slots')
        ->and($declared['nullable'])->toBeTrue();
});

it('leaves the GA1 column unscoped: only the Operatore slot is narrowed', function () {
    Sanctum::actingAs(operatorPickerScopeActor(['viewAny', 'update', 'viewAll']));

    $column = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')['manager_ga1'];

    expect($column['relation'])->not->toHaveKey('scope');
});

// ---------------------------------------------------------------------------
// The row keys the scope reads
// ---------------------------------------------------------------------------

it('answers assignment_category_ids from the offer own product lines', function () {
    $first = operatorPickerScopeCategory();
    $second = operatorPickerScopeCategory();
    operatorPickerScopeQuote(OperationalSite::factory()->create(), [$first, $second]);
    Sanctum::actingAs(operatorPickerScopeActor());

    expect(operatorPickerScopeRow()['assignment_category_ids'])->toBe([$first->id, $second->id]);
});

it('answers an empty assignment_category_ids when the offer requires no competence', function () {
    operatorPickerScopeQuote(OperationalSite::factory()->create(), []);
    Sanctum::actingAs(operatorPickerScopeActor());

    // Empty means "requires nothing", which the editor reads as "no filter"
    // (resolveScopeParams omits an empty set) — never "no candidate".
    expect(operatorPickerScopeRow()['assignment_category_ids'])->toBe([]);
});

it('keeps the Sede half of the scope answering from the offer OWN operational site', function () {
    $site = OperationalSite::factory()->create();
    operatorPickerScopeQuote($site, [operatorPickerScopeCategory()]);
    Sanctum::actingAs(operatorPickerScopeActor());

    expect(operatorPickerScopeRow()['operational_site']['id'])->toBe($site->id);
});

it('answers a null operational_site for an offer with no Sede, keeping the categories', function () {
    $category = operatorPickerScopeCategory();
    operatorPickerScopeQuote(null, [$category]);
    Sanctum::actingAs(operatorPickerScopeActor());

    $row = operatorPickerScopeRow();

    expect($row['operational_site'])->toBeNull()
        ->and($row['assignment_category_ids'])->toBe([$category->id]);
});

it('carries the key on the row PATCH re-maps after an inline edit', function () {
    $site = OperationalSite::factory()->create();
    $category = operatorPickerScopeCategory();
    $quote = operatorPickerScopeQuote($site, [$category]);
    Sanctum::actingAs(operatorPickerScopeActor(['viewAny', 'view', 'update', 'viewAll']));

    // Direttiva utente 2026-09-10: la scrittura dell'Operatore GA2 e' ora validata
    // (RequestAttributionWriter::assertOperatorCovers). L'offerta ha una Sede, quindi
    // servono ENTRAMBE le meta': prima bastava un utente qualunque.
    $operator = User::factory()->create();
    EmploymentProfile::factory()->for($operator)->physicalSite($site)
        ->competentIn($category->businessFunction, $category)->create();

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $operator->id,
    ])->assertOk()
        ->assertJsonPath('data.operational_site.id', $site->id)
        ->assertJsonPath('data.assignment_category_ids', [$category->id]);
});

// ---------------------------------------------------------------------------
// Batch: the page resolves once, whatever its size
// ---------------------------------------------------------------------------

it('resolves the whole page in one batch: the query count does not grow with the rows', function () {
    $site = OperationalSite::factory()->create();
    $category = operatorPickerScopeCategory();
    Sanctum::actingAs(operatorPickerScopeActor());

    $measure = function (int $rows) use ($site, $category): int {
        Quote::query()->forceDelete();

        for ($i = 0; $i < $rows; $i++) {
            operatorPickerScopeQuote($site, [$category]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // First request of the process pays the permission/custom-field cache
    // warm-up: measuring it would compare fixtures, not the page resolution.
    $measure(1);

    expect($measure(20))->toBe($measure(1));
});
