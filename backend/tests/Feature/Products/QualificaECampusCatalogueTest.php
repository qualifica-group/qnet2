<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\QualificaCatalog\ECampusCourseCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The e-Campus degree catalogue (user directive 2026-10-01): the
// "Corsi E-Campus" branch under Formazione, its two degree levels, their
// subject areas and one product per fee of each course.
uses(RefreshDatabase::class);

function eCampusChild(string $name, ?int $parentId): ProductCategory
{
    return ProductCategory::query()->where('name', $name)->where('parent_id', $parentId)->sole();
}

/** @return array<string, float> product name => price */
function eCampusProductsOf(ProductCategory $area): array
{
    return Product::query()
        ->where('category_id', $area->id)
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Product $product): array => [$product->name => (float) $product->price])
        ->all();
}

it('seeds the e-Campus branch, its degree fees and its products correctly and idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: (name, parent) keys, no duplicates.

    $formazione = eCampusChild('Formazione', null);
    $branch = eCampusChild(ECampusCourseCatalogue::CATEGORY, $formazione->id);
    $bachelor = eCampusChild('Corsi di Laurea Triennali', $branch->id);
    $master = eCampusChild('Corsi di Laurea Magistrali', $branch->id);

    // Containers down to the degree level, areas selectable (spec 0074) and
    // suffixed with their degree level, so the two "Ingegneria" read apart.
    expect($branch->is_selectable)->toBeFalse()
        ->and($bachelor->is_selectable)->toBeFalse()
        ->and($master->is_selectable)->toBeFalse()
        ->and(ProductCategory::query()->where('parent_id', $branch->id)->count())->toBe(2)
        ->and(ProductCategory::query()->where('parent_id', $bachelor->id)->orderBy('name')->pluck('name')->all())
        ->toBe([
            'Economia - Corsi di Laurea Triennali', 'Giurisprudenza - Corsi di Laurea Triennali',
            'Ingegneria - Corsi di Laurea Triennali', 'Letteratura - Corsi di Laurea Triennali',
            'Psicologia - Corsi di Laurea Triennali',
        ])
        ->and(ProductCategory::query()->where('parent_id', $master->id)->orderBy('name')->pluck('name')->all())
        ->toBe([
            'Economia - Corsi di Laurea Magistrali', 'Ingegneria - Corsi di Laurea Magistrali',
            'Letteratura - Corsi di Laurea Magistrali', 'Psicologia - Corsi di Laurea Magistrali',
        ])
        ->and(ProductCategory::query()->whereIn('parent_id', [$bachelor->id, $master->id])->where('is_selectable', false)->exists())
        ->toBeFalse();

    // One product per fee, named before the sheet's colon, priced at the fee.
    expect(eCampusProductsOf(eCampusChild('Economia - Corsi di Laurea Triennali', $bachelor->id)))->toBe([
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] PROGETTO FORM' => 1500.0,
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] ASSISTENZA E TUTORAGGIO' => 500.0,
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] 1°ANNO' => 2856.0,
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] 2°ANNO' => 2856.0,
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] 3°ANNO' => 2856.0,
        'Scienze del Turismo per il Management e i Beni Culturali [L-15] TESI' => 300.0,
        'Economia [L-33] PROGETTO FORM' => 1500.0,
        'Economia [L-33] ASSISTENZA E TUTORAGGIO' => 500.0,
        'Economia [L-33] 1°ANNO' => 2856.0,
        'Economia [L-33] 2°ANNO' => 2856.0,
        'Economia [L-33] 3°ANNO' => 2856.0,
        'Economia [L-33] TESI' => 300.0,
    ])->and(eCampusProductsOf(eCampusChild('Economia - Corsi di Laurea Magistrali', $master->id)))->toBe([
        'Scienze dell\'Economia [LM-56] PROGETTO FORM' => 1500.0,
        'Scienze dell\'Economia [LM-56] ASSISTENZA E TUTORAGGIO' => 500.0,
        'Scienze dell\'Economia [LM-56] 1°ANNO' => 3056.0,
        'Scienze dell\'Economia [LM-56] 2°ANNO' => 3056.0,
        'Scienze dell\'Economia [LM-56] TESI' => 300.0,
    ]);

    // 15 bachelor courses x 6 fees + 10 master courses x 5 fees.
    $products = Product::query()
        ->whereIn('category_id', ProductCategory::query()->whereIn('parent_id', [$bachelor->id, $master->id])->select('id'))
        ->get();

    expect($products)->toHaveCount(140)
        ->and($products->every(fn (Product $product): bool => $product->product_type === ProductType::Service
            && (float) $product->cost === 0.0
            && $product->vat_rate_id === null))->toBeTrue();
});
