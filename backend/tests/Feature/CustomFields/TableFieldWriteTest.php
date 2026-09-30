<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldRequestBag;
use App\CustomFields\CustomFieldValidator;
use App\Models\Company;
use App\Models\CustomFieldValue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

// spec 0180 (B1): `table` custom field through the type-agnostic write
// pipeline (bag -> validator -> writer), model level like the 0021 suite.
uses(RefreshDatabase::class);

function saveTable(Company $company, mixed $value): void
{
    app(CustomFieldRequestBag::class)->set(['inspections' => $value]);
    $company->save();
}

/**
 * @return array<string, mixed>
 */
function storedTable(): array
{
    return CustomFieldValue::first()->values['inspections'];
}

/**
 * @return array<int, string>
 */
function tableErrorKeys(Closure $action): array
{
    try {
        $action();
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('AC-005: persists rows with uuid ids and normalized cells, and reads them back', function (): void {
    tableCustomField();
    $company = Company::factory()->create();

    saveTable($company, ['rows' => [
        ['inspection_date' => '2026-10-12', 'inspector' => ' Rossi ', 'findings' => '2', 'alert_sent' => true],
        ['inspection_date' => '2026-11-12', 'active' => true],
    ]]);

    $stored = storedTable();
    expect($stored['rows'])->toHaveCount(2)
        ->and(Str::isUuid($stored['rows'][0]['id']))->toBeTrue()
        ->and($stored['rows'][0]['id'])->not->toBe($stored['rows'][1]['id'])
        ->and($stored['rows'][0]['findings'])->toBe(2)
        ->and($stored['summary'])->toBe('2026-11-12')
        ->and($company->fresh()->custom_fields['inspections'])->toEqual($stored);
});

it('AC-006: re-saving keeps existing ids and drops the omitted row', function (): void {
    tableCustomField();
    $company = Company::factory()->create();
    saveTable($company, ['rows' => [['inspection_date' => '2026-01-01'], ['inspection_date' => '2026-02-01']]]);
    [$first, $second] = storedTable()['rows'];

    saveTable($company, ['rows' => [[...$first, 'inspector' => 'Bianchi']]]);

    $rows = storedTable()['rows'];
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe($first['id'])
        ->and($rows[0]['inspector'])->toBe('Bianchi')
        ->and(collect($rows)->pluck('id'))->not->toContain($second['id']);
});

it('AC-007: a violating cell fails on custom_fields.<key>.rows.N.<col> and persists nothing', function (array $row, string $errorKey): void {
    tableCustomField();
    $company = Company::factory()->create();
    $valid = ['inspection_date' => '2026-01-01'];

    $keys = tableErrorKeys(fn () => saveTable($company, ['rows' => [$valid, $row]]));

    expect($keys)->toContain($errorKey)
        ->and(CustomFieldValue::count())->toBe(0);
})->with([
    'required missing' => [['inspector' => 'x'], 'custom_fields.inspections.rows.1.inspection_date'],
    'bad date' => [['inspection_date' => 'x'], 'custom_fields.inspections.rows.1.inspection_date'],
    'enum outside options' => [['inspection_date' => '2026-01-01', 'site' => 'moon'], 'custom_fields.inspections.rows.1.site'],
    'integer out of range' => [['inspection_date' => '2026-01-01', 'findings' => 99], 'custom_fields.inspections.rows.1.findings'],
]);

it('AC-008: two selected rows fail on .rows; one is persisted only inside its own record', function (): void {
    tableCustomField();
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    saveTable($other, ['rows' => [['inspection_date' => '2026-05-05', 'active' => true]]]);

    $keys = tableErrorKeys(fn () => saveTable($company, ['rows' => [
        ['inspection_date' => '2026-01-01', 'active' => true], ['inspection_date' => '2026-02-01', 'active' => true],
    ]]));
    expect($keys)->toContain('custom_fields.inspections.rows');

    saveTable($company, ['rows' => [['inspection_date' => '2026-01-01'], ['inspection_date' => '2026-02-01', 'active' => true]]]);

    $rowsByEntity = CustomFieldValue::all()->keyBy('entity_id')->map(fn ($v) => collect($v->values['inspections']['rows'])->pluck('active')->all());
    expect($rowsByEntity[$company->id])->toBe([false, true])
        ->and($rowsByEntity[$other->id])->toBe([true]);
});

it('AC-010: required and row limits fail on the field or .rows', function (array $overrides, array $config, int $count, string $errorKey): void {
    tableCustomField($config, $overrides);
    $company = Company::factory()->create();

    $keys = tableErrorKeys(fn () => saveTable($company, ['rows' => array_fill(0, $count, ['inspection_date' => '2026-01-01'])]));

    expect($keys)->toContain($errorKey);
})->with([
    'required, zero rows' => [['validation' => ['required' => true]], [], 0, 'custom_fields.inspections.rows'],
    'min 2 with one' => [[], ['min_rows' => 2], 1, 'custom_fields.inspections.rows'],
    'max 3 with four' => [[], ['max_rows' => 3], 4, 'custom_fields.inspections.rows'],
]);

it('AC-010: a required table not resubmitted on update does not fail; on create it does', function (): void {
    tableCustomField([], ['validation' => ['required' => true]]);
    $company = Company::factory()->create();

    expect(tableErrorKeys(fn () => app(CustomFieldValidator::class)->validate(new Company, [], null)))->toBe([]);

    app(CustomFieldRequestBag::class)->set(['inspections' => null]);
    expect(tableErrorKeys(fn () => $company->save()))->toContain('custom_fields.inspections');
    app(CustomFieldRequestBag::class)->pull();

    $company->save();
    expect(CustomFieldValue::count())->toBe(0);
});

function readonlyTableActor(): User
{
    $role = Role::create(['name' => 'table-readonly']);
    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }
    $role->givePermissionTo(['companies.viewAny', 'companies.update']);
    $role->fieldPermissions()->create(['resource' => 'companies', 'field' => 'custom.inspections', 'visible' => true, 'editable' => false, 'required' => false]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

it('AC-011: a non-editable table re-sent as read passes; a changed cell is rejected', function (): void {
    tableCustomField();
    $company = Company::factory()->create();
    saveTable($company, ['rows' => [['inspection_date' => '2026-01-01', 'findings' => 2, 'active' => true]]]);
    $read = $company->fresh()->custom_fields['inspections'];
    $actor = readonlyTableActor();
    $validator = app(CustomFieldValidator::class);

    $validator->validate($company, ['inspections' => $read], $actor);

    $read['rows'][0]['findings'] = 3;
    expect(tableErrorKeys(fn () => $validator->validate($company, ['inspections' => $read], $actor)))->toBe(['custom_fields.inspections']);
});

it('AC-013: a column removed after saving disappears from the read and does not break it', function (): void {
    $definition = tableCustomField();
    $company = Company::factory()->create();
    saveTable($company, ['rows' => [['inspection_date' => '2026-01-01', 'inspector' => 'Rossi']]]);

    $config = $definition->config;
    $config['columns'] = array_values(array_filter($config['columns'], fn (array $c): bool => $c['key'] !== 'inspector'));
    $definition->update(['config' => $config]);
    app(CustomFieldProvider::class)->forget('companies');

    $row = Company::find($company->id)->custom_fields['inspections']['rows'][0];
    expect($row)->not->toHaveKey('inspector')->and($row['inspection_date'])->toBe('2026-01-01');
});
