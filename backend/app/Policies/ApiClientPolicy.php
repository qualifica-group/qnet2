<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Policy for the integration clients of the external API (spec 0209).
 *
 * Four permissions only (view/create/update/delete): the list is gated by
 * `api-clients.view` (there is no separate viewAny), and the documentation
 * endpoints reuse it. Auto-discovered by Laravel from the ApiClient model.
 */
class ApiClientPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'api-clients';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->permission('view'));
    }
}
