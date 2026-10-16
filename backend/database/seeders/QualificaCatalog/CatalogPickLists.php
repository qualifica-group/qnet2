<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\RewardType;
use App\Models\Source;

/**
 * The standalone pick-lists of the Qualifica catalogue: the source catalogue
 * (spec 0018), the fixed provenance list used to classify registry/lead/
 * opportunity records, and the reward type catalogue (spec 0058). Split out of
 * QualificaCatalogSeeder (which stayed at its size limit, engineering.md §6).
 *
 * Idempotent: `firstOrCreate` on the natural name key, so a re-run never
 * duplicates rows nor overwrites manual edits.
 */
final class CatalogPickLists
{
    /**
     * The client's fixed source catalogue (spec 0018): user-facing domain
     * values, kept in their original language. Seeded in order.
     *
     * @var list<string>
     */
    private const array SOURCES = [
        'Diretto',
        'Passaparola',
        'Diretto / Passaparola',
        'Social',
        'Sito',
        'Spoki',
        'Centralino',
        'In Sede',
        'Segnalatore',
        'Spontaneo',
    ];

    /**
     * The client's reward type catalogue (spec 0058): name => palette token
     * from `BADGE_COLOR_TOKENS` (the grid badge resolves the value by TOKEN
     * NAME, never an arbitrary hex). Names are user-facing domain values,
     * kept in their original language.
     *
     * @var array<string, string>
     */
    private const array REWARD_TYPES = [
        'Buono Amazon' => 'orange',
    ];

    public function seed(): void
    {
        foreach (self::SOURCES as $name) {
            Source::firstOrCreate(['name' => $name]);
        }

        foreach (self::REWARD_TYPES as $name => $color) {
            RewardType::firstOrCreate(['name' => $name], ['color' => $color]);
        }
    }
}
