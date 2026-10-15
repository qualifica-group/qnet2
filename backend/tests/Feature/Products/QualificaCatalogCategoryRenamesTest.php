<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\QualificaCatalog\SafetyCatalogue;
use Database\Seeders\QualificaCatalog\SoaCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// User directive 2026-10-15: the SOA and Sicurezza subcategories carry their
// branch as prefix. An installation seeded with the former names sees them
// renamed in place on the re-run, never a second, empty node beside them.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/** Former name => current name, as an earlier seed left them. */
const FORMER_CATALOG_NAMES = [
    'Ente SOA' => SoaCatalogue::CERTIFYING_BODY_CATEGORY,
    'Consulenza SOA' => SoaCatalogue::CONSULTING_CATEGORY,
    'Corsi di formazione' => SafetyCatalogue::TRAINING_COURSES,
    'Documenti' => SafetyCatalogue::DOCUMENTS,
    'Visite mediche' => SafetyCatalogue::MEDICAL_VISITS,
    'Sopralluogo' => SafetyCatalogue::SITE_INSPECTIONS,
    'Trasferta' => SafetyCatalogue::TRAVEL,
];

it('renames the former SOA and Sicurezza subcategories in place, keeping ids and products', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $ids = [];
    foreach (FORMER_CATALOG_NAMES as $former => $current) {
        $category = ProductCategory::query()->where('name', $current)->sole();
        $category->update(['name' => $former]);
        $ids[$current] = $category->id;
    }
    $productCount = Product::query()->whereIn('category_id', $ids)->count();

    test()->seed(QualificaCatalogSeeder::class);

    foreach ($ids as $current => $id) {
        expect(ProductCategory::query()->where('name', $current)->sole()->id)->toBe($id);
    }

    expect(ProductCategory::query()->whereIn('name', array_keys(FORMER_CATALOG_NAMES))->exists())->toBeFalse()
        ->and(Product::query()->whereIn('category_id', $ids)->count())->toBe($productCount)
        ->and($productCount)->toBeGreaterThan(0);
});

it('leaves a same-named node outside the branch alone', function (): void {
    $elsewhere = ProductCategory::factory()->create(['name' => 'Documenti']);

    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    expect($elsewhere->fresh()->name)->toBe('Documenti')
        ->and(ProductCategory::query()->where('name', SafetyCatalogue::DOCUMENTS)->count())->toBe(1);
});
