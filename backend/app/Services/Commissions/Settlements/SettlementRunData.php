<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

/**
 * Validated launch parameters of a settlement run (spec 0213, D-6).
 */
final readonly class SettlementRunData
{
    /**
     * @param  array<int, int>|null  $referentIds
     * @param  array<int, string>|null  $recipientRoles
     */
    public function __construct(
        public string $periodFrom,
        public string $periodTo,
        public ?int $companyId = null,
        public ?array $referentIds = null,
        public ?array $recipientRoles = null,
        public ?string $minAmount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $referentIds = $validated['referent_ids'] ?? null;
        $roles = $validated['recipient_roles'] ?? null;
        $minAmount = $validated['min_amount'] ?? null;

        return new self(
            periodFrom: (string) $validated['period_from'],
            periodTo: (string) $validated['period_to'],
            companyId: isset($validated['company_id']) ? (int) $validated['company_id'] : null,
            referentIds: $referentIds === null || $referentIds === [] ? null : array_map('intval', $referentIds),
            recipientRoles: $roles === null || $roles === [] ? null : array_values($roles),
            minAmount: $minAmount === null || $minAmount === '' ? null : bcadd((string) $minAmount, '0', 2),
        );
    }

    /**
     * The filters as stored on the run.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'company_id' => $this->companyId,
            'referent_ids' => $this->referentIds,
            'recipient_roles' => $this->recipientRoles,
        ];
    }
}
