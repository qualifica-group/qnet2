<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reward type lookup entity (spec 0058): a pure anagraphic (name, color)
 * describing the TYPES of voucher/reward/incentive usable in the CRM — the
 * actual assignment flows are out of scope (D-1). Unlike its template
 * `opportunity_statuses`, this table carries no `system_key`/`sort_order`/
 * `group`: no row is a system row and there is no progression to order.
 * `name` is unique (BR-1); `color` is NOT NULL (D-5, deliberate divergence
 * from the nullable `opportunity_statuses.color`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_types');
    }
};
