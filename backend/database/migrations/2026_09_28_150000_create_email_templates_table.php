<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email template lookup entity (spec 0175, D-10): a reusable subject/body
 * pair with `{categoria.chiave}` placeholders (D-4), resolved server-side
 * when the Commessa email composer picks one. `module` is deliberately kept
 * (not hard-coded to work orders) so the same table extends to Quote/
 * Opportunity templates later without a migration — only `work_orders` ships
 * this version. `name` is unique WITHIN a module, mirroring
 * `create_document_layouts_table`'s own (name, module) shape. `old_id` is
 * the migration anchor for the legacy `email_templates` source (D-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('module', 32);
            $table->string('subject', 255);
            $table->longText('body');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('old_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['module', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
