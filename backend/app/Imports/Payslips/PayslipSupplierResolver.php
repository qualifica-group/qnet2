<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Imports\Support\PayrollLookups;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Decides, without writing, which supplier registry pays a person (spec 0431,
 * D-4): the one already linked to the user, else the only registry holding the
 * tax code, else a new one. Two queries whatever the number of people.
 */
final class PayslipSupplierResolver
{
    public const string EXISTING = 'existing';

    public const string LINK = 'link';

    public const string CREATE = 'create';

    /**
     * @param  array<int, string>  $taxCodeByUser  user id => normalized tax code
     * @return array<int, array{action: string, registry_id: int|null, duplicate: bool}>
     */
    public function resolve(array $taxCodeByUser): array
    {
        if ($taxCodeByUser === []) {
            return [];
        }

        // Step 1: users already linked
        $linked = User::query()->whereIn('id', array_keys($taxCodeByUser))->pluck('supplier_registry_id', 'id');

        // Step 2: registries holding the tax code of the others
        $unlinkedCodes = array_values(array_unique(array_filter(
            $taxCodeByUser,
            static fn (string $code, int $userId): bool => $linked->get($userId) === null,
            ARRAY_FILTER_USE_BOTH,
        )));
        $registriesByCode = $this->registriesByTaxCode($unlinkedCodes);

        // Step 3: one decision per user
        $decisions = [];
        foreach ($taxCodeByUser as $userId => $taxCode) {
            $matches = $registriesByCode[$taxCode] ?? [];
            $decisions[$userId] = match (true) {
                $linked->get($userId) !== null => ['action' => self::EXISTING, 'registry_id' => (int) $linked->get($userId), 'duplicate' => false],
                count($matches) === 1 => ['action' => self::LINK, 'registry_id' => $matches[0], 'duplicate' => false],
                default => ['action' => self::CREATE, 'registry_id' => null, 'duplicate' => count($matches) > 1],
            };
        }

        return $decisions;
    }

    /**
     * @param  array<int, string>  $taxCodes
     * @return array<string, list<int>> normalized tax code => registry ids
     */
    private function registriesByTaxCode(array $taxCodes): array
    {
        if ($taxCodes === []) {
            return [];
        }

        $found = DB::table('personal_data')
            ->where('personal_data.personable_type', (new Registry)->getMorphClass())
            ->whereRaw(PayrollLookups::NORMALIZED_TAX_CODE.' IN ('.implode(',', array_fill(0, count($taxCodes), '?')).')', $taxCodes)
            ->get(['personal_data.personable_id as registry_id', DB::raw(PayrollLookups::NORMALIZED_TAX_CODE.' as code')]);

        $map = [];
        foreach ($found as $item) {
            $map[$item->code][] = (int) $item->registry_id;
        }

        return $map;
    }
}
