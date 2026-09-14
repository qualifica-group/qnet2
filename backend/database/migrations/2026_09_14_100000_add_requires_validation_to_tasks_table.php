<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tasks.requires_validation` (spec 0121, D-1): NOT NULL, default false. No
 * sanatoria for existing rows — every Task already in the table keeps
 * closing directly until someone flips the flag by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('requires_validation')->default(false)->after('requires_closure_feedback');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('requires_validation');
        });
    }
};
