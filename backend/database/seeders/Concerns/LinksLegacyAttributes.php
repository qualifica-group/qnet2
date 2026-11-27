<?php

namespace Database\Seeders\Concerns;

use App\Models\Attribute;
use App\Services\Attributes\AttributeLinkRules;

/**
 * Points legacy fields at the current fields they continue (spec 0429,
 * `current_attribute_id`), so the grids show each pair in one column. Shared
 * by the one-shot seeders that link a branch's [OLD] catalogue.
 *
 * Never overwrites an existing link; a pair whose field is missing or would
 * break the rules (no chains, compatible types only) is skipped and reported.
 */
trait LinksLegacyAttributes
{
    /**
     * @param  array<string, string>  $links  legacy code => current code
     * @return int the links written
     */
    protected function linkLegacyAttributes(array $links): int
    {
        $attributes = Attribute::query()
            ->whereIn('code', [...array_keys($links), ...array_values($links)])
            ->get(['id', 'code', 'type', 'current_attribute_id'])
            ->keyBy('code');
        $linked = 0;

        foreach ($links as $legacyCode => $currentCode) {
            $skipReason = $this->linkSkipReason($attributes->get($legacyCode), $attributes->get($currentCode));

            if ($skipReason !== null) {
                $this->command?->warn("{$legacyCode} -> {$currentCode}: {$skipReason}");

                continue;
            }

            $attributes[$legacyCode]->update(['current_attribute_id' => $attributes[$currentCode]->id]);
            $linked++;
        }

        return $linked;
    }

    private function linkSkipReason(?Attribute $legacy, ?Attribute $current): ?string
    {
        return match (true) {
            $legacy === null || $current === null => 'campo assente',
            $legacy->current_attribute_id !== null => $legacy->current_attribute_id === $current->id ? 'gia\' collegato' : 'collegato a un altro campo',
            $current->current_attribute_id !== null => 'il campo attuale ha a sua volta un campo attuale',
            ! AttributeLinkRules::compatible($legacy->type, $current->type) => "tipi non compatibili ({$legacy->type} / {$current->type})",
            default => null,
        };
    }
}
