<?php

use App\Enums\ChartAccountKind;
use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\ChartAccount;
use App\Models\FinancialAccount;
use App\Models\InvoiceInstallment;
use App\Models\Registry;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const TREASURY_SEED_MIGRATION = '2026_11_10_100400_seed_financial_movements_chart_group.php';

it('adds the opening balance columns and the installment account column', function () {
    expect(Schema::hasColumns('financial_accounts', ['opening_balance', 'opening_balance_date']))->toBeTrue()
        ->and(Schema::hasColumn('invoice_installments', 'financial_account_id'))->toBeTrue()
        ->and(Schema::hasColumns('treasury_types', ['chart_account_id', 'code', 'name', 'description', 'is_active']))->toBeTrue()
        ->and(Schema::hasColumns('treasury_movements', [
            'type', 'direction', 'movement_date', 'financial_account_id', 'counterpart_account_id', 'amount',
            'description', 'cost_type_id', 'revenue_type_id', 'treasury_type_id', 'registry_id',
            'work_order_id', 'created_by',
        ]))->toBeTrue();

    $account = FinancialAccount::factory()->create();
    expect($account->fresh()->opening_balance)->toBe('0.00')
        ->and($account->fresh()->opening_balance_date)->toBeNull();

    $account->update(['opening_balance' => '1000.5', 'opening_balance_date' => '2026-03-01']);
    expect($account->fresh()->opening_balance)->toBe('1000.50')
        ->and($account->fresh()->opening_balance_date->toDateString())->toBe('2026-03-01');
});

it('AC-003: seeds group 90 as financial with 4 accounts and 4 sub-accounts', function () {
    $group = ChartAccount::where('code', '90')->firstOrFail();

    expect($group->name)->toBe('Movimenti finanziari')
        ->and($group->kind)->toBe(ChartAccountKind::Financial)
        ->and($group->children()->orderBy('code')->pluck('name', 'code')->all())->toBe([
            '90.01' => 'Giroconti',
            '90.02' => 'Finanziamenti',
            '90.03' => 'Tributi e contributi',
            '90.04' => 'Rettifiche di saldo',
        ])
        ->and(TreasuryType::orderBy('code')->pluck('name', 'code')->all())->toBe([
            '90.01.001' => 'Giroconto tra conti',
            '90.02.001' => 'Finanziamenti e rimborsi',
            '90.03.001' => 'Tributi e contributi (F24)',
            '90.04.001' => 'Rettifica di saldo',
        ]);

    $account = ChartAccount::where('code', '90.03')->firstOrFail();
    expect($account->treasuryTypes()->pluck('code')->all())->toBe(['90.03.001'])
        ->and($account->effectiveKind())->toBe(ChartAccountKind::Financial)
        ->and($account->hasChildren())->toBeTrue()
        ->and(ChartAccountKind::values())->toBe(['cost', 'revenue', 'financial']);
});

it('AC-003: the seed migration is idempotent and reversible', function () {
    $migration = require database_path('migrations/'.TREASURY_SEED_MIGRATION);

    $migration->up();
    expect(ChartAccount::where('code', 'like', '90%')->count())->toBe(5)
        ->and(TreasuryType::count())->toBe(4);

    $migration->down();
    expect(ChartAccount::where('code', 'like', '90%')->count())->toBe(0)
        ->and(TreasuryType::count())->toBe(0);

    $migration->up();
    expect(ChartAccount::where('code', 'like', '90%')->count())->toBe(5);
});

it('wires the movement relations', function () {
    $user = User::factory()->create();
    $registry = Registry::factory()->create();
    $from = FinancialAccount::factory()->create();
    $movement = TreasuryMovement::factory()->transfer($from)->create([
        'registry_id' => $registry->id,
        'created_by' => $user->id,
    ]);

    $movement = $movement->fresh();
    expect($movement->type)->toBe(TreasuryMovementType::Transfer)
        ->and($movement->account->is($from))->toBeTrue()
        ->and($movement->counterpartAccount)->not->toBeNull()
        ->and($movement->treasuryType)->toBeInstanceOf(TreasuryType::class)
        ->and($movement->treasuryType->chartAccount->parent->kind)->toBe(ChartAccountKind::Financial)
        ->and($movement->registry->is($registry))->toBeTrue()
        ->and($movement->creator->is($user))->toBeTrue()
        ->and($movement->attachments)->toHaveCount(0);

    $income = TreasuryMovement::factory()->income()->create();
    $expense = TreasuryMovement::factory()->expense()->create();
    $adjustment = TreasuryMovement::factory()->adjustment(TreasuryMovementDirection::Out)->create();

    expect($income->revenueType)->not->toBeNull()
        ->and($expense->costType)->not->toBeNull()
        ->and($adjustment->direction)->toBe(TreasuryMovementDirection::Out)
        ->and($adjustment->treasuryType)->not->toBeNull();
});

it('keeps the installment account nullable and sets it to null when the account is deleted', function () {
    $account = FinancialAccount::factory()->create();
    $installment = InvoiceInstallment::factory()->create();
    expect($installment->financial_account_id)->toBeNull();

    $installment->update(['financial_account_id' => $account->id]);
    expect($installment->fresh()->financialAccount->is($account))->toBeTrue();

    DB::table('financial_accounts')->where('id', $account->id)->delete();
    expect($installment->fresh()->financial_account_id)->toBeNull();
});

it('restricts deleting an account that has movements', function () {
    $movement = TreasuryMovement::factory()->income()->create();

    expect(fn () => DB::table('financial_accounts')->where('id', $movement->financial_account_id)->delete())
        ->toThrow(QueryException::class);
});

it('logs created, updated and deleted movements in the activity log with all fields', function () {
    $movement = TreasuryMovement::factory()->income()->create(['amount' => '100.00']);
    $movement->update(['amount' => '150.00']);
    $movementId = $movement->id;
    $movement->delete();

    $entries = Activity::where('log_name', 'treasury_movements')->where('subject_id', $movementId)->orderBy('id')->get();

    expect($entries->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($entries[0]->properties['attributes'])->toHaveKeys(['type', 'amount', 'movement_date', 'financial_account_id', 'revenue_type_id'])
        ->and($entries[1]->properties['old']['amount'])->toBe('100.00')
        ->and($entries[1]->properties['attributes']['amount'])->toBe('150.00')
        ->and($entries[0]->subject_type)->toBe('treasury_movement');
});

it('resolves the treasury morph aliases', function () {
    expect(Relation::getMorphedModel('treasury_movement'))->toBe(TreasuryMovement::class)
        ->and(Relation::getMorphedModel('treasury_type'))->toBe(TreasuryType::class);
});

it('exposes the treasury config defaults', function () {
    expect(config('treasury.default_opening_date'))->toBe('2026-01-01')
        ->and(config('treasury.statement_max_rows'))->toBe(5000);
});
