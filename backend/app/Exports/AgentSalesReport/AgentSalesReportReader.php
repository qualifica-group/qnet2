<?php

declare(strict_types=1);

namespace App\Exports\AgentSalesReport;

use App\Enums\CommissionRecipientRole;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\ProductCategories\CategoryRootResolver;
use App\Services\Table\TableQueryBuilder;
use App\Tables\AgentSalesReportTableDefinition;
use Generator;

/**
 * Shared read side of the two sync exports of the Report Vendite Agenti (spec 0365, D-9): selects
 * the offers with the grid's own query (TableQueryBuilder over the `agent-sales-report` domain,
 * so filters, search, sort and QuoteVisibilityScope are the grid's) and yields one normalized
 * block per offer, read in chunks with every relation eager-loaded.
 *
 * Only REVENUE lines count. Commissions are summed per role (SUPPLIER excluded); Netto is the
 * line amount minus the three commissions.
 */
final class AgentSalesReportReader
{
    /** Offers loaded per chunk: bounds memory and keeps the eager-load IN lists small. */
    private const int CHUNK_SIZE = 200;

    private const string CONSULTANCY_TYPOLOGY = 'consultancy';

    private const string INSTITUTION_TYPOLOGY = 'institution';

    private const int DECIMALS = 2;

    public function __construct(
        private readonly TableQueryBuilder $queryBuilder,
        private readonly AgentSalesReportTableDefinition $definition,
        private readonly CategoryRootResolver $rootResolver,
    ) {}

    /**
     * The ids of the offers the grid state selects, in grid order.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public function selectedIds(array $state): array
    {
        $max = (int) config('exports.max_rows');
        $ids = $this->queryBuilder->build($this->definition, $state)->limit($max + 1)->pluck('quotes.id')->map(fn ($id): int => (int) $id)->all();

        if (count($ids) > $max) {
            abort(422, __('agent_sales_report.export.too_many'));
        }

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     * @return Generator<int, array{header: array<string, mixed>, supervisor: string, consultancy: float, institution: float, lines: list<array<string, mixed>>}>
     */
    public function blocks(User $actor, array $ids): Generator
    {
        foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
            $quotes = $this->definition->baseQuery()
                ->with(['supervisor', 'offerLines.product.category', 'offerLines.product.productTypology', 'offerLines.commissions'])
                ->whereIn('quotes.id', $chunk)
                ->get()
                ->keyBy('id');
            $roots = $this->rootsFor($quotes->pluck('offerLines')->flatten());

            foreach ($chunk as $id) {
                if ($quotes->has($id)) {
                    yield $this->block($actor, $quotes->get($id), $roots);
                }
            }
        }
    }

    /**
     * @param  array<int, array{id: int, name: string}|null>  $roots
     * @return array{header: array<string, mixed>, supervisor: string, consultancy: float, institution: float, lines: list<array<string, mixed>>}
     */
    private function block(User $actor, Quote $quote, array $roots): array
    {
        $lines = $quote->offerLines->map(fn (QuoteLine $line): array => $this->line($line, $roots))->values()->all();

        return [
            'header' => $this->definition->mapRow($actor, $quote),
            'supervisor' => (string) $quote->supervisor?->name,
            'consultancy' => $this->amountOfTypology($quote, self::CONSULTANCY_TYPOLOGY),
            'institution' => $this->amountOfTypology($quote, self::INSTITUTION_TYPOLOGY),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<int, array{id: int, name: string}|null>  $roots
     * @return array<string, mixed>
     */
    private function line(QuoteLine $line, array $roots): array
    {
        $amount = (float) $line->net_amount;
        $supervisor = $this->commission($line, CommissionRecipientRole::Supervisor);
        $commercial = $this->commission($line, CommissionRecipientRole::Commercial);
        $reporter = $this->commission($line, CommissionRecipientRole::Reporter);
        $categoryId = $line->product?->category_id;

        return [
            'root_category' => $categoryId === null ? '' : ($roots[$categoryId]['name'] ?? ''),
            'category' => (string) $line->product?->category?->name,
            'product' => (string) ($line->product?->name ?? $line->additional_description),
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'amount' => $amount,
            'typology' => (string) $line->product?->productTypology?->name,
            'supervisor_commission' => $supervisor,
            'commercial_commission' => $commercial,
            'reporter_commission' => $reporter,
            'net' => round($amount - $supervisor - $commercial - $reporter, self::DECIMALS),
        ];
    }

    private function commission(QuoteLine $line, CommissionRecipientRole $role): float
    {
        return round((float) $line->commissions->where('recipient_role', $role)->sum('calculated_amount'), self::DECIMALS);
    }

    private function amountOfTypology(Quote $quote, string $typologyCode): float
    {
        return round((float) $quote->offerLines
            ->filter(fn (QuoteLine $line): bool => $line->product?->productTypology?->code === $typologyCode)
            ->sum('net_amount'), self::DECIMALS);
    }

    /**
     * @param  iterable<QuoteLine>  $lines
     * @return array<int, array{id: int, name: string}|null>
     */
    private function rootsFor(iterable $lines): array
    {
        $categoryIds = collect($lines)->map(fn (QuoteLine $line): ?int => $line->product?->category_id)->filter()->unique()->values()->all();

        return $categoryIds === [] ? [] : $this->rootResolver->rootSummariesFor($categoryIds);
    }
}
