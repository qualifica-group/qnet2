<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Computed payment status of an invoice (spec 0194, D-13); never persisted.
 */
enum InvoicePaymentStatus: string
{
    use HasMeta;

    /** Days past the due date after which an unpaid invoice is seriously overdue (legacy threshold). */
    public const int SERIOUSLY_OVERDUE_DAYS = 21;

    #[Label('Paid')]
    case Paid = 'paid';

    #[Label('Not due')]
    case NotDue = 'not_due';

    #[Label('Overdue')]
    case Overdue = 'overdue';

    #[Label('Seriously overdue')]
    case SeriouslyOverdue = 'seriously_overdue';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
