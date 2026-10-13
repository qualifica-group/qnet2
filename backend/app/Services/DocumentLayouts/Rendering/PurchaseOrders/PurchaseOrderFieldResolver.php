<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\OrganizationFieldResolver;
use App\Services\DocumentLayouts\Rendering\PartyFieldResolver;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;
use App\Support\AddressLabel;
use App\Support\OperationalSiteLabel;
use Illuminate\Support\Carbon;

/**
 * Resolves the `purchase_orders` module's variable categories (spec 0210) for
 * a PurchaseOrder. The supplier and the issuing site reuse the shared
 * resolvers (PartyFieldResolver / OrganizationFieldResolver), so PII masking
 * and address formatting are shared, not duplicated. An unknown category/key
 * is ''.
 */
final class PurchaseOrderFieldResolver
{
    /** `supplier.*` keys that map 1:1 onto the Registry-backed `client.*` resolution. */
    private const array SUPPLIER_KEYS = ['name', 'vat_number', 'address', 'email'];

    public function __construct(
        private readonly PartyFieldResolver $partyFields,
        private readonly OrganizationFieldResolver $organizationFields,
    ) {}

    public function resolve(string $category, string $key, PurchaseOrder $order, User $actor): string
    {
        return match ($category) {
            'purchase_order' => $this->order($key, $order),
            'supplier' => $this->supplier($key, $order, $actor),
            'company' => $this->company($key, $order),
            'company_site' => $this->organizationFields->siteField($key, $order->companySite) ?? '',
            'operational_site' => $key === 'label' ? OperationalSiteLabel::compose($order->operationalSite?->primaryAddress) : '',
            'payment' => $key === 'method_name' ? ValueFormatter::text($order->paymentMethod?->name) : '',
            'customer' => $key === 'name' ? ValueFormatter::text($order->customer?->name) : '',
            'work_order' => $this->workOrder($key, $order),
            'totals' => $this->totals($key, $order),
            'document' => $this->document($key, $actor),
            default => '',
        };
    }

    private function order(string $key, PurchaseOrder $order): string
    {
        return match ($key) {
            'code' => $order->number === null ? '' : $order->code(),
            'order_date' => ValueFormatter::date($order->order_date),
            'description' => ValueFormatter::text($order->description),
            'general_notes' => ValueFormatter::text($order->general_notes),
            default => '',
        };
    }

    private function supplier(string $key, PurchaseOrder $order, User $actor): string
    {
        if (! in_array($key, self::SUPPLIER_KEYS, true)) {
            return '';
        }

        return $this->partyFields->registryField($key, $order->supplier, $actor) ?? '';
    }

    private function company(string $key, PurchaseOrder $order): string
    {
        $company = $order->company;

        return match ($key) {
            'name' => ValueFormatter::text($company?->denomination),
            'vat_number' => ValueFormatter::text($company?->vat_number),
            'address' => AddressLabel::singleLine($company?->primaryAddress),
            default => '',
        };
    }

    private function workOrder(string $key, PurchaseOrder $order): string
    {
        return match ($key) {
            'code' => ValueFormatter::text($order->workOrder?->code),
            'title' => ValueFormatter::text($order->workOrder?->title),
            default => '',
        };
    }

    private function totals(string $key, PurchaseOrder $order): string
    {
        return match ($key) {
            'net' => ValueFormatter::currency($order->taxable_total),
            'vat' => ValueFormatter::currency($order->vat_total),
            'total' => ValueFormatter::currency($order->grand_total),
            default => '',
        };
    }

    private function document(string $key, User $actor): string
    {
        return match ($key) {
            'generated_at' => ValueFormatter::date(Carbon::now()),
            'generated_by' => ValueFormatter::text($actor->name),
            default => '',
        };
    }
}
