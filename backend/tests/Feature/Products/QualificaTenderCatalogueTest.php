<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\TenderCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Gare" branch (user directive 2026-10-20, "GESTIONE RICHIESTE SETTORE
// GARE" sheet and "SERVIZI GARE" list): a root linked to the "Gare" business
// function, worked like "Consulenza", one subcategory per product line, the
// documents each line asks for as offer flags and the 33 listed products
// under the "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return Collection<int, Product>
 */
function tenderProducts(): Collection
{
    return Product::query()
        ->with(['category', 'productTypology', 'vatRate'])
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', TenderCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get();
}

/**
 * @return list<string>
 */
function tenderQuoteCodes(string $categoryName): array
{
    $category = ProductCategory::query()->where('name', $categoryName)->sole();

    return app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all();
}

it('seeds the Gare root with its twelve selectable subcategories, the Gare function and the Consulenza rules, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', TenderCatalogue::ROOT)->sole();
    $function = BusinessFunction::query()->where('name', TenderCatalogue::BUSINESS_FUNCTION)->sole();

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->all())
        ->toEqualCanonicalizing([
            'Gare - Albo Fornitori', 'Gare - Albo Gestori Ambientali', 'Gare - ANAC', 'Gare - Avvalimenti',
            'Gare - Consorzio Grade', 'Gare - Corso preparazione gare', 'Gare - Gare', 'Gare - Giornale Telematico',
            'Gare - Mepa', 'Gare - Progetto Tecnico', 'Gare - RTI/ATI', 'Gare - WHITE LIST',
        ]);

    foreach (TenderCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();

        expect($category->is_selectable)->toBeTrue($name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($function->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives each line only the documents the sheet lists for it, as offer flags with their own form, and no Commessa field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $tenderDocuments = ['tender_attachments', 'tender_company_report', 'tender_director_id_document', 'tender_last_financial_statement', 'tender_notice_and_rules', 'tender_soa_certificate', 'tender_white_list_registration'];
    $registrationDocuments = ['tender_company_report', 'tender_director_id_document', 'tender_iso_certifications', 'tender_last_three_financial_statements', 'tender_majority_shareholders_id_documents', 'tender_soa_certificate'];

    expect(tenderQuoteCodes(TenderCatalogue::RELIANCE))->toBe($tenderDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::TENDERS))->toBe($tenderDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::TECHNICAL_PROPOSAL))->toBe($tenderDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::MEPA))->toBe($registrationDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::SUPPLIER_REGISTERS))->toBe($registrationDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::ANAC))->toBe($registrationDocuments)
        ->and(tenderQuoteCodes(TenderCatalogue::WHITE_LIST))->toBe(['tender_company_report', 'tender_director_id_document', 'tender_family_members_statement'])
        ->and(tenderQuoteCodes(TenderCatalogue::TENDER_JOURNAL))->toBe(['tender_company_report'])
        ->and(tenderQuoteCodes(TenderCatalogue::TRAINING_COURSE))->toBe(['tender_company_report_or_id_document'])
        // The sheet asks nothing for these three.
        ->and(tenderQuoteCodes(TenderCatalogue::JOINT_VENTURES))->toBe([])
        ->and(tenderQuoteCodes(TenderCatalogue::GRADE_CONSORTIUM))->toBe([])
        ->and(tenderQuoteCodes(TenderCatalogue::ENVIRONMENTAL_OPERATORS_REGISTER))->toBe([])
        ->and(Attribute::query()->where('code', 'like', 'tender%')->pluck('type')->unique()->values()->all())->toBe(['boolean'])
        ->and(Attribute::query()->where('code', 'tender_company_report')->sole()->name)->toBe('Visura camerale aggiornata');

    foreach (array_keys(TenderCatalogue::QUOTE_FORMS) as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();
        $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];
        $placed = collect($sections[0]['rows'])->flatMap(fn (array $row): array => array_column($row['items'], 'attribute_code'))->sort()->values()->all();

        expect(array_column($sections, 'title'))->toBe(['Documenti da allegare'])
            ->and($placed)->toBe(tenderQuoteCodes($name))
            ->and(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::WorkOrder)->all())->toBe([]);
    }
});

it('seeds the 33 listed products with their price, 22% VAT, the Consulenza typology and "Mesi scadenza" 0, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $products = tenderProducts();

    expect($products)->toHaveCount(33)
        ->and($products->countBy('category.name')->all())->toEqual([
            TenderCatalogue::SUPPLIER_REGISTERS => 1,
            TenderCatalogue::ENVIRONMENTAL_OPERATORS_REGISTER => 1,
            TenderCatalogue::ANAC => 1,
            TenderCatalogue::RELIANCE => 2,
            TenderCatalogue::GRADE_CONSORTIUM => 1,
            TenderCatalogue::TRAINING_COURSE => 1,
            TenderCatalogue::TENDERS => 15,
            TenderCatalogue::TENDER_JOURNAL => 1,
            TenderCatalogue::MEPA => 6,
            TenderCatalogue::TECHNICAL_PROPOSAL => 1,
            TenderCatalogue::JOINT_VENTURES => 2,
            TenderCatalogue::WHITE_LIST => 1,
        ])
        ->and($products->pluck('productTypology.code')->unique()->values()->all())->toBe(['consultancy'])
        ->and($products->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and(ProductSupplier::query()->exists())->toBeFalse()
        ->and((float) $products->firstWhere('name', 'Adesione Consorzio Grade')->price)->toBe(3900.0)
        ->and((float) $products->firstWhere('name', 'MePa_ Iscrizione al Portale + Abilitazione a N°1 Bando')->price)->toBe(250.0)
        ->and($products->map(fn (Product $product): mixed => $product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null)->unique()->values()->all())->toBe([0]);
});
