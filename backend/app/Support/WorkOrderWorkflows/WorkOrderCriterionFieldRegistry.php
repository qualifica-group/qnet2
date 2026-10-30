<?php

declare(strict_types=1);

namespace App\Support\WorkOrderWorkflows;

use App\Models\WorkOrder;
use App\Support\QuoteWorkflows\CategoryBranchResolver;
use InvalidArgumentException;

/**
 * Centralized allow-list of the "criterion fields" a work order workflow may
 * be matched on (spec 0322, D-8). The single source of truth consumed by the
 * resolver (App\Services\WorkOrders\WorkOrderWorkflowResolver) and the
 * FormRequest validating criteria payloads — `field`/`existsTable()` values
 * only ever come from THIS registry, never from raw request input.
 *
 * Native fields only: `source_id` (the offer's own since spec 0332) and
 * custom fields (inherited from the opportunity) are ambiguous on
 * multi-offer commesse, so they are excluded.
 * All three are multi-valued and evaluated on the REVENUE lines scheduled in
 * the commessa (WorkOrderClassificationSource).
 */
final class WorkOrderCriterionFieldRegistry
{
    /**
     * The one field whose match is NOT an exact category equality: it matches
     * the line's own category or any ancestor of it (as on the offers, spec
     * 0092). Public because the resolver needs it for the closest-branch
     * tie-break.
     */
    public const string BRANCH_FIELD = 'product_category_branch_id';

    /**
     * @var array<string, array{for_select_resource: string, table: string}>
     */
    private const array FIELDS = [
        'business_function_id' => [
            'for_select_resource' => 'business-functions',
            'table' => 'business_functions',
        ],
        'product_category_id' => [
            'for_select_resource' => 'product-categories',
            'table' => 'product_categories',
        ],
        self::BRANCH_FIELD => [
            'for_select_resource' => 'product-category-branches',
            'table' => 'product_categories',
        ],
    ];

    public function __construct(
        private readonly WorkOrderClassificationSource $classification,
        private readonly CategoryBranchResolver $branchResolver,
    ) {}

    /**
     * The allow-list, shaped for GET /api/work-order-workflows/criterion-fields.
     *
     * @return array<int, array{field: string, label: string, source: 'native', for_select_resource: string, multi_valued: true, inherited: false}>
     */
    public function allowedFields(): array
    {
        return array_map(
            static fn (string $field, array $definition): array => [
                'field' => $field,
                'label' => "workOrderWorkflows.criterionFields.{$field}",
                'source' => 'native',
                'for_select_resource' => $definition['for_select_resource'],
                'multi_valued' => true,
                'inherited' => false,
            ],
            array_keys(self::FIELDS),
            self::FIELDS,
        );
    }

    /**
     * @return array<int, string>
     */
    public function fieldNames(): array
    {
        return array_keys(self::FIELDS);
    }

    public function isAllowed(string $field): bool
    {
        return array_key_exists($field, self::FIELDS);
    }

    /**
     * The DB table backing `value_id` for $field, for a `Rule::exists()`
     * check — only an already-allow-listed $field is accepted.
     */
    public function existsTable(string $field): string
    {
        return (self::FIELDS[$field] ?? throw $this->unknownField($field))['table'];
    }

    /**
     * The distinct value(s) $workOrder carries for $field: its classifying
     * categories, their effective business functions, or those categories
     * widened to every ancestor for the branch field. Assumes
     * `quoteLines.product` is already eager-loaded by the caller.
     *
     * @return array<int, int>
     */
    public function workOrderValues(WorkOrder $workOrder, string $field): array
    {
        return match ($field) {
            'business_function_id' => $this->classification->businessFunctionIds($workOrder),
            'product_category_id' => $this->classification->categoryIds($workOrder),
            self::BRANCH_FIELD => array_keys($this->branchDistances($workOrder)),
            default => throw $this->unknownField($field),
        };
    }

    /**
     * How far up the category tree each branch value sits from the
     * commessa's classifying categories (0 = the category itself).
     *
     * @return array<int, int>
     */
    public function branchDistances(WorkOrder $workOrder): array
    {
        return $this->branchResolver->distancesForCategoryIds($this->classification->categoryIds($workOrder));
    }

    private function unknownField(string $field): InvalidArgumentException
    {
        return new InvalidArgumentException("Unknown criterion field [{$field}].");
    }
}
