<?php

namespace Database\Seeders;

use App\Migrations\Support\ExternalApiClient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user directive 2026-11-12): `php artisan db:seed --class=QualificaLegacyReportsToSeeder --force`,
 * after the `users` migration.
 *
 * Aligns "Risponde a" (the spec 0166 `employment_profile_manager` pivot) of every
 * migrated user to the legacy hierarchy, read live from the legacy `users`
 * migration endpoint (`reports_to_ids` = the legacy `user_responses`). It
 * REPLACES what QualificaReportsToSeeder and the org chart superiors of
 * QualificaHrCatalogSeeder wrote: the legacy hierarchy is the right one.
 *
 * Untouched: users without `old_id` and migrated users the legacy does not
 * list. A legacy manager not migrated is left out with a warning. Re-running it
 * changes nothing.
 */
class QualificaLegacyReportsToSeeder extends Seeder
{
    private const string ENDPOINT = 'users';

    private const int PAGE_SIZE = 100;

    public function __construct(private readonly ExternalApiClient $client) {}

    public function run(): void
    {
        // Step 1: legacy user id => legacy manager ids, as the legacy has them now.
        $legacyManagers = $this->legacyManagers();

        // Step 2: the migrated users, by legacy id.
        $usersByOldId = User::query()->whereNotNull('old_id')->with('employment.reportsTo')->get()->keyBy('old_id');

        // Step 3: one pivot sync per migrated user whose managers differ, all or nothing.
        $aligned = DB::transaction(fn (): int => $this->align($legacyManagers, $usersByOldId));

        $this->command?->info(sprintf('%d reports-to sets aligned to the legacy.', $aligned));
    }

    /**
     * @return array<int, list<int>>
     */
    private function legacyManagers(): array
    {
        $managers = [];
        $offset = 0;

        do {
            $payload = $this->client->get(self::ENDPOINT, ['offset' => $offset, 'limit' => self::PAGE_SIZE]);
            $items = (array) ($payload['items'] ?? []);

            foreach ($items as $item) {
                $managers[(int) $item['id']] = array_map(intval(...), (array) ($item['reports_to_ids'] ?? []));
            }

            $offset += self::PAGE_SIZE;
            $total = (int) ($payload['pagination']['total'] ?? 0);
        } while ($items !== [] && $offset < $total);

        return $managers;
    }

    /**
     * @param  array<int, list<int>>  $legacyManagers
     * @param  Collection<int, User>  $usersByOldId
     */
    private function align(array $legacyManagers, Collection $usersByOldId): int
    {
        $aligned = 0;

        foreach ($legacyManagers as $legacyId => $legacyManagerIds) {
            $user = $usersByOldId->get($legacyId);

            if ($user !== null && $this->sync($user, $this->managerIds($user, $legacyManagerIds, $usersByOldId))) {
                $aligned++;
            }
        }

        return $aligned;
    }

    /**
     * @param  list<int>  $legacyManagerIds
     * @param  Collection<int, User>  $usersByOldId
     * @return list<int>
     */
    private function managerIds(User $user, array $legacyManagerIds, Collection $usersByOldId): array
    {
        $ids = [];

        foreach ($legacyManagerIds as $legacyManagerId) {
            $manager = $usersByOldId->get($legacyManagerId);

            if ($manager === null) {
                $this->command?->warn(sprintf('%s: legacy manager %d not migrated, skipped.', $user->email, $legacyManagerId));

                continue;
            }

            $ids[] = $manager->id;
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<int>  $managerIds
     */
    private function sync(User $user, array $managerIds): bool
    {
        $current = $user->employment?->reportsTo->pluck('id')->sort()->values()->all() ?? [];

        if ($current === $managerIds) {
            return false;
        }

        // An account without a profile yet: the pivot hangs off one.
        $employment = $user->employment ?? $user->employment()->create();
        $employment->reportsTo()->sync($managerIds);

        return true;
    }
}
