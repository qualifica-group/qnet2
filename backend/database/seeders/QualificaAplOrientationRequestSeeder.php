<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\LayoutSectionVariant;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\AplOrientationRequestCatalogue;
use Database\Seeders\Support\RequestSheetLookups;
use Database\Seeders\Support\RequestSheetSeeder;
use Database\Seeders\Support\RequestSheetWriter;

/**
 * Loads the APL Orientamento specialistico "trattative" of the user's sheet into
 * Gestione Richieste (user directive 2026-11-07, RequestSheetSeeder).
 * Run with `php artisan db:seed --class=QualificaAplOrientationRequestSeeder`.
 */
class QualificaAplOrientationRequestSeeder extends RequestSheetSeeder
{
    use SeedsCategoryAttributes;

    /**
     * "Data App CPI" and "CPI" of the sheet (user decision 2026-11-07): the
     * existing DIL/GOL attributes, assigned to the category for the Offerta.
     */
    private const array CPI_ATTRIBUTES = [
        ['code' => 'data_scelta_cpi', 'name' => 'Data app. CPI', 'type' => 'date'],
        ['code' => 'cpi', 'name' => 'CPI', 'type' => 'text'],
    ];

    private const string CPI_SECTION_ID = 'apl-orientation-cpi';

    public function __construct(
        RequestSheetWriter $writer,
        RequestSheetLookups $lookups,
        private readonly AttributeLayoutService $layouts,
    ) {
        parent::__construct($writer, $lookups);
    }

    protected function label(): string
    {
        return 'APL Orientamento specialistico';
    }

    protected function categoryName(): string
    {
        return AplOrientationRequestCatalogue::CATEGORY;
    }

    /**
     * Every row of the sheet is the one Orientamento specialistico product.
     */
    protected function rows(): array
    {
        return array_map(
            static fn (array $row): array => [...$row, 'product' => AplOrientationRequestCatalogue::PRODUCT],
            AplOrientationRequestCatalogue::ROWS,
        );
    }

    protected function newReporters(): array
    {
        return AplOrientationRequestCatalogue::NEW_REPORTERS;
    }

    protected function attributeColumns(): array
    {
        return ['data_scelta_cpi' => 'cpi_date', 'cpi' => 'cpi'];
    }

    protected function prepare(ProductCategory $category): void
    {
        $this->seedCategoryAttributes($category, self::CPI_ATTRIBUTES, AttributeContext::Quote);
        $this->addCpiSection($category);
    }

    /**
     * Places the two CPI fields in the category's Offerta form, once. A category
     * without a configured layout renders every field already.
     */
    private function addCpiSection(ProductCategory $category): void
    {
        $layout = $this->layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All);

        if ($layout === null || in_array(self::CPI_SECTION_ID, array_column($layout['sections'], 'id'), true)) {
            return;
        }

        $layout['sections'][] = [
            'id' => self::CPI_SECTION_ID,
            'title' => 'CPI',
            'description' => 'Centro per l\'impiego e data dell\'appuntamento.',
            'variant' => LayoutSectionVariant::Default->value,
            'columns' => 2,
            'sort_order' => count($layout['sections']),
            'collapsible' => false,
            'default_collapsed' => false,
            'visible_when' => null,
            'rows' => [[
                'id' => self::CPI_SECTION_ID.'-0',
                'items' => [
                    ['attribute_code' => 'data_scelta_cpi', 'width' => 'half'],
                    ['attribute_code' => 'cpi', 'width' => 'half'],
                ],
            ]],
        ];

        $this->layouts->upsert($category, AttributeContext::Quote, LayoutFormScope::All, $layout);
    }
}
