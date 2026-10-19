<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0278 D-7: approvers snapshot taken when the request is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_request_approver', function (Blueprint $table): void {
            $table->foreignId('absence_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unique(['absence_request_id', 'user_id'], 'absence_request_approver_unique');
            $table->index('user_id', 'absence_request_approver_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_request_approver');
    }
};
