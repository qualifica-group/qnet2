<?php

namespace Database\Seeders\QualificaCatalog;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatRate;
use App\Services\ProductService;

/**
 * Every product the Qualifica catalogue seeds. Split out of
 * QualificaCatalogSeeder (which stayed at its size limit, engineering.md §6) —
 * it holds the three product sources AND the single upsert they share, since
 * the two are meaningless apart. The category tree they file onto is still the
 * seeder's, and must exist before seed() runs.
 *
 * Three sources, one shape (a SERVICE product on a catalogue node):
 *   - the funded courses — GOL (TrainingCourseCatalogue) and DIL
 *     (DilCourseCatalogue): one per row, in its own `<Misura> - <Regione>`
 *     category, priced 0 — a funded course is not sold to the learner;
 *   - the self-funded courses (SelfFundedCourseCatalogue): one per row in its
 *     own `Autofinanziato - <Regione>` category, with its list price and, when
 *     quoted "+ iva", the 22% VAT rate;
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
 * The one exception is a self-funded course an earlier revision filed on the
 * "Autofinanziato" node itself, before the per-region split: it is MOVED onto
 * its region, never duplicated there — see moveOffContainer().
 */
final class CatalogProducts
{
    /**
     * The subcategories that host ONE offer of their own instead of a course
     * list: one SERVICE product per category, named exactly like it (user
     * directive 2026-09-04). "Orientamento Specialistico" is the single offer
     * of the "APL" root (user directive 2026-09-07). "DIL" was one too until
     * its course catalogue arrived (user directive 2026-09-17): it is now a
     * container like its GOL sibling, its courses filed on "DIL - Lombardia".
     * A "DIL" product an earlier revision seeded is left untouched. Cost and
     * price stay 0: they are filled in later through the CRUD modules, like
     * every other seeded product.
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
        'Orientamento Specialistico',
    ];

    public function __construct(private readonly ProductService $products) {}

    public function seed(): void
    {
        // Step 1: the funded courses (GOL and DIL), split per region.
        $this->seedTrainingCourses();
        // Step 2: the self-funded ones, split per region.
        $this->seedSelfFundedCourses();
        // Step 3: the categories selling a single offer of their own.
        $this->seedSingleOfferProducts();
    }

    private function seedTrainingCourses(): void
    {
        foreach ([...TrainingCourseCatalogue::COURSES, ...DilCourseCatalogue::COURSES] as $categoryName => $courses) {
            foreach ($this->disambiguate($courses) as $course) {
                $this->seedProduct($this->category($categoryName), $course['name'], 0.0);
            }
        }
    }

    private function seedSelfFundedCourses(): void
    {
        $container = $this->category(SelfFundedCourseCatalogue::CATEGORY);
        $vatRateId = $this->plusVatRate()->id;

        foreach (SelfFundedCourseCatalogue::COURSES as $categoryName => $courses) {
            $category = $this->category($categoryName);

            foreach ($courses as $course) {
                $courseVatRateId = $course['plus_vat'] ? $vatRateId : null;

                $this->moveOffContainer($container, $category, $course['name'], $courseVatRateId);
                $this->seedProduct($category, $course['name'], $course['price'], $courseVatRateId);
            }
        }
    }

    /**
     * The production seed creates no VAT catalogue of its own (the full one is
     * DemoVatRateSeeder's), so the rate a "+ iva" price needs is adopted by
     * percentage when an operator already has it, created otherwise.
     */
    private function plusVatRate(): VatRate
    {
        return VatRate::query()->firstOrCreate(
            ['rate' => SelfFundedCourseCatalogue::VAT_RATE],
            ['name' => SelfFundedCourseCatalogue::VAT_RATE_NAME],
        );
    }

    /**
     * Moves a course seeded on the "Autofinanziato" node itself — back when it
     * was the single self-funded category — onto its regional leaf, so the
     * split does not leave a stray copy on what is now a container. Its VAT
     * rate is filled in only when still unset: a rate chosen by hand wins.
     */
    private function moveOffContainer(ProductCategory $container, ProductCategory $category, string $name, ?int $vatRateId): void
    {
        $product = Product::query()
            ->where('name', $name)
            ->where('category_id', $container->id)
            ->first();

        if ($product === null) {
            return;
        }

        $product->update([
            'category_id' => $category->id,
            'vat_rate_id' => $product->vat_rate_id ?? $vatRateId,
        ]);
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

    private function seedProduct(ProductCategory $category, string $name, float $price, ?int $vatRateId = null): void
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
            vatRateId: $vatRateId,
        ));
    }
}
