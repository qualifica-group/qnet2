<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityNameWriter;
use App\Support\ManagerPositions;
use App\Support\PositionalPivotSync;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes the legacy values onto an already imported, untouched opportunity (spec 0372, D-5/D-8/D-9):
 * only the fields that really differ, no service (no notification, no title re-derivation beyond the
 * name itself), quiet save with the legacy `updated_at`. Anagrafica, referent, commercial, reporter,
 * site, categories and the status are never written. The caller runs it without activity log.
 *
 * Desired values (already remapped by the source):
 * - `name`: trimmed and cut to the column; null (blank in the legacy) keeps the current name.
 * - `supervisor_id`: omitted when the legacy supervisor is not migrated (the current one stays).
 */
final class LegacyOpportunityRealigner
{
    public function __construct(private readonly OpportunityNameWriter $nameWriter) {}

    /**
     * @param  array{name: ?string, start_date: ?string, expected_close_date: ?string, estimated_value: ?float, general_notes: ?string, supervisor_id?: ?int, manager_slots: array<int, int|null>, updated_at: ?CarbonInterface}  $desired
     * @return bool whether anything was written
     */
    public function realign(Opportunity $opportunity, array $desired): bool
    {
        // Step 1: the title, through its single owner (manual flag included).
        $changed = $this->realignName($opportunity, $desired['name']);

        // Step 2: the scalar columns that differ.
        $attributes = $this->changedAttributes($opportunity, $desired);
        $changed = $attributes !== [] || $changed;

        // Step 3: the managers, positional pivot (the quotes have their own source).
        $changed = $this->realignManagers($opportunity, $desired['manager_slots']) || $changed;

        // Step 4: one quiet save carrying the legacy `updated_at`.
        if ($changed) {
            $opportunity->forceFill([
                ...$attributes,
                ...($desired['updated_at'] !== null ? ['updated_at' => $desired['updated_at']] : []),
            ])->saveQuietly();
        }

        return $changed;
    }

    private function realignName(Opportunity $opportunity, ?string $name): bool
    {
        if ($name === null || $name === $opportunity->name) {
            return false;
        }

        $this->nameWriter->write($opportunity, $name);

        return true;
    }

    /**
     * @param  array<string, mixed>  $desired
     * @return array<string, mixed>
     */
    private function changedAttributes(Opportunity $opportunity, array $desired): array
    {
        $candidates = [
            'start_date' => [$opportunity->start_date?->toDateString(), $desired['start_date']],
            'expected_close_date' => [$opportunity->expected_close_date?->toDateString(), $desired['expected_close_date']],
            'estimated_value' => [
                $opportunity->estimated_value === null ? null : (float) $opportunity->estimated_value,
                $desired['estimated_value'],
            ],
            'general_notes' => [trim((string) $opportunity->general_notes) === '' ? null : $opportunity->general_notes, $desired['general_notes']],
        ];

        if (array_key_exists('supervisor_id', $desired)) {
            $candidates['supervisor_id'] = [$opportunity->supervisor_id, $desired['supervisor_id']];
        }

        $changes = [];

        foreach ($candidates as $column => [$current, $legacy]) {
            if ($current !== $legacy) {
                $changes[$column] = $legacy;
            }
        }

        return $changes;
    }

    /**
     * @param  array<int, int|null>  $slots
     */
    private function realignManagers(Opportunity $opportunity, array $slots): bool
    {
        $syncMap = $this->keepingOfferManagers($opportunity, ManagerPositions::syncMap($slots));
        $result = PositionalPivotSync::sync($opportunity->managers(), $syncMap);

        return $result['attached'] !== [] || $result['detached'] !== [] || $result['updated'] !== [];
    }

    /**
     * A Gestore of one of the offers must stay a Gestore of the opportunity (spec 0186, D-6):
     * the offers' own legacy managers were promoted here at import and are not in the
     * opportunity's legacy list. They keep their slot, or take the first free one.
     *
     * @param  array<int, array{position: int}>  $syncMap
     * @return array<int, array{position: int}>
     */
    private function keepingOfferManagers(Opportunity $opportunity, array $syncMap): array
    {
        $offerManagerIds = DB::table('quote_user')
            ->whereIn('quote_id', $opportunity->quotes()->select('quotes.id'))
            ->pluck('user_id')
            ->map(intval(...))
            ->all();
        $occupied = array_column($syncMap, 'position');

        foreach ($opportunity->managers()->get() as $manager) {
            if (isset($syncMap[$manager->id]) || ! in_array($manager->id, $offerManagerIds, true)) {
                continue;
            }

            $position = (int) $manager->pivot->position;

            while (in_array($position, $occupied, true)) {
                $position++;
            }

            $syncMap[$manager->id] = ['position' => $position];
            $occupied[] = $position;
        }

        return $syncMap;
    }
}
