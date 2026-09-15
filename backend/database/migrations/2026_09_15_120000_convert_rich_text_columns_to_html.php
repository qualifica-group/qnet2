<?php

declare(strict_types=1);

use App\RichText\RichTextConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-10: one-time backfill converting the plain-text values already stored in
 * `notes.body`, `tasks.description`, `task_templates.description` and
 * `task_template_items.description` to the rich text HTML fragment shape
 * D-1 now requires. `App\RichText\RichTextConverter` is the single source of
 * truth for both directions — this migration owns no conversion logic of
 * its own, only the row-by-row plumbing.
 *
 * Runs on the query builder (`DB::table`), never through Eloquent: models
 * would fire activity-log events, casts and soft-delete scopes here, all
 * unrelated side effects of a data backfill. Querying through the plain
 * builder also means soft-deleted notes are converted too — the query
 * builder never applies `SoftDeletingScope` in the first place.
 *
 * Idempotent by construction: a value whose trimmed form already starts
 * with `<p>` is assumed already converted and left untouched by `up()`, so
 * re-running it (e.g. after a later deploy inserts more legacy rows) never
 * double-escapes existing HTML.
 */
return new class extends Migration
{
    private const int CHUNK_SIZE = 200;

    public function up(): void
    {
        $this->convertColumn('notes', 'body', convertMentionTokens: true, nullable: false);
        $this->convertColumn('tasks', 'description', convertMentionTokens: false, nullable: true);
        $this->convertColumn('task_templates', 'description', convertMentionTokens: false, nullable: true);
        $this->convertColumn('task_template_items', 'description', convertMentionTokens: false, nullable: true);
    }

    public function down(): void
    {
        $this->revertColumn('notes', 'body', restoreMentionTokens: true);
        $this->revertColumn('tasks', 'description', restoreMentionTokens: false);
        $this->revertColumn('task_templates', 'description', restoreMentionTokens: false);
        $this->revertColumn('task_template_items', 'description', restoreMentionTokens: false);
    }

    /**
     * Plain text -> HTML for every non-null, not-yet-converted value of
     * $table.$column.
     *
     * `$nullable` mirrors the column's own nullability: a nullable
     * description column follows D-2 and becomes NULL when the source was
     * only whitespace (`RichTextConverter::plainTextToHtml` returns null for
     * that input). `notes.body` is NOT NULL and can never receive that null
     * — for that one case the original text is preserved verbatim, escaped,
     * inside a `<p>` rather than losing the row's data.
     */
    private function convertColumn(string $table, string $column, bool $convertMentionTokens, bool $nullable): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table, $column, $convertMentionTokens, $nullable) {
                DB::transaction(function () use ($rows, $table, $column, $convertMentionTokens, $nullable) {
                    foreach ($rows as $row) {
                        $original = (string) $row->$column;

                        if (self::looksLikeHtml($original)) {
                            continue;
                        }

                        $html = RichTextConverter::plainTextToHtml($original, $convertMentionTokens);

                        if ($html === null) {
                            $html = $nullable ? null : '<p>'.e($original).'</p>';
                        }

                        DB::table($table)->where('id', $row->id)->update([$column => $html]);
                    }
                });
            });
    }

    /**
     * HTML -> plain text, the inverse of convertColumn(). Every row touched
     * by up() has non-null HTML here, so no null/NOT NULL branching is
     * needed on the way back.
     */
    private function revertColumn(string $table, string $column, bool $restoreMentionTokens): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($rows) use ($table, $column, $restoreMentionTokens) {
                DB::transaction(function () use ($rows, $table, $column, $restoreMentionTokens) {
                    foreach ($rows as $row) {
                        $plainText = RichTextConverter::htmlToPlainText((string) $row->$column, $restoreMentionTokens);

                        DB::table($table)->where('id', $row->id)->update([$column => $plainText]);
                    }
                });
            });
    }

    private static function looksLikeHtml(string $value): bool
    {
        return str_starts_with(trim($value), '<p>');
    }
};
