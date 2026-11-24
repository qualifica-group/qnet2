<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Integrations\Formalab\Models\FormalabLearnerCommission;

/**
 * The commercial partner data of the learners of one class, by tax code
 * (spec 0427 D-2): the partner is the "Partner commerciale" (spec 0417) of the
 * learner's offer, the commission and the supplier are the stored row.
 */
final class CommissionBook
{
    /**
     * @param  Collection<string, FormalabLearnerCommission>  $rows
     * @param  array<string, string>  $partners  tax code => partner name from the learner's offer
     * @param  array<string, list<int>>  $registryIds  tax code => registries carrying it
     */
    private function __construct(
        private readonly Collection $rows,
        private readonly array $partners,
        private readonly array $registryIds,
    ) {}

    /**
     * @param  list<string>  $taxCodes
     */
    public static function for(array $taxCodes): self
    {
        $taxCodes = array_values(array_unique(array_map(self::normalize(...), $taxCodes)));

        $rows = FormalabLearnerCommission::query()->whereIn('tax_code', $taxCodes)->get()->keyBy('tax_code');

        $cards = PersonalData::query()
            ->whereIn('tax_code', $taxCodes)
            ->where('personable_type', (new Registry)->getMorphClass())
            ->get(['tax_code', 'personable_id']);

        $registryIds = [];
        foreach ($cards->sortBy('personable_id') as $card) {
            $registryIds[self::normalize((string) $card->tax_code)][] = (int) $card->personable_id;
        }

        return new self($rows, self::offerPartners($registryIds), $registryIds);
    }

    /**
     * The partner of each learner: the one on the most recent offer of the
     * learner's registries that carries a "Partner commerciale".
     *
     * @param  array<string, list<int>>  $registryIds
     * @return array<string, string>
     */
    private static function offerPartners(array $registryIds): array
    {
        $taxCodesOfRegistry = [];
        foreach ($registryIds as $taxCode => $ids) {
            foreach ($ids as $id) {
                $taxCodesOfRegistry[$id][] = $taxCode;
            }
        }

        $quotes = Quote::query()
            ->whereNotNull('commercial_partner_id')
            ->whereHas('opportunity', static fn (Builder $query) => $query->whereIn('registry_id', array_keys($taxCodesOfRegistry)))
            ->with(['opportunity:id,registry_id', 'commercialPartner:id,name'])
            ->orderBy('id')
            ->get(['id', 'opportunity_id', 'commercial_partner_id']);

        $partners = [];
        foreach ($quotes as $quote) {
            $partner = $quote->commercialPartner?->name;
            if (! is_string($partner) || $partner === '') {
                continue;
            }
            foreach ($taxCodesOfRegistry[$quote->opportunity->registry_id] ?? [] as $taxCode) {
                $partners[$taxCode] = $partner;
            }
        }

        return $partners;
    }

    public static function normalize(string $taxCode): string
    {
        return strtoupper(trim($taxCode));
    }

    /** The learner's partner name; null when none (legacy `empty()`). */
    public function partnerOf(string $taxCode): ?string
    {
        return $this->partners[self::normalize($taxCode)] ?? null;
    }

    public function percentageOf(string $taxCode): float
    {
        return (float) ($this->rows->get(self::normalize($taxCode))?->percentage ?? 0);
    }

    /** The stored amount: the fixed commission when there is no percentage. */
    public function amountOf(string $taxCode): float
    {
        return (float) ($this->rows->get(self::normalize($taxCode))?->amount ?? 0);
    }

    /**
     * The supplier of each partner (legacy `partnerFornitoreId`): read from
     * the learners of the class that carry both, the last one wins.
     *
     * @param  list<string>  $taxCodes  the class learners, in order
     * @return array<string, int>
     */
    public function partnerSuppliers(array $taxCodes): array
    {
        $suppliers = [];

        foreach ($taxCodes as $taxCode) {
            $partner = $this->partnerOf($taxCode);
            $supplierId = $this->rows->get(self::normalize($taxCode))?->supplier_registry_id;

            if ($partner !== null && $supplierId !== null) {
                $suppliers[$partner] = $supplierId;
            }
        }

        return $suppliers;
    }

    /**
     * @return list<int> the registries carrying this tax code
     */
    public function registryIdsOf(string $taxCode): array
    {
        return $this->registryIds[self::normalize($taxCode)] ?? [];
    }
}
