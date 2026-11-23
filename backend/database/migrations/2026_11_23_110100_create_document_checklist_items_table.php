<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_checklist_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('section', 255)->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('allowed_file_types');
            $table->unsignedInteger('max_file_size_kb');
            $table->unsignedTinyInteger('min_files');
            $table->unsignedTinyInteger('max_files');
            $table->text('validation_prompt')->nullable();
            $table->unsignedSmallInteger('max_age_days')->nullable();
            $table->timestamps();

            $table->index(['document_checklist_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_checklist_items');
    }
};
