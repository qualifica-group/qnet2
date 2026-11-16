<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Exceptions\ExternalApiException;
use App\Migrations\Support\RegistryResponsibleReferents;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-off re-import of "Referente 1/2" on the anagrafiche already migrated (spec 0398).
 * Every anagrafica runs in its own transaction, committed only with `--apply`: the
 * simulation does the same work and rolls it back, so its report is exact. Nothing is
 * logged in the activity log, so a principale changed by hand stays recognisable.
 */
final class RegistryReferentsBackfill
{
    public const string SOURCE = 'registries';

    public function __construct(
        private readonly LegacyRealignClient $client,
        private readonly RegistryResponsibleReferents $responsibles,
    ) {}

    /**
     * @param  callable(int): void  $onPage  called with the number of anagrafiche read
     * @return array{linked: list<list<string|int|bool|null>>, primaries: list<list<string|int|null>>, skipped: list<list<string|int|null>>, read: int, kept_manual: int}
     *
     * @throws ExternalApiException the legacy did not answer: the run stops
     */
    public function run(bool $apply, User $actor, callable $onPage): array
    {
        $result = ['linked' => [], 'primaries' => [], 'skipped' => [], 'read' => 0, 'kept_manual' => 0];

        Registry::query()->whereNotNull('old_id')
            ->chunkById(LegacyRealignClient::MAX_IDS_PER_REQUEST, function ($registries) use ($apply, $actor, $onPage, &$result): void {
                $byLegacyId = $registries->keyBy(static fn (Registry $registry): string => (string) $registry->old_id);

                // Step 1: one legacy page for the whole chunk.
                foreach ($this->client->records(self::SOURCE, $byLegacyId->keys()->all()) as $legacy) {
                    $registry = $byLegacyId->get((string) ($legacy['id'] ?? ''));

                    // Step 2: each anagrafica in its own transaction.
                    if ($registry !== null) {
                        $this->backfill($registry, $legacy, $apply, $actor, $result);
                    }
                }

                $result['read'] += $registries->count();
                $onPage($result['read']);
            });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array{linked: list<list<string|int|bool|null>>, primaries: list<list<string|int|null>>, skipped: list<list<string|int|null>>, read: int, kept_manual: int}  $result
     */
    private function backfill(Registry $registry, array $legacy, bool $apply, User $actor, array &$result): void
    {
        $warnings = [];
        $row = [$registry->old_id, $registry->id, $registry->name];

        DB::beginTransaction();

        try {
            $outcome = activity()->withoutLogs(fn (): array => $this->responsibles->align($registry, $legacy, $actor, $warnings));
            $apply ? DB::commit() : DB::rollBack();
        } catch (Throwable $exception) {
            DB::rollBack();
            $result['skipped'][] = [...$row, $exception->getMessage()];

            return;
        }

        foreach ($outcome['links'] as $link) {
            $result['linked'][] = [
                ...$row, $link['slot'], $link['created'] && ! $apply ? null : $link['referent']->id,
                $link['referent']->name, $link['created'], $link['contacts_added'],
            ];
        }

        if ($outcome['primary'] !== null) {
            $primary = $outcome['primary'];
            $result['primaries'][] = [...$row, $primary['old']?->name, $primary['new']?->name, $primary['reason']];
        }

        $result['kept_manual'] += $outcome['kept_manual'] ? 1 : 0;

        foreach ($warnings as $warning) {
            $result['skipped'][] = [...$row, $warning];
        }
    }
}
