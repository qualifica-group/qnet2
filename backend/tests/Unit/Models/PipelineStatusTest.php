<?php

use App\Models\Campaign;
use App\Models\Concerns\LogsModelActivity;
use App\Models\PipelineStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the pipeline_statuses table with the expected columns', function () {
    expect(Schema::hasTable('pipeline_statuses'))->toBeTrue();
    expect(Schema::hasColumns('pipeline_statuses', [
        'id', 'name', 'color', 'sort_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('pipeline_statuses')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('sort_order defaults to 0', function () {
    DB::table('pipeline_statuses')->insert(['name' => 'Default Sort', 'created_at' => now(), 'updated_at' => now()]);

    expect(DB::table('pipeline_statuses')->where('name', 'Default Sort')->value('sort_order'))->toBe(0);
});

// ---------------------------------------------------------------------------
// model relations, casts, activity log
// ---------------------------------------------------------------------------

it('projects() is a HasMany relation to Project', function () {
    $relation = (new PipelineStatus)->projects();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Project::class);
});

it('campaigns() is a HasMany relation to Campaign', function () {
    $relation = (new PipelineStatus)->campaigns();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Campaign::class);
});

it('casts sort_order to int', function () {
    $status = PipelineStatus::factory()->create(['sort_order' => '5']);

    expect($status->sort_order)->toBeInt()->toBe(5);
});

it('a status referencing Project restricts deletion at the schema level', function () {
    $status = PipelineStatus::factory()->create();
    Project::factory()->create(['pipeline_status_id' => $status->id]);

    expect(fn () => DB::table('pipeline_statuses')->where('id', $status->id)->delete())
        ->toThrow(QueryException::class);
});

it('logs model activity on the pipeline_statuses log channel', function () {
    expect(class_uses(PipelineStatus::class))->toHaveKey(LogsModelActivity::class);
});
