<?php

namespace App\Http\Resources;

use App\Models\Note;
use App\Notes\Mentions\MentionParser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for a Note (spec 0052, data_contract SHAPE Note), reused by
 * index/store/update. `mentions` is ordered by first appearance in `body`
 * (MentionParser::extractIds), not by pivot insertion order. `can` is
 * computed server-side for the CURRENT user via NotePolicy (Gate::before
 * still grants the super-admin, D-8).
 *
 * `replies` is included ONLY when $includeReplies is true (index roots);
 * absent on a bare note (store/update) and never nested past one level
 * (each reply is rendered with $includeReplies = false, D-7).
 *
 * `quote_id`/`quote` (spec 0085, D-1) label the note's scope: null for a
 * general note. The `quote` relation is assumed eager-loaded by the caller
 * (NoteService) — reading it here never triggers a lazy load.
 *
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    public function __construct(Note $resource, private readonly bool $includeReplies = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Note $note */
        $note = $this->resource;
        $user = $request->user();

        $data = [
            'id' => $note->id,
            'body' => $note->body,
            'author' => new NoteAuthorResource($note->author),
            'mentions' => $this->mentions($note),
            'parent_id' => $note->parent_id,
            'quote_id' => $note->quote_id,
            'quote' => $this->quote($note),
            'created_at' => $note->created_at?->toIso8601String(),
            'edited_at' => $note->edited_at?->toIso8601String(),
            'can' => [
                'update' => $user !== null && $user->can('update', $note),
                'delete' => $user !== null && $user->can('delete', $note),
            ],
        ];

        if ($this->includeReplies) {
            $data['replies'] = $note->replies
                ->map(fn (Note $reply): array => (new self($reply))->toArray($request))
                ->values()
                ->all();
        }

        return $data;
    }

    /**
     * @return array{id: int, code: string, title: string}|null
     */
    private function quote(Note $note): ?array
    {
        $quote = $note->quote;

        if ($quote === null) {
            return null;
        }

        return ['id' => $quote->id, 'code' => $quote->code, 'title' => $quote->title];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function mentions(Note $note): array
    {
        $namesById = $note->mentionedUsers->pluck('name', 'id');

        return collect(MentionParser::extractIds($note->body))
            ->filter(fn (int $id): bool => $namesById->has($id))
            ->map(fn (int $id): array => ['id' => $id, 'name' => $namesById->get($id)])
            ->values()
            ->all();
    }
}
