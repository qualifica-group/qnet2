<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `layout_id` on a Quote (spec 0070, D-3/D-8): the Document Layout used to
 * generate the preventivo's `.docx`. Nullable and `nullOnDelete` — the real
 * protection against losing a referenced layout is the application-level
 * delete-guard in `DocumentLayoutService::delete()` (spec 0070 D-7), which is
 * more informative than a DB integrity error; `nullOnDelete` is only the
 * schema's own safety net, the same choice already made for
 * `company_id`/`company_site_id`/`operational_site_id` in the
 * 2026-07-30 migration.
 *
 * No explicit index() call: `constrained()` already creates the FK and its
 * index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('layout_id')
                ->nullable()
                ->after('operational_site_id')
                ->constrained('document_layouts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });
    }
};
