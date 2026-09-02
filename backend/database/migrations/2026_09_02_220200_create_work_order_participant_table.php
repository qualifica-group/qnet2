<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commessa <-> Partecipanti (spec 0096, D-3): the work order's team, assigned
 * from its own form after creation (never from the Contract's "Programma"
 * dialog).
 *
 * Shape-identical to `quote_user`/`opportunity_user` — the ordered, gap-aware
 * `position` slots that ValidatesManagerSlots already validates and the shared
 * ManagerSlotsField already renders (user directive: "stessa grafica di quelle
 * di opportunita e offerte"). `position` is tied to the SLOT, not the user, so
 * removing a participant frees its position and leaves a gap the relation
 * preserves via orderByPivot.
 *
 * Named explicitly rather than as Laravel's alphabetical `user_work_order`:
 * see the sibling `work_order_supervisor` migration — with two user relations
 * on the same model, neither may claim the generic name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_participant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['work_order_id', 'user_id']);
            $table->unique(['work_order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_participant');
    }
};
