<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Spec 0427: document groups of a checklist (ArchivioSmart `checklist_template_sections`),
| the optional `section_id` link on the items and the extracted date field of the age rule.
| Explicit short index names keep the identifiers under the MySQL 64 characters limit.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_checklist_id')->constrained(indexName: 'dc_sections_checklist_fk')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['document_checklist_id', 'position'], 'dc_sections_checklist_position_index');
        });

        Schema::table('document_checklist_items', function (Blueprint $table) {
            $table->foreignId('section_id')->nullable()->after('description')
                ->constrained('document_checklist_sections', indexName: 'dc_items_section_fk')->nullOnDelete();
            $table->string('date_field', 64)->nullable()->after('max_age_days');
        });
    }

    public function down(): void
    {
        Schema::table('document_checklist_items', function (Blueprint $table) {
            $table->dropForeign('dc_items_section_fk');
            $table->dropColumn(['section_id', 'date_field']);
        });

        Schema::dropIfExists('document_checklist_sections');
    }
};
