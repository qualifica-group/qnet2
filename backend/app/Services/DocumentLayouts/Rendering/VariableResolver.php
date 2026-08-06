<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering;

use App\Models\Quote;
use App\Models\User;

/**
 * Resolves every `{category.key}` variable reference (spec 0069's frozen
 * catalogue, spec 0070's rendering_contract) against a Quote for an acting
 * User. The category dispatch is a thin `match`; the actual per-category
 * lookups live in the four split resolvers below (engineering.md §6) —
 * QuoteFieldResolver (quote/totals/document), PartyFieldResolver
 * (client/referent/commercial/reporter/supervisor, the only one that masks
 * PII per D-6), OrganizationFieldResolver (company/company_site/
 * operational_site) and DynamicFieldResolver (custom_fields/
 * quote_attributes).
 *
 * A reference whose category is unknown, or whose key is unknown within a
 * known category, resolves to an empty string and NEVER throws (spec 0070:
 * "un riferimento sintatticamente valido ma non presente nel catalogo ... si
 * rende come stringa VUOTA e NON fa fallire la generazione").
 */
final class VariableResolver
{
    /**
     * Matches a `{category.key}` token: category and key are both
     * snake_case identifiers (letters/digits/underscore), split on the
     * FIRST dot only — `quote_attributes` is itself an underscored
     * category name, not a nested path.
     */
    private const string TOKEN_PATTERN = '/\{([a-zA-Z][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/';

    public function __construct(
        private readonly QuoteFieldResolver $quoteFields,
        private readonly PartyFieldResolver $partyFields,
        private readonly OrganizationFieldResolver $organizationFields,
        private readonly DynamicFieldResolver $dynamicFields,
    ) {}

    /**
     * Replace every `{category.key}` occurrence in $text with its resolved
     * value for $quote/$actor. Plain text around/between tokens is left
     * untouched. Never leaves a `{...}` residue: an unresolved token becomes
     * an empty string (see class docblock).
     */
    public function substitute(string $text, Quote $quote, User $actor): string
    {
        return (string) preg_replace_callback(
            self::TOKEN_PATTERN,
            fn (array $matches): string => $this->resolve($matches[1], $matches[2], $quote, $actor),
            $text,
        );
    }

    private function resolve(string $category, string $key, Quote $quote, User $actor): string
    {
        return match ($category) {
            'quote' => $this->quoteFields->quote($key, $quote) ?? '',
            'totals' => $this->quoteFields->totals($key, $quote) ?? '',
            'document' => $this->quoteFields->document($key, $actor) ?? '',
            'client' => $this->partyFields->client($key, $quote, $actor) ?? '',
            'referent' => $this->partyFields->referent($key, $quote) ?? '',
            'commercial' => $this->partyFields->commercial($key, $quote) ?? '',
            'reporter' => $this->partyFields->reporter($key, $quote) ?? '',
            'supervisor' => $this->partyFields->supervisor($key, $quote) ?? '',
            'company' => $this->organizationFields->company($key, $quote) ?? '',
            'company_site' => $this->organizationFields->companySite($key, $quote) ?? '',
            'operational_site' => $this->organizationFields->operationalSite($key, $quote) ?? '',
            'custom_fields' => $this->dynamicFields->customField($key, $quote),
            'quote_attributes' => $this->dynamicFields->quoteAttribute($key, $quote),
            default => '',
        };
    }
}
