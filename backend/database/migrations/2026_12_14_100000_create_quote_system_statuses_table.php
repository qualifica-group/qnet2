<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0404 part A: the configurable "stati di sistema" of the Offerta. Each
 * working status (`quote_workflow_statuses`) links one of them; its `group`
 * stays as the copy of the linked row's `behavior`, so every rule that reads
 * `group` today keeps working unchanged (A-D0/A-D5).
 *
 * The FK stays nullable at the schema level: making it NOT NULL would rebuild
 * `quote_workflow_statuses` on SQLite, and the QuoteWorkflowStatus model fills
 * it on every save (A-D4/A-D9). Permissions are created here so the
 * super-admin sees the configurator right after the deploy (same convention as
 * 2026_12_11_100000); a fresh installation is left to permissions:sync.
 */
return new class extends Migration
{
    private const string TABLE = 'quote_system_statuses';

    private const string WORKING_TABLE = 'quote_workflow_statuses';

    private const string FOREIGN_KEY = 'quote_system_status_id';

    private const string INSTALLED_MARKER = 'quotes.update';

    private const string GUARD = 'web';

    private const array PERMISSIONS = [
        'quote-system-statuses.viewAny',
        'quote-system-statuses.view',
        'quote-system-statuses.create',
        'quote-system-statuses.update',
        'quote-system-statuses.delete',
        'quote-system-statuses.export',
        'quote-system-statuses.import',
        'quote-system-statuses.viewActivity',
    ];

    /**
     * name, color, behavior, system_key — in sort_order. "Vinto provvisorio"
     * takes the place of "Validato" (A-D3).
     */
    private const array SEED = [
        ['Aperto', 'blue', 'open', 'open'],
        ['In attesa', 'amber', 'pending', null],
        ['Vinto provvisorio', 'sky', 'validated', null],
        ['Vinto', 'emerald', 'closed_won', 'closed_won'],
        ['Perso', 'red', 'closed_lost', 'closed_lost'],
    ];

    private const int STEP = 10;

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('behavior', 20);
            $table->string('system_key', 20)->nullable()->unique();
            $table->timestamps();
        });

        $this->seedRows();

        Schema::table(self::WORKING_TABLE, function (Blueprint $table): void {
            $table->foreignId(self::FOREIGN_KEY)->nullable()->after('group')
                ->constrained(self::TABLE)->restrictOnDelete();
        });

        $this->backfill();
        $this->createPermissions();
    }

    public function down(): void
    {
        Schema::table(self::WORKING_TABLE, function (Blueprint $table): void {
            $table->dropConstrainedForeignId(self::FOREIGN_KEY);
        });

        Schema::dropIfExists(self::TABLE);

        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->where('guard_name', self::GUARD)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedRows(): void
    {
        $now = now();

        foreach (self::SEED as $index => [$name, $color, $behavior, $systemKey]) {
            DB::table(self::TABLE)->insert([
                'name' => $name,
                'color' => $color,
                'sort_order' => $index * self::STEP,
                'behavior' => $behavior,
                'system_key' => $systemKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Every working status links the seeded row whose behavior equals its
     * group (A-D4); the seed has exactly one row per behavior, and a system
     * row's group always equals its key, so the system rows land on their
     * namesake.
     */
    private function backfill(): void
    {
        foreach (DB::table(self::TABLE)->pluck('id', 'behavior') as $behavior => $id) {
            DB::table(self::WORKING_TABLE)->where('group', $behavior)->update([self::FOREIGN_KEY => $id]);
        }
    }

    private function createPermissions(): void
    {
        if (! DB::table('permissions')->where('name', self::INSTALLED_MARKER)->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(static fn (string $name): array => [
            'name' => $name,
            'guard_name' => self::GUARD,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::PERMISSIONS));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
