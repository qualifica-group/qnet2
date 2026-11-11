<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaDilAssociationDateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// "DIL" gets "Data associazione" (`stato_assoc_cpi`) in "Dati Lavorazione
// Contatto" (task 427520); an installation seeded before converges through
// QualificaCatalogSeeder or the one-shot seeder.

uses(RefreshDatabase::class);

function dilContactProcessingCodes(AttributeLayoutService $service, ProductCategory $dil): array
{
    $sections = collect($service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All)['sections']);

    return array_merge(...array_map(
        static fn (array $row): array => array_column($row['items'], 'attribute_code'),
        $sections->firstWhere('id', 'contact-processing')['rows'],
    ));
}

it('a fresh installation shows "Data associazione" right after "Residuo Ore" on DIL, reusing the one attribute', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $codes = dilContactProcessingCodes(app(AttributeLayoutService::class), $dil);

    expect(Attribute::query()->where('code', 'stato_assoc_cpi')->count())->toBe(1)
        ->and($codes)->toContain('stato_assoc_cpi')
        ->and(array_search('stato_assoc_cpi', $codes, true))->toBe(array_search('remaining_hours', $codes, true) + 1);
});

it('converges an installation seeded before "Data associazione" joined "DIL" on a re-run of the catalogue', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $service = app(AttributeLayoutService::class);
    rewindDilBeforeAssociationDate($dil);

    test()->seed(QualificaCatalogSeeder::class);

    expect(dilContactProcessingCodes($service, $dil))->toContain('stato_assoc_cpi');
});

it('the one-shot seeder adds only the field to "DIL", keeping hand edits, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();
    $service = app(AttributeLayoutService::class);
    rewindDilBeforeAssociationDate($dil);
    $blob = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);
    $blob['sections'][0]['title'] = 'Lavorazione (rinominata a mano)';
    $service->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, $blob);
    $attributeId = Attribute::query()->where('code', 'stato_assoc_cpi')->value('id');
    $otherAssignments = DB::table('attribute_category')->where('attribute_id', '!=', $attributeId)->count();
    $attributesBefore = Attribute::query()->count();
    $trainingAssignment = fn (): int => DB::table('attribute_category')->where('attribute_id', $attributeId)->count();
    $assignedBefore = $trainingAssignment();

    test()->seed(QualificaDilAssociationDateSeeder::class);
    $afterFirst = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);
    test()->seed(QualificaDilAssociationDateSeeder::class);
    $afterSecond = $service->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);

    $codes = dilContactProcessingCodes($service, $dil);

    expect($afterSecond)->toBe($afterFirst)
        ->and($afterSecond['sections'][0]['title'])->toBe('Lavorazione (rinominata a mano)')
        ->and(array_search('stato_assoc_cpi', $codes, true))->toBe(array_search('remaining_hours', $codes, true) + 1)
        ->and(array_count_values($codes)['stato_assoc_cpi'])->toBe(1)
        ->and(Attribute::query()->count())->toBe($attributesBefore)
        ->and($trainingAssignment())->toBe($assignedBefore + 1)
        ->and(DB::table('attribute_category')->where('attribute_id', '!=', $attributeId)->count())->toBe($otherAssignments)
        ->and(DB::table('attribute_category')->where('attribute_id', $attributeId)->where('category_id', $dil->id)->where('context', AttributeContext::Quote->value)->count())->toBe(1);
});
