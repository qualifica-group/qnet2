<?php

namespace Database\Seeders;

use App\Services\AttributeRemover;
use App\Services\AttributeService;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user requests 2026-11-23, spec 0424 D-5):
 * `php artisan db:seed --class=IsoRequestedStandardsSeeder --force`.
 *
 * Gives the ISO offer one checkbox per norm, "Norme richieste"
 * (IsoCatalogue::STANDARDS), in place of what came before: the six yes/no
 * "norm group" fields (production) or the list of groups that briefly
 * replaced them (`iso_requested_standard_groups`, development). Each ticked
 * group becomes its norms: a one-norm group its norm; a many-norm group the
 * norms named by the offer's products ("Consulenza ISO 9001 + ISO 14001"),
 * else FALLBACK_STANDARDS (user decision 2026-11-23: keeps the group block
 * open, so its values survive), listed in the output to be checked by hand.
 *
 * Then the ISO offer form is rewritten from IsoCatalogue
 * (QualificaIsoQuoteFormSeeder: a hand edit of that form is lost) and the
 * replaced fields are deleted with their values, assignments and layout
 * placements (AttributeRemover). Query builder writes: no activity log, no
 * notification. One transaction; re-running it changes nothing.
 */
class IsoRequestedStandardsSeeder extends Seeder
{
    /** Each retired yes/no field => the group block it opened. */
    public const array RETIRED_GROUP_FIELDS = [
        'iso_group_general' => 'iso-group-general',
        'iso_group_food' => 'iso-group-food',
        'iso_group_energy' => 'iso-group-energy',
        'iso_group_anti_bribery' => 'iso-group-anti-bribery',
        'iso_group_road_safety' => 'iso-group-road-safety',
        'iso_group_gender_equality' => 'iso-group-gender-equality',
    ];

    /** The retired list of groups, and each of its entries => the group block it opened. */
    public const string RETIRED_GROUP_LIST = 'iso_requested_standard_groups';

    public const array RETIRED_GROUP_ENTRIES = [
        'general' => 'iso-group-general',
        'food' => 'iso-group-food',
        'energy' => 'iso-group-energy',
        'anti_bribery' => 'iso-group-anti-bribery',
        'road_safety' => 'iso-group-road-safety',
        'gender_equality' => 'iso-group-gender-equality',
    ];

    /** The norm ticked for a many-norm group no product names. */
    public const array FALLBACK_STANDARDS = [
        'iso-group-general' => 'standard_9001',
        'iso-group-food' => 'standard_22000',
    ];

    /** How a product name names each norm of a many-norm group. */
    private const array STANDARD_PATTERNS = [
        'standard_9001' => '/(?<!\d)9001(?!\d)/',
        'standard_14001' => '/(?<!\d)14001(?!\d)/',
        'standard_45001' => '/(?<!\d)45001(?!\d)/',
        'standard_27001' => '/(?<!\d)27001(?!\d)/',
        'standard_sa8000' => '/\bSA\s*8000(?!\d)/i',
        'standard_13549' => '/(?<!\d)13549(?!\d)/',
        'standard_emas' => '/\bEMAS\b/i',
        'standard_ecolabel' => '/\bECOLABEL\b/i',
        'standard_1090' => '/(?<!\d)1090(?!\d)/',
        'standard_16636' => '/(?<!\d)16636(?!\d)/',
        'standard_brc' => '/\bBRC\b/i',
        'standard_ifs' => '/\bIFS\b/i',
        'standard_22000' => '/(?<!\d)22000(?!\d)/',
        'standard_22005' => '/(?<!\d)22005(?!\d)/',
    ];

    private const int CHUNK_SIZE = 500;

    /** @var list<string> "table #id" of the records given a fallback norm */
    private array $fallbacks = [];

    public function run(AttributeRemover $remover): void
    {
        $moved = 0;
        $removed = DB::transaction(function () use ($remover, &$moved): array {
            // Step 1: the new field and the rewritten ISO offer form.
            $this->call(QualificaIsoQuoteFormSeeder::class);

            // Step 2: the ticked groups into norms, on every record type.
            foreach (AttributeService::VALUE_TABLES as $table) {
                $moved += $this->moveTickedGroups($table);
            }

            // Step 3: the replaced fields, everywhere.
            return $remover->remove([...array_keys(self::RETIRED_GROUP_FIELDS), self::RETIRED_GROUP_LIST]);
        });

        $this->command?->info(sprintf(
            'Norme richieste valorizzate su %d record. Campi eliminati: %d (valori tolti da %d record, %d assegnazioni, %d layout).',
            $moved,
            $removed['deleted'],
            $removed['values'],
            $removed['detached'],
            $removed['layouts'],
        ));

        if ($this->fallbacks !== []) {
            $this->command?->warn('Norma non ricavabile, spuntata quella di default (da ricontrollare): '.implode(', ', $this->fallbacks));
        }
    }

    private function moveTickedGroups(string $table): int
    {
        $moved = 0;

        DB::table($table)
            ->where(function ($query): void {
                foreach ([...array_keys(self::RETIRED_GROUP_FIELDS), self::RETIRED_GROUP_LIST] as $code) {
                    $query->orWhereNotNull("attribute_values->{$code}");
                }
            })
            ->select(['id', 'attribute_values'])
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table, &$moved): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);
                    $blocks = is_array($values) ? $this->tickedBlocks($values) : [];

                    if ($blocks === []) {
                        continue;
                    }

                    $current = is_array($values[IsoCatalogue::STANDARDS] ?? null) ? $values[IsoCatalogue::STANDARDS] : [];
                    $values[IsoCatalogue::STANDARDS] = $this->standardsFor($blocks, $current, $table, (int) $row->id);
                    DB::table($table)->where('id', $row->id)->update([
                        'attribute_values' => json_encode($values, JSON_UNESCAPED_UNICODE),
                    ]);
                    $moved++;
                }
            });

        return $moved;
    }

    /**
     * The group blocks the retired fields of $values opened.
     *
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function tickedBlocks(array $values): array
    {
        $blocks = [];

        foreach (self::RETIRED_GROUP_FIELDS as $code => $block) {
            if (in_array($values[$code] ?? null, [true, 1, '1'], true)) {
                $blocks[] = $block;
            }
        }

        foreach ((array) ($values[self::RETIRED_GROUP_LIST] ?? []) as $entry) {
            if (isset(self::RETIRED_GROUP_ENTRIES[$entry])) {
                $blocks[] = self::RETIRED_GROUP_ENTRIES[$entry];
            }
        }

        return array_values(array_unique($blocks));
    }

    /**
     * The norms of $blocks merged into $current, in the list order. A block
     * already opened by a norm of $current needs nothing more.
     *
     * @param  list<string>  $blocks
     * @param  array<int, string>  $current
     * @return list<string>
     */
    private function standardsFor(array $blocks, array $current, string $table, int $id): array
    {
        $ticked = $current;

        foreach ($blocks as $block) {
            $norms = IsoCatalogue::GROUP_STANDARDS[$block];

            if (array_intersect($norms, $ticked) !== []) {
                continue;
            }

            $named = count($norms) === 1 ? $norms : $this->namedByProducts($norms, $table, $id);

            if ($named === []) {
                $named = [self::FALLBACK_STANDARDS[$block]];
                $this->fallbacks[] = $this->describe($table, $id);
            }

            $ticked = [...$ticked, ...$named];
        }

        $order = array_column(IsoCatalogue::STANDARD_OPTIONS, 'value');

        return array_values(array_unique([...array_intersect($order, $ticked), ...array_diff($ticked, $order)]));
    }

    /**
     * The norms of $norms the offer's products name; none for other records.
     *
     * @param  list<string>  $norms
     * @return list<string>
     */
    private function namedByProducts(array $norms, string $table, int $id): array
    {
        if ($table !== 'quotes') {
            return [];
        }

        $names = implode(' | ', DB::table('quote_lines')
            ->join('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('quote_lines.quote_id', $id)
            ->pluck('products.name')
            ->all());

        return array_values(array_filter($norms, static fn (string $norm): bool => preg_match(self::STANDARD_PATTERNS[$norm], $names) === 1));
    }

    private function describe(string $table, int $id): string
    {
        $code = $table === 'quotes' ? DB::table('quotes')->where('id', $id)->value('code') : null;

        return $code !== null ? "{$code} (#{$id})" : "{$table} #{$id}";
    }
}
