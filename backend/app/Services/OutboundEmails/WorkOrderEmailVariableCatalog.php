<?php

declare(strict_types=1);

namespace App\Services\OutboundEmails;

use App\Enums\DocumentLayoutModule;
use App\Models\User;
use App\Services\DocumentLayouts\DocumentLayoutVariableCatalog;

/**
 * The Commessa email variable catalogue (spec 0175, D-4): every
 * `{category.key}` token an email template's / composer's variable picker
 * may offer, for `GET /api/email-templates/variables?module=work_orders`.
 *
 * `work_order`/`sender` are declared here; every other category (quote,
 * client, opportunity, referent, commercial, reporter, supervisor, company,
 * company_site) is the SAME definition DocumentLayoutVariableCatalog already
 * exposes for the quotes module, reused verbatim (never redeclared) via its
 * public `categoriesFor()` and narrowed to D-4's allow-list — `totals`,
 * `document`, `custom_fields`, `quote_attributes` and `operational_site` are
 * dropped on purpose (D-4).
 */
final class WorkOrderEmailVariableCatalog
{
    private const string TYPE_STRING = 'string';

    private const string TYPE_DATE = 'date';

    /**
     * The DocumentLayoutVariableCatalog quote categories reused by this
     * module (D-4) — every other quote category is dropped.
     *
     * @var array<int, string>
     */
    private const array DELEGATED_QUOTE_CATEGORIES = [
        'quote', 'client', 'opportunity', 'referent', 'commercial', 'reporter', 'supervisor', 'company', 'company_site',
    ];

    public function __construct(private readonly DocumentLayoutVariableCatalog $quoteCatalog) {}

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    public function categoriesFor(User $actor): array
    {
        return [
            $this->workOrderCategory(),
            $this->senderCategory(),
            ...$this->delegatedQuoteCategories($actor),
        ];
    }

    /**
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function workOrderCategory(): array
    {
        return $this->staticCategory('work_order', [
            ['key' => 'code', 'type' => self::TYPE_STRING, 'example' => 'COM-0001'],
            ['key' => 'title', 'type' => self::TYPE_STRING, 'example' => 'Manutenzione impianto'],
            ['key' => 'type', 'type' => self::TYPE_STRING, 'example' => 'Processing'],
            ['key' => 'start_date', 'type' => self::TYPE_DATE, 'example' => '2026-09-01'],
            ['key' => 'callback_date', 'type' => self::TYPE_DATE, 'example' => '2026-09-15'],
            ['key' => 'description', 'type' => self::TYPE_STRING, 'example' => 'Sostituzione componenti usurati'],
        ]);
    }

    /**
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function senderCategory(): array
    {
        return $this->staticCategory('sender', [
            ['key' => 'name', 'type' => self::TYPE_STRING, 'example' => 'Mario Rossi'],
            ['key' => 'email', 'type' => self::TYPE_STRING, 'example' => 'mario.rossi@example.com'],
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}>
     */
    private function delegatedQuoteCategories(User $actor): array
    {
        $categories = $this->quoteCatalog->categoriesFor(DocumentLayoutModule::Quotes, $actor);

        return array_values(array_filter(
            $categories,
            static fn (array $category): bool => in_array($category['key'], self::DELEGATED_QUOTE_CATEGORIES, true),
        ));
    }

    /**
     * @param  array<int, array{key: string, type: string, example: string}>  $definitions
     * @return array{key: string, label: string, variables: array<int, array{variable: string, label: string, type: string, example: string}>}
     */
    private function staticCategory(string $key, array $definitions): array
    {
        return [
            'key' => $key,
            'label' => __("email_templates.variables.categories.{$key}"),
            'variables' => array_map(
                fn (array $definition): array => $this->variable($key, $definition['key'], $definition['type'], $definition['example']),
                $definitions,
            ),
        ];
    }

    /**
     * @return array{variable: string, label: string, type: string, example: string}
     */
    private function variable(string $category, string $key, string $type, string $example): array
    {
        return [
            'variable' => "{{$category}.{$key}}",
            'label' => __("email_templates.variables.{$category}.{$key}"),
            'type' => $type,
            'example' => $example,
        ];
    }
}
