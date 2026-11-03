<?php

namespace App\Imports\RequestManagement;

use App\Enums\AttributeContext;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Reference data the request-management import looks up for every row (active
 * users, a category's products and "Informazioni aggiuntive"), read once and
 * memoized: the row recognizer is built per row by the staging engine, so
 * without one shared instance each of the file's thousands of rows would repeat
 * the same queries. RequestManagementImportDefinition publishes its own
 * instance in the container, so one definition (= one job or request) shares
 * a single, fresh memo.
 */
final class RequestImportLookups
{
    /** @var array<string, array<int, int>>|null normalized user name => user ids */
    private ?array $userIdsByName = null;

    /** @var array<string, array<int, int>>|null normalized referent name => referent ids */
    private ?array $referentIdsByName = null;

    /** @var array<int, int|null> user id => primary operational site id */
    private array $userSiteIds = [];

    /** @var array<int, array<int, array{id: int, name: string}>> category id => products */
    private array $products = [];

    /** @var array<int, array<string, array<string, mixed>>> category id => attribute code => effective attribute row */
    private array $attributes = [];

    /** @var array<int, array<int, string>> category id => words of its own and its ancestors' names */
    private array $categoryWords = [];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The words naming the category and every ancestor ("DIL - Lombardia" ->
     * dil, lombardia): a product cell repeats them without meaning anything.
     *
     * @return array<int, string>
     */
    public function categoryWords(int $categoryId): array
    {
        if (! isset($this->categoryWords[$categoryId])) {
            $words = [];
            $currentId = $categoryId;
            $visited = [];

            while ($currentId !== null && ! isset($visited[$currentId])) {
                $visited[$currentId] = true;
                $category = ProductCategory::query()->find($currentId, ['id', 'name', 'parent_id']);
                $words = [...$words, ...RequestImportText::words((string) $category?->name)];
                $currentId = $category?->parent_id;
            }

            $this->categoryWords[$categoryId] = array_values(array_unique($words));
        }

        return $this->categoryWords[$categoryId];
    }

    /**
     * The selectable, active product line a column of category names points
     * to: exactly one distinct non-empty spelling (ignoring case, accents and
     * spacing) naming exactly one category, else null.
     *
     * @param  array<int, array{value: string, count: int}>  $values
     */
    public function categoryIdByName(array $values): ?int
    {
        $keys = array_values(array_unique(array_filter(
            array_map(static fn (array $value): string => RequestImportText::key($value['value']), $values),
            static fn (string $key): bool => $key !== '',
        )));

        if (count($keys) !== 1) {
            return null;
        }

        $matches = ProductCategory::query()->where('is_selectable', true)->where('is_active', true)->get(['id', 'name'])
            ->filter(static fn (ProductCategory $category): bool => RequestImportText::key($category->name) === $keys[0]);

        return $matches->count() === 1 ? $matches->first()->id : null;
    }

    /**
     * The ids of the referents whose name equals $name, ignoring case, accents
     * and spacing. The whole table is read once (id and name only): a file
     * names the same few reporters on thousands of rows.
     *
     * @return array<int, int>
     */
    public function referentIds(string $name): array
    {
        if ($this->referentIdsByName === null) {
            $this->referentIdsByName = [];

            foreach (Referent::query()->get(['id', 'name']) as $referent) {
                $this->referentIdsByName[RequestImportText::key((string) $referent->name)][] = (int) $referent->id;
            }
        }

        return $this->referentIdsByName[RequestImportText::key($name)] ?? [];
    }

    /**
     * The ids of the active users whose name equals $name, ignoring case,
     * accents and spacing.
     *
     * @return array<int, int>
     */
    public function activeUserIds(string $name): array
    {
        if ($this->userIdsByName === null) {
            $this->userIdsByName = [];

            foreach (User::query()->where('is_active', true)->get(['id', 'name']) as $user) {
                $this->userIdsByName[RequestImportText::key((string) $user->name)][] = (int) $user->id;
            }
        }

        return $this->userIdsByName[RequestImportText::key($name)] ?? [];
    }

    /**
     * The user's primary operational site: an imported request sits in its
     * operator's Sede (user directive 2026-11-03). Read once per user and run.
     */
    public function primarySiteIdOf(int $userId): ?int
    {
        if (! array_key_exists($userId, $this->userSiteIds)) {
            $user = User::query()->with('employment.operationalSites')->find($userId);
            $this->userSiteIds[$userId] = $user?->employment?->primary_operational_site_id;
        }

        return $this->userSiteIds[$userId];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function productsOf(int $categoryId): array
    {
        return $this->products[$categoryId] ??= Product::query()
            ->where('category_id', $categoryId)
            ->get(['id', 'name'])
            ->map(static fn (Product $product): array => ['id' => (int) $product->id, 'name' => (string) $product->name])
            ->all();
    }

    /**
     * The category's effective "Informazioni aggiuntive" (quote context),
     * keyed by attribute code; empty for an unknown category.
     *
     * @return array<string, array<string, mixed>>
     */
    public function quoteAttributesOf(int $categoryId): array
    {
        if (! isset($this->attributes[$categoryId])) {
            $category = ProductCategory::query()->find($categoryId);

            $this->attributes[$categoryId] = $category === null
                ? []
                : $this->hierarchy->effectiveAttributes($category, AttributeContext::Quote)->keyBy('code')->all();
        }

        return $this->attributes[$categoryId];
    }
}
