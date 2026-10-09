<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0215 (D-2/D-5): marks a Commessa title typed by the user, so the
 * automatic `<code> - <products>` title leaves it alone. Every existing row
 * becomes manual: its title was written by hand, so no visible title changes
 * with the deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->boolean('title_is_manual')->default(false)->after('title');
        });

        DB::table('work_orders')->update(['title_is_manual' => true]);
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('title_is_manual');
        });
    }
};
