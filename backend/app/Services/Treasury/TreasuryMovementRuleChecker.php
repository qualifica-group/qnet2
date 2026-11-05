<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\TreasuryMovementType;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\RevenueType;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use Carbon\CarbonImmutable;

/**
 * Cross-field rules of a manual treasury movement (spec 0360): direction and
 * destination account by type, exactly one coherent and active sub-account, the
 * same-company transfer (D-6) and the opening date of both accounts. Runs on the
 * effective payload (stored values overlaid by the submitted ones on update).
 */
final class TreasuryMovementRuleChecker
{
    /** Sub-account column => model class. */
    private const array SUB_ACCOUNTS = [
        'cost_type_id' => CostType::class,
        'revenue_type_id' => RevenueType::class,
        'treasury_type_id' => TreasuryType::class,
    ];

    /** Sub-account columns accepted by each movement type. */
    private const array ALLOWED_SUB_ACCOUNTS = [
        'income' => ['revenue_type_id', 'treasury_type_id'],
        'expense' => ['cost_type_id', 'treasury_type_id'],
        'transfer' => ['treasury_type_id'],
        'adjustment' => ['treasury_type_id'],
    ];

    private const array COLUMNS = [
        'type', 'direction', 'movement_date', 'financial_account_id', 'counterpart_account_id',
        'cost_type_id', 'revenue_type_id', 'treasury_type_id',
    ];

    public function __construct(private readonly AccountStatementService $statements) {}

    /**
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function effective(array $submitted, ?TreasuryMovement $existing): array
    {
        if ($existing === null) {
            return $submitted;
        }

        $stored = $existing->only(self::COLUMNS);
        $stored['type'] = $existing->type->value;
        $stored['direction'] = $existing->direction?->value;
        $stored['movement_date'] = $existing->movement_date->toDateString();

        return [...$stored, ...$submitted];
    }

    /**
     * @param  array<string, mixed>  $data  effective payload
     * @return array<string, string> field => message (first error per field)
     */
    public function check(array $data, ?TreasuryMovement $existing = null): array
    {
        $type = TreasuryMovementType::from((string) $data['type']);

        // Step 1: direction and sub-account coherence
        $errors = $this->directionErrors($type, $data) + $this->subAccountErrors($type, $data, $existing);

        // Step 2: destination account and opening dates
        return $errors + $this->accountErrors($type, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function directionErrors(TreasuryMovementType $type, array $data): array
    {
        $direction = $data['direction'] ?? null;

        return match (true) {
            $type === TreasuryMovementType::Adjustment && $direction === null => ['direction' => __('treasury_movements.direction_required')],
            $type !== TreasuryMovementType::Adjustment && $direction !== null => ['direction' => __('treasury_movements.direction_not_allowed')],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function subAccountErrors(TreasuryMovementType $type, array $data, ?TreasuryMovement $existing): array
    {
        $chosen = array_keys(array_filter(
            array_intersect_key($data, self::SUB_ACCOUNTS),
            static fn (mixed $id): bool => $id !== null,
        ));

        if (count($chosen) !== 1) {
            return ['sub_account' => __('treasury_movements.sub_account_exactly_one')];
        }

        $column = $chosen[0];

        if (! in_array($column, self::ALLOWED_SUB_ACCOUNTS[$type->value], true)) {
            return [$column => __('treasury_movements.sub_account_not_allowed')];
        }

        $unchanged = $existing !== null && $existing->getAttribute($column) === (int) $data[$column];
        $modelClass = self::SUB_ACCOUNTS[$column];
        $isActive = $modelClass::query()->whereKey($data[$column])->where('is_active', true)->exists();

        return $isActive || $unchanged ? [] : [$column => __('treasury_movements.sub_account_inactive')];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function accountErrors(TreasuryMovementType $type, array $data): array
    {
        $counterpartId = $data['counterpart_account_id'] ?? null;
        $accountId = (int) $data['financial_account_id'];

        if ($type !== TreasuryMovementType::Transfer) {
            return $counterpartId === null
                ? $this->openingErrors((string) $data['movement_date'], $this->account($accountId), null)
                : ['counterpart_account_id' => __('treasury_movements.counterpart_not_allowed')];
        }

        if ($counterpartId === null) {
            return ['counterpart_account_id' => __('treasury_movements.counterpart_required')];
        }

        if ((int) $counterpartId === $accountId) {
            return ['counterpart_account_id' => __('treasury_movements.counterpart_same_account')];
        }

        $account = $this->account($accountId);
        $counterpart = $this->account((int) $counterpartId);

        if ($counterpart?->company_id !== $account?->company_id) {
            return ['counterpart_account_id' => __('treasury_movements.counterpart_other_company')];
        }

        return $this->openingErrors((string) $data['movement_date'], $account, $counterpart);
    }

    private function account(int $id): ?FinancialAccount
    {
        return FinancialAccount::query()->find($id);
    }

    /**
     * @return array<string, string>
     */
    private function openingErrors(string $date, ?FinancialAccount $account, ?FinancialAccount $counterpart): array
    {
        $day = CarbonImmutable::parse($date);

        foreach (array_filter([$account, $counterpart]) as $target) {
            $opening = $this->statements->openingDate($target);

            if ($day->lt($opening)) {
                return ['movement_date' => __('treasury_movements.before_opening', ['date' => $opening->toDateString()])];
            }
        }

        return [];
    }
}
