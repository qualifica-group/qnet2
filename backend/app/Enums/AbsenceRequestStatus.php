<?php

namespace App\Enums;

use App\Enums\Attributes\Color;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of an absence request (spec 0278, D-2): `rejected` and `cancelled` are final.
 */
enum AbsenceRequestStatus: string
{
    use HasMeta;

    #[Label('Draft')]
    #[Color('slate')]
    case Draft = 'draft';

    #[Label('Submitted')]
    #[Color('amber')]
    case Submitted = 'submitted';

    #[Label('Approved')]
    #[Color('green')]
    case Approved = 'approved';

    #[Label('Rejected')]
    #[Color('red')]
    case Rejected = 'rejected';

    #[Label('Cancelled')]
    #[Color('gray')]
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
