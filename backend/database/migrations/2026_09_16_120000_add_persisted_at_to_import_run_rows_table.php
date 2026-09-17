<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0136 (D-8): `import_run_rows.persisted_at` makes the commit phase
 * idempotent per row. `ProcessStagedImportJob` only reads rows where this is
 * still null and sets it INSIDE the same transaction that persists the row,
 * so re-running the job after a crash/retry never re-creates a lead or
 * anagrafica for a row already written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->timestamp('persisted_at')->nullable()->after('is_edited');
        });
    }

    public function down(): void
    {
        Schema::table('import_run_rows', function (Blueprint $table): void {
            $table->dropColumn('persisted_at');
        });
    }
};
