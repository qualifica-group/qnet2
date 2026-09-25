<?php

declare(strict_types=1);

namespace App\RequestManagement;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

/**
 * The `request-management` notable_types descriptor (spec 0052, D-9/D-10;
 * spec 0086, D-9; spec 0087, D-9): declares how the agnostic notes component
 * may attach to an Opportunity through THIS module's OWN authorization story
 * (spec 0049) — read access and the mentionable set both mirror the work
 * panel's own scope (RequestManagementScope::scopeToActor(), `request-management.
 * viewAll`, `request-management.viewSite` — spec 0105 — and, for Gestione
 * Iscritti, `viewPrimarySite` — spec 0165), just re-keyed on
 * the Opportunity's Offerte since the predicate itself is a Quote one
 * (`quotes.operator_id`/`quotes.operational_site_id`); this class never
 * invents a separate rule.
 *
 * Lives in app/RequestManagement/ (this module's own namespace, alongside
 * AttributeSetResolver et al.), NOT app/Notes/: the module declares
 * how it wants to be treated by the notes component, the notes component
 * never names the module (AC-021). Resolved from the container by
 * App\Notes\NoteEntityRegistry via the class-string mapped in
 * config/notes.php ('request-management' => self::class) — pure data there,
 * config:cache-safe.
 *
 * Spec 0130: module()'s default return is RequestModule::Requests, so every
 * permission check and RequestManagementScope call below reads its
 * prefix/status-filter off it — EnrolleeManagementNotable, registered under
 * the `enrollee-management` slug, is the minimal subclass overriding ONLY
 * module() (mirrors EnrolleeManagementPolicy extends
 * RequestManagementPolicy). No method body is duplicated.
 */
class RequestManagementNotable implements NotableEntity
{
    protected function module(): RequestModule
    {
        return RequestModule::Requests;
    }

    public function modelClass(): string
    {
        return Opportunity::class;
    }

    /**
     * D-9: read access is decided by the SAME scoping rule the work panel
     * applies, but keyed on the Opportunity's Offerte (RequestManagementScope's
     * predicate is a Quote one) — an actor reads the collaborative record's
     * notes when they are the GA2 Operatore of at least one of this
     * Opportunity's Offerte (spec 0087, D-9), or hold `request-management.viewAll`.
     *
     * `viewAll` short-circuits BEFORE the existence check, and must: for a
     * viewAll actor RequestManagementScope::scopeToActor() returns the query
     * unrestricted, so `exists()` would still answer "does this Opportunity
     * have any Offerta at all" — denying the thread of an Opportunity with
     * zero Offerte to everyone, super-admin included. The Notes tab lives on
     * the Opportunity detail too (`/opportunities/{id}`), where zero Offerte
     * is a legitimate state; the existence check is only meaningful as the
     * operator predicate, so it is confined to the non-viewAll branch.
     *
     * Spec 0105, D-8: `viewSite` gets NO such short circuit. Its holder must
     * reach at least one Offerta of this Opportunity through their own Sedi,
     * which is exactly what scopeToActor() + `exists()` already answer — a
     * short circuit there would hand them the thread of every Opportunity,
     * including those with no Offerta of theirs at all.
     */
    public function authorizeRead(User $user, Model $record): bool
    {
        $module = $this->module();

        if (! $user->can($module->permission('view'))) {
            return false;
        }

        if ($user->can($module->permission('viewAll'))) {
            return true;
        }

        /** @var Opportunity $record */
        $query = Quote::query()->where('opportunity_id', $record->getKey());

        return RequestManagementScope::scopeToActor($query, $user, $module)->exists();
    }

    /**
     * D-10: active users who hold `request-management.view` AND reach this
     * Opportunity through any of the three visibility tiers — they operate at
     * least one of its Offerte (spec 0087, D-9), they hold
     * `request-management.viewAll`, or (spec 0105, D-9) they hold
     * `request-management.viewSite` and belong to the Sede operativa of one of
     * those Offerte — plus super-admins. A plain `whereHas` matching the role
     * by NAME — not the `role()` scope, which resolves the name via
     * `Role::findByName()` and THROWS `RoleDoesNotExist` if that row hasn't
     * been created yet (e.g. before `roles:create-super-admin` ever ran). This
     * must never 500 the endpoint on an unseeded environment.
     *
     * The third tier mirrors authorizeRead() above rather than extending it:
     * there the actor is known and the Offerte are queried, here the Offerte
     * are known and the actors are queried — the same rule read from the other
     * end. Without it a site-scoped colleague could read the thread and never
     * be mentioned in it. Spec 0165 D-4 adds the physical-Sede twin of that
     * tier, on the modules that have it.
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        $module = $this->module();

        /** @var Opportunity $record */
        $operatorIds = Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->whereNotNull('operator_id')
            ->pluck('operator_id');

        $siteIds = Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->whereNotNull('operational_site_id')
            ->pluck('operational_site_id');

        // A site branch is added only once its permission row EXISTS:
        // spatie's `permission()` scope goes through `Permission::findByName()`
        // and THROWS `PermissionDoesNotExist` on a name no row carries — the
        // same failure mode the super-admin `whereHas` above avoids for roles,
        // and the same rule applies: this must never 500 the endpoint on an
        // environment where `permissions:sync` has not run yet.
        $siteTiers = array_filter([
            // Spec 0105 D-9: any membership of the actor.
            'employment.operationalSites' => $this->existingPermission($module->permission('viewSite')),
            // Spec 0165 D-4: the actor's PHYSICAL Sede only.
            'employment.primaryOperationalSite' => $module->hasPrimarySiteTier()
                ? $this->existingPermission($module->permission(RequestModule::PRIMARY_SITE_ABILITY))
                : null,
        ]);

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($module, $operatorIds, $siteIds, $siteTiers): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->orWhere(function (Builder $canRead) use ($module, $operatorIds, $siteIds, $siteTiers): void {
                        $canRead->permission($module->permission('view'))
                            ->where(function (Builder $access) use ($module, $operatorIds, $siteIds, $siteTiers): void {
                                $access->whereIn('id', $operatorIds)
                                    ->orWhere(function (Builder $viewAll) use ($module): void {
                                        $viewAll->permission($module->permission('viewAll'));
                                    });

                                foreach ($siteTiers as $relation => $permission) {
                                    $access->orWhere(function (Builder $bySite) use ($permission, $relation, $siteIds): void {
                                        $bySite->permission($permission)
                                            ->whereHas($relation, fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds));
                                    });
                                }
                            });
                    });
            });
    }

    /**
     * $name when a permission row carries it on the guard the `permission()`
     * scope will resolve (`Guard::getDefaultName(User::class)`, exactly what
     * `scopePermission` passes to `findByName`), null otherwise: the same
     * name can carry one row per guard, and a hit on the wrong one would
     * leave the branch matching nobody.
     */
    private function existingPermission(string $name): ?string
    {
        $exists = Permission::query()
            ->where('name', $name)
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();

        return $exists ? $name : null;
    }

    /**
     * D-1: an Offerta belongs to this Opportunity when its own
     * `opportunity_id` matches — the same ownership the module already
     * enforces everywhere else a Quote is scoped to its parent.
     */
    public function ownsQuote(Model $record, int $quoteId): bool
    {
        /** @var Opportunity $record */
        return Quote::query()->whereKey($quoteId)->where('opportunity_id', $record->getKey())->exists();
    }

    /**
     * D-1: this Opportunity's own Offerte, the same ownership ownsQuote()
     * validates a single id against. Ordered by `code` so the selector reads
     * in the order the operator knows the offers by, and projected with an
     * explicit column list — the notes index runs this on every page.
     *
     * @return array<int, array{id: int, code: string, title: string}>
     */
    public function quoteScopes(Model $record): array
    {
        return Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(static fn (Quote $quote): array => [
                'id' => $quote->id,
                'code' => $quote->code,
                'title' => $quote->title,
            ])
            ->all();
    }

    public function label(Model $record): string
    {
        /** @var Opportunity $record */
        return (string) $record->name;
    }

    /**
     * Per-recipient (decisione utente 2026-09-07), because the three screens
     * that can show a note of this module answer to two different permission
     * sets and only one of them shows a note scoped to an Offerta:
     *
     * 1. the note's own Offerta — `/request-management/{quote}`, THE landing
     *    page for a scoped note: the work panel filters its thread on that
     *    exact `quote_id` (NoteService::applyQuoteScope()), and the route is
     *    keyed on the Quote, not on the Opportunity (spec 0086, D-1/D-2);
     * 2. the host Opportunity — `/opportunities/{opportunity}`, whose Notes
     *    tab is the ONLY screen showing a general note (`quote_id` null);
     * 3. the module's list, for a general note the recipient cannot reach
     *    through the Opportunity: the note itself is not readable there, but
     *    the module they do hold opens instead of a dead row.
     *
     * Null closes the chain: no reachable module, no link — the caller then
     * appends the request-access sentence rather than handing out a 403.
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string
    {
        $module = $this->module();
        $canWorkRequests = $recipient->can($module->permission('view'));

        if ($quoteId !== null && $canWorkRequests) {
            return $module->recordPath().'/'.$quoteId;
        }

        if ($recipient->can('opportunities.view')) {
            return '/opportunities/'.$record->getKey();
        }

        return $canWorkRequests ? $module->recordPath() : null;
    }
}
