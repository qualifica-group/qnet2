<?php

use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
 * Legacy task fixtures shared by the `tasks` migration source tests
 * (TasksSourceImportTest, TasksSourceRecurrenceImportTest). The default
 * record mirrors the frozen contract of spec 0289: a plain root task with no
 * links, no people and no recurrence.
 */

if (! function_exists('legacyTaskRecord')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function legacyTaskRecord(array $overrides = []): array
    {
        return array_merge([
            'id' => 500,
            'parent_id' => null,
            'subtask_position' => 0,
            'title' => 'Legacy task',
            'description' => null,
            'type_name' => null,
            'category_name' => null,
            'priority_name' => null,
            'importance_name' => null,
            'status_system_key' => null,
            'status_name' => null,
            'registry_id' => null,
            'referent_id' => null,
            'opportunity_id' => null,
            'work_order_id' => null,
            'work_order_stage_name' => null,
            'stage_position' => 0,
            'creator_user_id' => 60,
            'requester_user_id' => null,
            'assignee_user_ids' => [],
            'watcher_user_ids' => [],
            'start_date' => null,
            'end_date' => null,
            'completion_date' => null,
            'start_time' => null,
            'end_time' => null,
            'estimated_minutes' => null,
            'is_blocked' => false,
            'requires_closure_feedback' => false,
            'requires_validation' => false,
            'closure_feedback' => null,
            'recurrence' => null,
            'recurrence_source_id' => null,
            'warnings' => [],
            'created_at' => '2024-03-01 08:00:00',
            'updated_at' => '2024-03-02 08:00:00',
        ], $overrides);
    }
}

if (! function_exists('legacyTaskCreator')) {
    function legacyTaskCreator(): User
    {
        return User::factory()->create(['old_id' => 60]);
    }
}

if (! function_exists('runLegacyTasksImport')) {
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    function runLegacyTasksImport(array $records, ?User $actor = null): MigrationRun
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/tasks*' => Http::response([
                'items' => $records,
                'pagination' => ['total' => count($records)],
            ]),
        ]);

        $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'tasks']);
        runMigrationJobFor($run);

        return $run->fresh();
    }
}

if (! function_exists('legacyTaskWarnings')) {
    /**
     * @return array<int, string>
     */
    function legacyTaskWarnings(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'warning')->pluck('message')->values()->all();
    }
}

if (! function_exists('legacyTaskErrors')) {
    /**
     * @return array<int, string>
     */
    function legacyTaskErrors(MigrationRun $run): array
    {
        return collect($run->report)->where('level', 'error')->pluck('message')->values()->all();
    }
}
