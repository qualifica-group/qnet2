<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "This category can be chosen as a classification target" flag (spec 0074).
 *
 * Unlike `requires_quote`, this flag is OWNED BY EACH NODE and never
 * inherited (spec 0074 D-2): the whole point is a container category that
 * cannot be picked while its children can. It therefore needs no inheritance
 * writer and no subtree sync — a plain per-row column.
 *
 * A false value hides the category from the for-select list feeding every
 * destination picker and makes the destination endpoints reject it, but it
 * stays a valid `parent_id` and keeps supplying its attributes to its
 * descendants.
 *
 * Defaults to true: no existing category changes behaviour at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_selectable')->default(true)->after('requires_quote');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('is_selectable');
        });
    }
};
