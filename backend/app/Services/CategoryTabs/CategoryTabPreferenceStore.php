<?php

declare(strict_types=1);

namespace App\Services\CategoryTabs;

use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use App\RequestManagement\ResolveDefaultCategoryTabPreference;
use Closure;

/**
 * Reads and writes a user's category tab favourites for one strip (spec 0184,
 * competence default 0193), shared by Gestione Richieste/Iscritti and the
 * Opportunities/Offerte grids (spec 0335). `$module` is the preference key
 * ("request-management", "opportunities", ...), always taken from the matched
 * route, never from input. At the root level (spec 0337) the row is
 * "{module}:root" and the default is lifted from categories to their roots.
 */
final class CategoryTabPreferenceStore
{
    public function __construct(private readonly ResolveDefaultCategoryTabPreference $defaults) {}

    /**
     * The stored row, or an UNSAVED one carrying the competence default:
     * reading never writes, the row appears on the first save.
     *
     * @param  Closure(): array<int, int>  $stripCategoryIds  the `all`-level strip, lazily resolved, only when no row exists
     */
    public function read(User $user, string $module, Closure $stripCategoryIds, CategoryTabLevel $level = CategoryTabLevel::All): UserCategoryTabPreference
    {
        $preference = UserCategoryTabPreference::query()->firstOrNew(['user_id' => $user->id, 'module' => $level->preferenceModule($module)]);

        if (! $preference->exists) {
            $favorites = $level === CategoryTabLevel::Root
                ? $this->defaults->handleForRoots($user, $stripCategoryIds())
                : $this->defaults->handle($user, $stripCategoryIds());
            $preference->favorite_category_ids = $favorites;
            $preference->show_only_favorites = $favorites !== [];
        }

        return $this->withoutDeletedCategories($preference);
    }

    /**
     * @param  array{favorite_category_ids: array<int, int>, show_only_favorites: bool}  $attributes
     */
    public function save(User $user, string $module, array $attributes, CategoryTabLevel $level = CategoryTabLevel::All): UserCategoryTabPreference
    {
        $preference = UserCategoryTabPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module' => $level->preferenceModule($module)],
            $attributes,
        );

        return $this->withoutDeletedCategories($preference);
    }

    /** A favourite whose category was deleted since is dropped from the answer. */
    private function withoutDeletedCategories(UserCategoryTabPreference $preference): UserCategoryTabPreference
    {
        $preference->favorite_category_ids = ProductCategory::query()
            ->whereKey($preference->favorite_category_ids)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $preference;
    }
}
