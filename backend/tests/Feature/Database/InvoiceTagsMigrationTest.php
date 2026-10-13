<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0247 AC-006. No RefreshDatabase on purpose: the migration changes a
 * table SQLite rebuilds, so the foreign-key pragma (a no-op inside a
 * transaction) must be effective. A minimal copy of the tables it touches is
 * built in the in-memory database and the real migration file is run on it.
 */
function invoiceTagsMigration(): Migration
{
    return require database_path('migrations/2026_10_17_130000_create_invoice_tags_table.php');
}

function legacyInvoicesSchema(): void
{
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();
        $table->text('internal_note')->nullable();
        $table->string('tag', 16)->nullable();
    });
    Schema::create('role_field_permissions', function (Blueprint $table) {
        $table->id();
        $table->string('resource');
        $table->string('field');
    });
}

it('AC-006: creates Preventivo and Consuntivo, maps estimate/final onto them and drops the tag column', function () {
    legacyInvoicesSchema();
    DB::table('invoices')->insert([['id' => 1, 'tag' => 'estimate'], ['id' => 2, 'tag' => 'final'], ['id' => 3, 'tag' => null], ['id' => 4, 'tag' => 'final']]);
    DB::table('role_field_permissions')->insert([
        ['resource' => 'invoices', 'field' => 'tag'],
        ['resource' => 'quotes', 'field' => 'tag'],
    ]);

    invoiceTagsMigration()->up();

    $tags = DB::table('invoice_tags')->orderBy('sort_order')->get(['id', 'name', 'color', 'sort_order', 'is_active', 'description']);
    expect($tags->pluck('name')->all())->toBe(['Preventivo', 'Consuntivo'])
        ->and($tags->pluck('color')->all())->toBe(['amber', 'teal'])
        ->and($tags->pluck('sort_order')->all())->toBe([1, 2])
        ->and((bool) $tags[0]->is_active)->toBeTrue()
        ->and($tags[0]->description)->toBeNull()
        ->and(Schema::hasColumn('invoices', 'tag'))->toBeFalse()
        ->and(DB::table('invoices')->orderBy('id')->pluck('invoice_tag_id')->all())->toBe([$tags[0]->id, $tags[1]->id, null, $tags[1]->id])
        ->and(DB::table('role_field_permissions')->orderBy('id')->pluck('field', 'resource')->all())->toBe(['invoices' => 'invoice_tag_id', 'quotes' => 'tag']);
});

it('AC-006: the new foreign key is restrictive, nullable and the tag name is unique', function () {
    legacyInvoicesSchema();
    invoiceTagsMigration()->up();
    $tagId = DB::table('invoice_tags')->where('name', 'Preventivo')->value('id');
    DB::table('invoices')->insert(['id' => 1, 'invoice_tag_id' => $tagId]);
    DB::table('invoices')->insert(['id' => 2, 'invoice_tag_id' => null]);

    expect(fn () => DB::table('invoice_tags')->where('id', $tagId)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('invoice_tags')->insert(['name' => 'Preventivo', 'color' => 'blue', 'created_at' => now(), 'updated_at' => now()]))->toThrow(QueryException::class);
});

it('AC-006: rolling back restores the string column and the field permission rows', function () {
    legacyInvoicesSchema();
    DB::table('invoices')->insert([['id' => 1, 'tag' => 'estimate'], ['id' => 2, 'tag' => 'final'], ['id' => 3, 'tag' => null]]);
    DB::table('role_field_permissions')->insert([['resource' => 'invoices', 'field' => 'tag']]);
    $migration = invoiceTagsMigration();
    $migration->up();

    $custom = DB::table('invoice_tags')->insertGetId(['name' => 'Custom', 'color' => 'blue', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('invoices')->insert(['id' => 4, 'invoice_tag_id' => $custom]);

    $migration->down();

    expect(Schema::hasTable('invoice_tags'))->toBeFalse()
        ->and(Schema::hasColumn('invoices', 'invoice_tag_id'))->toBeFalse()
        ->and(DB::table('invoices')->orderBy('id')->pluck('tag')->all())->toBe(['estimate', 'final', null, null])
        ->and(DB::table('role_field_permissions')->value('field'))->toBe('tag');
});
