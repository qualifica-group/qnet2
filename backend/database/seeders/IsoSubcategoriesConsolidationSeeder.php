<?php

namespace Database\Seeders;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UserCategoryTabPreference;
use App\Services\ProductCategoryService;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user request 2026-11-20):
 * `php artisan db:seed --class=IsoSubcategoriesConsolidationSeeder --force`.
 *
 * Replaces every subcategory of the current "ISO" root (never the "[OLD] ISO"
 * legacy one) with two: "ISO - Ente" gets the products of typology `institution`,
 * "ISO - Consulenza" all the others. What pointed at a removed subcategory
 * follows it: opportunity/project/campaign/competence lines and the favourite
 * tabs move to "ISO - Ente" when every product of the old subcategory was an
 * Ente one, to "ISO - Consulenza" otherwise. A commission rule on a removed
 * subcategory (the reporters' "UNI PDR 125 e SA 8000" rates) becomes one
 * PRODUCT rule per product it covered, so no rate changes; an existing product
 * rule of the same recipient wins and is not duplicated. Re-running it changes
 * nothing.
 */
class IsoSubcategoriesConsolidationSeeder extends Seeder
{
    public const string INSTITUTION_CATEGORY = 'ISO - Ente';

    public const string CONSULTANCY_CATEGORY = 'ISO - Consulenza';

    public const string INSTITUTION_TYPOLOGY = 'institution';

    private const string CONSULTANCY_TYPOLOGY = 'consultancy';

    /** Line table => owner column; each is unique on (owner, business_function_id, product_category_id). */
    private const array LINE_TABLES = [
        'opportunity_product_lines' => 'opportunity_id',
        'project_product_lines' => 'project_id',
        'campaign_product_lines' => 'campaign_id',
        'employment_product_lines' => 'employment_profile_id',
    ];

    public function run(ProductCategoryService $categories): void
    {
        $root = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->where('name', IsoCatalogue::ROOT)
            ->firstOrFail();

        $summary = DB::transaction(function () use ($root, $categories): array {
            // Step 1: the two destination subcategories.
            $institution = $this->destination($root, self::INSTITUTION_CATEGORY, $categories);
            $consultancy = $this->destination($root, self::CONSULTANCY_CATEGORY, $categories);

            // Step 2: every other node of the branch, deepest first, and where each one goes.
            $retired = $this->retiredCategories($root, [$institution->id, $consultancy->id]);
            $productsByCategory = Product::query()->with('productTypology:id,code')
                ->whereIn('category_id', $retired->keys())->get()->groupBy('category_id');
            $destinationOf = $retired->map(fn (ProductCategory $category): int => $this->isInstitutionOnly($productsByCategory->get($category->id))
                ? $institution->id
                : $consultancy->id);

            // Step 3: move products, references and rules, then drop the empty subcategories.
            return [
                'products' => $this->moveProducts($productsByCategory->collapse(), $institution->id, $consultancy->id),
                'lines' => $this->moveLines($destinationOf),
                'rules' => $this->convertCommissionRules($destinationOf, $productsByCategory),
                'favourites' => $this->moveFavourites($destinationOf),
                'deleted' => $retired->each(fn (ProductCategory $category) => $category->delete())->count(),
            ];
        });

        $this->command?->info(sprintf(
            'Sottocategorie ISO eliminate: %d. Prodotti spostati: %d (Ente %d, Consulenza %d, di cui %d con tipologia diversa da Consulenza). Righe ricollegate: %d. Regole provvigionali: %d convertite in %d regole per prodotto. Preferiti aggiornati: %d.',
            $summary['deleted'],
            $summary['products']['institution'] + $summary['products']['consultancy'],
            $summary['products']['institution'],
            $summary['products']['consultancy'],
            $summary['products']['other_typology'],
            $summary['lines'],
            $summary['rules']['converted'],
            $summary['rules']['created'],
            $summary['favourites'],
        ));
    }

    private function destination(ProductCategory $root, string $name, ProductCategoryService $categories): ProductCategory
    {
        return ProductCategory::query()->where('parent_id', $root->id)->where('name', $name)->first()
            ?? $categories->create(new CreateProductCategoryData(name: $name, parentId: $root->id));
    }

    /**
     * @param  list<int>  $keptIds
     * @return Collection<int, ProductCategory> keyed by id, children before their parent
     */
    private function retiredCategories(ProductCategory $root, array $keptIds): Collection
    {
        $levels = [];
        $level = ProductCategory::query()->where('parent_id', $root->id)->whereNotIn('id', $keptIds)->get();

        while ($level->isNotEmpty()) {
            $levels[] = $level;
            $level = ProductCategory::query()->whereIn('parent_id', $level->modelKeys())->get();
        }

        return collect(array_reverse($levels))->collapse()->keyBy('id');
    }

    /**
     * @param  Collection<int, Product>|null  $products
     */
    private function isInstitutionOnly(?Collection $products): bool
    {
        return $products !== null && $products->every(fn (Product $product): bool => $product->productTypology?->code === self::INSTITUTION_TYPOLOGY);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{institution: int, consultancy: int, other_typology: int}
     */
    private function moveProducts(Collection $products, int $institutionId, int $consultancyId): array
    {
        $counts = ['institution' => 0, 'consultancy' => 0, 'other_typology' => 0];

        foreach ($products as $product) {
            $code = $product->productTypology?->code;
            $isInstitution = $code === self::INSTITUTION_TYPOLOGY;
            $product->update(['category_id' => $isInstitution ? $institutionId : $consultancyId]);

            $counts[$isInstitution ? 'institution' : 'consultancy']++;
            $counts['other_typology'] += (int) (! $isInstitution && $code !== self::CONSULTANCY_TYPOLOGY);
        }

        return $counts;
    }

    /**
     * A line whose owner already has the destination is a duplicate and goes.
     *
     * @param  Collection<int, int>  $destinationOf
     */
    private function moveLines(Collection $destinationOf): int
    {
        $moved = 0;

        foreach (self::LINE_TABLES as $table => $owner) {
            foreach (DB::table($table)->whereIn('product_category_id', $destinationOf->keys())->orderBy('id')->get() as $line) {
                $destinationId = $destinationOf->get($line->product_category_id);
                $duplicate = DB::table($table)
                    ->where($owner, $line->{$owner})
                    ->where('business_function_id', $line->business_function_id)
                    ->where('product_category_id', $destinationId)
                    ->exists();

                $duplicate
                    ? DB::table($table)->where('id', $line->id)->delete()
                    : DB::table($table)->where('id', $line->id)->update(['product_category_id' => $destinationId, 'updated_at' => now()]);
                $moved++;
            }
        }

        return $moved;
    }

    /**
     * @param  Collection<int, int>  $destinationOf
     * @param  Collection<int, Collection<int, Product>>  $productsByCategory
     * @return array{converted: int, created: int}
     */
    private function convertCommissionRules(Collection $destinationOf, Collection $productsByCategory): array
    {
        $counts = ['converted' => 0, 'created' => 0];
        $rules = CommissionConfiguration::query()->whereIn('product_category_id', $destinationOf->keys())->get();

        foreach ($rules as $rule) {
            foreach ($productsByCategory->get($rule->product_category_id, collect()) as $product) {
                $counts['created'] += (int) $this->productRule($rule, $product);
            }

            $this->retireRule($rule, $destinationOf->get($rule->product_category_id));
            $counts['converted']++;
        }

        return $counts;
    }

    private function productRule(CommissionConfiguration $rule, Product $product): bool
    {
        $exists = CommissionConfiguration::query()
            ->where('application_scope', CommissionApplicationScope::Product)
            ->where('product_id', $product->id)
            ->where('recipient_role', $rule->recipient_role)
            ->where('recipient_type', $rule->recipient_type)
            ->where('recipient_id', $rule->recipient_id)
            ->exists();

        if ($exists) {
            return false;
        }

        $rule->replicate()->fill([
            'application_scope' => CommissionApplicationScope::Product,
            'product_category_id' => null,
            'product_id' => $product->id,
        ])->save();

        return true;
    }

    /**
     * A rule already applied to quote lines stays as history: suspended, on
     * the destination, since its old category is about to go.
     */
    private function retireRule(CommissionConfiguration $rule, int $destinationId): void
    {
        if (! $rule->appliedCommissions()->exists()) {
            $rule->delete();

            return;
        }

        $rule->update(['product_category_id' => $destinationId, 'status' => CommissionConfigurationStatus::Suspended]);
    }

    /**
     * @param  Collection<int, int>  $destinationOf
     */
    private function moveFavourites(Collection $destinationOf): int
    {
        $updated = 0;

        foreach (UserCategoryTabPreference::query()->get() as $preference) {
            $favourites = collect($preference->favorite_category_ids)->map(intval(...));

            if ($favourites->intersect($destinationOf->keys())->isEmpty()) {
                continue;
            }

            $preference->update([
                'favorite_category_ids' => $favourites->map(fn (int $id): int => $destinationOf->get($id, $id))->unique()->values()->all(),
            ]);
            $updated++;
        }

        return $updated;
    }
}
