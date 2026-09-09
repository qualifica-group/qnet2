<?php

namespace App\Services;

use App\DataObjects\Users\EmploymentData;
use App\Models\EmploymentProfile;
use App\Models\User;

/**
 * Single source of truth for persisting a user's nested employment profile
 * (spec 0015): a plain hasOne upsert/delete, with the server-side invariants
 * enforced here (not trusted from the request):
 *
 *  - a manager cannot also report to someone (`is_manager` forces
 *    `reports_to_id` to null);
 *  - a user can never report to itself (defense in depth — the FormRequest
 *    already 422s this on update; a create can never self-reference since
 *    the user's own id does not exist yet at validation time);
 *  - at most ONE `employment_profile_operational_site` row is ever
 *    `is_primary = true` (spec 0103 D-10): MySQL has no partial unique
 *    index for this, so it is enforced here, applying the two site fields'
 *    tri-state instead of trusting whatever shape the payload sends;
 *  - the assignment competence (spec 0110) is written on its own pivot,
 *    with the same tri-state discipline (see syncProductCategories()).
 *
 * The caller (UserService::create/update) is responsible for the surrounding
 * transaction, mirroring ProfileWriter.
 */
class EmploymentWriter
{
    /**
     * Persist the nested employment profile for the user inside the caller's
     * transaction. No-op when `$employment` is null (the key was absent from
     * the request — leave the row untouched). Deletes the row when `$employment
     * ->delete` is true (an explicit `employment: null`); a delete on a user
     * with no row is itself a harmless no-op, so create and update share this
     * single code path.
     */
    public function write(User $user, ?EmploymentData $employment): void
    {
        if ($employment === null) {
            return;
        }

        if ($employment->delete) {
            // Cascades onto employment_profile_operational_site and
            // employment_profile_product_category (FK cascadeOnDelete), so
            // the site memberships and the competence go with the row.
            $user->employment()->delete();

            return;
        }

        // Step 1: upsert the plain-column attributes onto the 1:1 row.
        $profile = $user->employment()->updateOrCreate([], $this->guardedAttributes($user, $employment));

        // Step 2: apply the site-membership tri-state onto the pivot.
        $this->syncSiteMemberships($profile, $employment);

        // Step 3: apply the competence tri-state onto its own pivot.
        $this->syncProductCategories($profile, $employment);
    }

    /**
     * Apply the `product_category_ids` tri-state (spec 0110) onto
     * `employment_profile_product_category`: absent leaves the pivot alone,
     * any submitted array replaces it wholesale (an empty one clears it).
     * Simpler than syncSiteMemberships() above because there is no second,
     * independently-touched side to carry over — the competence is one flat
     * set with no pivot payload.
     */
    private function syncProductCategories(EmploymentProfile $profile, EmploymentData $employment): void
    {
        if (! $employment->productCategoryIdsProvided) {
            return;
        }

        $profile->productCategories()->sync($employment->productCategoryIds);
    }

    /**
     * The row attributes with the manager/self-report invariants applied.
     *
     * @return array<string, mixed>
     */
    private function guardedAttributes(User $user, EmploymentData $employment): array
    {
        $attributes = $employment->attributes();

        if ($employment->isManager || $attributes['reports_to_id'] === $user->id) {
            $attributes['reports_to_id'] = null;
        }

        return $attributes;
    }

    /**
     * Apply the two independent per-field tri-states (primary, remote) onto
     * `employment_profile_operational_site` in a single `sync()` call — a
     * partial call would detach whichever side was left untouched, since
     * `sync()` always replaces the WHOLE pivot set it is given.
     */
    private function syncSiteMemberships(EmploymentProfile $profile, EmploymentData $employment): void
    {
        if (! $employment->primaryOperationalSiteIdProvided && ! $employment->remoteOperationalSiteIdsProvided) {
            return;
        }

        // The accessors below read off this eager-loaded collection, so this
        // is the only query the whole sync needs beyond the sync() itself.
        $profile->load('operationalSites');

        $profile->operationalSites()->sync($this->targetMemberships($profile, $employment));
    }

    /**
     * The full desired pivot set: current rows on the untouched side are
     * carried over unchanged, current rows on the touched side are dropped
     * wholesale and replaced by the request's ids (spec 0103 D-10 — the
     * primary flag is always computed here, never read from the payload).
     *
     * @return array<int, array{is_primary: bool}>
     */
    private function targetMemberships(EmploymentProfile $profile, EmploymentData $employment): array
    {
        $target = [];

        foreach ($profile->remoteOperationalSiteIds as $id) {
            $target[$id] = ['is_primary' => false];
        }

        if ($profile->primaryOperationalSiteId !== null) {
            $target[$profile->primaryOperationalSiteId] = ['is_primary' => true];
        }

        if ($employment->remoteOperationalSiteIdsProvided) {
            $target = $this->replaceSide($target, isPrimary: false, replacement: $employment->remoteOperationalSiteIds);
        }

        if ($employment->primaryOperationalSiteIdProvided) {
            $replacement = $employment->primaryOperationalSiteId === null ? [] : [$employment->primaryOperationalSiteId];
            $target = $this->replaceSide($target, isPrimary: true, replacement: $replacement);
        }

        return $target;
    }

    /**
     * Drop every row currently on the given side (primary or remote) and
     * reattach the replacement ids on that same side, leaving the other
     * side's rows as-is. A replacement id already present on the OTHER side
     * (e.g. promoting an existing remote to primary) is simply overwritten,
     * never duplicated — the unique (profile, site) pair is preserved.
     *
     * @param  array<int, array{is_primary: bool}>  $target
     * @param  array<int, int>  $replacement
     * @return array<int, array{is_primary: bool}>
     */
    private function replaceSide(array $target, bool $isPrimary, array $replacement): array
    {
        $target = array_filter($target, fn (array $pivot): bool => $pivot['is_primary'] !== $isPrimary);

        foreach ($replacement as $id) {
            $target[$id] = ['is_primary' => $isPrimary];
        }

        return $target;
    }
}
