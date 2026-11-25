<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Models\WorkOrder;

/**
 * Everything one "Prospetto contabile discenti" is computed from (spec 0427):
 * the work order, its Formalab edition and class payload (null when Formalab
 * could not give it), the quote figures, the learners' partner data and the
 * calculator holding the legacy formulas.
 */
final readonly class LearnerStatement
{
    public const string CLASS_OK = 'ok';

    public const string CLASS_EMPTY = 'empty';

    public const string CLASS_UNAVAILABLE = 'unavailable';

    /**
     * @param  array<string, mixed>|null  $class
     */
    public function __construct(
        public WorkOrder $workOrder,
        public int $editionId,
        public ?array $class,
        public QuoteFigures $quote,
        public CommissionBook $book,
        public LearnerFigures $rates,
        public StatementCalculator $calculator,
    ) {}

    public function classStatus(): string
    {
        if ($this->class === null) {
            return self::CLASS_UNAVAILABLE;
        }

        return $this->calculator->learners() === [] ? self::CLASS_EMPTY : self::CLASS_OK;
    }

    /** The legacy shows figures only with a class that has learners. */
    public function hasLearners(): bool
    {
        return $this->classStatus() === self::CLASS_OK;
    }

    public function classCode(): ?string
    {
        $code = $this->class['codice'] ?? null;

        return is_scalar($code) ? (string) $code : null;
    }

    public function twoPhase(): bool
    {
        return (bool) data_get($this->class, 'corso.modalita_due_fasi', false);
    }

    /**
     * @return list<string>
     */
    public function taxCodes(): array
    {
        return array_map(static fn (StatementLearner $l): string => $l->taxCode, $this->calculator->learners());
    }

    /**
     * Supplier registry id of each partner.
     *
     * @return array<string, int>
     */
    public function partnerSuppliers(): array
    {
        return $this->book->partnerSuppliers($this->taxCodes());
    }
}
