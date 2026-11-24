<?php

declare(strict_types=1);

namespace App\Support\Amounts;

use App\Models\VatRate;

/**
 * The VAT rate every cost carries by default (user decision 2026-11-24): the
 * legacy QNet applied no VAT to costs, so offer cost lines and work order
 * costs start on the 0% rate named in `work-orders.cost_vat_rate_name`.
 * Looked up by name because its id differs per environment; resolved once
 * per request. Null when that rate does not exist.
 */
final class CostVatRate
{
    public static function resolve(): ?VatRate
    {
        return once(static fn (): ?VatRate => VatRate::query()
            ->where('name', config('work-orders.cost_vat_rate_name'))
            ->where('rate', 0)
            ->orderBy('id')
            ->first(['id', 'name', 'rate']));
    }
}
