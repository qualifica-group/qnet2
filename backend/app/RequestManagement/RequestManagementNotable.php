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
 * viewAll` and — spec 0105 — `request-management.viewSite`), just re-keyed on
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
 */
final class RequestManagementNotable implements NotableEntity
{
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
        if (! $user->can('request-management.view')) {
            return false;
        }

        if ($user->can('request-management.viewAll')) {
            return true;
        }

        /** @var Opportunity $record */
        $query = Quote::query()->where('opportunity_id', $record->getKey());

        return RequestManagementScope::scopeToActor($query, $user)->exists();
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
     * be mentioned in it.
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var Opportunity $record */
        $operatorIds = Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->whereNotNull('operator_id')
            ->pluck('operator_id');

        $siteIds = Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->whereNotNull('operational_site_id')
            ->pluck('operational_site_id');

        // The branch is added only once the permission row EXISTS: spatie's
        // `permission()` scope goes through `Permission::findByName()` and
        // THROWS `PermissionDoesNotExist` on a name no row carries — the same
        // failure mode the super-admin `whereHas` above avoids for roles, and
        // the same rule applies: this must never 500 the endpoint on an
        // environment where `permissions:sync` has not run yet.
        //
        // Probed on the name AND the guard the scope itself will resolve
        // (`Guard::getDefaultName(User::class)`, exactly what
        // `scopePermission` passes to `findByName`): the same name can carry
        // one row per guard, and a hit on the wrong one would leave the
        // branch matching nobody.
        $viewSiteExists = Permission::query()
            ->where('name', 'request-management.viewSite')
            ->where('guard_name', Guard::getDefaultName(User::class))
            ->exists();

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($operatorIds, $siteIds, $viewSiteExists): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->orWhere(function (Builder $canRead) use ($operatorIds, $siteIds, $viewSiteExists): void {
                        $canRead->permission('request-management.view')
                            ->where(function (Builder $access) use ($operatorIds, $siteIds, $viewSiteExists): void {
                                $access->whereIn('id', $operatorIds)
                                    ->orWhere(function (Builder $viewAll): void {
                                        $viewAll->permission('request-management.viewAll');
                                    })
                                    ->when($viewSiteExists, function (Builder $tiers) use ($siteIds): void {
                                        $tiers->orWhere(function (Builder $bySite) use ($siteIds): void {
                                            $bySite->permission('request-management.viewSite')
                                                ->whereHas(
                                                    'employment.operationalSites',
                                                    fn (Builder $sites) => $sites->whereIn('operational_sites.id', $siteIds)
                                                );
                                        });
                                    });
                            });
                    });
            });
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
        $canWorkRequests = $recipient->can('request-management.view');

        if ($quoteId !== null && $canWorkRequests) {
            return '/request-management/'.$quoteId;
        }

        if ($recipient->can('opportunities.view')) {
            return '/opportunities/'.$record->getKey();
        }

        return $canWorkRequests ? '/request-management' : null;
    }
}
