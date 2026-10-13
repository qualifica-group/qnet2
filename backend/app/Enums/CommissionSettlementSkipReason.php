<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Why a settlement run left an (agent, company) pair out (spec 0213, D-6):
 * the movements of a skipped pair stay open and are carried to the next run.
 */
enum CommissionSettlementSkipReason: string
{
    use HasMeta;

    #[Label('A draft already exists')]
    case DraftExists = 'draft_exists';

    #[Label('Non-positive balance')]
    case NonPositive = 'non_positive';

    #[Label('Below the minimum amount')]
    case BelowThreshold = 'below_threshold';

    #[Label('No valid fiscal profile')]
    case MissingProfile = 'missing_profile';

    #[Label('Missing ENASARCO parameters')]
    case MissingEnasarcoParameters = 'missing_enasarco_parameters';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
