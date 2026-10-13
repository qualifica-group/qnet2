<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A registered invoice (type=invoice, external number on Fatture in Cloud) is
 * frozen: no full edit, no delete; its external number/date change only with
 * `invoices.downgrade` (spec 0243, D-3).
 */
final class RegisteredInvoiceGuard
{
    public const string DOWNGRADE_PERMISSION = 'invoices.downgrade';

    public function assertEditable(Invoice $invoice): void
    {
        if ($invoice->isRegistered()) {
            abort(409, __('invoices.registered_locked'));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes  details attributes, external number/date already normalized
     */
    public function assertExternalReferenceChangeAllowed(Invoice $invoice, array $attributes, User $actor): void
    {
        if (! $invoice->isRegistered() || $actor->can(self::DOWNGRADE_PERMISSION)) {
            return;
        }

        if ($this->changes($attributes, 'external_number', $invoice->external_number)
            || $this->changes($attributes, 'external_date', $invoice->external_date?->toDateString())) {
            abort(403, __('invoices.downgrade_forbidden'));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function changes(array $attributes, string $key, ?string $stored): bool
    {
        if (! array_key_exists($key, $attributes)) {
            return false;
        }

        $incoming = $attributes[$key];

        if ($key === 'external_date' && filled($incoming)) {
            $incoming = CarbonImmutable::parse($incoming)->toDateString();
        }

        return ($incoming === '' ? null : $incoming) !== $stored;
    }
}
