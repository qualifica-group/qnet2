<?php

namespace Database\Seeders;

use App\Models\Source;
use App\Services\Deletion\UsageMap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * One-shot (user request 2026-11-25): merges the duplicate Fonti into the one
 * kept. Every registry, lead, campaign and quote of a duplicate moves to the
 * kept source, then the duplicate is deleted. The referencing columns come
 * from UsageMap, whose schema test keeps them complete.
 *
 * Identified by id, as read on the production copy, with the names checked:
 * on a database where an id names another source the seeder stops. A
 * duplicate already gone is skipped, so a second run changes nothing. Run with
 * `php artisan db:seed --class=SourcesDeduplicationSeeder --force`.
 */
class SourcesDeduplicationSeeder extends Seeder
{
    /** Duplicate => kept, each as [id, name]. */
    public const array MERGES = [
        [[14, 'Passa parola'], [2, 'Passaparola']],
        [[15, 'Passa parola + buono amazon'], [2, 'Passaparola']],
        [[11, 'Contatto Diretto'], [1, 'Diretto']],
        [[9, 'Segnalatore'], [12, 'Derivanti da CAF e Segnalazioni']],
    ];

    public function run(): void
    {
        $moved = 0;
        $deleted = 0;

        DB::transaction(function () use (&$moved, &$deleted): void {
            foreach (self::MERGES as [[$duplicateId, $duplicateName], [$keptId, $keptName]]) {
                // Step 1: both sources checked by name; a duplicate already merged is skipped
                $duplicate = $this->source($duplicateId, $duplicateName, required: false);
                $kept = $this->source($keptId, $keptName, required: true);

                if ($duplicate === null) {
                    continue;
                }

                // Step 2: every reference moves to the kept source, then the duplicate goes
                $moved += $this->moveReferences($duplicate->id, $kept->id);
                $duplicate->delete();
                $deleted++;
            }
        });

        $this->command?->info(sprintf('Fonti doppie eliminate: %d. Record spostati: %d.', $deleted, $moved));
    }

    private function source(int $id, string $name, bool $required): ?Source
    {
        $source = Source::query()->find($id);

        if ($source === null && ! $required) {
            return null;
        }

        if ($source?->name !== $name) {
            throw new RuntimeException(sprintf('La fonte %d non e\' "%s": seeder interrotto.', $id, $name));
        }

        return $source;
    }

    private function moveReferences(int $fromId, int $toId): int
    {
        $moved = 0;

        foreach (array_keys(UsageMap::USAGES['sources']) as $reference) {
            [$table, $column] = explode('.', $reference);
            $moved += DB::table($table)->where($column, $fromId)->update([$column => $toId]);
        }

        return $moved;
    }
}
