<?php

namespace Database\Seeders\QualificaCatalog;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;

/**
 * Every product the Qualifica catalogue seeds. Split out of
 * QualificaCatalogSeeder (which stayed at its size limit, engineering.md §6) —
 * it holds the three product sources AND the single upsert they share, since
 * the two are meaningless apart. The category tree they file onto is still the
 * seeder's, and must exist before seed() runs.
 *
 * Three sources, one shape (a SERVICE product on a catalogue node):
 *   - the GOL courses (TrainingCourseCatalogue): one per row, in its own
 *     region's `GOL - <Regione>` category, priced 0 — a funded course is not
 *     sold to the learner;
 *   - the self-funded courses (SelfFundedCourseCatalogue): one per row under
 *     the single "Autofinanziato" subcategory, with its list price;
 *   - the single-offer categories (SINGLE_OFFER_CATEGORIES): one product
 *     named after the category itself, filed directly on it.
 *
 * NO ATTRIBUTE VALUE IS WRITTEN (user directive 2026-09-08). The duration and
 * the delivery mode used to be seeded here, onto the product; they moved to
 * the OFFERTA together with the "Dati Aula" set
 * (QualificaCatalog\CourseDataAttributeCatalogue), so the codes are no longer
 * part of a product's applicable set and ProductService would reject them.
 * A course's hours survive in TrainingCourseCatalogue only as the
 * discriminator of a repeated name — see disambiguate().
 *
 * Idempotent and non-destructive: the natural key is (name, category), and an
 * already-seeded product is left untouched so a manual edit survives a re-run.
 */
final class CatalogProducts
{
    /**
     * The subcategories that host ONE offer of their own instead of a course
     * list: one SERVICE product per category, named exactly like it (user
     * directive 2026-09-04). "Orientamento Specialistico" is the single offer
     * of the "APL" root (user directive 2026-09-07); "DIL" is the one of the
     * "Formazione" root (user directive 2026-09-10) — it stays under that root
     * and sells a single service, so it hosts its product the same way its
     * siblings "Autoimpiego" and "Yisu" do. Cost and price stay 0: they are
     * filled in later through the CRUD modules, like every other seeded
     * product.
     *
     * QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES reads this list: a node
     * hosting its own product must be a classification target, never a
     * container (spec 0074).
     *
     * @var list<string>
     */
    public const array SINGLE_OFFER_CATEGORIES = [
        'Autoimpiego',
        'Yisu',
        'DIL',
        'Orientamento Specialistico',
    ];

    public function __construct(private readonly ProductService $products) {}

    public function seed(): void
    {
        // Step 1: the funded courses, split per region.
        $this->seedTrainingCourses();
        // Step 2: the self-funded ones, all on a single subcategory.
        $this->seedSelfFundedCourses();
        // Step 3: the categories selling a single offer of their own.
        $this->seedSingleOfferProducts();
    }

    private function seedTrainingCourses(): void
    {
        foreach (TrainingCourseCatalogue::COURSES as $categoryName => $courses) {
            foreach ($this->disambiguate($courses) as $course) {
                $this->seedProduct($this->category($categoryName), $course['name'], 0.0);
            }
        }
    }

    private function seedSelfFundedCourses(): void
    {
        $category = $this->category(SelfFundedCourseCatalogue::CATEGORY);

        foreach (SelfFundedCourseCatalogue::COURSES as $course) {
            $this->seedProduct($category, $course['name'], $course['price']);
        }
    }

    private function seedSingleOfferProducts(): void
    {
        foreach (self::SINGLE_OFFER_CATEGORIES as $categoryName) {
            $this->seedProduct($this->category($categoryName), $categoryName, 0.0);
        }
    }

    /**
     * Created by QualificaCatalogSeeder::seedCatalog(): a miss means the
     * catalogue lists drifted apart, which must fail loudly rather than
     * silently drop a whole category's products.
     */
    private function category(string $name): ProductCategory
    {
        return ProductCategory::query()->where('name', $name)->firstOrFail();
    }

    /**
     * A course name repeating inside one region is a DISTINCT course with its
     * own duration (user decision 2026-07-27): every occurrence of a repeated
     * name takes an "(N ore)" suffix — the duration is the only discriminator
     * the source list carries — so the two survive as separate products
     * instead of collapsing onto the same natural key. A name occurring once
     * is left untouched.
     *
     * @param  list<array{name: string, hours: int}>  $courses
     * @return list<array{name: string, hours: int}>
     */
    private function disambiguate(array $courses): array
    {
        $occurrences = array_count_values(array_column($courses, 'name'));

        return array_map(
            static fn (array $course): array => $occurrences[$course['name']] > 1
                ? ['name' => sprintf('%s (%d ore)', $course['name'], $course['hours']), 'hours' => $course['hours']]
                : $course,
            $courses,
        );
    }

    private function seedProduct(ProductCategory $category, string $name, float $price): void
    {
        // Natural key (name, category) — scoped to the category because the
        // SAME course runs in several regions. An already-seeded product is
        // left untouched, so a manual edit survives the re-run.
        $exists = Product::query()
            ->where('name', $name)
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            return;
        }

        $this->products->create(new CreateProductData(
            name: $name,
            description: null,
            // Cost is filled in later through the CRUD modules.
            cost: 0.0,
            price: $price,
            categoryId: $category->id,
            productType: ProductType::Service,
        ));
    }
}
