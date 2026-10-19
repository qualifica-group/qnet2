<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of an absence request (spec 0278, D-2): `rejected` and `cancelled` are final.
 */
enum AbsenceRequestStatus: string
{
    use HasMeta;

    #[Label('Draft')]
    case Draft = 'draft';

    #[Label('Submitted')]
    case Submitted = 'submitted';

    #[Label('Approved')]
    case Approved = 'approved';

    #[Label('Rejected')]
    case Rejected = 'rejected';

    #[Label('Cancelled')]
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
