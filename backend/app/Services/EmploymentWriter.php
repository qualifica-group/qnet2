<?php

namespace App\Services;

use App\DataObjects\Users\EmploymentData;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\ProductLines\ProductLineWriter;

/**
 * Single source of truth for persisting a user's nested employment profile
 * (spec 0015): a plain hasOne upsert/delete, with the server-side invariants
 * enforced here (not trusted from the request):
 *
 *  - a manager cannot also report to someone (`is_manager` forces the
 *    `reportsTo` pivot empty, spec 0166 D-5 — replacing the former
 *    `reports_to_id` column invariant);
 *  - a user can never report to itself (defense in depth — the FormRequest
 *    already 422s this on update; a create can never self-reference since
 *    the user's own id does not exist yet at validation time);
 *  - at most ONE `employment_profile_operational_site` row is ever
 *    `is_primary = true` (spec 0103 D-10): MySQL has no partial unique
 *    index for this, so it is enforced here, applying the two site fields'
 *    tri-state instead of trusting whatever shape the payload sends;
 *  - the assignment competence (spec 0111) is written on its own child
 *    table, with the same tri-state discipline (see syncProductLines());
 *  - the reports-to managers (spec 0166) are written on their own pivot
 *    (`employment_profile_manager`), with the same tri-state discipline
 *    (see syncManagers()) — a genuine set change is logged as ONE explicit
 *    activity() entry on the profile (D-6), since the pivot is invisible to
 *    the automatic logFillable() the model otherwise relies on.
 *
 * The caller (UserService::create/update) is responsible for the surrounding
 * transaction, mirroring ProfileWriter.
 */
class EmploymentWriter
{
    private const string MANAGERS_ACTIVITY_DESCRIPTION = 'Employment reports-to update';

    public function __construct(private readonly ProductLineWriter $productLineWriter) {}

    /**
     * Persist the nested employment profile for the user inside the caller's
     * transaction. No-op when `$employment` is null (the key was absent from
     * the request — leave the row untouched). Deletes the row when `$employment
     * ->delete` is true (an explicit `employment: null`); a delete on a user
     * with no row is itself a harmless no-op, so create and update share this
     * single code path. $actor causes the explicit reports-to activity entry
     * (D-6), when the managers set actually changes.
     */
    public function write(User $user, ?EmploymentData $employment, User $actor): void
    {
        if ($employment === null) {
            return;
        }

        if ($employment->delete) {
            // Cascades onto employment_profile_operational_site,
            // employment_product_lines and employment_profile_manager (FK
            // cascadeOnDelete), so the site memberships, the competence and
            // the reports-to managers go with the row.
            $user->employment()->delete();

            return;
        }

        // Step 1: upsert the plain-column attributes onto the 1:1 row.
        $profile = $user->employment()->updateOrCreate([], $employment->attributes());

        // Step 2: apply the site-membership tri-state onto the pivot.
        $this->syncSiteMemberships($profile, $employment);

        // Step 3: apply the competence tri-state onto its own child rows.
        $this->syncProductLines($profile, $employment);

        // Step 4: apply the reports-to tri-state onto its own pivot,
        // enforcing D-5 (a manager reports to no one) unconditionally.
        $this->syncManagers($profile, $employment, $user, $actor);
    }

    /**
     * Apply the `product_lines` tri-state (spec 0111) onto
     * `employment_product_lines`: absent leaves the rows alone, any submitted
     * array replaces them wholesale (an empty one clears them, D-8).
     *
     * Spec 0129 D-2 takes precedence: when the wildcard flag is on, the rows
     * are cleared REGARDLESS of what (or whether) `product_lines` was
     * submitted — the FormRequest already 422s a flag+non-empty-rows payload
     * (ValidatesEmployment::validateEmploymentProductLines), so by the time
     * this runs the only legitimate combinations are "flag true, rows absent"
     * (AC-009) and "flag true, rows []" (AC-008); both end up here clearing
     * any rows left over from before the flag was turned on.
     *
     * The delete-all + insert itself is NOT reimplemented here: the profile
     * exposes the same `productLines()` relation every other owner does, so
     * the shared ProductLineWriter is the single write path for the
     * collection (spec 0111 constraint).
     */
    private function syncProductLines(EmploymentProfile $profile, EmploymentData $employment): void
    {
        if ($employment->coversAllProductCategories) {
            $profile->productLines()->delete();

            return;
        }

        if (! $employment->productLinesProvided) {
            return;
        }

        $this->productLineWriter->sync($profile, $employment->productLines);
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

    /**
     * Apply the `reports_to_ids` tri-state (spec 0166 D-2/D-4) onto
     * `employment_profile_manager`: absent leaves the pivot alone, any
     * submitted array replaces it wholesale (an empty one clears it).
     *
     * D-5 takes precedence, exactly like the wildcard flag on the competence
     * (syncProductLines() above): when the profile is a manager, the pivot is
     * cleared REGARDLESS of what (or whether) `reports_to_ids` was
     * submitted — a Responsible reports to no one. The self-reference guard
     * (D-5 "defense in depth") discards the user's own id here too, even
     * though the FormRequest already 422s it on update.
     *
     * A genuine set change is logged as ONE explicit activity() entry (D-6);
     * resubmitting the same set — in any order — is a no-op, both on the
     * pivot and on the log.
     */
    private function syncManagers(EmploymentProfile $profile, EmploymentData $employment, User $user, User $actor): void
    {
        if (! $employment->isManager && ! $employment->reportsToIdsProvided) {
            return;
        }

        $targetIds = $employment->isManager ? [] : $this->managerIdsExcludingSelf($employment->reportsToIds, $user);

        // The accessor below reads off this eager-loaded collection, so this
        // is the only query the whole sync needs beyond the sync() itself.
        $profile->load('reportsTo');
        $currentIds = $profile->reportsToIds;

        if ($this->sameIdSet($currentIds, $targetIds)) {
            return;
        }

        $profile->reportsTo()->sync($targetIds);

        $this->logManagersChange($profile, $actor, $currentIds, $targetIds);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function managerIdsExcludingSelf(array $ids, User $user): array
    {
        return collect($ids)->reject(fn (int $id): bool => $id === $user->id)->unique()->values()->all();
    }

    /**
     * Order-insensitive comparison of two id sets (spec 0166 AC-004/AC-007:
     * resubmitting the same managers, in any order, is a no-op).
     *
     * @param  array<int, int>  $a
     * @param  array<int, int>  $b
     */
    private function sameIdSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * @param  array<int, int>  $before
     * @param  array<int, int>  $after
     */
    private function logManagersChange(EmploymentProfile $profile, User $actor, array $before, array $after): void
    {
        sort($before);
        sort($after);

        activity($profile->getTable())
            ->performedOn($profile)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => ['reports_to_ids' => $after], 'old' => ['reports_to_ids' => $before]])
            ->log(self::MANAGERS_ACTIVITY_DESCRIPTION);
    }
}
