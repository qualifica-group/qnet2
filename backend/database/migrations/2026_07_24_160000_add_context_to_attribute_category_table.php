<?php

use App\Enums\AttributeContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `context` discriminator to `attribute_category` (spec 0061): the
 * SAME catalogue attribute can now be assigned to a category's Product
 * section, Opportunity section, or both (two pivot rows). The column
 * defaults to 'opportunity', so every existing row backfills automatically —
 * the pre-existing Opportunity path (spec 0049) stays byte-for-byte
 * unchanged. The old (attribute_id, category_id) unique no longer holds (an
 * attribute may now repeat across contexts), replaced by
 * (attribute_id, category_id, context).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attribute_category', function (Blueprint $table) {
            $table->string('context')->default(AttributeContext::Opportunity->value)->after('category_id');
        });

        Schema::table('attribute_category', function (Blueprint $table) {
            // The old (attribute_id, category_id) unique is the only index whose
            // leftmost column is attribute_id, so MySQL leans on it to back the
            // attribute_id foreign key. Create the replacement (also attribute_id
            // -leading) FIRST so the FK stays covered, THEN drop the old one —
            // otherwise MySQL refuses the drop (errno 1553).
            $table->unique(['attribute_id', 'category_id', 'context']);
            $table->dropUnique(['attribute_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attribute_category', function (Blueprint $table) {
            // Same constraint in reverse: restore the two-column unique before
            // dropping the three-column one that currently backs the FK.
            $table->unique(['attribute_id', 'category_id']);
            $table->dropUnique(['attribute_id', 'category_id', 'context']);
        });

        Schema::table('attribute_category', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
