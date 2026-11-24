<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Models\PersonalData;
use App\Models\Registry;
use Illuminate\Support\Collection;
use Integrations\Formalab\Models\FormalabLearnerCommission;

/**
 * The commercial partner data of the learners of one class, by tax code
 * (spec 0427 D-2): the stored row first; with no partner name on it, the
 * "Partner commerciale" of the learner's registry (spec 0417).
 */
final class CommissionBook
{
    /**
     * @param  Collection<string, FormalabLearnerCommission>  $rows
     * @param  array<string, string>  $registryPartners  tax code => partner name from the registry
     * @param  array<string, list<int>>  $registryIds  tax code => registries carrying it
     */
    private function __construct(
        private readonly Collection $rows,
        private readonly array $registryPartners,
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

        $registries = Registry::query()
            ->with('commercialPartner:id,name')
            ->whereIn('id', $cards->pluck('personable_id'))
            ->get(['id', 'commercial_partner_id'])
            ->keyBy('id');

        $registryPartners = [];
        $registryIds = [];
        foreach ($cards->sortBy('personable_id') as $card) {
            $taxCode = self::normalize((string) $card->tax_code);
            $registryIds[$taxCode][] = (int) $card->personable_id;
            $partner = $registries->get($card->personable_id)?->commercialPartner?->name;
            if (! isset($registryPartners[$taxCode]) && is_string($partner) && $partner !== '') {
                $registryPartners[$taxCode] = $partner;
            }
        }

        return new self($rows, $registryPartners, $registryIds);
    }

    public static function normalize(string $taxCode): string
    {
        return strtoupper(trim($taxCode));
    }

    /** The learner's partner name; null when none (legacy `empty()`). */
    public function partnerOf(string $taxCode): ?string
    {
        $taxCode = self::normalize($taxCode);
        $stored = $this->rows->get($taxCode)?->partner_name;

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return $this->registryPartners[$taxCode] ?? null;
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
