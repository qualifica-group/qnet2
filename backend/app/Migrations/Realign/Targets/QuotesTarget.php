<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Enums\QuoteLineType;
use App\Migrations\MigrationRegistry;
use App\Migrations\Realign\AbstractSourceRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\QuoteLineRealigner;
use App\Migrations\Realign\Support\QuotePositionedPeople;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Offerte with status, contract, lines and costs (spec 0375 D-11, D-12, D-13, D-16). */
final class QuotesTarget extends AbstractSourceRealignTarget
{
    private const array COLUMNS = ['title', 'quote_date', 'internal_notes', 'next_callback_at'];

    private const array AMOUNT_COLUMNS = ['revenue_net', 'revenue_vat', 'cost_net', 'cost_vat', 'margin_net'];

    private const string LINE_FIELD = 'line.';

    private const string COST_LINE_FIELD = 'cost_line.';

    private const string OLD_STATUS_PATTERN = '[OLD]%';

    private ?string $since = null;

    public function __construct(MigrationRegistry $registry, private readonly QuoteLineRealigner $lines)
    {
        parent::__construct($registry);
    }

    /** The first day of the changes: older lines carry import conversion differences (D-12). */
    public function changedSince(string $since): self
    {
        $this->since = $since;

        return $this;
    }

    public function source(): string
    {
        return 'quotes';
    }

    protected function modelClass(): string
    {
        return Quote::class;
    }

    public function touchSubjects(Model $record): array
    {
        /** @var Quote $record */
        return [[Quote::class, $record->getKey()], [Opportunity::class, $record->opportunity_id]];
    }

    public function isAmountField(string $field): bool
    {
        return in_array($field, self::AMOUNT_COLUMNS, true)
            || str_starts_with($field, self::LINE_FIELD)
            || str_starts_with($field, self::COST_LINE_FIELD);
    }

    public function snapshot(Model $record): array
    {
        /** @var Quote $record */
        return [
            ...$this->columns($record, [...self::COLUMNS, ...self::AMOUNT_COLUMNS]),
            'status' => $record->quoteWorkflowStatus?->name,
            'supervisor' => $this->personName($record->supervisor),
            'managers' => QuotePositionedPeople::describe($record->managers()),
            'attribute_values' => $this->json($record->attribute_values),
            'contract' => $this->json($this->contract($record->contract)),
            ...$this->lineFields($record),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var Quote $record */
        if ($this->since === null) {
            throw new LogicException('QuotesTarget needs changedSince() before realigning.');
        }

        $before = $this->oldStatusNames();
        parent::realign($record, $legacy, $notes);
        $this->lines->handle($this->reload($record), $legacy, $this->since, $notes);

        foreach (array_diff($this->oldStatusNames(), $before) as $name) {
            $notes->configurationCreated($name);
        }
    }

    /**
     * @return list<string>
     */
    private function oldStatusNames(): array
    {
        return QuoteWorkflowStatus::query()->where('name', 'like', self::OLD_STATUS_PATTERN)->pluck('name')->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contract(?Contract $contract): ?array
    {
        if ($contract === null) {
            return null;
        }

        return [
            'status' => ContractStatus::query()->whereKey($contract->contract_status_id)->value('name'),
            'accepted_at' => $contract->getRawOriginal('accepted_at'),
            'validated_at' => $contract->getRawOriginal('validated_at'),
            'renewal_date' => $contract->getRawOriginal('renewal_date'),
            'terminated_at' => $contract->getRawOriginal('terminated_at'),
        ];
    }

    /**
     * One report field per row, so a changed price reads as that row and not as the whole offer.
     *
     * @return array<string, string|null>
     */
    private function lineFields(Quote $quote): array
    {
        $fields = [];

        foreach ($quote->lines()->with('product:id,name')->get() as $line) {
            /** @var QuoteLine $line */
            $key = $line->line_type === QuoteLineType::Cost ? self::COST_LINE_FIELD.$line->id : self::LINE_FIELD.($line->old_id ?? 'local-'.$line->id);
            $fields[$key] = $this->json([
                'product' => $line->product?->name,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
                'description' => $line->additional_description,
                'net' => $line->net_amount,
                'vat' => $line->vat_amount,
                'total' => $line->total_amount,
            ]);
        }

        return $fields;
    }
}
