<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CustomFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0180 (B1) AC-014: `table` column in the generic grid, sort/filter on summary.
uses(RefreshDatabase::class);

function tableGridActor(): User
{
    foreach (['viewAny', 'view'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }

    return User::factory()->create()->givePermissionTo(['companies.viewAny', 'companies.view']);
}

function companyWithInspections(string $name, string $summary): void
{
    $company = Company::factory()->create(['denomination' => $name]);
    CustomFieldValue::factory()->forEntity('companies', $company->id)->create(['values' => ['inspections' => [
        'rows' => [['id' => (string) Str::uuid(), 'inspection_date' => $summary, 'active' => true]],
        'summary' => $summary,
    ]]]);
}

it('AC-014: exposes the table column, sorts and filters on the summary, and returns no set values', function (): void {
    tableCustomField();
    companyWithInspections('Alpha', '2026-03-01');
    companyWithInspections('Beta', '2026-01-01');
    companyWithInspections('Gamma', '2026-02-01');
    Sanctum::actingAs(tableGridActor());

    $column = collect($this->getJson('/api/tables/companies/columns')->assertOk()->json('data.columns'))->keyBy('id')['custom.inspections'];
    expect($column['type'])->toBe('table')
        ->and($column['filterType'])->toBe('text')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue();

    foreach (['asc' => ['Beta', 'Gamma', 'Alpha'], 'desc' => ['Alpha', 'Gamma', 'Beta']] as $direction => $expected) {
        $names = $this->postJson('/api/tables/companies/rows', [
            'startRow' => 0, 'endRow' => 25,
            'sortModel' => [['colId' => 'custom.inspections', 'sort' => $direction]],
        ])->assertOk()->json('items.*.denomination');
        expect($names)->toBe($expected);
    }

    $filtered = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['custom.inspections' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '2026-02']],
    ])->assertOk()->json('items.*.denomination');
    expect($filtered)->toBe(['Gamma']);

    $row = $this->postJson('/api/tables/companies/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');
    expect($row['custom.inspections'])->toHaveKeys(['rows', 'summary']);

    expect($this->postJson('/api/tables/companies/values', ['columnId' => 'custom.inspections'])->assertOk()->json('data.values'))->toBe([]);
});

it('AC-025: the table column carries the definition config, other columns do not', function (): void {
    tableCustomField();
    Sanctum::actingAs(tableGridActor());

    $columns = collect($this->getJson('/api/tables/companies/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['custom.inspections']['table'])->toBe(inspectionTableConfig())
        ->and($columns['denomination'] ?? $columns->first(fn (array $column): bool => $column['type'] === 'text'))->not->toHaveKey('table');
});
