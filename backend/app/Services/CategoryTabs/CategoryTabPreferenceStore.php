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
 * route, never from input.
 */
final class CategoryTabPreferenceStore
{
    public function __construct(private readonly ResolveDefaultCategoryTabPreference $defaults) {}

    /**
     * The stored row, or an UNSAVED one carrying the competence default:
     * reading never writes, the row appears on the first save.
     *
     * @param  Closure(): array<int, int>  $stripCategoryIds  lazily resolved, only when no row exists
     */
    public function read(User $user, string $module, Closure $stripCategoryIds): UserCategoryTabPreference
    {
        $preference = UserCategoryTabPreference::query()->firstOrNew(['user_id' => $user->id, 'module' => $module]);

        if (! $preference->exists) {
            $favorites = $this->defaults->handle($user, $stripCategoryIds());
            $preference->favorite_category_ids = $favorites;
            $preference->show_only_favorites = $favorites !== [];
        }

        return $this->withoutDeletedCategories($preference);
    }

    /**
     * @param  array{favorite_category_ids: array<int, int>, show_only_favorites: bool}  $attributes
     */
    public function save(User $user, string $module, array $attributes): UserCategoryTabPreference
    {
        $preference = UserCategoryTabPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module' => $module],
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
