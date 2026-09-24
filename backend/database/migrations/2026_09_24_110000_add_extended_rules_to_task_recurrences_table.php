<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extended recurrence rules aligned with q-net (spec 0155, D-1): monthly and
 * yearly occurrences are either on a fixed `month_day` or on an ordinal
 * weekday ("2nd Tuesday": `ordinal` 1..5 + `ordinal_weekday` 1..7, ISO);
 * `year_month` is the month of a yearly rule; `workdays_only` moves nothing,
 * it only admits Monday..Friday dates. All nullable/defaulted, so existing
 * daily/weekly/monthly rules keep their exact meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_recurrences', function (Blueprint $table) {
            $table->string('month_mode', 16)->nullable()->after('month_day');
            $table->unsignedTinyInteger('ordinal')->nullable()->after('month_mode');
            $table->unsignedTinyInteger('ordinal_weekday')->nullable()->after('ordinal');
            $table->unsignedTinyInteger('year_month')->nullable()->after('ordinal_weekday');
            $table->boolean('workdays_only')->default(false)->after('year_month');
        });
    }

    public function down(): void
    {
        Schema::table('task_recurrences', function (Blueprint $table) {
            $table->dropColumn(['month_mode', 'ordinal', 'ordinal_weekday', 'year_month', 'workdays_only']);
        });
    }
};
