<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Priority of a purchase request (spec 0208, D-6).
 */
enum PurchaseRequestPriority: string
{
    use HasMeta;

    #[Label('Low')]
    case Low = 'low';

    #[Label('Medium')]
    case Medium = 'medium';

    #[Label('High')]
    case High = 'high';

    #[Label('Urgent')]
    case Urgent = 'urgent';

    #[Label('Critical')]
    case Critical = 'critical';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
