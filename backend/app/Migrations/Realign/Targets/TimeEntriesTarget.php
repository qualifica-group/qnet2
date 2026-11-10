<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\Realign\AbstractLegacyRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\OperationalLegacyValues;
use App\Migrations\Realign\Support\OperationalLookupNames;
use App\Migrations\Support\LegacyTaskLookups;
use App\Migrations\Support\LegacyTimeEntryLinks;
use App\Models\TaskType;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Aligns a segnatempo already imported (spec 0375 D-11): the owner never moves. The
 * link rule is the import's own (LegacyTimeEntryLinks), and a link the legacy sends
 * but qnet-2 cannot resolve leaves the current one.
 */
final class TimeEntriesTarget extends AbstractLegacyRealignTarget
{
    use OperationalLegacyValues;

    private const int TITLE_MAX = 191;

    private const int NOTES_MAX = 5000;

    private const int MIN_MINUTES = 1;

    private const int MAX_MINUTES = 1440;

    private const array COLUMNS = ['title', 'minutes', 'notes', 'registry_id', 'opportunity_id', 'work_order_id', 'task_id', 'work_order_stage_id'];

    /** Entry column => legacy reference: sent but unresolved keeps the current link. */
    private const array KEPT_LINKS = [
        'registry_id' => 'registry_id',
        'opportunity_id' => 'opportunity_id',
        'work_order_id' => 'work_order_id',
        'task_id' => 'task_id',
        'work_order_stage_id' => 'stage_name',
    ];

    public function __construct(
        private readonly LegacyTimeEntryLinks $links,
        private readonly LegacyTaskLookups $lookups,
        private readonly OperationalLookupNames $names,
    ) {}

    public function source(): string
    {
        return 'time-entries';
    }

    protected function modelClass(): string
    {
        return TimeEntry::class;
    }

    public function label(Model $record): string
    {
        return $this->shortDate($record->getRawOriginal('date')).' '.$record->getRawOriginal('title');
    }

    public function snapshot(Model $record): array
    {
        return [
            ...$this->columns($record, self::COLUMNS),
            'date' => $this->shortDate($record->getRawOriginal('date')),
            'start_time' => $this->shortTime($record->getRawOriginal('start_time')),
            'end_time' => $this->shortTime($record->getRawOriginal('end_time')),
            'type' => $this->names->name(TaskType::class, $record->getRawOriginal('task_type_id')),
        ];
    }

    /**
     * @param  TimeEntry  $record
     */
    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        $warnings = array_map('strval', (array) ($legacy['warnings'] ?? []));

        // Step 1: the links, imposed by the task when there is one.
        $links = $this->links->resolve($legacy, $warnings);

        // Step 2: the values the legacy decides; a duration or a type qnet-2 cannot hold fails the row.
        $record->forceFill([
            ...$this->keptLinks($links, $legacy),
            'date' => $this->blankToNull($legacy['date'] ?? null) ?? throw new RuntimeException('date is required.'),
            'title' => $this->capped($links['title'] ?? $legacy['title'] ?? null, self::TITLE_MAX, 'Title', $warnings) ?? throw new RuntimeException('Title is required.'),
            'task_type_id' => $this->taskTypeId($legacy['type_name'] ?? null),
            'start_time' => $this->legacyTime($record, 'start_time', $legacy['start_time'] ?? null),
            'end_time' => $this->legacyTime($record, 'end_time', $legacy['end_time'] ?? null),
            'minutes' => $this->minutes($legacy['minutes'] ?? null),
            'notes' => $this->capped($legacy['notes'] ?? null, self::NOTES_MAX, 'Notes', $warnings),
        ]);

        // Step 3: one quiet save, only when something differs.
        if ($record->isDirty()) {
            $this->saveWithLegacyTimestamp($record, $legacy);
            array_walk($warnings, static fn (string $warning) => $notes->warn($warning));
        }
    }

    /**
     * @param  array{title: ?string, registry_id: ?int, opportunity_id: ?int, work_order_id: ?int, task_id: ?int, work_order_stage_id: ?int}  $links
     * @param  array<string, mixed>  $legacy
     * @return array<string, int|null>
     */
    private function keptLinks(array $links, array $legacy): array
    {
        unset($links['title']);

        // A migrated task imposes every link, nulls included.
        if ($links['task_id'] !== null) {
            return $links;
        }

        foreach (self::KEPT_LINKS as $column => $legacyKey) {
            $reference = $legacy[$legacyKey] ?? null;
            $sent = $legacyKey === 'stage_name' ? $this->blankToNull($reference) !== null : $this->isSent($reference);

            if ($links[$column] === null && $sent) {
                unset($links[$column]);
            }
        }

        return $links;
    }

    /**
     * @throws RuntimeException when the type is missing or unknown in qnet-2
     */
    private function taskTypeId(mixed $typeName): int
    {
        $name = $this->blankToNull($typeName) ?? throw new RuntimeException('type_name is required.');

        return $this->lookups->typeIdByName($name)
            ?? throw new RuntimeException("Task type \"{$name}\" not found; the time entry is not aligned.");
    }

    /**
     * @throws RuntimeException when the legacy sends a duration qnet-2 cannot hold
     */
    private function minutes(mixed $minutes): int
    {
        $value = filter_var($minutes, FILTER_VALIDATE_INT);

        if ($value === false || $value < self::MIN_MINUTES || $value > self::MAX_MINUTES) {
            throw new RuntimeException('Minutes must be between '.self::MIN_MINUTES.' and '.self::MAX_MINUTES.'; the time entry is not aligned.');
        }

        return $value;
    }
}
