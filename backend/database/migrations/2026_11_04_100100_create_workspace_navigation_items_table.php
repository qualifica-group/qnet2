<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation items (by stable config `key`) assigned to a workspace (spec 0317).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('navigation_key', 100);
            $table->timestamps();

            $table->unique(['workspace_id', 'navigation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_navigation_items');
    }
};
