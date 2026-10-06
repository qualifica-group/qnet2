<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Enums\DocumentLayoutModule;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutProductSources;
use App\Services\DocumentLayouts\Rendering\Blocks\ProductLineColumnResolver;

/**
 * The `quotes` module's render subject: wraps a Quote and the existing
 * VariableResolver / ProductLineColumnResolver, so the quote document is
 * unchanged by the spec 0195 D-5 generalization. Build it with ::for().
 */
final class QuoteRenderSubject implements DocumentRenderSubject
{
    private const string SOURCE_COST_LINES = 'cost_lines';

    /**
     * Every relation VariableResolver/ProductLineColumnResolver may read.
     * Model::preventLazyLoading() is active outside production (backend.md
     * §3), so a relation missing from this list fails loudly rather than
     * silently degrading — eager-loaded explicitly rather than reused from
     * QuoteService::DETAIL_RELATIONS, which does not cover the personal-data/
     * address/contact depth the rendering-only VariableResolver needs.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'opportunity.registry.personalData.contacts',
        'opportunity.registry.personalData.addresses.city',
        'opportunity.registry.personalData.addresses.province',
        'opportunity.registry.personalData.addresses.state',
        'opportunity.registry.personalData.addresses.country',
        'opportunity.referent.personalData.contacts',
        'opportunity.referent.referentType',
        'commercial.personalData.contacts',
        'reporter.personalData.contacts',
        'supervisor',
        'company.addresses.city',
        'company.addresses.province',
        'company.addresses.state',
        'company.addresses.country',
        'companySite.personalData.addresses.city',
        'companySite.personalData.addresses.province',
        'companySite.personalData.addresses.state',
        'companySite.personalData.addresses.country',
        'companySite.banks',
        'operationalSite.addresses.city',
        'quoteWorkflowStatus',
        'offerLines.product',
        'offerLines.vatRate',
        'costLines.product',
        'costLines.vatRate',
    ];

    public function __construct(
        private readonly Quote $quote,
        private readonly VariableResolver $variableResolver,
        private readonly ProductLineColumnResolver $columnResolver,
    ) {}

    public static function for(Quote $quote): self
    {
        return app()->make(self::class, ['quote' => $quote]);
    }

    public function module(): DocumentLayoutModule
    {
        return DocumentLayoutModule::Quotes;
    }

    public function prepare(): void
    {
        $this->quote->loadMissing(self::DETAIL_RELATIONS);
    }

    public function resolveVariable(string $category, string $key, User $actor): string
    {
        return $this->variableResolver->resolve($category, $key, $this->quote, $actor);
    }

    public function productRows(string $source): array
    {
        // Already sort_order-ordered relations on Quote: never re-sorted here.
        $lines = $source === self::SOURCE_COST_LINES ? $this->quote->costLines : $this->quote->offerLines;

        return $lines->map(fn (QuoteLine $line): array => $this->row($line))->values()->all();
    }

    /**
     * @return array<string, string>
     */
    private function row(QuoteLine $line): array
    {
        $row = [];

        foreach (DocumentLayoutProductSources::QUOTE_LINE_COLUMNS as $key) {
            $row[$key] = $this->columnResolver->value($key, $line);
        }

        return $row;
    }
}
