<?php

namespace App\Notes;

use App\Models\User;
use App\Notes\Contracts\NotableEntity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The single translation point between the API's authorization vocabulary
 * (`entity_type` slug) and the database's identity vocabulary (`notable_type`
 * morph alias) — spec 0052, D-9. Every note endpoint resolves its host
 * record and its authorization through here, by resolving whichever
 * NotableEntity class config('notes.notable_types') maps the slug to (pure
 * class-string, config:cache-safe) and delegating to it. Nothing in this
 * class, the Note model, the note controllers/resources or the mention
 * helpers ever names a host module directly (AC-021) — a concrete
 * NotableEntity implementation belongs to the host module's OWN namespace,
 * never to app/Notes/.
 */
final class NoteEntityRegistry
{
    /**
     * @return array<int, string>
     */
    public function registeredTypes(): array
    {
        return array_keys($this->types());
    }

    public function isRegistered(string $entityType): bool
    {
        return array_key_exists($entityType, $this->types());
    }

    /**
     * @throws ModelNotFoundException 404 when no record matches
     */
    public function findRecord(string $entityType, int $entityId): Model
    {
        $modelClass = $this->entityFor($entityType)->modelClass();

        return $modelClass::query()->findOrFail($entityId);
    }

    /**
     * The message is explicit (and catalogued in lang/*.json) because
     * BaseApiController degrades a message-less HttpException to the generic
     * "An unexpected error occurred.": a legitimate refusal would otherwise
     * reach the operator as an unexpected server error.
     *
     * @throws HttpException 403 when unreadable
     */
    public function assertReadable(User $user, string $entityType, Model $record): void
    {
        abort_unless($this->entityFor($entityType)->authorizeRead($user, $record), 403, 'This action is unauthorized.');
    }

    public function mentionableUsersQuery(string $entityType, Model $record): Builder
    {
        return $this->entityFor($entityType)->mentionableUsersQuery($record);
    }

    public function ownsQuote(string $entityType, Model $record, int $quoteId): bool
    {
        return $this->entityFor($entityType)->ownsQuote($record, $quoteId);
    }

    /**
     * @return array<int, array{id: int, code: string, title: string}>
     */
    public function quoteScopes(string $entityType, Model $record): array
    {
        return $this->entityFor($entityType)->quoteScopes($record);
    }

    public function labelFor(string $entityType, Model $record): string
    {
        return $this->entityFor($entityType)->label($record);
    }

    /**
     * The SPA PATH the mention notification points $recipient to, or null
     * when no reachable screen shows that note. A path, never an absolute
     * URL: `action_url` is stored as an internal path across this whole app
     * (the campanella rejects anything else, see safe-internal-path.ts), and
     * `config('app.frontend_url')` is prepended in the mail CTA only.
     */
    public function deepLinkFor(string $entityType, Model $record, User $recipient, ?int $quoteId): ?string
    {
        return $this->entityFor($entityType)->deepLinkPath($record, $recipient, $quoteId);
    }

    /**
     * Reverse lookup used when a note's own `entity_type` is not on the
     * request (PATCH/DELETE only carry the note id, D-6/D-8): the slug whose
     * `modelClass()` matches $record's class. Phase 1 registers exactly one
     * slug per model, so this is unambiguous; a future phase letting two
     * slugs share a model (D-9's own example) would need the note to carry
     * its slug explicitly — out of scope here (only one entry exists).
     */
    public function entityTypeForModel(Model $record): string
    {
        foreach (array_keys($this->types()) as $entityType) {
            if ($this->entityFor($entityType)->modelClass() === get_class($record)) {
                return $entityType;
            }
        }

        abort(422, 'This note is attached to an unregistered entity.');
    }

    private function entityFor(string $entityType): NotableEntity
    {
        abort_unless($this->isRegistered($entityType), 422, "Unknown entity_type \"{$entityType}\".");

        return app($this->types()[$entityType]);
    }

    /**
     * @return array<string, class-string<NotableEntity>>
     */
    private function types(): array
    {
        /** @var array<string, class-string<NotableEntity>> $types */
        $types = (array) config('notes.notable_types');

        return $types;
    }
}
