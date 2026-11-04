<?php

namespace App\DataObjects\Notes;

use App\Models\Note;
use Illuminate\Support\Collection;

/**
 * One keyset page of ROOT notes (spec 0052, D-13), each already carrying its
 * full `replies` collection. Service -> Controller, so the controller never
 * touches pagination internals.
 */
final readonly class NotePage
{
    /**
     * @param  Collection<int, Note>  $items
     * @param  array<int, array{id: int, code: string, title: string}>  $quoteScopes  Every scoping
     *                                                                                unit of the host record (spec 0085), independent of the applied filter: the
     *                                                                                selector must keep offering the scopes the current one excludes.
     * @param  array{allowed: bool, role: string|null}  $mentionOutside  Spec 0353: whether the actor
     *                                                                   may mention people outside the record, and the role they would get.
     */
    public function __construct(
        public Collection $items,
        public ?string $nextCursor,
        public bool $hasMore,
        public array $quoteScopes = [],
        public array $mentionOutside = ['allowed' => false, 'role' => null],
    ) {}
}
