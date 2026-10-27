<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

/**
 * "Previsione commerciale" columns of the `request-management` grid (spec
 * 0313, D-6): the Opportunity's forecast trio, reached through
 * `quote.opportunity` — real `opportunities` columns, not `quotes` ones, so
 * sorting and filtering are DERIVED by RequestRelationColumns
 * (OPPORTUNITY_SCALAR_COLUMNS) and `hasFilterValues: false` skips the generic
 * distinct-values fallback, exactly like `general_notes`.
 *
 * Display-only in the cell (the work panel edits them) and hidden by default:
 * the direttiva utente 2026-09-17 fixed the visible worklist, these join the
 * column chooser.
 */
final class RequestForecastColumns
{
    /**
     * @var array<int, string>
     */
    public const array COLUMN_IDS = ['estimated_value', 'success_probability', 'expected_close_date'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function declarations(): array
    {
        return [
            self::column('estimated_value', 'requestManagement.columns.estimatedValue', 'number', 'number'),
            self::column('success_probability', 'requestManagement.columns.successProbability', 'number', 'number'),
            self::column('expected_close_date', 'requestManagement.columns.expectedCloseDate', 'date', 'date'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function column(string $id, string $label, string $type, string $filterType): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => $type,
            'visible' => false,
            'sortable' => true,
            'filterable' => true,
            'filterType' => $filterType,
            'hasFilterValues' => false,
        ];
    }
}
