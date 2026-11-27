<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Note commessa" become the work order's "Descrizione" (user decision
 * 2026-11-27): the notes text moves into `description` and `internal_notes`
 * is dropped. A work order holding both keeps them, the notes first and the
 * old description after a blank line, so no text is lost. Query builder on
 * purpose: no activity log, `updated_at` untouched.
 *
 * `down()` restores the column only: the merged text cannot be split back.
 */
return new class extends Migration
{
    private const string TABLE = 'work_orders';

    private const string SEPARATOR = "\n\n";

    private const int CHUNK = 500;

    public function up(): void
    {
        // Step 1: the notes become the description.
        DB::table(self::TABLE)
            ->whereNotNull('internal_notes')
            ->where('internal_notes', '<>', '')
            ->select(['id', 'description', 'internal_notes'])
            ->chunkById(self::CHUNK, function (Collection $rows): void {
                foreach ($rows as $row) {
                    DB::table(self::TABLE)->where('id', $row->id)->update([
                        'description' => $this->merged($row->internal_notes, $row->description),
                    ]);
                }
            });

        // Step 2: the notes column is gone.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('internal_notes');
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->text('internal_notes')->nullable()->after('description');
        });
    }

    private function merged(string $notes, ?string $description): string
    {
        $parts = array_filter([$notes, $description], static fn (?string $text): bool => trim((string) $text) !== '');

        return implode(self::SEPARATOR, $parts);
    }
};
