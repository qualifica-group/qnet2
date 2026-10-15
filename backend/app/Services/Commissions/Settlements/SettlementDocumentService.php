<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementStatus;
use App\Enums\HttpStatusEnum;
use App\Models\CommissionSettlement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Registers and removes the document (invoice or fee note) the agent sent for
 * an issued settlement (spec 0267, D-1, D-3). It runs on the same lock as the
 * settlement transitions (agent first, then settlement) and never changes the
 * settlement amounts: the declared ones are only compared within the tolerance.
 */
class SettlementDocumentService
{
    public function __construct(
        private readonly CommissionSettlementService $settlements,
        private readonly DocumentToleranceChecker $checker,
    ) {}

    /**
     * @param  array<string, mixed>  $data  document_number, document_date and the five document_*_amount of the validated request
     */
    public function register(CommissionSettlement $settlement, array $data, User $actor): CommissionSettlement
    {
        return $this->settlements->transition($settlement, function (CommissionSettlement $locked) use ($data, $actor): void {
            // Step 1: only an issued settlement takes the document
            if ($locked->status !== CommissionSettlementStatus::Issued) {
                abort(HttpStatusEnum::CONFLICT->value, __('Only an issued settlement can receive the agent document.'));
            }

            // Step 2: the declared amounts are coherent and within the tolerance
            $declared = $this->declaredAmounts($data);
            $this->assertTotal($declared);
            $this->assertWithinTolerance($locked, $declared);

            // Step 3: store the document and move the status
            $locked->fill([
                'status' => CommissionSettlementStatus::DocumentReceived,
                'document_number' => $data['document_number'],
                'document_date' => $data['document_date'],
                ...$this->columns($declared),
                'document_received_at' => now(),
                'document_received_by' => $actor->id,
            ])->save();
        });
    }

    public function remove(CommissionSettlement $settlement): CommissionSettlement
    {
        return $this->settlements->transition($settlement, function (CommissionSettlement $locked): void {
            if ($locked->status !== CommissionSettlementStatus::DocumentReceived) {
                abort(HttpStatusEnum::CONFLICT->value, __('Only a settlement with a received document can have it removed.'));
            }

            $locked->fill([
                'status' => CommissionSettlementStatus::Issued,
                'document_number' => null,
                'document_date' => null,
                ...$this->columns(array_fill_keys(DocumentToleranceChecker::AMOUNTS, null)),
                'document_received_at' => null,
                'document_received_by' => null,
            ])->save();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function declaredAmounts(array $data): array
    {
        $declared = [];

        foreach (DocumentToleranceChecker::AMOUNTS as $amount) {
            $declared[$amount] = bcadd((string) $data[DocumentToleranceChecker::FIELD_PREFIX.$amount], '0', 2);
        }

        return $declared;
    }

    /**
     * @param  array<string, string|null>  $amounts  by short amount name
     * @return array<string, string|null>
     */
    private function columns(array $amounts): array
    {
        $columns = [];

        foreach ($amounts as $amount => $value) {
            $columns[DocumentToleranceChecker::FIELD_PREFIX.$amount] = $value;
        }

        return $columns;
    }

    /**
     * @param  array<string, string>  $declared
     */
    private function assertTotal(array $declared): void
    {
        if (bccomp($declared['total_amount'], bcadd($declared['taxable_amount'], $declared['vat_amount'], 2), 2) !== 0) {
            throw ValidationException::withMessages([
                'document_total_amount' => [__('The document total must equal the taxable amount plus the VAT.')],
            ]);
        }
    }

    /**
     * @param  array<string, string>  $declared
     */
    private function assertWithinTolerance(CommissionSettlement $settlement, array $declared): void
    {
        $violations = $this->checker->violations($declared, $this->checker->referenceOf($settlement), (string) config('commissions.document_tolerance'));

        if ($violations === []) {
            return;
        }

        throw ValidationException::withMessages(array_map(
            fn (array $violation): array => [__('The amount differs from the settlement by :difference, over the tolerance of :tolerance.', $violation)],
            $violations,
        ));
    }
}
