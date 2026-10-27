<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use App\Services\Opportunities\OpportunityVisibilityScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD policy for the `opportunities` resource (spec 0040),
 * auto-discovered by Laravel from the Opportunity model. One addition beyond
 * BasePolicy: `viewDocuments` gates the opportunity documents section (the
 * reused polymorphic Attachment subsystem), mirroring how BasePolicy's own
 * `viewActivity` gates the activity-log section.
 */
class OpportunityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'opportunities';
    }

    /**
     * Resource-level gate for the documents section on the opportunity
     * detail; the per-attachment boundary is enforced separately by
     * AttachmentPolicy on each attachment endpoint.
     */
    public function viewDocuments(User $user): bool
    {
        return $user->can($this->permission('viewDocuments'));
    }

    /**
     * Spec 0316, D-8: record-level abilities need the resource permission AND
     * the record to be visible to the actor (viewAll, supervisor or manager).
     */
    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $this->isVisible($user, $model);
    }

    /**
     * Resource-level gate lifting the record scoping (see
     * OpportunityVisibilityScope).
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $this->isVisible($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $this->isVisible($user, $model);
    }

    private function isVisible(User $user, Model $model): bool
    {
        return ! $model instanceof Opportunity || OpportunityVisibilityScope::isVisibleTo($user, $model);
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewAll', 'viewDocuments'];
    }
}
