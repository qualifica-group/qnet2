<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\DocumentLayoutModule;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;

/**
 * The purchase order email variable catalogue (spec 0210) for
 * `GET /api/email-templates/variables?module=purchase_orders`: the
 * purchase_order, supplier, company, payment and totals categories are the
 * SAME definitions the `purchase_orders` layout catalogue exposes (reused,
 * never redeclared); `sender` is declared here.
 */
final class PurchaseOrderEmailVariableCatalog
{
    /** @var array<int, string> */
    public const array DELEGATED_CATEGORIES = ['purchase_order', 'supplier', 'company', 'payment', 'totals'];

    public function __construct(private readonly DocumentLayoutVariableCatalog $layoutCatalog) {}

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    public function categoriesFor(User $actor): array
    {
        $delegated = array_filter(
            $this->layoutCatalog->categoriesFor(DocumentLayoutModule::PurchaseOrders, $actor),
            static fn (array $category): bool => in_array($category['key'], self::DELEGATED_CATEGORIES, true),
        );

        return [
            ...array_values($delegated),
            [
                'key' => 'sender',
                'label' => __('email_templates.variables.categories.sender'),
                'variables' => [
                    ['variable' => '{sender.name}', 'label' => __('email_templates.variables.sender.name'), 'type' => 'string', 'example' => 'Mario Rossi'],
                    ['variable' => '{sender.email}', 'label' => __('email_templates.variables.sender.email'), 'type' => 'string', 'example' => 'mario.rossi@example.com'],
                ],
            ],
        ];
    }
}
