<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use Illuminate\Support\Facades\DB;
use Integrations\Formalab\Models\FormalabLearnerCommission;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The prospetto's edits (spec 0427 D-3): a learner's commission percentage
 * or fixed amount, and the supplier a partner is paid through. Only learners
 * of the work order's class can be touched.
 */
final class CommissionEditor
{
    private const float MAX_PERCENTAGE = 100.0;

    /**
     * Legacy `updatedPercentualiPartner`: percentage kept within 0-100; with a
     * percentage the stored amount becomes that share of the profit per
     * learner of the active scenario.
     *
     * @throws NotFoundHttpException the tax code is not a learner of the class
     */
    public function setPercentage(LearnerStatement $statement, string $taxCode, float $percentage): void
    {
        $taxCode = $this->assertLearner($statement, $taxCode);
        $percentage = max(0.0, min(self::MAX_PERCENTAGE, $percentage));
        $row = FormalabLearnerCommission::query()->firstOrNew(['tax_code' => $taxCode]);
        $row->percentage = $percentage;

        if ($percentage > 0) {
            $row->amount = round(($statement->calculator->scenarioProfitPerLearner() * $percentage) / 100, 2);
        }

        $row->save();
    }

    /**
     * Legacy `updatedImportiPartner`: the fixed amount, never negative.
     *
     * @throws NotFoundHttpException the tax code is not a learner of the class
     */
    public function setAmount(LearnerStatement $statement, string $taxCode, float $amount): void
    {
        $taxCode = $this->assertLearner($statement, $taxCode);

        FormalabLearnerCommission::query()->updateOrCreate(['tax_code' => $taxCode], ['amount' => max(0.0, $amount)]);
    }

    /**
     * Legacy `setPartnerFornitore`: the supplier goes on every learner of the
     * class with that partner; null clears it.
     */
    public function setPartnerSupplier(LearnerStatement $statement, string $partner, ?int $supplierId): void
    {
        $taxCodes = array_map(
            static fn (StatementLearner $l): string => $l->taxCode,
            array_filter($statement->calculator->learners(), static fn (StatementLearner $l): bool => $l->partner === $partner),
        );

        DB::transaction(static function () use ($taxCodes, $supplierId): void {
            foreach ($taxCodes as $taxCode) {
                FormalabLearnerCommission::query()->updateOrCreate(['tax_code' => $taxCode], ['supplier_registry_id' => $supplierId]);
            }
        });
    }

    private function assertLearner(LearnerStatement $statement, string $taxCode): string
    {
        $taxCode = CommissionBook::normalize($taxCode);

        if (! in_array($taxCode, $statement->taxCodes(), true)) {
            throw new NotFoundHttpException(__('formalab::messages.learner_not_in_class'));
        }

        return $taxCode;
    }
}
