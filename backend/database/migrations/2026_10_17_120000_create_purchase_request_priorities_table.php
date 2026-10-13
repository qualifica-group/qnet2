<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase request priorities (spec 0247, D-3/D-4): the fixed `low..critical`
 * enum of a purchase request becomes a user-managed lookup shaped like
 * `task_priorities`. The five reference rows are created here (they must exist
 * in production and in tests), `purchase_requests.priority` is backfilled onto
 * `purchase_requests.purchase_request_priority_id` (required afterwards) and
 * dropped together with its index, and the field permission rows of the old
 * field follow the rename. down() restores the string column from the
 * reference names (any other priority becomes `medium`). The purchase_requests
 * changes run without FK checks: SQLite rebuilds the table for them and
 * dropping the old copy would otherwise cascade to its children.
 */
return new class extends Migration
{
    /** Legacy `purchase_requests.priority` value => reference row [name, color, icon, sort_order]. */
    private const array REFERENCE_PRIORITIES = [
        'low' => ['Bassa', 'slate', 'flag', 1],
        'medium' => ['Media', 'blue', 'gauge', 2],
        'high' => ['Alta', 'amber', 'flame', 3],
        'urgent' => ['Urgente', 'orange', 'alarm-clock', 4],
        'critical' => ['Critica', 'red', 'zap', 5],
    ];

    private const string DEFAULT_LEGACY_VALUE = 'medium';

    public function up(): void
    {
        Schema::create('purchase_request_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('purchase_request_priority_id')->nullable()->after('priority')->constrained('purchase_request_priorities')->restrictOnDelete();
        }));

        foreach (self::REFERENCE_PRIORITIES as $legacyValue => [$name, $color, $icon, $sortOrder]) {
            $priorityId = DB::table('purchase_request_priorities')->insertGetId([
                'name' => $name,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'is_default' => $legacyValue === self::DEFAULT_LEGACY_VALUE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('purchase_requests')->where('priority', $legacyValue)->update(['purchase_request_priority_id' => $priorityId]);
        }

        DB::table('role_field_permissions')->where('resource', 'purchase-requests')->where('field', 'priority')->update(['field' => 'purchase_request_priority_id']);

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['priority']);
            $table->dropColumn('priority');
            $table->unsignedBigInteger('purchase_request_priority_id')->nullable(false)->change();
        }));
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('priority', 20)->default(self::DEFAULT_LEGACY_VALUE)->after('requested_at');
        }));

        foreach (self::REFERENCE_PRIORITIES as $legacyValue => [$name]) {
            $priorityId = DB::table('purchase_request_priorities')->where('name', $name)->value('id');

            if ($priorityId !== null) {
                DB::table('purchase_requests')->where('purchase_request_priority_id', $priorityId)->update(['priority' => $legacyValue]);
            }
        }

        DB::table('role_field_permissions')->where('resource', 'purchase-requests')->where('field', 'purchase_request_priority_id')->update(['field' => 'priority']);

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_request_priority_id');
            $table->index('priority');
        }));

        Schema::dropIfExists('purchase_request_priorities');
    }
};
