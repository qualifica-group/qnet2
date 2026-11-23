<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Policy of the checklist categories (spec 0410, D-2): no permissions of its own,
 * every ability maps to the existing `document-checklists.*` ones, so
 * `permissions:sync` creates nothing new. `export`/`import` do not apply.
 */
class DocumentChecklistCategoryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'document-checklists';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity'];
    }
}
