<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\PurchaseOrders;

use App\Enums\DocumentLayoutModule;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\DocumentLayouts\Rendering\DocumentRenderSubject;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;

/**
 * The `purchase_orders` module's render subject (spec 0210): wraps a
 * PurchaseOrder and exposes its variables and the `purchase_order_lines`
 * products_table source (keys = DocumentLayoutProductSources). Build it with ::for().
 */
final class PurchaseOrderRenderSubject implements DocumentRenderSubject
{
    /**
     * Every relation the resolver and rows read (preventLazyLoading is active
     * outside production). Already-loaded relations (the in-memory preview
     * sample) are left untouched by loadMissing.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'company.addresses.city',
        'company.addresses.province',
        'company.addresses.state',
        'company.addresses.country',
        'supplier.personalData.contacts',
        'supplier.personalData.addresses.city',
        'supplier.personalData.addresses.province',
        'supplier.personalData.addresses.state',
        'supplier.personalData.addresses.country',
        'customer',
        'paymentMethod',
        'workOrder',
        'companySite.personalData.addresses.city',
        'companySite.personalData.addresses.province',
        'companySite.personalData.addresses.state',
        'companySite.personalData.addresses.country',
        'companySite.banks',
        'operationalSite.addresses.city',
        'lines.costType',
        'lines.unitOfMeasure',
        'lines.vatRate',
    ];

    public function __construct(
        private readonly PurchaseOrder $order,
        private readonly PurchaseOrderFieldResolver $fieldResolver,
    ) {}

    public static function for(PurchaseOrder $order): self
    {
        return app()->make(self::class, ['order' => $order]);
    }

    public function module(): DocumentLayoutModule
    {
        return DocumentLayoutModule::PurchaseOrders;
    }

    public function prepare(): void
    {
        $this->order->loadMissing(self::DETAIL_RELATIONS);
    }

    public function resolveVariable(string $category, string $key, User $actor): string
    {
        return $this->fieldResolver->resolve($category, $key, $this->order, $actor);
    }

    public function productRows(string $source): array
    {
        return $this->order->lines
            ->map(fn (PurchaseOrderLine $line): array => $this->lineRow($line))
            ->values()->all();
    }

    /**
     * @return array<string, string>
     */
    private function lineRow(PurchaseOrderLine $line): array
    {
        return [
            'description' => ValueFormatter::text($line->description),
            'cost_type' => ValueFormatter::text($line->costType?->name),
            'unit_of_measure' => ValueFormatter::text($line->unitOfMeasure?->symbol ?: $line->unitOfMeasure?->name),
            'quantity' => ValueFormatter::quantity($line->quantity),
            'unit_price' => ValueFormatter::currency($line->unit_price),
            'vat_rate' => ValueFormatter::vatRate($line->vatRate),
            'taxable_amount' => ValueFormatter::currency($line->taxable_amount),
            'vat_amount' => ValueFormatter::currency($line->vat_amount),
            'total_amount' => ValueFormatter::currency($line->total_amount),
        ];
    }
}
