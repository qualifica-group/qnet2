<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of a purchase order (spec 0210, D-9). The transition matrix lives here
 * and nowhere else: the services enforce it and the resource exposes it, the
 * frontend only reads `abilities.transitions`.
 */
enum PurchaseOrderStatus: string
{
    use HasMeta;

    #[Label('Draft')]
    case Draft = 'draft';

    #[Label('In validation')]
    case InValidation = 'in_validation';

    #[Label('Approved')]
    case Approved = 'approved';

    #[Label('Sent to supplier')]
    case SentToSupplier = 'sent_to_supplier';

    #[Label('Partially fulfilled')]
    case PartiallyFulfilled = 'partially_fulfilled';

    #[Label('Fulfilled')]
    case Fulfilled = 'fulfilled';

    #[Label('Cancelled')]
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The states an order in this state may move to (D-9). Permissions are
     * applied on top of this matrix by the status service.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::InValidation, self::Approved, self::Cancelled],
            self::InValidation => [self::Draft, self::Approved, self::Cancelled],
            self::Approved => [self::InValidation, self::SentToSupplier, self::Cancelled],
            self::SentToSupplier => [self::PartiallyFulfilled, self::Fulfilled, self::Cancelled],
            self::PartiallyFulfilled => [self::SentToSupplier, self::Fulfilled],
            self::Fulfilled => [self::PartiallyFulfilled],
            self::Cancelled => [self::Draft],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * The order can be emailed to the supplier only once approved, and again
     * after it was sent (spec 0244, D-4).
     */
    public function isSendable(): bool
    {
        return in_array($this, [self::Approved, self::SentToSupplier], true);
    }

    /**
     * Header and lines can be edited, the order deleted or merged only while
     * it is a draft or in validation (D-10).
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::InValidation], true);
    }
}
