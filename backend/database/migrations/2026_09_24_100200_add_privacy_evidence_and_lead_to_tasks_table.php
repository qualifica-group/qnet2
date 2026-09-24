<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task fields aligned with q-net (spec 0154): `is_private` restricts the task
 * to its own members (D-2), `evidence` is the rich-text outcome of the work
 * (D-3), `lead_id` links the task to a lead (D-4, `nullOnDelete`: deleting a
 * lead never deletes its tasks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_private')->default(false)->after('is_blocked');
            $table->text('evidence')->nullable()->after('description');
            $table->foreignId('lead_id')
                ->nullable()
                ->after('opportunity_id')
                ->constrained('leads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
            $table->dropColumn(['is_private', 'evidence']);
        });
    }
};
