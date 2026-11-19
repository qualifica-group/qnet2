<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Per-user read state of a shared content item (notes, documents): the row
 * action badges of the grids count only what the user has not read yet.
 *
 * Reads live in `<singular table>_reads` (`<singular table>_id`, `user_id`,
 * `read_at`). An item never counts as unread for its own author, nor when it
 * was migrated from the legacy QNet (`old_id` set): it was already read
 * there, and marking 100k+ legacy items for every user is not an option.
 */
trait TracksReads
{
    /** The column holding the id of the user who wrote/uploaded the item. */
    abstract protected function readAuthorColumn(): string;

    /**
     * Items $userId has not read yet.
     */
    #[Scope]
    protected function unreadBy(Builder $query, int $userId): void
    {
        $readsTable = $this->readsTable();
        $authorColumn = $query->qualifyColumn($this->readAuthorColumn());

        $query->whereNull($query->qualifyColumn('old_id'))
            ->where(fn (Builder $author) => $author->whereNull($authorColumn)->orWhere($authorColumn, '!=', $userId))
            ->whereNotExists(fn ($reads) => $reads->from($readsTable)
                ->whereColumn($readsTable.'.'.$this->getForeignKey(), $query->qualifyColumn($this->getKeyName()))
                ->where($readsTable.'.user_id', $userId));
    }

    /**
     * Marks every unread item of $items as read by $userId, in one
     * INSERT ... SELECT. Returns how many items became read.
     *
     * @param  Builder<static>  $items
     */
    public static function markReadBy(int $userId, Builder $items): int
    {
        $model = new static;
        $selection = (clone $items)->unreadBy($userId)
            ->selectRaw($model->qualifyColumn($model->getKeyName()).', ?', [$userId]);

        return DB::table($model->readsTable())->insertOrIgnoreUsing([$model->getForeignKey(), 'user_id'], $selection->toBase());
    }

    private function readsTable(): string
    {
        return Str::singular($this->getTable()).'_reads';
    }
}
