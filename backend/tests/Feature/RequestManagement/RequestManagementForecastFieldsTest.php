<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0313: "Previsione commerciale" — the Opportunity's estimated_value,
// expected_close_date and success_probability, managed from Gestione Richieste
// too (work panel PATCH, create POST, grid columns). The values stay on the
// parent Opportunity (D-1), so the field-permission gate reads them through
// `quote.opportunity` (UpdateRequestRequest::OPPORTUNITY_FIELDS).

uses(RefreshDatabase::class);

if (! function_exists('forecastQuote')) {
    /** @param  array<string, mixed>  $forecast */
    function forecastQuote(User $operator, array $forecast = []): Quote
    {
        $opportunity = Opportunity::factory()->create([
            'estimated_value' => null,
            'expected_close_date' => null,
            'success_probability' => null,
            ...$forecast,
        ]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

if (! function_exists('forecastGridRowIds')) {
    /**
     * @param  array<string, mixed>  $request
     * @return array<int, int>
     */
    function forecastGridRowIds(array $request): array
    {
        return collect(test()->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25, ...$request])
            ->assertOk()->json('items'))->pluck('id')->all();
    }
}

// ---------------------------------------------------------------------------
// Work panel: GET / PATCH
// ---------------------------------------------------------------------------

it('GET exposes the forecast trio top-level, read from the Opportunity (AC-001)', function () {
    $actor = requestManagementUserWith(['view', 'viewAll']);
    $quote = forecastQuote($actor, ['estimated_value' => 1500.5, 'expected_close_date' => '2026-12-15', 'success_probability' => 40]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.estimated_value', '1500.50')
        ->assertJsonPath('data.expected_close_date', '2026-12-15')
        ->assertJsonPath('data.success_probability', 40)
        ->assertJsonPath('permissions.fields.estimated_value.editable', false)
        ->assertJsonMissingPath('data.context');
});

it('PATCH writes the forecast trio on the Opportunity (AC-001)', function () {
    $actor = requestManagementUserWith(['view', 'update', 'viewAll']);
    $quote = forecastQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'estimated_value' => 2500,
        'expected_close_date' => '2027-01-31',
        'success_probability' => 75,
    ])->assertOk()
        ->assertJsonPath('data.estimated_value', '2500.00')
        ->assertJsonPath('data.expected_close_date', '2027-01-31')
        ->assertJsonPath('data.success_probability', 75);

    $opportunity = $quote->opportunity->fresh();
    expect($opportunity->estimated_value)->toBe('2500.00')
        ->and($opportunity->expected_close_date->format('Y-m-d'))->toBe('2027-01-31')
        ->and($opportunity->success_probability)->toBe(75);
});

it('PATCH null clears the forecast trio, an absent key leaves it untouched (AC-001)', function () {
    $actor = requestManagementUserWith(['view', 'update', 'viewAll']);
    $quote = forecastQuote($actor, ['estimated_value' => 900, 'expected_close_date' => '2026-11-30', 'success_probability' => 20]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['estimated_value' => null, 'success_probability' => null])
        ->assertOk();

    $opportunity = $quote->opportunity->fresh();
    expect($opportunity->estimated_value)->toBeNull()
        ->and($opportunity->success_probability)->toBeNull()
        ->and($opportunity->expected_close_date->format('Y-m-d'))->toBe('2026-11-30');
});

it('PATCH out-of-rule forecast values -> 422 on each field (AC-002)', function () {
    $actor = requestManagementUserWith(['view', 'update', 'viewAll']);
    $quote = forecastQuote($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'estimated_value' => -1,
        'expected_close_date' => 'not-a-date',
        'success_probability' => 101,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['estimated_value', 'expected_close_date', 'success_probability']);

    expect($quote->opportunity->fresh()->success_probability)->toBeNull();
});

it('a role with success_probability readonly cannot change it, but may resubmit it unchanged (AC-003)', function () {
    $actor = requestManagementUserWith(['view', 'update', 'viewAll']);
    $role = Role::create(['name' => 'forecast-readonly-'.uniqid()]);
    $role->fieldPermissions()->create([
        'resource' => 'request-management',
        'field' => 'success_probability',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);
    $actor->assignRole($role);
    $quote = forecastQuote($actor, ['success_probability' => 30]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['success_probability' => 90])
        ->assertStatus(422)
        ->assertJsonValidationErrors('success_probability');

    $this->patchJson("/api/request-management/{$quote->id}", ['success_probability' => 30])->assertOk();

    expect($quote->opportunity->fresh()->success_probability)->toBe(30);
});

// ---------------------------------------------------------------------------
// Create form: POST
// ---------------------------------------------------------------------------

it('POST creates the Opportunity with the submitted forecast trio (AC-004)', function () {
    Sanctum::actingAs(requestManagementCreatorWith(['view', 'create']));

    $response = $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => aSourceId(),
        'product_lines' => oneProductLine(),
        'estimated_value' => 1200.25,
        'expected_close_date' => '2026-12-01',
        'success_probability' => 60,
    ])->assertCreated();

    expect($response->json('data.estimated_value'))->toBe('1200.25');

    $opportunity = Opportunity::query()->sole();
    expect($opportunity->estimated_value)->toBe('1200.25')
        ->and($opportunity->expected_close_date->format('Y-m-d'))->toBe('2026-12-01')
        ->and($opportunity->success_probability)->toBe(60);
});

it('POST out-of-rule success_probability -> 422 (AC-002)', function () {
    Sanctum::actingAs(requestManagementCreatorWith(['view', 'create']));

    $this->postJson('/api/request-management', [
        'registry_id' => Registry::factory()->create()->id,
        'source_id' => aSourceId(),
        'product_lines' => oneProductLine(),
        'success_probability' => 150,
    ])->assertStatus(422)->assertJsonValidationErrors('success_probability');
});

// ---------------------------------------------------------------------------
// Grid columns
// ---------------------------------------------------------------------------

it('grid: the three columns are hidden, display-only, sortable and filterable (AC-005)', function () {
    Sanctum::actingAs(requestManagementUserWith(['viewAny', 'update']));

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    foreach (['estimated_value' => 'number', 'success_probability' => 'number', 'expected_close_date' => 'date'] as $id => $filterType) {
        expect($columns[$id]['visible'])->toBeFalse()
            ->and($columns[$id]['sortable'])->toBeTrue()
            ->and($columns[$id]['filterType'])->toBe($filterType)
            ->and($columns[$id]['editable'] ?? false)->toBeFalse();
    }
});

it('grid: rows project the Opportunity values, sortable and filterable through the hop (AC-005)', function () {
    $actor = requestManagementUserWith(['viewAny', 'view']);
    $low = forecastQuote($actor, ['estimated_value' => 100, 'success_probability' => 10, 'expected_close_date' => '2026-11-01']);
    $high = forecastQuote($actor, ['estimated_value' => 5000, 'success_probability' => 90, 'expected_close_date' => '2027-03-01']);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->firstWhere('id', $high->id);

    expect($row['estimated_value'])->toBe('5000.00')
        ->and($row['success_probability'])->toBe(90)
        ->and($row['expected_close_date'])->toBe('2027-03-01');

    expect(forecastGridRowIds(['sortModel' => [['colId' => 'estimated_value', 'sort' => 'desc']]]))
        ->toBe([$high->id, $low->id])
        ->and(forecastGridRowIds(['sortModel' => [['colId' => 'expected_close_date', 'sort' => 'asc']]]))
        ->toBe([$low->id, $high->id])
        ->and(forecastGridRowIds([
            'filterModel' => ['success_probability' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 50]],
        ]))->toBe([$high->id])
        ->and(forecastGridRowIds([
            'filterModel' => ['expected_close_date' => ['filterType' => 'date', 'type' => 'lessThan', 'dateFrom' => '2027-01-01']],
        ]))->toBe([$low->id]);
});
