<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `registries` resource (spec 0020),
 * auto-discovered by Laravel from the Registry model. One addition beyond
 * BasePolicy (spec 0173): `viewDocuments` gates the registry documents
 * section, exactly like OpportunityPolicy::viewDocuments.
 */
class RegistryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'registries';
    }

    /**
     * Resource-level gate for the documents tab of the detail and the
     * `documents` row action; the per-attachment boundary is enforced
     * separately by AttachmentPolicy on each attachment endpoint.
     */
    public function viewDocuments(User $user): bool
    {
        return $user->can($this->permission('viewDocuments'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewDocuments'];
    }
}
