<?php

use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignReportWriter;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function realignTagTarget(): AbstractLegacyRealignTarget
{
    return new class extends AbstractLegacyRealignTarget
    {
        public function source(): string
        {
            return 'tags';
        }

        protected function modelClass(): string
        {
            return Tag::class;
        }

        public function label(Model $record): string
        {
            return (string) $record->getAttribute('name');
        }

        public function snapshot(Model $record): array
        {
            return $this->columns($record, ['name']);
        }

        public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
        {
            if (($legacy['name'] ?? null) === 'boom') {
                throw new RuntimeException('The row cannot be written.');
            }

            if (isset($legacy['note'])) {
                $notes->warn($legacy['note']);
            }

            $record->forceFill(['name' => $legacy['name']]);

            if ($record->isDirty()) {
                $record->saveQuietly();
            }
        }
    };
}

function legacyTag(int $oldId, string $name): Tag
{
    $tag = Tag::factory()->create(['name' => $name]);
    $tag->forceFill(['old_id' => $oldId])->saveQuietly();
    Activity::query()->delete();

    return $tag;
}

function runRealignOnTags(bool $apply): LegacyRealignReport
{
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(realignTagTarget(), '2026-10-23', $apply, $report);

    return $report;
}

it('reports without writing when simulating (AC-001)', function () {
    $tag = legacyTag(7, 'Before');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After']]);

    $report = runRealignOnTags(apply: false);

    expect($tag->fresh()->name)->toBe('Before')
        ->and($report->counters['tags'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and($report->changes)->toBe([['tags', '7', $tag->id, 'Before', 'name', 'Before', 'After']]);
});

it('writes the same differences the simulation reported, without activity (AC-002)', function () {
    $tag = legacyTag(7, 'Before');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After']]);

    $simulated = runRealignOnTags(apply: false)->changes;
    $applied = runRealignOnTags(apply: true)->changes;

    expect($tag->fresh()->name)->toBe('After')
        ->and($applied)->toBe($simulated)
        ->and(Activity::query()->count())->toBe(0);
});

it('finds nothing to do on a second run (AC-003)', function () {
    legacyTag(7, 'Before');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After']]);

    runRealignOnTags(apply: true);
    $second = runRealignOnTags(apply: true);

    expect($second->counters['tags'][LegacyRealignReport::UNCHANGED])->toBe(1)
        ->and($second->changes)->toBe([]);
});

it('ignores a record that is not on qnet-2 (AC-004)', function () {
    fakeLegacyRealign('tags', [99], [], [['id' => 99, 'name' => 'New']]);

    $report = runRealignOnTags(apply: true);

    expect($report->counters['tags'][LegacyRealignReport::NOT_ON_QNET2])->toBe(1)
        ->and(Tag::query()->count())->toBe(0);
});

it('leaves a record changed on qnet-2 and lists the difference (AC-005)', function () {
    $tag = legacyTag(7, 'Before');
    $user = User::factory()->create(['name' => 'Ada Rossi']);
    activity()->performedOn($tag)->causedBy($user)->event('updated')->log('updated');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After']]);

    $report = runRealignOnTags(apply: true);

    expect($tag->fresh()->name)->toBe('Before')
        ->and($report->counters['tags'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1)
        ->and($report->touched[0][5])->toBe('Ada Rossi')
        ->and(array_slice($report->touched[0], 6))->toBe(['name', 'Before', 'After']);
});

it('does not count the import created entry as a change (AC-006)', function () {
    $tag = legacyTag(7, 'Before');
    activity()->performedOn($tag)->event('created')->log('created');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After']]);

    $report = runRealignOnTags(apply: true);

    expect($tag->fresh()->name)->toBe('After')
        ->and($report->counters['tags'][LegacyRealignReport::ALIGNED])->toBe(1);
});

it('keeps a record deleted in the legacy and lists it (AC-007)', function () {
    $tag = legacyTag(7, 'Kept');
    fakeLegacyRealign('tags', [], [7]);

    $report = runRealignOnTags(apply: true);

    expect($tag->fresh())->not->toBeNull()
        ->and($report->counters['tags'][LegacyRealignReport::DELETED_IN_LEGACY])->toBe(1)
        ->and($report->deleted[0][3])->toBe('Kept');
});

it('fails one row and keeps going, with nothing partial (AC-019)', function () {
    $bad = legacyTag(7, 'Bad');
    $good = legacyTag(8, 'Good');
    fakeLegacyRealign('tags', [7, 8], [], [['id' => 7, 'name' => 'boom'], ['id' => 8, 'name' => 'Better']]);

    $report = runRealignOnTags(apply: true);

    expect($bad->fresh()->name)->toBe('Bad')
        ->and($good->fresh()->name)->toBe('Better')
        ->and($report->counters['tags'][LegacyRealignReport::FAILED])->toBe(1)
        ->and($report->messages[0])->toBe(['tags', '7', 'error', 'The row cannot be written.']);
});

it('reports a legacy orphan as an error without writing', function () {
    $tag = legacyTag(7, 'Before');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'orphan_reason' => 'Utente inesistente']]);

    $report = runRealignOnTags(apply: true);

    expect($tag->fresh()->name)->toBe('Before')
        ->and($report->messages[0][3])->toBe('Utente inesistente');
});

it('writes the five sheets of the report', function () {
    $tag = legacyTag(7, 'Before');
    fakeLegacyRealign('tags', [7], [], [['id' => 7, 'name' => 'After', 'note' => 'Stage kept.']]);
    $report = runRealignOnTags(apply: false);
    $path = legacyRealignOutputPath();

    app(LegacyRealignReportWriter::class)->write($report, $path, applied: false);
    $sheets = readLegacyRealignReport($path);
    unlink($path);

    expect(array_keys($sheets))->toBe(['Riepilogo', 'Modifiche', 'Modificati su qnet-2', 'Cancellati nel legacy', 'Avvisi ed errori'])
        ->and($sheets['Modifiche'][1])->toBe(['tags', '7', $tag->id, 'Before', 'name', 'Before', 'After'])
        ->and($sheets['Avvisi ed errori'][1])->toBe(['tags', '7', 'warning', 'Stage kept.']);
});

it('refuses bad options with exit 1 (AC-020)', function (array $options) {
    $this->artisan('migrations:realign-legacy', $options)->assertExitCode(1);
})->with([
    'no since' => [[]],
    'bad date' => [['--since' => '23/10/2026']],
    'unknown source' => [['--since' => '2026-10-23', '--sources' => 'nope']],
]);
