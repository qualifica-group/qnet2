<?php

declare(strict_types=1);

namespace App\Migrations\Realign;

use App\Exceptions\ExternalApiException;
use App\Migrations\Realign\Support\RegistryLegacyProfile;
use App\Migrations\Support\MissingCardContacts;
use App\Models\Registry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-off re-import of the contacts of the anagrafiche already migrated (spec 0378).
 * The legacy now sends every value of its free-text contact columns: what is missing
 * on the card is added, what is already there (same type and normalized value) is left
 * alone, and nothing is ever changed or deleted, manual contacts included. A value
 * becomes primary only when the card has no primary of its type yet.
 */
final class RegistryContactsBackfill
{
    public const string SOURCE = 'registries';

    public function __construct(
        private readonly LegacyRealignClient $client,
        private readonly RegistryLegacyProfile $profiles,
    ) {}

    /**
     * @param  callable(int): void  $onPage  called with the number of anagrafiche read
     * @return array{added: list<list<string|int|bool|null>>, skipped: list<list<string|int|null>>, read: int}
     *
     * @throws ExternalApiException the legacy did not answer: the run stops
     */
    public function run(bool $apply, callable $onPage): array
    {
        $result = ['added' => [], 'skipped' => [], 'read' => 0];

        Registry::query()->whereNotNull('old_id')->with('personalData.contacts')
            ->chunkById(LegacyRealignClient::MAX_IDS_PER_REQUEST, function ($registries) use ($apply, $onPage, &$result): void {
                $byLegacyId = $registries->keyBy(static fn (Registry $registry): string => (string) $registry->old_id);

                // Step 1: one legacy page for the whole chunk.
                foreach ($this->client->records(self::SOURCE, $byLegacyId->keys()->all()) as $legacy) {
                    $registry = $byLegacyId->get((string) ($legacy['id'] ?? ''));

                    // Step 2: the missing contacts of each anagrafica, written only with --apply.
                    if ($registry !== null) {
                        $this->backfill($registry, $legacy, $apply, $result);
                    }
                }

                $result['read'] += $registries->count();
                $onPage($result['read']);
            });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array{added: list<list<string|int|bool|null>>, skipped: list<list<string|int|null>>, read: int}  $result
     */
    private function backfill(Registry $registry, array $legacy, bool $apply, array &$result): void
    {
        $card = $registry->personalData;
        $warnings = [];

        try {
            $contacts = $this->profiles->profile($legacy, $warnings)->contacts ?? [];
        } catch (RuntimeException $exception) {
            $result['skipped'][] = [$registry->old_id, $registry->id, $registry->name, $exception->getMessage()];

            return;
        }

        if ($card === null) {
            $result['skipped'][] = [$registry->old_id, $registry->id, $registry->name, 'The anagrafica has no personal-data card.'];

            return;
        }

        $missing = MissingCardContacts::of($card, $contacts);

        if ($apply && $missing !== []) {
            DB::transaction(static fn () => activity()->withoutLogs(static function () use ($card, $missing): void {
                foreach ($missing as $attributes) {
                    $card->contacts()->create($attributes);
                }
            }));
        }

        foreach ($missing as $attributes) {
            $result['added'][] = [$registry->old_id, $registry->id, $registry->name, $attributes['type'], $attributes['value'], $attributes['label'], $attributes['is_primary']];
        }
    }
}
