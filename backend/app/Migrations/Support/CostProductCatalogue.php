<?php

namespace App\Migrations\Support;

/**
 * Target layout of the legacy cost import (spec 0174, D-1/D-2): the category
 * branch each legacy cost table lands in, and the legacy fields with no native
 * product column, stored as PRODUCT-context attributes of that branch. Pure
 * data — CostCategoryResolver provisions it, CostProductsSource fills it.
 */
final class CostProductCatalogue
{
    /**
     * Root of the whole branch, with no business function: without one a
     * category never pairs into a product line, so a cost item never becomes a
     * REVENUE row (same choice as DemoCostProductCatalogue).
     */
    public const string ROOT = 'Costi';

    public const string ATTRIBUTE_TYPE = 'text';

    /**
     * Legacy table => its branch category under ROOT.
     *
     * @var array<string, string>
     */
    public const array BRANCHES = [
        'products' => 'Articoli',
        'vehicles' => 'Veicoli',
        'equipment' => 'Attrezzature',
        'expense_reports' => 'Note spese',
    ];

    /**
     * Legacy table => record field => attribute (`code` is the natural key,
     * prefixed so it never adopts an unrelated catalogue attribute).
     *
     * @var array<string, array<string, array{code: string, name: string}>>
     */
    public const array ATTRIBUTES = [
        'products' => [
            'brand' => ['code' => 'cost_brand', 'name' => 'Marca'],
            'barcode' => ['code' => 'cost_barcode', 'name' => 'Barcode'],
            'ministerial_code' => ['code' => 'cost_ministerial_code', 'name' => 'Codice ministeriale'],
            'storage_position' => ['code' => 'cost_storage_position', 'name' => 'Posizione magazzino'],
        ],
        'vehicles' => [
            'license_plate' => ['code' => 'cost_license_plate', 'name' => 'Targa'],
        ],
        'equipment' => [
            'brand' => ['code' => 'cost_brand', 'name' => 'Marca'],
            'model' => ['code' => 'cost_model', 'name' => 'Modello'],
            'serial_number' => ['code' => 'cost_serial_number', 'name' => 'Matricola'],
        ],
        'expense_reports' => [],
    ];
}
