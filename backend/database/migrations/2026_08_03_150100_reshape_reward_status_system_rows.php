<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings `reward_statuses` down to the THREE states a buono actually has
 * (user directive 2026-08-03, amendment to spec 0073 D-6): "In attesa"
 * (`pending`), "Approvato" (`won`) and "Negato" (`lost`).
 *
 * Three moves, all on raw DB queries — no Model/Service, a migration must keep
 * behaving the same when those later change:
 *  1. "Aperto" (`system_key='new'`) is gone. Every reward sitting on it is
 *     moved onto "In attesa" FIRST: `rewards.reward_status_id` is
 *     restrictOnDelete (spec 0060 BR-4), so the row cannot be deleted while
 *     referenced, and "In attesa" is where a new reward is born anyway
 *     (RewardAssignmentWriter, BR-6).
 *  2. The two tail rows are renamed to the words the user uses: "Chiuso
 *     positivo" -> "Approvato", "Chiuso negativo" -> "Negato". `name` is
 *     UNIQUE, so a CUSTOM row already holding one of those names (the demo
 *     seeder used to create "Approvato") is MERGED into the system row it
 *     duplicates — its rewards are repointed, then it is deleted — rather than
 *     renamed aside, which would leave two rows meaning the same thing.
 *  3. `group` loses its `open` value (App\Enums\RewardStatusGroup): every row
 *     carrying it lands on `pending`, which also becomes the column default —
 *     the phase a custom row starts from now that "aperto" no longer exists.
 *
 * down() restores the schema and the four system rows, NOT the data: rewards
 * moved off "Aperto" stay on "In attesa" and a merged custom row is not
 * recreated, both being unrecoverable from the post-migration state.
 */
return new class extends Migration
{
    /**
     * Same STEP as App\Services\Statuses\StatusOrderManager: duplicated on
     * purpose (a migration is a frozen snapshot, it must not import a service
     * constant that may change later).
     */
    private const int STEP = 10;

    /**
     * The tail rows' new names, keyed by `system_key`.
     *
     * @var array<string, string>
     */
    private const array RENAMED_TAIL_ROWS = ['won' => 'Approvato', 'lost' => 'Negato'];

    /**
     * The names those rows carried before, for down().
     *
     * @var array<string, string>
     */
    private const array FORMER_TAIL_NAMES = ['won' => 'Chiuso positivo', 'lost' => 'Chiuso negativo'];

    public function up(): void
    {
        // Step 1: drop the "Aperto" head row, rehoming its rewards.
        $this->removeOpenSystemRow();

        // Step 2: rename the tail rows, merging any custom namesake.
        foreach (self::RENAMED_TAIL_ROWS as $systemKey => $name) {
            $this->mergeCustomNamesake($systemKey, $name);

            DB::table('reward_statuses')->where('system_key', $systemKey)->update(['name' => $name]);
        }

        // Step 3: the `open` phase disappears from the vocabulary.
        DB::table('reward_statuses')->where('group', 'open')->update(['group' => 'pending']);

        Schema::table('reward_statuses', function (Blueprint $table): void {
            $table->string('group', 16)->default('pending')->change();
        });

        $this->renormalizeSortOrder();
    }

    public function down(): void
    {
        Schema::table('reward_statuses', function (Blueprint $table): void {
            $table->string('group', 16)->default('open')->change();
        });

        foreach (self::FORMER_TAIL_NAMES as $systemKey => $name) {
            DB::table('reward_statuses')->where('system_key', $systemKey)->update(['name' => $name]);
        }

        $now = now();

        DB::table('reward_statuses')->insert([
            'name' => 'Aperto',
            'description' => null,
            'color' => 'blue',
            'group' => 'open',
            'sort_order' => 0,
            'is_active' => true,
            'system_key' => 'new',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->renormalizeSortOrder(['new', 'pending']);
    }

    private function removeOpenSystemRow(): void
    {
        $openId = DB::table('reward_statuses')->where('system_key', 'new')->value('id');

        if ($openId === null) {
            return;
        }

        $pendingId = DB::table('reward_statuses')->where('system_key', 'pending')->value('id');

        DB::table('rewards')->where('reward_status_id', $openId)->update(['reward_status_id' => $pendingId]);
        DB::table('reward_statuses')->where('id', $openId)->delete();
    }

    /**
     * Frees $name for the $systemKey row by absorbing the custom row that
     * already holds it: its rewards move onto the system row, then it goes.
     */
    private function mergeCustomNamesake(string $systemKey, string $name): void
    {
        $duplicateId = DB::table('reward_statuses')
            ->where('name', $name)
            ->whereNull('system_key')
            ->value('id');

        if ($duplicateId === null) {
            return;
        }

        $systemId = DB::table('reward_statuses')->where('system_key', $systemKey)->value('id');

        DB::table('rewards')->where('reward_status_id', $duplicateId)->update(['reward_status_id' => $systemId]);
        DB::table('reward_statuses')->where('id', $duplicateId)->delete();
    }

    /**
     * The sequence App\Services\Statuses\StatusOrderManager maintains: the
     * head rows STEP apart from 0, then the customs in their current relative
     * order, then the tail rows last.
     *
     * @param  array<int, string>  $headKeys
     */
    private function renormalizeSortOrder(array $headKeys = ['pending']): void
    {
        $sortOrder = 0;

        foreach ($headKeys as $headKey) {
            DB::table('reward_statuses')->where('system_key', $headKey)->update(['sort_order' => $sortOrder]);

            $sortOrder += self::STEP;
        }

        $sortOrder -= self::STEP;

        $customIds = DB::table('reward_statuses')
            ->whereNull('system_key')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id');

        foreach ($customIds as $id) {
            $sortOrder += self::STEP;

            DB::table('reward_statuses')->where('id', $id)->update(['sort_order' => $sortOrder]);
        }

        foreach (array_keys(self::RENAMED_TAIL_ROWS) as $tailKey) {
            $sortOrder += self::STEP;

            DB::table('reward_statuses')->where('system_key', $tailKey)->update(['sort_order' => $sortOrder]);
        }
    }
};
