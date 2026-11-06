<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recurrence rule and series state of a scheduled OutboundEmail (spec
 * 0362, D-6): one row per programmed email, deleted with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_email_recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_email_id')->unique()->constrained('outbound_emails')->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval');
            $table->json('weekdays')->nullable();
            $table->string('month_mode', 16)->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->unsignedTinyInteger('ordinal')->nullable();
            $table->unsignedTinyInteger('ordinal_weekday')->nullable();
            $table->unsignedTinyInteger('year_month')->nullable();
            $table->boolean('workdays_only')->default(false);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('next_send_on')->nullable()->index();
            $table->date('last_sent_on')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->string('end_reason', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_email_recurrences');
    }
};
