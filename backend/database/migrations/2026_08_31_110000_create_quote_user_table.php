<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the Offerta <-> internal manager (user) relation (spec 0087,
 * "Gestori Account sull'Offerta", D-1): a byte-for-byte mirror of
 * `opportunity_user` (2026_07_16_140100), the same shape `manager_slots`,
 * `App\Support\ManagerPositions::attachedPositions()` and the shared
 * ManagerSlotsField already know how to sync. Both sides cascade — deleting
 * either the quote or the user drops the membership row — and `position` is
 * tied to the SLOT, not the user (removing a manager frees its position, a
 * gap `Quote::managers()` preserves via orderByPivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->unique(['quote_id', 'user_id']);
            $table->unique(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_user');
    }
};
