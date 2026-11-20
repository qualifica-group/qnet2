<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalab_classes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('edition_id')->unique();
            $table->string('code');
            $table->string('title');
            $table->string('label')->index();
            $table->string('status')->nullable();
            $table->string('course_status')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('total_hours')->nullable();
            $table->date('exam_date')->nullable();
            $table->date('ip_collection_date')->nullable();
            $table->string('advance_decree')->nullable();
            $table->date('advance_credited_at')->nullable();
            $table->string('balance_decree')->nullable();
            $table->date('balance_credited_at')->nullable();
            $table->string('site_name')->nullable();
            $table->string('site_city')->nullable();
            $table->string('site_code')->nullable();
            $table->unsignedInteger('learners_count')->nullable();
            $table->char('payload_hash', 64);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalab_classes');
    }
};
