<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\Attachment;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The `documents_count` badge of the `documents` row action (spec 0363 D-4),
 * one rule for every grid that offers it: the files of the `documents`
 * collection only, never the other collections of the same owner.
 */
final class DocumentsCountColumn
{
    public const string FIELD = 'documents_count';

    private const string COLLECTION = 'documents';

    /**
     * `withCount` argument for a model using HasAttachments: its own documents.
     *
     * @return array<string, callable(Builder<Attachment>): void>
     */
    public static function own(): array
    {
        return ['attachments as '.self::FIELD => fn (Builder $query) => $query->where('collection', self::COLLECTION)];
    }

    /**
     * `addSelect` argument counting the documents of the opportunity the row
     * points to (an offer shows its parent opportunity's documents).
     *
     * @return array<string, Builder<Attachment>>
     */
    public static function ofOpportunity(string $opportunityIdColumn): array
    {
        return [self::FIELD => Attachment::query()
            ->selectRaw('count(*)')
            ->where('attachable_type', (new Opportunity)->getMorphClass())
            ->whereColumn('attachable_id', $opportunityIdColumn)
            ->where('collection', self::COLLECTION)];
    }

    public static function project(Model $row): int
    {
        return (int) ($row->getAttribute(self::FIELD) ?? 0);
    }
}
