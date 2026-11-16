<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataObjects\Tasks\BulkTaskData;
use App\Exceptions\Tasks\TaskBulkIncompatibleException;
use App\Http\Requests\TimeEntries\BulkTimeEntrySharesRules;
use App\Http\Requests\TimeEntries\TimeEntryValidationRules;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskBulkService;
use App\Services\Tasks\TaskBulkTimeEntrySplitter;
use App\Services\Tasks\TaskDefaultLookupResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Closes a list of Tasks in one go with a TOTAL time spent, split evenly
 * across them: the CLI twin of the bulk "Completa" of the tasks list
 * (`POST /api/tasks/bulk`, spec 0156 D-6 + spec 0338). It goes through the
 * SAME TaskBulkService, so it is all-or-nothing, re-asserts every per-task
 * ability for the acting user and logs the time entries exactly as the UI does.
 *
 * Example: `php artisan tasks:bulk-complete 1,2,3,4 120 --user=mario@example.com`
 */
class BulkCompleteTasks extends Command
{
    private const string BASE_PERMISSION = 'tasks.complete';

    private const string DATE_FORMAT = 'Y-m-d';

    private const string START_TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    protected $signature = 'tasks:bulk-complete
        {ids : task ids separated by commas, e.g. 1,2,3,4}
        {minutes : total time spent in minutes, split evenly across the tasks}
        {--user= : id or email of the user who closes the tasks}
        {--date= : date of the time entry (Y-m-d), today when omitted}
        {--start= : start time of the time entry (H:i); each task gets the next consecutive slot}
        {--type= : task type id of the time entry, the default task type when omitted}
        {--notes= : notes of the time entry}
        {--feedback= : closure feedback}
        {--force : close without asking for confirmation}';

    protected $description = 'Close several tasks at once, splitting a total time spent across them';

    public function __construct(
        private readonly TaskBulkService $bulkService,
        private readonly TaskBulkTimeEntrySplitter $splitter,
        private readonly TaskDefaultLookupResolver $defaults,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            // Step 1: the acting user, with the base permission the bulk endpoint checks.
            $actor = $this->resolveActor();

            // Step 2: the bulk payload, validated with the endpoint's own rules.
            $validated = $this->validatedPayload($this->parseTaskIds((string) $this->argument('ids')));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        // Step 3: preview and confirmation.
        $this->renderPreview($validated);

        if (! $this->option('force') && ! $this->confirm('Close these tasks?', true)) {
            $this->warn('Nothing changed.');

            return self::FAILURE;
        }

        // Step 4: all-or-nothing close through the bulk service.
        return $this->closeTasks(BulkTaskData::fromValidated($validated), $actor);
    }

    private function resolveActor(): User
    {
        $identifier = trim((string) $this->option('user'));

        if ($identifier === '') {
            throw new InvalidArgumentException('The --user option is required (id or email).');
        }

        $actor = ctype_digit($identifier)
            ? User::query()->find((int) $identifier)
            : User::query()->where('email', $identifier)->first();

        if ($actor === null) {
            throw new InvalidArgumentException("User \"{$identifier}\" not found.");
        }

        if (Gate::forUser($actor)->denies(self::BASE_PERMISSION)) {
            throw new InvalidArgumentException("User #{$actor->id} lacks the ".self::BASE_PERMISSION.' permission.');
        }

        return $actor;
    }

    /**
     * @return array<int, int>
     */
    private function parseTaskIds(string $raw): array
    {
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $raw)), fn (string $part): bool => $part !== ''));

        foreach ($parts as $part) {
            if (! ctype_digit($part) || (int) $part === 0) {
                throw new InvalidArgumentException("Invalid task id \"{$part}\": use a comma-separated list such as 1,2,3,4.");
            }
        }

        if ($parts === []) {
            throw new InvalidArgumentException('No task id given.');
        }

        return array_values(array_unique(array_map(intval(...), $parts)));
    }

    /**
     * @param  array<int, int>  $taskIds
     * @return array<string, mixed>
     */
    private function buildPayload(array $taskIds): array
    {
        $minutes = $this->totalMinutes(count($taskIds));
        $startTime = $this->option('start');

        $timeEntry = [
            'date' => $this->option('date') ?? CarbonImmutable::now(config('app.timezone'))->format(self::DATE_FORMAT),
            'task_type_id' => $this->option('type') ?? $this->defaults->taskTypeId(),
            'minutes' => $minutes,
            'start_time' => $startTime,
            'end_time' => $startTime === null ? null : $this->endTime((string) $startTime, $minutes),
            'notes' => $this->option('notes'),
        ];

        $payload = [
            'action' => 'complete',
            'task_ids' => $taskIds,
            'time_entry' => $timeEntry,
            BulkTimeEntrySharesRules::FIELD => $this->splitter->evenShares($minutes, $taskIds),
        ];

        if ($this->option('feedback') !== null) {
            $payload['closure_feedback'] = $this->option('feedback');
        }

        return $payload;
    }

    private function totalMinutes(int $taskCount): int
    {
        $raw = (string) $this->argument('minutes');

        if (! ctype_digit($raw) || (int) $raw < $taskCount) {
            throw new InvalidArgumentException("The total minutes must be a whole number of at least {$taskCount} (one minute per task).");
        }

        return (int) $raw;
    }

    /** A malformed start time is left to the time entry rules; only a valid one gets an end. */
    private function endTime(string $startTime, int $minutes): ?string
    {
        if (preg_match(self::START_TIME_PATTERN, $startTime) !== 1) {
            return null;
        }

        $end = BulkTimeEntrySharesRules::toMinutes($startTime) + $minutes;

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    /**
     * The endpoint's own rule sources (BulkTaskRequest), so the CLI cannot
     * accept a time entry the UI would refuse.
     *
     * @param  array<int, int>  $taskIds
     * @return array<string, mixed>
     */
    private function validatedPayload(array $taskIds): array
    {
        $payload = $this->buildPayload($taskIds);

        $rules = array_merge(
            ['closure_feedback' => ['sometimes', 'nullable', 'string']],
            TimeEntryValidationRules::rules('time_entry'),
            BulkTimeEntrySharesRules::rules($payload, true),
        );

        $validator = Validator::make($payload, $rules)
            ->after(fn (ValidatorContract $validator) => BulkTimeEntrySharesRules::validateConsistency($validator, $payload));

        if ($validator->fails()) {
            throw new InvalidArgumentException(implode(PHP_EOL, $validator->errors()->all()));
        }

        return $validator->validated() + ['action' => $payload['action'], 'task_ids' => $taskIds];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderPreview(array $payload): void
    {
        $titles = Task::query()->whereKey($payload['task_ids'])->pluck('title', 'id');
        $timeEntry = $payload['time_entry'];

        $this->table(['Task', 'Title', 'Minutes'], array_map(
            fn (array $share): array => [$share['task_id'], $titles[$share['task_id']] ?? '(not found)', $share['minutes']],
            $payload[BulkTimeEntrySharesRules::FIELD],
        ));

        $this->line("Total: {$timeEntry['minutes']} min on {$timeEntry['date']}, task type #{$timeEntry['task_type_id']}.");
    }

    private function closeTasks(BulkTaskData $data, User $actor): int
    {
        try {
            $closed = $this->bulkService->execute($data, $actor);
        } catch (TaskBulkIncompatibleException $exception) {
            $this->error('No task closed: some tasks cannot be completed.');
            $this->table(['Task', 'Reason'], array_map(
                fn (array $row): array => [$row['id'], $row['reason']],
                $exception->incompatibleTasks,
            ));

            return self::FAILURE;
        }

        $this->info("{$closed} task(s) closed.");

        return self::SUCCESS;
    }
}
