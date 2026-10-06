<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\DocumentLayoutModule;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;

/**
 * The invoice email variable catalogue (spec 0195, D-13) for
 * `GET /api/email-templates/variables?module=invoices`: the invoice,
 * customer, company, payment and totals categories are the SAME definitions
 * the invoices layout catalogue exposes (reused, never redeclared);
 * `sender` and `reminder` are declared here.
 */
final class InvoiceEmailVariableCatalog
{
    /** @var array<int, string> */
    public const array DELEGATED_CATEGORIES = ['invoice', 'customer', 'company', 'payment', 'totals'];

    public function __construct(private readonly DocumentLayoutVariableCatalog $layoutCatalog) {}

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    public function categoriesFor(User $actor): array
    {
        $delegated = array_filter(
            $this->layoutCatalog->categoriesFor(DocumentLayoutModule::Invoices, $actor),
            static fn (array $category): bool => in_array($category['key'], self::DELEGATED_CATEGORIES, true),
        );

        return [
            ...array_values($delegated),
            $this->category('sender', [
                ['name', 'string', 'Mario Rossi'],
                ['email', 'string', 'mario.rossi@example.com'],
            ]),
            $this->category('reminder', [
                ['overdue_installments', 'string', '<ul><li>...</li></ul>'],
                ['overdue_amount', 'currency', '1.025,00'],
            ]),
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $definitions
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function category(string $key, array $definitions): array
    {
        return [
            'key' => $key,
            'label' => __("email_templates.variables.categories.{$key}"),
            'variables' => array_map(fn (array $definition): array => [
                'variable' => "{{$key}.{$definition[0]}}",
                'label' => __("email_templates.variables.{$key}.{$definition[0]}"),
                'type' => $definition[1],
                'example' => $definition[2],
            ], $definitions),
        ];
    }
}
