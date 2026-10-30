<?php

declare(strict_types=1);

namespace App\Services\CategoryTabs;

/**
 * Which categories a tab strip lists (spec 0337): every category with records
 * (`All`, the default) or only the root ones, each counting its whole branch.
 * The root level keeps its own favourites row, so the module key of the
 * preferences carries a suffix.
 */
enum CategoryTabLevel: string
{
    case All = 'all';
    case Root = 'root';

    /**
     * The `user_category_tab_preferences.module` value for this level.
     */
    public function preferenceModule(string $module): string
    {
        return $this === self::Root ? "{$module}:{$this->value}" : $module;
    }
}
