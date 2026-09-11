<?php

namespace Database\Seeders;

use App\DataObjects\Notes\CreateNoteData;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Services\Notes\NoteService;
use Database\Seeders\DemoCatalog\DemoTaskCatalogue;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;

/**
 * Development seed for the Task collaborative thread (spec 0117): root notes
 * and their replies on part of the Tasks DemoTaskSeeder produced, written
 * through NoteService::create() — the same path POST /api/notes uses — so the
 * flat-thread normalisation (spec 0052 D-7) and the per-record read gate
 * (App\Services\Tasks\TaskNotable) both hold on every seeded row.
 *
 * WHO writes: only a Task MEMBER (creatore, richiedente, assegnatario,
 * osservatore) who also holds `tasks.view` — exactly the two conditions
 * TaskNotable::authorizeRead() ANDs, re-checked here rather than discovered as
 * a 403 mid-seed. A Task whose members all lack the resource permission simply
 * gets no thread: the demo roles (DemoRolesSeeder) grant `tasks.view` to some
 * roles and not others, and that is the dataset telling the truth about its own
 * permission matrix.
 *
 * NO @mention is seeded: NoteMentionNotification is a queued notification with
 * a `mail` channel, and on the default `QUEUE_CONNECTION=sync` a seed would
 * then depend on a reachable SMTP server. `note_mentions` therefore stays empty
 * — a deliberate gap, not an oversight.
 *
 * Idempotent: force-deletes the existing threads of the Tasks it seeds before
 * writing (a soft-deleted note would otherwise pile up invisibly on a re-run).
 */
class DemoTaskNoteSeeder extends Seeder
{
    /** Every Nth Task gets a thread, so the demo also shows Tasks with none. */
    private const int TASK_STRIDE = 2;

    private const int MAX_ROOT_NOTES = 3;

    private const int MAX_REPLIES = 2;

    /** The `notable_types` slug of config/notes.php — the authorization vocabulary. */
    private const string ENTITY_TYPE = 'tasks';

    /** Fixed so a re-run reproduces the same threads. */
    private const int FAKER_SEED = 20260911;

    public function __construct(private readonly NoteService $notes) {}

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(self::FAKER_SEED);

        $tasks = Task::query()->with(['assignees', 'watchers'])->orderBy('id')->get();

        foreach ($tasks as $index => $task) {
            if ($index % self::TASK_STRIDE !== 0) {
                continue;
            }

            $this->seedThread($faker, $task);
        }
    }

    private function seedThread(Generator $faker, Task $task): void
    {
        $authors = $this->authorsFor($task);

        if ($authors === []) {
            return;
        }

        $task->notesWithTrashed()->forceDelete();

        $rootCount = $faker->numberBetween(1, self::MAX_ROOT_NOTES);

        for ($index = 0; $index < $rootCount; $index++) {
            $root = $this->write($faker, $task, $authors, null);

            $replyCount = $faker->numberBetween(0, self::MAX_REPLIES);

            for ($reply = 0; $reply < $replyCount; $reply++) {
                $this->write($faker, $task, $authors, $root->id);
            }
        }
    }

    /**
     * @param  array<int, User>  $authors
     */
    private function write(Generator $faker, Task $task, array $authors, ?int $parentId): Note
    {
        $bodies = $parentId === null ? DemoTaskCatalogue::NOTE_BODIES : DemoTaskCatalogue::NOTE_REPLIES;

        return $this->notes->create(
            $faker->randomElement($authors),
            new CreateNoteData(
                entityType: self::ENTITY_TYPE,
                entityId: $task->id,
                body: $faker->randomElement($bodies),
                parentId: $parentId,
                // D-6: a Task has no scoping unit, so a note on it is never
                // scoped to one.
                quoteId: null,
                mentionIds: [],
            ),
        );
    }

    /**
     * The Task's members who may actually read it — the same set
     * TaskNotable::authorizeRead() would admit, built from the four record
     * roles (spec 0101 D-9) and filtered on the resource permission, which
     * membership narrows but never grants (AC-062).
     *
     * @return array<int, User>
     */
    private function authorsFor(Task $task): array
    {
        $memberIds = array_values(array_unique(array_filter([
            $task->creator_id,
            $task->requester_id,
            ...$task->assignees->pluck('id')->all(),
            ...$task->watchers->pluck('id')->all(),
        ])));

        return User::query()->whereKey($memberIds)->orderBy('id')->get()
            ->filter(static fn (User $user): bool => $user->can('tasks.view'))
            ->values()
            ->all();
    }
}
