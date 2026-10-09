<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * State of a purchase request line (spec 0208, D-6). The transition matrix
 * (D-8) lives here and nowhere else: the services enforce it and the resource
 * exposes it, the frontend only reads `abilities.transitions`.
 */
enum PurchaseRequestLineStatus: string
{
    use HasMeta;

    #[Label('Pending approval')]
    case PendingApproval = 'pending_approval';

    #[Label('Approved')]
    case Approved = 'approved';

    #[Label('Ordered')]
    case Ordered = 'ordered';

    #[Label('Received')]
    case Received = 'received';

    #[Label('Rejected')]
    case Rejected = 'rejected';

    #[Label('On hold')]
    case OnHold = 'on_hold';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Terminal states (D-7): a request whose lines are all terminal closes by itself.
     *
     * @return array<int, self>
     */
    public static function terminal(): array
    {
        return [self::Received, self::OnHold, self::Rejected];
    }

    /**
     * @return array<int, string>
     */
    public static function terminalValues(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::terminal());
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    /**
     * The states a line in this state may move to for the given capabilities
     * (union of the slices, D-8).
     *
     * @param  array<int, PurchaseRequestCapability>  $capabilities
     * @return array<int, self>
     */
    public function allowedTransitions(array $capabilities): array
    {
        $allowed = [];

        foreach ($capabilities as $capability) {
            $allowed = [...$allowed, ...$this->transitionsFor($capability)];
        }

        return array_values(array_unique($allowed, SORT_REGULAR));
    }

    /**
     * @return array<int, self>
     */
    private function transitionsFor(PurchaseRequestCapability $capability): array
    {
        return match ($capability) {
            PurchaseRequestCapability::Approve => match ($this) {
                self::PendingApproval => [self::Approved, self::Rejected],
                default => [],
            },
            PurchaseRequestCapability::Fulfill => match ($this) {
                self::Approved => [self::Ordered, self::OnHold],
                self::Ordered => [self::Received, self::OnHold],
                default => [],
            },
            PurchaseRequestCapability::Manage => array_values(array_filter(
                self::cases(),
                fn (self $status): bool => $status !== $this,
            )),
        };
    }
}
