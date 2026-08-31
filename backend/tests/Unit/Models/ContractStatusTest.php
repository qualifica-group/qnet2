<?php

use App\Enums\ContractStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Concerns\LogsModelActivity;
use App\Models\Contract;
use App\Models\ContractStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly, mirroring QuoteTest's own sibling.
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the contract_statuses table with the expected columns', function () {
    expect(Schema::hasTable('contract_statuses'))->toBeTrue();
    expect(Schema::hasColumns('contract_statuses', [
        'id', 'name', 'description', 'color', 'sort_order', 'is_active',
        'is_default', 'system_key', 'group', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('name is unique at the database level', function () {
    ContractStatus::factory()->create(['name' => 'Duplicate']);

    expect(fn () => ContractStatus::factory()->create(['name' => 'Duplicate']))
        ->toThrow(QueryException::class);
});

// ---------------------------------------------------------------------------
// model: casts, relations, isSystem(), mass-assignment, activity log
// ---------------------------------------------------------------------------

it('casts sort_order to int, is_active/is_default to bool, group to ContractStatusGroup', function () {
    $status = ContractStatus::factory()->create([
        'sort_order' => '5',
        'is_active' => 1,
        'is_default' => 0,
        'group' => ContractStatusGroup::Pending,
    ]);

    expect($status->sort_order)->toBeInt()->toBe(5)
        ->and($status->is_active)->toBeBool()->toBeTrue()
        ->and($status->is_default)->toBeBool()->toBeFalse()
        ->and($status->group)->toBe(ContractStatusGroup::Pending);
});

it('contracts() is a HasMany relation to Contract', function () {
    $relation = (new ContractStatus)->contracts();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Contract::class);
});

it('isSystem() distinguishes system rows from custom rows', function () {
    // The 5 system rows already exist, seeded by the migrations themselves.
    $system = ContractStatus::where('system_key', 'suspended')->firstOrFail();
    $custom = ContractStatus::factory()->create();

    expect($system->isSystem())->toBeTrue()
        ->and($custom->isSystem())->toBeFalse();
});

it('SYSTEM_HEAD_KEYS is [New, Validated] and SYSTEM_TAIL_KEYS is [Suspended, Cancelled, Terminated] in order', function () {
    expect(ContractStatus::SYSTEM_HEAD_KEYS)->toBe([StatusSystemKey::New, StatusSystemKey::Validated])
        ->and(ContractStatus::SYSTEM_TAIL_KEYS)->toBe([
            StatusSystemKey::Suspended,
            StatusSystemKey::Cancelled,
            StatusSystemKey::Terminated,
        ]);
});

it('system_key is deliberately absent from #[Fillable]: mass-assigning it leaves the column null', function () {
    $status = ContractStatus::create([
        'name' => 'Senza system_key mass-assignato',
        'color' => 'blue',
        'group' => ContractStatusGroup::Open,
        'system_key' => 'hacked',
    ]);

    expect($status->fresh()->system_key)->toBeNull();
});

it('a status referencing a Contract restricts deletion at the schema level (AC-022)', function () {
    $status = ContractStatus::factory()->create();
    Contract::factory()->create(['contract_status_id' => $status->id]);

    expect(fn () => DB::table('contract_statuses')->where('id', $status->id)->delete())
        ->toThrow(QueryException::class);
});

it('logs model activity on the contract_statuses log channel', function () {
    expect(class_uses(ContractStatus::class))->toHaveKey(LogsModelActivity::class);
});
