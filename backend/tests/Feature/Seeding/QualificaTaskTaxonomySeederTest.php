<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Support\BadgeTokens;
use Database\Seeders\QualificaCatalog\TaskTaxonomyCatalogue;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The client's Task classification vocabulary (spec 0101): four pure lookups
// plus the status pick-list, seeded as ordinary reference rows and idempotent
// on the name key. The three PROTECTED statuses are not created here — they
// come from the migrations and are only reshaped, matched by `system_key`.
uses(RefreshDatabase::class);

/**
 * @return array<int, array{0: class-string, 1: int, 2: string}>
 */
function taskTaxonomyLookups(): array
{
    return [
        [TaskType::class, 7, 'Attività'],
        [TaskCategory::class, 15, 'Tecnico (informatico)'],
        [TaskPriority::class, 5, 'Critica'],
        [TaskImportance::class, 4, 'Massima'],
    ];
}

it('seeds the four classification catalogues', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    foreach (taskTaxonomyLookups() as [$modelClass, $expectedCount, $sampleName]) {
        expect($modelClass::query()->count())->toBe($expectedCount, $modelClass)
            ->and($modelClass::query()->where('name', $sampleName)->exists())->toBeTrue($modelClass);
    }
});

it('is idempotent and does not overwrite a rename or a recolour made from the module', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    $renamed = TaskType::query()->where('name', 'Chiamata')->firstOrFail();
    $renamed->update(['name' => 'Telefonata', 'color' => 'pink']);

    test()->seed(QualificaTaskTaxonomySeeder::class);

    // AC-005: the re-run leaves the edited row alone and re-creates the
    // catalogue name it no longer occupies, without duplicating anything else.
    expect($renamed->fresh()->name)->toBe('Telefonata')
        ->and($renamed->fresh()->color)->toBe('pink')
        ->and(TaskType::query()->count())->toBe(8)
        ->and(TaskCategory::query()->count())->toBe(15)
        ->and(TaskPriority::query()->count())->toBe(5)
        ->and(TaskImportance::query()->count())->toBe(4);
});

it('seeds only badge tokens the grid can render', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    foreach (taskTaxonomyLookups() as [$modelClass]) {
        foreach ($modelClass::query()->get() as $row) {
            expect($row->color)->toBeIn(BadgeTokens::colors(), "{$modelClass}: {$row->name}")
                ->and($row->icon)->toBeIn(BadgeTokens::icons(), "{$modelClass}: {$row->name}");
        }
    }
});

it('orders every catalogue on the sequence the reorder endpoint produces', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    foreach (taskTaxonomyLookups() as [$modelClass]) {
        $orders = $modelClass::query()->orderBy('id')->pluck('sort_order')->all();

        expect($orders)->toBe(range(0, (count($orders) - 1) * 10, 10), $modelClass);
    }
});

// ---------------------------------------------------------------------------
// The status pick-list (D-5)
// ---------------------------------------------------------------------------

it('seeds the client status pick-list between the protected opening and closing rows', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    $rows = TaskStatus::query()->orderBy('sort_order')->get();

    // 8 ordinary rows from the catalogue + the 3 protected ones. The three
    // bootstrap rows that stood for a phase were retired by the migration.
    expect($rows)->toHaveCount(count(TaskTaxonomyCatalogue::STATUSES) + 3)
        ->and($rows->pluck('name')->all())->toBe([
            'Da assegnare',
            'Assegnato',
            'In preanalisi',
            'Preanalisi da validare',
            'Preanalisi validata',
            'In corso',
            'In attesa controparte',
            'Interrotto',
            'Esecuzione da validare',
            'Esecuzione validata',
            'Chiuso negativo',
        ]);
});

it('gives every status a phase, and marks as closing only the two protected ones', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    $closing = TaskStatus::query()->get()
        ->filter(fn (TaskStatus $status): bool => $status->isClosing())
        ->pluck('name')->values()->all();

    expect($closing)->toEqualCanonicalizing(['Esecuzione validata', 'Chiuso negativo']);

    // "Interrotto" suspends rather than closes (user directive 2026-09-04):
    // it is in the Pending phase, so it demands no closure feedback.
    $interrupted = TaskStatus::query()->where('name', 'Interrotto')->firstOrFail();

    expect($interrupted->group)->toBe(TaskStatusGroup::Pending)
        ->and($interrupted->isClosing())->toBeFalse();
});

it('reshapes the protected rows by system_key and leaves them protected', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    $byKey = TaskStatus::query()->whereNotNull('system_key')->get()
        ->keyBy(fn (TaskStatus $status): string => $status->system_key->value);

    expect($byKey)->toHaveCount(3)
        ->and($byKey[TaskStatusSystemKey::Open->value]->name)->toBe('Da assegnare')
        ->and($byKey[TaskStatusSystemKey::Open->value]->completion_percentage)->toBe(0)
        ->and($byKey[TaskStatusSystemKey::ClosedPositive->value]->name)->toBe('Esecuzione validata')
        ->and($byKey[TaskStatusSystemKey::ClosedPositive->value]->completion_percentage)->toBe(100);
});

it('leaves a renamed protected row alone on a re-run', function (): void {
    $open = TaskStatus::query()->where('system_key', TaskStatusSystemKey::Open->value)->firstOrFail();
    $open->update(['name' => 'Da prendere in carico']);

    test()->seed(QualificaTaskTaxonomySeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);

    // Matched by KEY, rewritten only while it still carries the bootstrap
    // name: an admin rename survives the seeder (AC-005).
    expect($open->fresh()->name)->toBe('Da prendere in carico')
        ->and(TaskStatus::query()->where('name', 'Da assegnare')->exists())->toBeFalse();
});

it('does not duplicate the statuses on a re-run', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);
    $afterFirst = TaskStatus::query()->count();

    test()->seed(QualificaTaskTaxonomySeeder::class);

    expect(TaskStatus::query()->count())->toBe($afterFirst);
});

it('seeds only badge tokens the grid can render on the statuses too', function (): void {
    test()->seed(QualificaTaskTaxonomySeeder::class);

    foreach (TaskStatus::query()->get() as $status) {
        expect($status->color)->toBeIn(BadgeTokens::colors(), $status->name)
            ->and($status->icon)->toBeIn(BadgeTokens::icons(), $status->name);
    }
});
