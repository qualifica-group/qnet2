<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0426 D-1: every formalab_classes row gets id = edition_id, so a
 * relation field (which stores the record id) stores the edition id. Two
 * passes: first every id is moved above both ranges, then set to its
 * edition_id, so no intermediate value collides with another row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $offset = (int) DB::table('formalab_classes')->max('id') + (int) DB::table('formalab_classes')->max('edition_id') + 1;

        if ($offset === 1) {
            return;
        }

        DB::table('formalab_classes')->whereColumn('id', '<>', 'edition_id')->update(['id' => DB::raw("id + {$offset}")]);
        DB::table('formalab_classes')->whereColumn('id', '<>', 'edition_id')->update(['id' => DB::raw('edition_id')]);
    }

    /** The ids stay valid as they are: nothing to undo. */
    public function down(): void {}
};
