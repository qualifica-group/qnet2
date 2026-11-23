<?php

namespace Database\Seeders;

use App\Services\AttributeRemover;
use App\Services\AttributeService;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user request 2026-11-23, spec 0424 D-5):
 * `php artisan db:seed --class=IsoRequestedStandardGroupsSeeder --force`.
 *
 * Replaces the six yes/no "norm group" fields of the ISO offer with ONE
 * checkbox-list field, "Norme richieste" (IsoCatalogue::STANDARD_GROUPS),
 * each entry opening its group block. The ticked groups of every record move
 * into the list, the ISO offer form is rewritten from IsoCatalogue
 * (QualificaIsoQuoteFormSeeder: a hand edit of that form is lost), then the
 * six fields are deleted with their values, assignments and layout
 * placements (AttributeRemover). Query builder writes: no activity log, no
 * notification. One transaction; re-running it changes nothing.
 */
class IsoRequestedStandardGroupsSeeder extends Seeder
{
    /** Each retired yes/no field => the list entry that replaces it. */
    public const array RETIRED_GROUP_FIELDS = [
        'iso_group_general' => IsoCatalogue::GROUP_GENERAL,
        'iso_group_food' => IsoCatalogue::GROUP_FOOD,
        'iso_group_energy' => IsoCatalogue::GROUP_ENERGY,
        'iso_group_anti_bribery' => IsoCatalogue::GROUP_ANTI_BRIBERY,
        'iso_group_road_safety' => IsoCatalogue::GROUP_ROAD_SAFETY,
        'iso_group_gender_equality' => IsoCatalogue::GROUP_GENDER_EQUALITY,
    ];

    private const int CHUNK_SIZE = 500;

    public function run(AttributeRemover $remover): void
    {
        $moved = 0;
        $removed = DB::transaction(function () use ($remover, &$moved): array {
            // Step 1: the new field and the rewritten ISO offer form.
            $this->call(QualificaIsoQuoteFormSeeder::class);

            // Step 2: the ticked groups into the list, on every record type.
            foreach (AttributeService::VALUE_TABLES as $table) {
                $moved += $this->moveTickedGroups($table);
            }

            // Step 3: the six yes/no fields, everywhere.
            return $remover->remove(array_keys(self::RETIRED_GROUP_FIELDS));
        });

        $this->command?->info(sprintf(
            'Norme richieste valorizzate su %d record. Campi eliminati: %d (valori tolti da %d record, %d assegnazioni, %d layout).',
            $moved,
            $removed['deleted'],
            $removed['values'],
            $removed['detached'],
            $removed['layouts'],
        ));
    }

    private function moveTickedGroups(string $table): int
    {
        $moved = 0;

        DB::table($table)
            ->where(function ($query): void {
                foreach (array_keys(self::RETIRED_GROUP_FIELDS) as $code) {
                    $query->orWhereNotNull("attribute_values->{$code}");
                }
            })
            ->select(['id', 'attribute_values'])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table, &$moved): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);
                    $groups = is_array($values) ? $this->tickedGroups($values) : [];

                    if ($groups === []) {
                        continue;
                    }

                    $values[IsoCatalogue::STANDARD_GROUPS] = $groups;
                    DB::table($table)->where('id', $row->id)->update([
                        'attribute_values' => json_encode($values, JSON_UNESCAPED_UNICODE),
                    ]);
                    $moved++;
                }
            });

        return $moved;
    }

    /**
     * The list entries of the groups ticked on $values, merged with a list
     * already there, in the catalogue order; empty when nothing changes.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function tickedGroups(array $values): array
    {
        $current = is_array($values[IsoCatalogue::STANDARD_GROUPS] ?? null) ? $values[IsoCatalogue::STANDARD_GROUPS] : [];
        $ticked = [];

        foreach (self::RETIRED_GROUP_FIELDS as $code => $group) {
            if (in_array($values[$code] ?? null, [true, 1, '1'], true)) {
                $ticked[] = $group;
            }
        }

        $merged = array_values(array_intersect(array_values(self::RETIRED_GROUP_FIELDS), [...$current, ...$ticked]));
        $merged = [...$merged, ...array_values(array_diff($current, $merged))];

        return $ticked === [] || $merged === $current ? [] : $merged;
    }
}
