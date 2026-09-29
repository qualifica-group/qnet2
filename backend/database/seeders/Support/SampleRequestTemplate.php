<?php

namespace Database\Seeders\Support;

/**
 * One request as the REAL write path left it — its rows, table by table,
 * exactly as read back from the database — for the bulk sample seeder to copy.
 * Ids are kept where a copy has to remap them (the template's own lines, its
 * managers); `productNames` are what its title and its opportunity's name are
 * composed from.
 */
final readonly class SampleRequestTemplate
{
    /**
     * @param  array<string, mixed>  $opportunity  the `opportunities` row
     * @param  list<array<string, mixed>>  $productLines  its `opportunity_product_lines` rows
     * @param  list<array<string, mixed>>  $opportunityManagers  its `opportunity_user` rows, by position
     * @param  array<string, mixed>  $quote  the `quotes` row
     * @param  list<array<string, mixed>>  $quoteManagers  its `quote_user` rows, by position
     * @param  list<array<string, mixed>>  $lines  its `quote_lines` rows, by id
     * @param  list<array<string, mixed>>  $commissions  the `quote_line_commissions` rows of those lines
     * @param  list<string>  $productNames
     */
    public function __construct(
        public array $opportunity,
        public array $productLines,
        public array $opportunityManagers,
        public array $quote,
        public array $quoteManagers,
        public array $lines,
        public array $commissions,
        public array $productNames,
    ) {}
}
