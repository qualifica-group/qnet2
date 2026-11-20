<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\Attachment;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The `documents_count` badge of the `documents` row action (spec 0363 D-4),
 * one rule for every grid that offers it: the files of the `documents`
 * collection only, never the other collections of the same owner, and only
 * those the actor has not read yet (Attachment::unreadBy, TracksReads).
 */
final class DocumentsCountColumn
{
    public const string FIELD = 'documents_count';

    private const string COLLECTION = 'documents';

    /**
     * `withCount` argument for a model using HasAttachments: its own unread documents.
     *
     * @return array<string, callable(Builder<Attachment>): void>
     */
    public static function own(): array
    {
        return ['attachments as '.self::FIELD => fn (Builder $query) => self::constrain($query)];
    }

    /**
     * `addSelect` argument counting the unread documents of the opportunity
     * the row points to (an offer shows its parent opportunity's documents).
     *
     * @return array<string, Builder<Attachment>>
     */
    public static function ofOpportunity(string $opportunityIdColumn): array
    {
        $query = Attachment::query()
            ->selectRaw('count(*)')
            ->where('attachable_type', (new Opportunity)->getMorphClass())
            ->whereColumn('attachable_id', $opportunityIdColumn);
        self::constrain($query);

        return [self::FIELD => $query];
    }

    public static function project(Model $row): int
    {
        return (int) ($row->getAttribute(self::FIELD) ?? 0);
    }

    /**
     * @param  Builder<Attachment>  $query
     */
    private static function constrain(Builder $query): void
    {
        $query->where('collection', self::COLLECTION)->unreadBy((int) Auth::id());
    }
}
