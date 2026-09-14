<?php

namespace Database\Seeders\Concerns;

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use Faker\Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything a demo Task may point at, read ONCE and drawn from afterwards:
 * the status pick-list and the four pure classification lookups (the client's
 * reference vocabulary, never invented here), the users who staff it, and the
 * optional record links — anagrafica, referente, opportunita', commessa.
 *
 * Split off DemoTaskSeeder, which keeps the orchestration and the payload
 * shape, because loading the context and deciding a Task's content are two
 * reasons to change (engineering.md §6: the seeder was past the 300-line
 * threshold). The host seeder owns the Faker instance and seeds it, so the
 * whole dataset stays reproducible from one seed.
 */
trait PicksTaskRecordLinks
{
    private Generator $faker;

    /** @var Collection<int, TaskStatus> */
    private Collection $statuses;

    /** @var Collection<int, User> */
    private Collection $users;

    /** @var array<int, int> */
    private array $userIds = [];

    /** @var array<class-string, array<int, int>> lookup class => its active row ids */
    private array $lookupIds = [];

    /** @var array<int, string> registry id => its name */
    private array $registryNames = [];

    /** @var array<int, array<int, int>> registry id => its referent ids */
    private array $referentsByRegistry = [];

    /** @var array<int, int> opportunity id => its registry id */
    private array $registryByOpportunity = [];

    /** @var array<int, int> */
    private array $workOrderIds = [];

    /**
     * Ordered by id/sort_order everywhere, so the draws below depend on the
     * Faker seed alone and not on how the database happens to return rows.
     */
    private function loadTaskContext(Generator $faker): void
    {
        $this->faker = $faker;

        $this->statuses = TaskStatus::query()->where('is_active', true)->orderBy('sort_order')->get();
        $this->users = User::query()->orderBy('id')->get();
        $this->userIds = $this->users->pluck('id')->all();

        foreach ([TaskType::class, TaskPriority::class, TaskImportance::class, TaskCategory::class] as $lookup) {
            $this->lookupIds[$lookup] = $lookup::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->all();
        }

        $this->registryNames = Registry::query()->orderBy('id')->pluck('name', 'id')->all();

        $this->referentsByRegistry = DB::table('referent_registry')
            ->orderBy('referent_id')
            ->get(['registry_id', 'referent_id'])
            ->groupBy('registry_id')
            ->map(static fn ($rows): array => $rows->pluck('referent_id')->map(static fn ($id): int => (int) $id)->all())
            ->all();

        $this->registryByOpportunity = Opportunity::query()->orderBy('id')->pluck('registry_id', 'id')
            ->map(static fn ($id): int => (int) $id)->all();

        $this->workOrderIds = WorkOrder::query()->orderBy('id')->pluck('id')->all();
    }

    private function hasTaskContext(): bool
    {
        return $this->statuses->isNotEmpty() && $this->users->isNotEmpty();
    }

    /**
     * AC-014: a referente must belong to the Task's anagrafica through the
     * `referent_registry` pivot, so it is picked from that anagrafica's own set
     * or not at all.
     */
    private function pickReferent(?int $registryId): ?int
    {
        return $this->pickOptional($registryId === null ? [] : ($this->referentsByRegistry[$registryId] ?? []), 0.5);
    }

    /**
     * Creator, requester and assignees can never also be watchers (spec 0118
     * D-9, TaskWatcherOverlapGuard): the watchers are picked from the users
     * outside $excludedIds.
     *
     * @param  array<int, int>  $excludedIds
     * @return array<int, int>
     */
    private function pickWatchers(array $excludedIds, int $max): array
    {
        $candidates = array_values(array_diff($this->userIds, $excludedIds));

        if ($candidates === []) {
            return [];
        }

        return $this->faker->randomElements(
            $candidates,
            $this->faker->numberBetween(0, min($max, count($candidates))),
        );
    }

    /**
     * One value of $candidates with probability $weight, null otherwise — the
     * shape every optional link on a Task shares. An empty candidate set
     * answers null without consuming randomness, so an installation missing a
     * whole module (no commesse, no opportunita') still seeds.
     *
     * @param  array<int, int>  $candidates
     */
    private function pickOptional(array $candidates, float $weight): ?int
    {
        if ($candidates === []) {
            return null;
        }

        return $this->faker->boolean((int) round($weight * 100))
            ? (int) $this->faker->randomElement($candidates)
            : null;
    }
}
