<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\CommissionApplicationScope;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Closure;

/**
 * Resolves ReporterCommissionCatalogue::COLUMNS against the catalog: each
 * column becomes the list of categories and products its rate applies to.
 * A category or named product that is missing is reported through $warn and
 * skipped, never invented.
 */
final class ReporterCommissionTargets
{
    /** @var array<string, int> */
    private array $categoryIds = [];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * @param  Closure(string): void  $warn
     * @return array<string, list<array{scope: CommissionApplicationScope, id: int, label: string}>>
     */
    public function resolve(Closure $warn): array
    {
        $this->categoryIds = ProductCategory::query()->pluck('id', 'name')->all();
        $targets = [];

        foreach (ReporterCommissionCatalogue::COLUMNS as $column => $definition) {
            $targets[$column] = [
                ...$this->categoryTargets($definition['categories'] ?? [], $warn),
                ...$this->yearProductTargets($definition, $warn),
                ...$this->namedProductTargets($definition['products'] ?? [], $warn),
            ];
        }

        return $targets;
    }

    /**
     * Whether a product name marks the given year of a three-year contract:
     * "2° Anno", "_2°Anno", "_II anno", "_2 anno", "II° Anno". "III" never
     * reads as "II": the numeral must not follow a letter or digit.
     */
    public static function marksYear(string $productName, int $year): bool
    {
        $numeral = str_repeat('I', $year);

        return preg_match(sprintf('/(?<![\p{L}\p{N}])(?:%d|%s)\s*°?\s*anno(?!\p{L})/iu', $year, $numeral), $productName) === 1;
    }

    /**
     * @param  list<string>  $names
     * @param  Closure(string): void  $warn
     * @return list<array{scope: CommissionApplicationScope, id: int, label: string}>
     */
    private function categoryTargets(array $names, Closure $warn): array
    {
        $targets = [];

        foreach ($names as $name) {
            $id = $this->categoryId($name, $warn);

            if ($id !== null) {
                $targets[] = ['scope' => CommissionApplicationScope::ProductCategory, 'id' => $id, 'label' => $name];
            }
        }

        return $targets;
    }

    /**
     * @param  array{year?: int, within?: list<string>, except?: list<string>}  $definition
     * @param  Closure(string): void  $warn
     * @return list<array{scope: CommissionApplicationScope, id: int, label: string}>
     */
    private function yearProductTargets(array $definition, Closure $warn): array
    {
        if (! isset($definition['year'])) {
            return [];
        }

        $within = $this->branchIds($definition['within'] ?? [], $warn);
        $except = $this->branchIds($definition['except'] ?? [], $warn);

        return Product::query()
            ->whereIn('category_id', array_diff($within, $except))
            ->orderBy('id')
            ->get(['id', 'name'])
            ->filter(fn (Product $product) => self::marksYear($product->name, $definition['year']))
            ->map(fn (Product $product) => ['scope' => CommissionApplicationScope::Product, 'id' => $product->id, 'label' => $product->name])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $productsByCategory
     * @param  Closure(string): void  $warn
     * @return list<array{scope: CommissionApplicationScope, id: int, label: string}>
     */
    private function namedProductTargets(array $productsByCategory, Closure $warn): array
    {
        $targets = [];

        foreach ($productsByCategory as $category => $names) {
            $ids = Product::query()
                ->where('category_id', $this->categoryId($category, $warn))
                ->whereIn('name', $names)
                ->pluck('id', 'name');

            foreach ($names as $name) {
                isset($ids[$name])
                    ? $targets[] = ['scope' => CommissionApplicationScope::Product, 'id' => $ids[$name], 'label' => $name]
                    : $warn(sprintf('Product "%s" not found in "%s": its reporter commissions are skipped.', $name, $category));
            }
        }

        return $targets;
    }

    /**
     * Each named category with all its descendants.
     *
     * @param  list<string>  $names
     * @param  Closure(string): void  $warn
     * @return list<int>
     */
    private function branchIds(array $names, Closure $warn): array
    {
        $ids = [];

        foreach ($names as $name) {
            $id = $this->categoryId($name, $warn);

            if ($id !== null) {
                array_push($ids, $id, ...$this->hierarchy->descendantIds($id));
            }
        }

        return $ids;
    }

    /**
     * @param  Closure(string): void  $warn
     */
    private function categoryId(string $name, Closure $warn): ?int
    {
        $id = $this->categoryIds[$name] ?? null;

        if ($id === null) {
            $warn(sprintf('Category "%s" not found: its reporter commissions are skipped.', $name));
        }

        return $id;
    }
}
