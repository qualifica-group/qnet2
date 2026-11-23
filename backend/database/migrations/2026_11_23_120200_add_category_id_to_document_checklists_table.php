<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional category of a checklist (spec 0410). No cascade: categories are only
 * soft-deleted, and only when they hold no checklist (D-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_checklists', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')
                ->constrained('document_checklist_categories');
        });
    }

    public function down(): void
    {
        Schema::table('document_checklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
