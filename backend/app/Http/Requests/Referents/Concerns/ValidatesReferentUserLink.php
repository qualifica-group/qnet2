<?php

declare(strict_types=1);

namespace App\Http\Requests\Referents\Concerns;

use App\Models\Referent;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * D-10 (spec 0090): a `user_id` link is not silently reassignable. Ahead of
 * the DB unique constraint (`referents_user_id_unique`, the safety net, never
 * the message shown to the operator), this rejects a submitted `user_id`
 * already linked to ANOTHER referent with a field-keyed 422 naming the
 * occupying referent.
 *
 * Both StoreReferentRequest and UpdateReferentRequest declare
 * authorizationModel() (EnforcesFieldPermissions' own abstract), reused here
 * to tell create ($model === null) from update, and to exclude the referent
 * under edit from the collision check (keeping its own link a no-op).
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesReferentUserLink
{
    abstract protected function authorizationModel(): ?Model;

    /**
     * Skipped when the actor lacks the resource's base write ability: the
     * base CRUD 403 (checked in the controller) is the relevant failure then,
     * not a field-level 422 leaking payload feedback (same reasoning as
     * StoreReferentRequest::validatePhoneContact).
     */
    protected function validateUserLinkUniqueness(Validator $validator): void
    {
        /** @var User $actor */
        $actor = $this->user();
        $referent = $this->authorizationModel();
        $baseAbility = $referent === null ? 'create' : 'update';

        if (! $actor->can("referents.{$baseAbility}")) {
            return;
        }

        if (! $this->has('user_id') || $validator->errors()->has('user_id')) {
            return;
        }

        $userId = $this->input('user_id');

        if ($userId === null) {
            // Unlinking (or never having linked) never collides with anyone.
            return;
        }

        $occupant = Referent::query()
            ->where('user_id', $userId)
            ->when($referent !== null, fn ($query) => $query->whereKeyNot($referent->getKey()))
            ->first();

        if ($occupant !== null) {
            $validator->errors()->add('user_id', __('referents.user_already_linked', ['name' => $occupant->name]));
        }
    }
}
