<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Static guarantees over the Task module's own files (AC-024, AC-073,
| AC-091, AC-092)
|--------------------------------------------------------------------------
|
| Four acceptance criteria are stated as repository greps rather than as
| behaviour, because that is the only way to catch them: a status label
| leaking into a condition, an `orderByRaw` built from input, a file past the
| hard size limit or an anticipated recurrence column are all things a
| passing feature test would happily coexist with.
|
| The scan deliberately covers the PRODUCTION files of the module only.
| Migrations and seeders are excluded from the label grep (they are where the
| six labels are legitimately written, AC-024), and this test file's own
| directory is excluded from every grep — a test naming a forbidden token in
| an assertion is not a violation.
*/

/**
 * Every production file belonging to spec 0101, backend and frontend.
 * Frontend paths are included so AC-091/AC-092 mean what they say ("no file
 * OF THE MODULE"); a directory that does not exist yet simply contributes no
 * files.
 *
 * @return array<int, string>
 */
function taskModuleProductionFiles(): array
{
    $patterns = [
        app_path('Models/Task*.php'),
        app_path('Enums/TaskStatusSystemKey.php'),
        app_path('Support/BadgeTokens.php'),
        app_path('Services/Tasks/*.php'),
        app_path('Services/Task*Service.php'),
        app_path('Policies/Task*.php'),
        app_path('Authorization/Task*.php'),
        app_path('Http/Controllers/Task*/*.php'),
        app_path('Http/Requests/Task*/*.php'),
        app_path('Http/Resources/Task*.php'),
        app_path('DataObjects/Task*/*.php'),
        app_path('DataObjects/Task*.php'),
        app_path('Tables/Task*.php'),
        app_path('Tables/Task*/*.php'),
        base_path('routes/api/tasks.php'),
        base_path('../frontend/src/features/tasks/*.ts'),
        base_path('../frontend/src/features/tasks/*.tsx'),
        base_path('../frontend/src/features/tasks/**/*.ts'),
        base_path('../frontend/src/features/tasks/**/*.tsx'),
        base_path('../frontend/src/features/task-*/*.ts'),
        base_path('../frontend/src/features/task-*/*.tsx'),
        base_path('../frontend/src/features/task-*/**/*.ts'),
        base_path('../frontend/src/features/task-*/**/*.tsx'),
    ];

    $files = [];

    foreach ($patterns as $pattern) {
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file) && ! str_contains($file, '.test.')) {
                $files[] = $file;
            }
        }
    }

    return array_values(array_unique($files));
}

/**
 * @return array<int, string>
 */
function taskModuleFilesContaining(string $pattern, bool $codeOnly = false): array
{
    $offenders = [];

    foreach (taskModuleProductionFiles() as $file) {
        $contents = file_get_contents($file);

        if ($contents === false) {
            continue;
        }

        if ($codeOnly) {
            $contents = taskModuleStripComments($contents);
        }

        if (preg_match($pattern, $contents) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file);
        }
    }

    return $offenders;
}

/**
 * Blanks out block and line comments so a grep can distinguish a label
 * WRITTEN IN CODE from a label merely NAMED IN PROSE. AC-024 forbids a
 * condition on a label; a docblock explaining which phase a system row
 * represents is documentation, not a branch, and failing the module over one
 * would be enforcing the letter of the grep against its own stated purpose.
 */
function taskModuleStripComments(string $contents): string
{
    return (string) preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $contents);
}

it('the module file scan is not vacuous: it finds the production files it is meant to police', function () {
    // A guard on the guards: if the glob set ever stops matching, every
    // assertion below would pass by finding nothing.
    expect(count(taskModuleProductionFiles()))->toBeGreaterThan(20);
});

// ---------------------------------------------------------------------------
// AC-024 — no production file conditions on a status LABEL
// ---------------------------------------------------------------------------

it('AC-024: no production file of the module mentions a status label', function () {
    // The six shipped labels, plus the two the acceptance criterion names
    // explicitly as the shape of the mistake ("Completato"/"Chiuso").
    $labels = ['Aperto', 'In corso', 'In sospeso', 'In validazione', 'Chiuso positivo', 'Chiuso negativo', 'Completato', 'Chiuso'];

    foreach ($labels as $label) {
        expect(taskModuleFilesContaining('/'.preg_quote($label, '/').'/', codeOnly: true))
            ->toBe([], "the status label '{$label}' appears in the CODE of a production file");
    }
});

it('AC-024: the application reads the phase from system_key, and the label is free to change', function () {
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();

    expect($closedPositive->isClosing())->toBeTrue();

    $closedPositive->update(['name' => 'Un nome qualunque']);

    expect($closedPositive->fresh()->isClosing())->toBeTrue()
        ->and($closedPositive->fresh()->system_key)->toBe(TaskStatusSystemKey::ClosedPositive);
});

// ---------------------------------------------------------------------------
// AC-073 — no SQL built from raw input
// ---------------------------------------------------------------------------

it('AC-073: no file of the module uses whereRaw, orderByRaw, groupByRaw, havingRaw or selectRaw', function () {
    expect(taskModuleFilesContaining('/\b(whereRaw|orderByRaw|groupByRaw|havingRaw|selectRaw)\s*\(/'))->toBe([]);
});

it('AC-073: no file of the module interpolates a variable into a DB::raw fragment', function () {
    expect(taskModuleFilesContaining('/DB::raw\s*\(\s*["\'][^"\']*\$/'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-091 / AC-092 — size, emoji, and the absence of anticipated recurrence
// ---------------------------------------------------------------------------

it('AC-091: no file of the module exceeds 500 lines', function () {
    $oversized = [];

    foreach (taskModuleProductionFiles() as $file) {
        $lines = count(file($file) ?: []);

        if ($lines > 500) {
            $oversized[str_replace(base_path().'/', '', $file)] = $lines;
        }
    }

    expect($oversized)->toBe([]);
});

it('AC-091: no file of the module contains an emoji', function () {
    // The SAME predicate .claude/hooks/code-guard.js blocks at write time
    // (\p{Extended_Pictographic}), asserted here as a standing invariant
    // rather than a one-off hook run. Written as the property and not as a
    // hand-rolled range list on purpose: the ranges would also catch the
    // arrows and technical symbols the codebase legitimately uses in prose.
    expect(taskModuleFilesContaining('/\p{Extended_Pictographic}/u'))->toBe([]);
});

it('AC-092: no production file of the module mentions recurrence (D-3)', function () {
    expect(taskModuleFilesContaining('/recurrence|recurring|ricorrenz/i'))->toBe([]);
});
