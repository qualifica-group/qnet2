<?php

namespace App\Imports\Leads;

/**
 * The rich outcome of `LeadDuplicateMatcher::match()` (spec 0036, spec 0041
 * D-1): the matched Registry's id/display name plus every channel (email/
 * phone/mobile/tax_code/vat_number) that ALSO matches it, cumulative when more than one
 * applies — backs both `LeadsImportDefinition::resolveDuplicate()` (the
 * legacy id) and `resolveDuplicateMatch()`'s `import_run_rows.duplicate_meta`.
 */
final readonly class LeadDuplicateMatch
{
    /**
     * @param  array<int, string>  $matchedOn  subset of ["email","phone","mobile","tax_code","vat_number"], in that order
     */
    public function __construct(
        public int $registryId,
        public string $registryName,
        public array $matchedOn,
    ) {}
}
