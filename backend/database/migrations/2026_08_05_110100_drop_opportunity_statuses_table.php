<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0082: the "Stati opportunita'" configurator (spec 0043) is removed
 * entirely — the Opportunity's status is computed from its Quotes' statuses,
 * which have their own configurator (`quote_statuses`, spec 0065).
 *
 * DESTRUCTIVE: every custom status row the users created is lost.
 * `down()` recreates the EMPTY table (structure only, not even the 3 former
 * system rows: nothing reads them any more).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('opportunity_statuses');
    }

    public function down(): void
    {
        Schema::create('opportunity_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('system_key', 16)->nullable()->unique();
            $table->string('group', 16)->default('open');
            $table->timestamps();
        });
    }
};
