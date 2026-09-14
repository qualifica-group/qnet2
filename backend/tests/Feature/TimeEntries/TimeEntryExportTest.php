<?php

use App\Models\EmploymentProfile;
use App\Models\Registry;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/time-entries/exports/{filtered,monthly} (spec 0122, MT-B5,
| AC-024, AC-025)
|--------------------------------------------------------------------------
*/

if (! function_exists('timeEntryActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function timeEntryActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

function readXlsxFromResponse(TestResponse $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'time-entry-export-test-').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $spreadsheet = IOFactory::load($path);
    unlink($path);

    return $spreadsheet;
}

/**
 * Reads column A top-to-bottom and returns the row number of the first
 * exact match, so section-content assertions do not hardcode row numbers.
 */
function findRowByLabel(Worksheet $worksheet, string $label): int
{
    for ($row = 1; $row <= $worksheet->getHighestRow(); $row++) {
        if ($worksheet->getCell([1, $row])->getValue() === $label) {
            return $row;
        }
    }

    throw new RuntimeException("Row with label \"{$label}\" not found");
}

/**
 * @return list<list<mixed>>
 */
function readRows(Worksheet $worksheet, int $fromRow, int $toRow, int $columns): array
{
    $rows = [];
    for ($row = $fromRow; $row <= $toRow; $row++) {
        $rows[] = array_map(fn (int $col) => $worksheet->getCell([$col, $row])->getValue(), range(1, $columns));
    }

    return $rows;
}

// ---------------------------------------------------------------------------
// AC-024 — authorization: export permission, rule R
// ---------------------------------------------------------------------------

it('AC-024: without time-entries.export, GET exports/filtered is 403', function () {
    $actor = timeEntryActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries/exports/filtered')->assertForbidden();
});

it('AC-024: the filtered export enforces rule R — a stranger user_id is 403', function () {
    $actor = timeEntryActorWith(['viewAny', 'export']);
    $stranger = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/time-entries/exports/filtered?user_id={$stranger->id}")->assertForbidden();
});

it('AC-024: with the permission, GET exports/filtered is 200 xlsx with the expected filename', function () {
    $actor = timeEntryActorWith(['viewAny', 'export']);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create();
    Sanctum::actingAs($actor);

    $response = $this->get('/api/time-entries/exports/filtered?date_from=2026-09-14&date_to=2026-09-14')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('spreadsheetml.sheet')
        ->and($response->headers->get('Content-Disposition'))->toContain('segnatempo_2026-09-14_2026-09-14.xlsx');
});

// ---------------------------------------------------------------------------
// AC-024 — content: header, the three sections, sums/percentages
// ---------------------------------------------------------------------------

it('AC-024: the Segnatempo sheet has the header, the tipo/commessa sections and one panoramica row per matching segnatempo', function () {
    $actor = timeEntryActorWith(['viewAny', 'export']);
    $typeA = TaskType::factory()->create(['name' => 'Sviluppo']);
    $typeB = TaskType::factory()->create(['name' => 'Riunione']);
    $typeExcluded = TaskType::factory()->create(['name' => 'Escluso']);
    $registry = Registry::factory()->create(['name' => 'Acme Srl']);
    $workOrder = WorkOrder::factory()->create(['title' => 'Manutenzione']);
    $workOrder->code = 'COM-0099';
    $workOrder->save();

    $entryA = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create([
        'task_type_id' => $typeA->id,
        'title' => 'Sviluppo modulo',
        'minutes' => 40,
        'registry_id' => $registry->id,
        'work_order_id' => $workOrder->id,
        'notes' => 'nota A',
    ]);
    $entryB = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create([
        'task_type_id' => $typeB->id,
        'title' => 'Call cliente',
        'minutes' => 20,
    ]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')->create([
        'task_type_id' => $typeExcluded->id,
        'minutes' => 100,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->get(
        "/api/time-entries/exports/filtered?date_from=2026-09-14&date_to=2026-09-14&task_type_ids[]={$typeA->id}&task_type_ids[]={$typeB->id}"
    )->assertOk();

    $sheet = readXlsxFromResponse($response)->getActiveSheet();

    expect($sheet->getTitle())->toBe('Segnatempo')
        ->and($sheet->getCell('A1')->getValue())->toBe('Rapporto segnatempo')
        ->and($sheet->getCell('A2')->getValue())->toBe($actor->name)
        ->and($sheet->getCell('A3')->getValue())->toBe('Lunedì 14/09/2026');

    // "Carico di lavoro per tipo": 2 rows desc by minutes, then the total row.
    $typeHeaderRow = findRowByLabel($sheet, 'Carico di lavoro per tipo') + 1;
    $totalRow = findRowByLabel($sheet, 'Tot. hh:mm');
    expect(readRows($sheet, $typeHeaderRow + 1, $totalRow - 1, 3))->toBe([
        ['Sviluppo', '00:40', '66.67%'],
        ['Riunione', '00:20', '33.33%'],
    ])->and($sheet->getCell([2, $totalRow])->getValue())->toBe('01:00');

    // "Carico di lavoro per commessa": only entryA (the only one with a work order).
    $workOrderHeaderRow = findRowByLabel($sheet, 'Carico di lavoro per commessa') + 1;
    $overviewSectionRow = findRowByLabel($sheet, 'Panoramica dei segnatempo');
    expect(readRows($sheet, $workOrderHeaderRow + 1, $overviewSectionRow - 2, 4))->toBe([
        ['Acme Srl', 'COM-0099 - Manutenzione', '00:40', '66.67%'],
    ]);

    // "Panoramica dei segnatempo": entryA then entryB (both null start_time, id asc).
    $overviewHeaderRow = $overviewSectionRow + 1;
    expect(readRows($sheet, $overviewHeaderRow + 1, $sheet->getHighestRow(), 11))->toBe([
        ['14/09/2026', '-', '-', '00:40', 'Sviluppo', 'Sviluppo modulo', 'Acme Srl', '-', 'COM-0099 - Manutenzione', '-', 'nota A'],
        ['14/09/2026', '-', '-', '00:20', 'Riunione', 'Call cliente', '-', '-', '-', '-', '-'],
    ]);

    expect($entryA->id)->toBeLessThan($entryB->id); // sanity: id-asc tie-break assumption holds.
});

it('AC-024: entry-level AND day-level filters (task_type_ids, registry_ids, daily_statuses, is_active) all narrow the panoramica rows together', function () {
    $actor = timeEntryActorWith(['viewAny', 'export']);
    EmploymentProfile::factory()->create(['user_id' => $actor->id, 'standard_daily_minutes' => 60, 'break_daily_minutes' => 0]);
    $matchingType = TaskType::factory()->create();
    $otherType = TaskType::factory()->create();
    $registry = Registry::factory()->create();

    // Day 1 (Monday, over_target once entry-level filtered): kept entry + one excluded by task_type.
    $kept = TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $matchingType->id, 'registry_id' => $registry->id, 'minutes' => 100]);
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-14')
        ->create(['task_type_id' => $otherType->id, 'registry_id' => $registry->id, 'minutes' => 50]);

    // Day 2 (Tuesday, under_target): excluded by daily_statuses even though it matches the entry filters.
    TimeEntry::factory()->forUser($actor)->onDate('2026-09-15')
        ->create(['task_type_id' => $matchingType->id, 'registry_id' => $registry->id, 'minutes' => 30]);

    // Day 3 (Wednesday): no entries at all, excluded by is_active.
    Sanctum::actingAs($actor);

    $response = $this->get(
        '/api/time-entries/exports/filtered?date_from=2026-09-14&date_to=2026-09-16'
        ."&task_type_ids[]={$matchingType->id}&registry_ids[]={$registry->id}"
        .'&daily_statuses[]=over_target&is_active=true'
    )->assertOk();

    $sheet = readXlsxFromResponse($response)->getActiveSheet();
    $overviewHeaderRow = findRowByLabel($sheet, 'Panoramica dei segnatempo') + 1;
    $rows = readRows($sheet, $overviewHeaderRow + 1, $sheet->getHighestRow(), 1);

    expect($rows)->toBe([['14/09/2026']]);
    expect($kept->date->format('Y-m-d'))->toBe('2026-09-14');
});

it('AC-024: with no matching segnatempo, every section shows a "-" placeholder row', function () {
    $actor = timeEntryActorWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $response = $this->get('/api/time-entries/exports/filtered?date_from=2026-09-14&date_to=2026-09-14')->assertOk();
    $sheet = readXlsxFromResponse($response)->getActiveSheet();

    $typeHeaderRow = findRowByLabel($sheet, 'Carico di lavoro per tipo') + 1;
    expect(readRows($sheet, $typeHeaderRow + 1, $typeHeaderRow + 1, 3))->toBe([['-', '-', '-']]);

    $workOrderHeaderRow = findRowByLabel($sheet, 'Carico di lavoro per commessa') + 1;
    expect(readRows($sheet, $workOrderHeaderRow + 1, $workOrderHeaderRow + 1, 4))->toBe([['-', '-', '-', '-']]);

    $overviewHeaderRow = findRowByLabel($sheet, 'Panoramica dei segnatempo') + 1;
    expect(readRows($sheet, $overviewHeaderRow + 1, $sheet->getHighestRow(), 11))
        ->toBe([array_fill(0, 11, '-')]);
});

// ---------------------------------------------------------------------------
// AC-025 — authorization, future month
// ---------------------------------------------------------------------------

it('AC-025: without time-entries.exportMonthly, GET exports/monthly is 403', function () {
    $actor = timeEntryActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/time-entries/exports/monthly?month=9&year=2026')->assertForbidden();
});

it('AC-025: a future month is 422 on month', function () {
    $actor = timeEntryActorWith(['viewAny', 'exportMonthly']);
    Sanctum::actingAs($actor);
    $future = CarbonImmutable::now('Europe/Rome')->addYear();

    $this->getJson("/api/time-entries/exports/monthly?month={$future->month}&year={$future->year}")
        ->assertStatus(422)->assertJsonValidationErrors('month');
});

// ---------------------------------------------------------------------------
// AC-025 — content: one sheet per user, TOTALE ORE, user_ids, Nessun dato
// ---------------------------------------------------------------------------

it('AC-025: two users and no user_ids produce one sheet each with the correct TOTALE ORE row', function () {
    $actor = timeEntryActorWith(['viewAny', 'exportMonthly']);
    $userA = User::factory()->create(['name' => 'Anna Bianchi']);
    $userB = User::factory()->create(['name' => 'Marco Verdi']);
    TimeEntry::factory()->forUser($userA)->onDate('2026-09-05')->create(['minutes' => 90]);
    TimeEntry::factory()->forUser($userA)->onDate('2026-09-06')->create(['minutes' => 30]);
    TimeEntry::factory()->forUser($userB)->onDate('2026-09-10')->create(['minutes' => 45]);
    Sanctum::actingAs($actor);

    $response = $this->get('/api/time-entries/exports/monthly?month=9&year=2026')->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('report_segnatempo_202609.xlsx');

    $spreadsheet = readXlsxFromResponse($response);
    expect($spreadsheet->getSheetCount())->toBe(2);

    $sheetA = $spreadsheet->getSheetByNameOrThrow('Anna Bianchi');
    $totalRowA = findRowByLabel($sheetA, 'TOTALE ORE Settembre 2026');
    expect($sheetA->getCell([7, $totalRowA])->getValue())->toBe('2h 00min');

    $sheetB = $spreadsheet->getSheetByNameOrThrow('Marco Verdi');
    $totalRowB = findRowByLabel($sheetB, 'TOTALE ORE Settembre 2026');
    expect($sheetB->getCell([7, $totalRowB])->getValue())->toBe('0h 45min');
});

it('AC-025: user_ids narrows the report to the given users only', function () {
    $actor = timeEntryActorWith(['viewAny', 'exportMonthly']);
    $userA = User::factory()->create(['name' => 'Anna Bianchi']);
    $userB = User::factory()->create(['name' => 'Marco Verdi']);
    TimeEntry::factory()->forUser($userA)->onDate('2026-09-05')->create();
    TimeEntry::factory()->forUser($userB)->onDate('2026-09-06')->create();
    Sanctum::actingAs($actor);

    $response = $this->get("/api/time-entries/exports/monthly?month=9&year=2026&user_ids[]={$userA->id}")->assertOk();
    $spreadsheet = readXlsxFromResponse($response);

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($spreadsheet->getActiveSheet()->getTitle())->toBe('Anna Bianchi');
});

it('AC-025: a month with no time entries produces a single "Nessun dato" sheet', function () {
    $actor = timeEntryActorWith(['viewAny', 'exportMonthly']);
    Sanctum::actingAs($actor);

    $response = $this->get('/api/time-entries/exports/monthly?month=9&year=2026')->assertOk();
    $spreadsheet = readXlsxFromResponse($response);
    $sheet = $spreadsheet->getActiveSheet();

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($sheet->getTitle())->toBe('Nessun dato')
        ->and($sheet->getCell('A1')->getValue())->toBe('Nessuna attività trovata per Settembre 2026');
});

it('gives duplicate-named users unique monthly sheet titles', function () {
    $actor = timeEntryActorWith(['viewAny', 'exportMonthly']);
    $userA = User::factory()->create(['name' => 'Mario Rossi']);
    $userB = User::factory()->create(['name' => 'Mario Rossi']);
    TimeEntry::factory()->forUser($userA)->onDate('2026-09-05')->create();
    TimeEntry::factory()->forUser($userB)->onDate('2026-09-06')->create();
    Sanctum::actingAs($actor);

    $response = $this->get('/api/time-entries/exports/monthly?month=9&year=2026')->assertOk();
    $spreadsheet = readXlsxFromResponse($response);

    $sheetNames = array_map(fn ($sheet) => $sheet->getTitle(), $spreadsheet->getAllSheets());
    expect($sheetNames)->toBe(['Mario Rossi', 'Mario Rossi (2)']);
});
