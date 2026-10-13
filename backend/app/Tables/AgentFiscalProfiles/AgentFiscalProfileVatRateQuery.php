<?php

namespace App\Tables\AgentFiscalProfiles;

use App\Models\AgentFiscalProfile;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter, sort and distinct values of the derived `vat_rate` column of the
 * `agent-fiscal-profiles` table (spec 0213): the label ("name (22%)") is
 * matched in PHP against the small VAT rate lookup, so the SQL only ever sees
 * bound ids and no driver-specific string concatenation.
 */
final class AgentFiscalProfileVatRateQuery
{
    /**
     * @param  Builder<AgentFiscalProfile>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function applyFilter(Builder $query, array $filter): bool
    {
        $raw = is_array($filter['values'] ?? null) ? $filter['values'] : [];
        $labels = array_values(array_filter($raw, 'is_string'));
        $matchesBlank = in_array(null, $raw, true);

        if ($labels === [] && ! $matchesBlank) {
            return true;
        }

        $ids = VatRate::query()->get()
            ->filter(static fn (VatRate $vatRate): bool => in_array(AgentFiscalProfileColumnCatalog::vatRateLabel($vatRate), $labels, true))
            ->pluck('id')
            ->all();

        $query->where(static function (Builder $group) use ($ids, $matchesBlank): void {
            $group->whereIn('agent_fiscal_profiles.vat_rate_id', $ids);

            if ($matchesBlank) {
                $group->orWhereNull('agent_fiscal_profiles.vat_rate_id');
            }
        });

        return true;
    }

    /**
     * @param  Builder<AgentFiscalProfile>  $query
     */
    public static function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            VatRate::query()->select('name')->whereColumn('vat_rates.id', 'agent_fiscal_profiles.vat_rate_id')->limit(1),
            $direction,
        );
    }

    /**
     * Labels of the VAT rates used by the filtered profiles, sorted.
     *
     * @param  Builder<AgentFiscalProfile>  $query
     * @return array<int, string>
     */
    public static function labels(Builder $query, ?string $search, int $limit): array
    {
        return VatRate::query()
            ->whereIn('id', (clone $query)->reorder()->select('vat_rate_id'))
            ->get()
            ->map(static fn (VatRate $vatRate): string => AgentFiscalProfileColumnCatalog::vatRateLabel($vatRate))
            ->filter(static fn (string $label): bool => $search === null || $search === '' || mb_stripos($label, $search) !== false)
            ->sort()
            ->take($limit)
            ->values()
            ->all();
    }
}
