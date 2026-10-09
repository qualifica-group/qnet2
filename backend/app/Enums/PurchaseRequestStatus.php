<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of a purchase request (spec 0208, D-6): open until every line reaches a
 * terminal state or it is closed by hand. A closed request is never reopened.
 */
enum PurchaseRequestStatus: string
{
    use HasMeta;

    #[Label('Open')]
    case Open = 'open';

    #[Label('Closed')]
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
