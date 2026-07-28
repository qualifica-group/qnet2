<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reward status lookup entity (spec 0060): a clone of `opportunity_statuses`
 * (name/color/sort_order/system_key) WITHOUT `group` (no open/pending/closed
 * semantics here) and WITH two additions: `description` (nullable, mirrors
 * `opportunity_workflow_statuses`) and `is_active` (default true, mirrors
 * `opportunity_workflows`). Unlike its template's THREE mandatory system
 * rows, this table carries a SINGLE system row, "In attesa" (`pending`, D-2):
 * the fixed HEAD at `sort_order = 0`, no tail (RewardStatus::SYSTEM_TAIL_KEYS
 * is empty — App\Services\Statuses\StatusOrderManager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('system_key', 16)->nullable()->unique();
            $table->timestamps();
        });

        $this->seedSystemRows();
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_statuses');
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table('reward_statuses')->insert([
            'name' => 'In attesa',
            'color' => 'amber',
            'sort_order' => 0,
            'is_active' => true,
            'system_key' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
