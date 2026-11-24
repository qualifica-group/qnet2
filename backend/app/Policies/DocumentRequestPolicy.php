<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentRequest;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Policy for the `document-requests` resource (spec 0428, D-2). `create` is
 * "Richiedi documenti"; `update` covers edit, reminder, cancel and refresh.
 * A request is always reached through its work order: every per-record ability
 * also needs `view` on the work order (its own perimeter).
 */
class DocumentRequestPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'document-requests';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update'];
    }

    public function view(User $user, Model $model): bool|Response
    {
        return $user->can($this->permission('view')) && $this->canViewWorkOrder($user, $model);
    }

    public function update(User $user, Model $model): bool|Response
    {
        return $user->can($this->permission('update')) && $this->canViewWorkOrder($user, $model);
    }

    private function canViewWorkOrder(User $user, Model $model): bool
    {
        return $model instanceof DocumentRequest
            && $user->can('view', $model->loadMissing('workOrder')->workOrder);
    }
}
