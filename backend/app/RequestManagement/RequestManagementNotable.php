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

/**
 * The `request-management` notable_types descriptor (spec 0052, D-9/D-10;
 * spec 0086, D-9; spec 0087, D-9): declares how the agnostic notes component
 * may attach to an Opportunity through THIS module's OWN authorization story
 * (spec 0049) — read access and the mentionable set both mirror the work
 * panel's own scope (RequestManagementScope::scopeToActor(), `request-management.
 * viewAll`), just re-keyed on the Opportunity's Offerte since the
 * predicate itself is a Quote one (`quotes.operator_id`, spec 0087 D-9);
 * this class never invents a separate rule.
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
     * D-10: active users who hold `request-management.view` AND either
     * operate at least one of this Opportunity's Offerte (spec 0087, D-9) or
     * hold `request-management.viewAll`, plus super-admins. A plain `whereHas`
     * matching the role by NAME — not the `role()` scope, which resolves the
     * name via `Role::findByName()` and THROWS `RoleDoesNotExist` if that row
     * hasn't been created yet (e.g. before `roles:create-super-admin` ever
     * ran). This must never 500 the endpoint on an unseeded environment.
     */
    public function mentionableUsersQuery(Model $record): Builder
    {
        /** @var Opportunity $record */
        $operatorIds = Quote::query()
            ->where('opportunity_id', $record->getKey())
            ->whereNotNull('operator_id')
            ->pluck('operator_id');

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($operatorIds): void {
                $query->whereHas('roles', fn (Builder $role) => $role->where('name', 'super-admin'))
                    ->orWhere(function (Builder $canRead) use ($operatorIds): void {
                        $canRead->permission('request-management.view')
                            ->where(function (Builder $access) use ($operatorIds): void {
                                $access->whereIn('id', $operatorIds)
                                    ->orWhere(function (Builder $viewAll): void {
                                        $viewAll->permission('request-management.viewAll');
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

    public function deepLinkPath(Model $record): string
    {
        return '/request-management/'.$record->getKey();
    }
}
