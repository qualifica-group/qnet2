<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_category_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_category_id')->unique()->constrained('product_categories')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->json('field_map')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_category_settings');
    }
};
