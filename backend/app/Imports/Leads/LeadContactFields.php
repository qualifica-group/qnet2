<?php

namespace App\Imports\Leads;

use App\Enums\ContactTypeEnum;

/**
 * Single source of truth for the mappable field ids the leads import wizard
 * treats as Registry contact channels (spec 0033 D-decision: "match su
 * un'Anagrafica esistente per email/telefono", spec 0041 D-1; the `mobile`
 * field was folded into `phone` by spec 0139),
 * shared by LeadsImportDefinition::validateRow(), LeadDuplicateMatcher and
 * LeadProfileBuilder so the 3 places can never drift.
 */
final class LeadContactFields
{
    /**
     * @return array<string, ContactTypeEnum>
     */
    public static function map(): array
    {
        return [
            'email' => ContactTypeEnum::Email,
            'phone' => ContactTypeEnum::Phone,
        ];
    }
}
