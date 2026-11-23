<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the `document-checklists` resource (spec 0403, D-5). Checklists
 * are never imported or exported, so `export`/`import` are dropped; the extra
 * `manageConnection` ability gates the single ArchivioSmart connection
 * (base URL + token), which is not a per-record permission.
 */
class DocumentChecklistPolicy extends BasePolicy
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
        return ['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity', 'manageConnection'];
    }

    public function manageConnection(User $user): bool
    {
        return $user->can($this->permission('manageConnection'));
    }
}
