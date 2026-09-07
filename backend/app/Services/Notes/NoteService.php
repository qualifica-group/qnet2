<?php

namespace App\Services\Notes;

use App\DataObjects\Notes\CreateNoteData;
use App\DataObjects\Notes\NoteCursor;
use App\DataObjects\Notes\NotePage;
use App\DataObjects\Notes\NoteQuoteScope;
use App\DataObjects\Notes\UpdateNoteData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Note;
use App\Models\User;
use App\Notes\Mentions\MentionValidator;
use App\Notes\NoteEntityRegistry;
use App\Notes\NoteThreadResolver;
use App\Notifications\NoteMentionNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * All business logic for the agnostic notes component (spec 0052): host
 * resolution + read authorization (delegated to NoteEntityRegistry, D-6/D-9),
 * D-7 thread normalization, D-10/D-12 mention validation, D-11 notification
 * dispatch. Controllers stay thin (permission/ownership gate + Resource
 * output); everything else lives here.
 */
final class NoteService
{
    private const int DEFAULT_LIMIT = 20;

    public function __construct(
        private readonly NoteEntityRegistry $registry,
        private readonly NoteThreadResolver $threadResolver,
        private readonly MentionValidator $mentionValidator,
    ) {}

    /**
     * GET /api/notes — a keyset page of ROOT notes (`created_at desc, id
     * desc`, D-13), each with its full `replies` eager-loaded. $scope (spec
     * 0085, D-2) narrows the ROOT set only — a reply is always returned
     * alongside its root regardless of its own `quote_id` (AC-016).
     *
     * The page also carries the host record's scoping units, so the caller
     * never has to fetch them separately to render filter and destination
     * (spec 0085 amendment 2026-08-06). They are NOT narrowed by $scope:
     * they are the choices, not the result.
     */
    public function listForEntity(User $user, string $entityType, int $entityId, ?NoteCursor $cursor, ?int $limit, NoteQuoteScope $scope): NotePage
    {
        $record = $this->authorizedRecord($user, $entityType, $entityId);
        $limit ??= self::DEFAULT_LIMIT;

        $query = Note::query()
            ->where('notable_type', $record->getMorphClass())
            ->where('notable_id', $record->getKey())
            ->whereNull('parent_id')
            ->with([
                'author.avatar',
                'mentionedUsers.avatar',
                'quote',
                'replies' => fn (HasMany $replies) => $replies
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->with(['author.avatar', 'mentionedUsers.avatar', 'quote']),
            ]);

        $this->applyQuoteScope($query, $scope);
        $this->applyCursor($query, $cursor);

        $roots = $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get();

        $hasMore = $roots->count() > $limit;
        $items = $roots->take($limit)->values();

        return new NotePage(
            $items,
            $hasMore ? $this->cursorFor($items->last())->encode() : null,
            $hasMore,
            $this->registry->quoteScopes($entityType, $record),
        );
    }

    /**
     * POST /api/notes. `notes.create` is checked by the controller (Policy);
     * read access to the host record is checked HERE, ANDed (D-6).
     */
    public function create(User $user, CreateNoteData $data): Note
    {
        $record = $this->authorizedRecord($user, $data->entityType, $data->entityId);
        $alias = $record->getMorphClass();

        $this->mentionValidator->validate($data->entityType, $record, $data->body, $data->mentionIds);
        $parentId = $this->threadResolver->resolveParentId($data->parentId, $record, $alias);
        $quoteId = $this->resolveQuoteId($data->quoteId, $parentId);

        return DB::transaction(function () use ($user, $record, $alias, $data, $parentId, $quoteId): Note {
            $note = new Note(['body' => $data->body]);
            $note->notable_type = $alias;
            $note->notable_id = $record->getKey();
            $note->parent_id = $parentId;
            $note->quote_id = $quoteId;
            $note->user_id = $user->id;
            $note->save();

            $this->syncMentionsAndNotify($note, $user, $data->entityType, $record, $data->mentionIds);

            return $note->load(['author.avatar', 'mentionedUsers.avatar', 'quote']);
        });
    }

    /**
     * PATCH /api/notes/{note}. Ownership is checked by the controller
     * (Policy, D-8); this additionally re-checks read access to the host
     * record, since it may have been revoked since the note was created
     * (data_contract: "403 ... o se hai perso l'accesso al record ospite").
     */
    public function update(User $user, Note $note, UpdateNoteData $data): Note
    {
        [$entityType, $record] = $this->reauthorizeHost($user, $note);

        $this->mentionValidator->validate($entityType, $record, $data->body, $data->mentionIds);

        return DB::transaction(function () use ($note, $data, $user, $entityType, $record): Note {
            $note->body = $data->body;
            $note->edited_at = now();
            $note->save();

            $this->syncMentionsAndNotify($note, $user, $entityType, $record, $data->mentionIds);

            return $note->load(['author.avatar', 'mentionedUsers.avatar', 'quote']);
        });
    }

    /**
     * DELETE /api/notes/{note} — soft delete only (D-8): replies are left
     * untouched in the database, they simply stop being reachable once their
     * root no longer appears in listForEntity's query.
     */
    public function delete(Note $note): void
    {
        $note->delete();
    }

    /**
     * GET /api/notes/mentionable-users — the D-10 set, paginated exactly
     * like every other for-select endpoint (ADR 0011): `ids[]` hydration is
     * still scoped to the mentionable set, never bypassing it.
     */
    public function mentionableUsers(User $user, string $entityType, int $entityId, ForSelectQuery $query): ForSelectResult
    {
        $record = $this->authorizedRecord($user, $entityType, $entityId);

        $scope = $this->registry->mentionableUsersQuery($entityType, $record)
            ->select(['id', 'name', 'email'])
            ->with('avatar');

        $filtered = clone $scope;

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $filtered->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $total = (clone $filtered)->count();

        $page = $filtered->orderBy('name')->orderBy('id')->offset($query->offset)->limit($query->limit)->get();

        if ($query->hasIds()) {
            $missingIds = array_values(array_diff($query->ids, $page->pluck('id')->all()));

            if ($missingIds !== []) {
                $page = $page->concat((clone $scope)->whereIn('id', $missingIds)->get());
            }
        }

        return new ForSelectResult($page, $total, $query->offset, $query->limit);
    }

    private function authorizedRecord(User $user, string $entityType, int $entityId): Model
    {
        $record = $this->registry->findRecord($entityType, $entityId);
        $this->registry->assertReadable($user, $entityType, $record);

        return $record;
    }

    /**
     * @return array{0: string, 1: Model}
     */
    private function reauthorizeHost(User $user, Note $note): array
    {
        $record = $note->notable;
        abort_if($record === null, 404);

        $entityType = $this->registry->entityTypeForModel($record);
        $this->registry->assertReadable($user, $entityType, $record);

        return [$entityType, $record];
    }

    /**
     * Re-syncs `note_mentions` and queues NoteMentionNotification for every
     * NEWLY attached recipient (D-11/D-62: already-mentioned users are never
     * renotified, the author never notifies itself), dispatched AFTER the
     * transaction commits so a slow/unavailable channel never blocks the save.
     *
     * @param  array<int, int>  $mentionIds
     */
    private function syncMentionsAndNotify(Note $note, User $author, string $entityType, Model $record, array $mentionIds): void
    {
        $sync = $note->mentionedUsers()->sync($mentionIds);
        $note->load('mentionedUsers.avatar');

        $newRecipientIds = array_values(array_diff(array_unique($sync['attached']), [$author->id]));

        if ($newRecipientIds === []) {
            return;
        }

        $label = $this->registry->labelFor($entityType, $record);

        // The deep link is NOT precomputed here: it depends on the recipient's
        // own abilities, so the notification resolves it per notifiable
        // (same rule as RecordAssignmentNotification, spec 0081).
        DB::afterCommit(function () use ($newRecipientIds, $note, $author, $label, $entityType, $record): void {
            $recipients = User::query()->whereIn('id', $newRecipientIds)->get();
            Notification::send($recipients, new NoteMentionNotification($note, $author, $label, $entityType, $record));
        });
    }

    /**
     * D-4: a reply always inherits its ROOT's `quote_id`, ignoring whatever
     * $requestedQuoteId the client sent — a reply's context can never
     * diverge from the message it answers. $parentId is already the
     * resolved ROOT id (NoteThreadResolver re-parents a reply-to-a-reply to
     * its own root), and every existing root/reply pair already shares the
     * same `quote_id` by this same invariant, so reading it off that single
     * row is exact.
     */
    private function resolveQuoteId(?int $requestedQuoteId, ?int $parentId): ?int
    {
        if ($parentId === null) {
            return $requestedQuoteId;
        }

        /** @var int|null $inherited */
        $inherited = Note::query()->whereKey($parentId)->value('quote_id');

        return $inherited;
    }

    /**
     * D-2, applied in QUERY (never after the fetch — the keyset pagination
     * requires it): an allow-list of the three scope shapes, no interpolated
     * input reaches the query.
     */
    private function applyQuoteScope(Builder $query, NoteQuoteScope $scope): void
    {
        match ($scope->type) {
            'general' => $query->whereNull('quote_id'),
            'quote' => $query->where('quote_id', $scope->quoteId),
            default => null,
        };
    }

    private function applyCursor(Builder $query, ?NoteCursor $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        $query->where(function (Builder $q) use ($cursor): void {
            $q->where('created_at', '<', $cursor->createdAt)
                ->orWhere(function (Builder $tie) use ($cursor): void {
                    $tie->where('created_at', $cursor->createdAt)->where('id', '<', $cursor->id);
                });
        });
    }

    private function cursorFor(Note $note): NoteCursor
    {
        return new NoteCursor($note->getRawOriginal('created_at'), (int) $note->id);
    }
}
