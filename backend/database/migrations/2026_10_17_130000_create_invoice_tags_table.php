<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice tags (spec 0247, D-5): the estimate/final marker of an invoice
 * (spec 0194, D-4) becomes a user-managed lookup. The two reference rows are
 * created here (they must exist in production and in tests), `invoices.tag`
 * is backfilled onto `invoices.invoice_tag_id` and dropped, and the field
 * permission rows of the old field follow the rename. down() restores the
 * string column from the two reference names (any other tag becomes NULL).
 * The invoices table changes run without FK checks: SQLite rebuilds the table
 * for them and dropping the old copy would otherwise cascade to its children.
 */
return new class extends Migration
{
    /** Legacy `invoices.tag` value => reference tag [name, color, sort_order]. */
    private const array REFERENCE_TAGS = [
        'estimate' => ['Preventivo', 'amber', 1],
        'final' => ['Consuntivo', 'teal', 2],
    ];

    public function up(): void
    {
        Schema::create('invoice_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('invoice_tag_id')->nullable()->after('internal_note')->constrained('invoice_tags')->restrictOnDelete();
        }));

        foreach (self::REFERENCE_TAGS as $legacyValue => [$name, $color, $sortOrder]) {
            $tagId = DB::table('invoice_tags')->insertGetId([
                'name' => $name,
                'color' => $color,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('invoices')->where('tag', $legacyValue)->update(['invoice_tag_id' => $tagId]);
        }

        DB::table('role_field_permissions')->where('resource', 'invoices')->where('field', 'tag')->update(['field' => 'invoice_tag_id']);

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tag');
        }));
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('invoices', function (Blueprint $table) {
            $table->string('tag', 16)->nullable()->after('internal_note');
        }));

        foreach (self::REFERENCE_TAGS as $legacyValue => [$name]) {
            $tagId = DB::table('invoice_tags')->where('name', $name)->value('id');

            if ($tagId !== null) {
                DB::table('invoices')->where('invoice_tag_id', $tagId)->update(['tag' => $legacyValue]);
            }
        }

        DB::table('role_field_permissions')->where('resource', 'invoices')->where('field', 'invoice_tag_id')->update(['field' => 'tag']);

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_tag_id');
        }));

        Schema::dropIfExists('invoice_tags');
    }
};
