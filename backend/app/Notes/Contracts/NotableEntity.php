<?php

declare(strict_types=1);

namespace App\Notes\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The per-module descriptor NoteEntityRegistry needs to attach the agnostic
 * notes component to a host module (spec 0052, D-9): which model backs the
 * entity, who may READ its notes, who is mentionable on one of its records,
 * how a record is labelled, and where its SPA deep link lives.
 *
 * One implementation per `notable_types` slug in config/notes.php — a pure
 * class-string there, resolved from the container by NoteEntityRegistry.
 * This interface (and the registry) live in app/Notes/ and stay agnostic on
 * purpose (AC-021): a concrete implementation belongs to the host module's
 * OWN namespace, never to app/Notes/ — the module declares how it wants to
 * be treated, the notes component never names the module.
 */
interface NotableEntity
{
    /**
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Whether $user may read $record's notes (D-6): the notes component
     * never owns a read permission of its own, it always delegates to the
     * host entity's existing gate.
     */
    public function authorizeRead(User $user, Model $record): bool;

    /**
     * Users allowed to be @mentioned on $record (D-10) — scoped, N+1-free.
     */
    public function mentionableUsersQuery(Model $record): Builder;

    /**
     * Whether $quoteId names a scoping unit that belongs to $record (spec
     * 0085, D-1) — the boundary `quote_id` (POST) and the numeric
     * `quote_scope` (GET) are validated against, delegated here so the
     * agnostic notes core never queries a host module's own scoping entity
     * directly. A host with no such concept simply returns false always.
     */
    public function ownsQuote(Model $record, int $quoteId): bool;

    /**
     * Every scoping unit that belongs to $record, in the SAME `{id, code,
     * title}` shape NoteResource exposes as a note's own `quote` — the list
     * the client offers as filter and as destination. Returned by the notes
     * index itself so a host mounting the component never has to fetch and
     * pass it: a second projection of the same thing would drift apart at
     * the first change, and a host that cannot fetch it (a grid row, a
     * dialog) would silently lose the selector.
     *
     * Same delegation reason as ownsQuote(): the agnostic core never queries
     * a host module's own scoping entity. A host with no such concept
     * returns an empty array, and the selectors do not mount at all.
     *
     * @return array<int, array{id: int, code: string, title: string}>
     */
    public function quoteScopes(Model $record): array;

    /**
     * Human label for $record, used in the mention notification message.
     */
    public function label(Model $record): string;

    /**
     * SPA-relative deep link path to the note identified by $record and
     * $quoteId, for THIS recipient (no host/scheme — the caller prefixes
     * config('app.frontend_url') for a mail CTA and nowhere else).
     *
     * Per-recipient, and returning null, for the same reason
     * RecordLinkResolver is (spec 0081): a host module can be reachable
     * through more than one route gated by more than one permission set, and
     * a link that lands on a 403 is worse than no link at all. $quoteId is
     * the note's own scoping unit (spec 0085, D-1), null for a general note:
     * a host whose deep link is keyed on that unit needs it to point at the
     * screen where the note is actually readable.
     */
    public function deepLinkPath(Model $record, User $recipient, ?int $quoteId): ?string;
}
