<?php

use App\Models\Company;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\Registry;
use App\Models\RevenueType;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use App\Models\WorkOrder;
use App\Services\Treasury\AccountStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Spec 0360 — manual treasury movements: AC-005..AC-009, AC-019 and the attachment boundary.

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function movementPayload(string $type, FinancialAccount $account, array $overrides = []): array
{
    $base = [
        'type' => $type,
        'movement_date' => '2026-03-10',
        'financial_account_id' => $account->id,
        'amount' => '200.00',
        'description' => 'Test movement',
    ];

    $subAccount = match ($type) {
        'income' => ['revenue_type_id' => RevenueType::factory()->create()->id],
        'expense' => ['cost_type_id' => CostType::factory()->create()->id],
        default => ['treasury_type_id' => TreasuryType::factory()->create()->id],
    };

    return array_merge($base, $subAccount, $overrides);
}

function balanceOf(FinancialAccount $account): string
{
    return app(AccountStatementService::class)->balanceAt($account, CarbonImmutable::parse('2026-12-31'));
}

it('AC-005: every valid type answers 201 with the TreasuryMovement shape and an activitylog entry', function () {
    $actor = treasuryWriter();
    Sanctum::actingAs($actor);
    $company = Company::factory()->create();
    $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
    $other = FinancialAccount::factory()->create(['company_id' => $company->id]);
    $registry = Registry::factory()->create();
    $workOrder = WorkOrder::factory()->create();

    $payloads = [
        movementPayload('income', $account, ['registry_id' => $registry->id, 'work_order_id' => $workOrder->id]),
        movementPayload('expense', $account),
        movementPayload('income', $account, ['revenue_type_id' => null, 'treasury_type_id' => TreasuryType::factory()->create()->id]),
        movementPayload('transfer', $account, ['counterpart_account_id' => $other->id]),
        movementPayload('adjustment', $account, ['direction' => 'out']),
    ];

    foreach ($payloads as $payload) {
        $response = $this->postJson('/api/treasury-movements', $payload)->assertCreated()->assertJsonPath('success', true);
        $data = $response->json('data');

        expect(array_keys($data))->toBe([
            'id', 'type', 'direction', 'movement_date', 'amount', 'account', 'counterpart_account', 'description',
            'sub_account', 'registry', 'work_order', 'attachments_count', 'created_by', 'created_at', 'updated_at', 'custom_fields',
        ])
            ->and($data['type'])->toBe($payload['type'])
            ->and($data['amount'])->toBe('200.00')
            ->and($data['account'])->toBe(['id' => $account->id, 'name' => $account->name, 'type' => $account->type->value, 'company' => ['id' => $company->id, 'name' => $company->denomination]])
            ->and($data['created_by'])->toBe(['id' => $actor->id, 'name' => $actor->name])
            ->and($data['attachments_count'])->toBe(0);

        $log = DB::table('activity_log')->where('log_name', 'treasury_movements')->where('subject_id', $data['id'])->where('event', 'created')->first();
        expect($log)->not->toBeNull();
    }

    $first = TreasuryMovement::query()->orderBy('id')->first();
    $shown = $this->getJson("/api/treasury-movements/{$first->id}")->assertOk()->json('data');
    expect($shown['sub_account']['kind'])->toBe('revenue')
        ->and($shown['registry']['id'])->toBe($registry->id)
        ->and($shown['work_order'])->toBe(['id' => $workOrder->id, 'code' => $workOrder->code])
        ->and($shown['counterpart_account'])->toBeNull();
    expect(TreasuryMovement::query()->count())->toBe(5);
});

it('AC-006: every invalid payload is 422 and writes no row', function (string $type, array $overrides) {
    Sanctum::actingAs(treasuryWriter());
    $company = Company::factory()->create();
    $account = FinancialAccount::factory()->create(['company_id' => $company->id]);
    $other = FinancialAccount::factory()->create(['company_id' => $company->id]);
    $overrides = array_map(fn ($value) => $value === '@other' ? $other->id : ($value === '@account' ? $account->id : $value), $overrides);

    $this->postJson('/api/treasury-movements', movementPayload($type, $account, $overrides))->assertUnprocessable();

    expect(TreasuryMovement::query()->count())->toBe(0);
})->with([
    'expense with a revenue type' => ['expense', ['cost_type_id' => null, 'revenue_type_id' => 1]],
    'income with a cost type' => ['income', ['revenue_type_id' => null, 'cost_type_id' => 1]],
    'transfer with a cost type' => ['transfer', ['counterpart_account_id' => '@other', 'treasury_type_id' => null, 'cost_type_id' => 1]],
    'adjustment with a revenue type' => ['adjustment', ['direction' => 'in', 'treasury_type_id' => null, 'revenue_type_id' => 1]],
    'no sub-account' => ['income', ['revenue_type_id' => null]],
    'two sub-accounts' => ['income', ['treasury_type_id' => 1]],
    'zero amount' => ['income', ['amount' => '0']],
    'negative amount' => ['income', ['amount' => '-5.00']],
    'three decimals' => ['income', ['amount' => '1.005']],
    'empty description' => ['income', ['description' => '']],
    'long description' => ['income', ['description' => str_repeat('a', 256)]],
    'transfer without destination' => ['transfer', []],
    'transfer to the same account' => ['transfer', ['counterpart_account_id' => '@account']],
    'destination on an income' => ['income', ['counterpart_account_id' => '@other']],
    'adjustment without direction' => ['adjustment', []],
    'direction on an expense' => ['expense', ['direction' => 'in']],
    'before the opening date' => ['income', ['movement_date' => '2025-12-31']],
    'unknown type' => ['bogus', []],
    'unknown registry' => ['income', ['registry_id' => 999999]],
    'unknown work order' => ['income', ['work_order_id' => 999999]],
]);

it('AC-006: an inactive sub-account is refused on create but kept when unchanged on update', function () {
    Sanctum::actingAs(treasuryWriter());
    $account = FinancialAccount::factory()->create();
    $inactive = RevenueType::factory()->create(['is_active' => false]);

    $this->postJson('/api/treasury-movements', movementPayload('income', $account, ['revenue_type_id' => $inactive->id]))->assertUnprocessable();

    $movement = TreasuryMovement::factory()->income()->create([
        'financial_account_id' => $account->id, 'revenue_type_id' => $inactive->id, 'movement_date' => '2026-03-01',
    ]);

    $this->patchJson("/api/treasury-movements/{$movement->id}", ['description' => 'Renamed', 'revenue_type_id' => $inactive->id])
        ->assertOk()->assertJsonPath('data.description', 'Renamed');
});

it('AC-006: the opening date of the account rules the movement date', function () {
    Sanctum::actingAs(treasuryWriter());
    $account = FinancialAccount::factory()->create(['opening_balance_date' => '2026-03-01']);

    $this->postJson('/api/treasury-movements', movementPayload('income', $account, ['movement_date' => '2026-02-28']))->assertUnprocessable()->assertJsonValidationErrors('movement_date');
    $this->postJson('/api/treasury-movements', movementPayload('income', $account, ['movement_date' => '2026-03-01']))->assertCreated();
});

it('AC-007: a same-company transfer moves the balances and keeps the company total', function () {
    Sanctum::actingAs(treasuryWriter());
    $company = Company::factory()->create();
    $from = FinancialAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => '500.00']);
    $to = FinancialAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => '100.00']);
    $totalBefore = bcadd(balanceOf($from), balanceOf($to), 2);

    $this->postJson('/api/treasury-movements', movementPayload('transfer', $from, ['counterpart_account_id' => $to->id]))
        ->assertCreated()
        ->assertJsonPath('data.counterpart_account.id', $to->id);

    expect(balanceOf($from))->toBe('300.00')
        ->and(balanceOf($to))->toBe('300.00')
        ->and(bcadd(balanceOf($from), balanceOf($to), 2))->toBe($totalBefore);
});

it('AC-007: a transfer across companies, or between a company account and one without, is 422', function () {
    Sanctum::actingAs(treasuryWriter());
    $from = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);
    $otherCompany = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);
    $noCompany = FinancialAccount::factory()->create(['company_id' => null]);
    $alsoNoCompany = FinancialAccount::factory()->create(['company_id' => null]);

    $this->postJson('/api/treasury-movements', movementPayload('transfer', $from, ['counterpart_account_id' => $otherCompany->id]))->assertUnprocessable()->assertJsonValidationErrors('counterpart_account_id');
    $this->postJson('/api/treasury-movements', movementPayload('transfer', $from, ['counterpart_account_id' => $noCompany->id]))->assertUnprocessable();
    $this->postJson('/api/treasury-movements', movementPayload('transfer', $noCompany, ['counterpart_account_id' => $from->id]))->assertUnprocessable();
    expect(TreasuryMovement::query()->count())->toBe(0);

    $this->postJson('/api/treasury-movements', movementPayload('transfer', $noCompany, ['counterpart_account_id' => $alsoNoCompany->id]))->assertCreated();
});

it('AC-008: update and delete are audited with before/after and the balance follows each step', function () {
    Sanctum::actingAs(treasuryWriter());
    $account = FinancialAccount::factory()->create(['opening_balance' => '0.00']);
    $id = $this->postJson('/api/treasury-movements', movementPayload('income', $account))->json('data.id');
    expect(balanceOf($account))->toBe('200.00');

    $this->patchJson("/api/treasury-movements/{$id}", ['amount' => '350.50'])->assertOk()->assertJsonPath('data.amount', '350.50');
    expect(balanceOf($account))->toBe('350.50');

    $updated = DB::table('activity_log')->where('subject_id', $id)->where('event', 'updated')->first();
    $properties = json_decode($updated->properties, true);
    expect($properties['old']['amount'])->toBe('200.00')->and($properties['attributes']['amount'])->toBe('350.50');

    $this->deleteJson("/api/treasury-movements/{$id}")->assertNoContent();
    expect(balanceOf($account))->toBe('0.00')
        ->and(TreasuryMovement::query()->find($id))->toBeNull()
        ->and(DB::table('activity_log')->where('subject_id', $id)->where('event', 'deleted')->exists())->toBeTrue();
});

it('AC-008: an update changing the type to an incoherent sub-account is 422 and nothing changes', function () {
    Sanctum::actingAs(treasuryWriter());
    $movement = TreasuryMovement::factory()->income()->create(['movement_date' => '2026-03-01']);

    $this->patchJson("/api/treasury-movements/{$movement->id}", ['type' => 'expense'])->assertUnprocessable();

    expect($movement->fresh()->type->value)->toBe('income');
});

it('AC-009: with view only the show works and every write is 403', function () {
    $movement = TreasuryMovement::factory()->income()->create();
    Sanctum::actingAs(treasuryUserWith(['viewAny', 'view']));

    $this->getJson("/api/treasury-movements/{$movement->id}")->assertOk();
    $this->postJson('/api/treasury-movements', movementPayload('income', $movement->account))->assertForbidden();
    $this->patchJson("/api/treasury-movements/{$movement->id}", ['description' => 'x'])->assertForbidden();
    $this->deleteJson("/api/treasury-movements/{$movement->id}")->assertForbidden();
    expect(TreasuryMovement::query()->count())->toBe(1);
});

it('AC-009: without any treasury permission everything is 403, unauthenticated 401, unknown 404', function () {
    $movement = TreasuryMovement::factory()->income()->create();
    $this->getJson("/api/treasury-movements/{$movement->id}")->assertUnauthorized();

    Sanctum::actingAs(treasuryUserWith([]));
    $this->getJson("/api/treasury-movements/{$movement->id}")->assertForbidden();
    $this->postJson('/api/treasury-movements', movementPayload('income', $movement->account))->assertForbidden();
    $this->patchJson("/api/treasury-movements/{$movement->id}", ['description' => 'x'])->assertForbidden();
    $this->deleteJson("/api/treasury-movements/{$movement->id}")->assertForbidden();

    Sanctum::actingAs(treasuryWriter());
    $this->getJson('/api/treasury-movements/999999')->assertNotFound();
});

it('AC-019: the menu shows both entries to who holds treasury-movements.view, nobody else', function () {
    Permission::findOrCreate('financial-accounts.view');
    $viewer = treasuryUserWith(['view']);
    Sanctum::actingAs(treasuryUserWith([]));
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))->not->toContain('treasury-movements')->not->toContain('account-statements');

    $viewer->givePermissionTo('financial-accounts.view');
    Sanctum::actingAs($viewer);
    $navigation = $this->getJson('/api/navigation')->json('data');
    $finance = collect(navigationNode($navigation, 'accounting-finance')['children'])->keyBy('key');

    expect($finance->keys()->all())->toContain('treasury-movements', 'account-statements')
        ->and($finance['treasury-movements'])->toMatchArray(['route' => '/treasury-movements', 'icon' => 'arrow-left-right'])
        ->and($finance['account-statements'])->toMatchArray(['route' => '/account-statements', 'icon' => 'scroll-text'])
        ->and($finance->keys()->search('treasury-movements'))->toBe($finance->keys()->search('financial-accounts') + 1);
});

it('attachments: a writer uploads to a movement, the count shows, a user who cannot view it is refused', function () {
    Storage::fake('local');
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("attachments.{$ability}");
    }
    $movement = TreasuryMovement::factory()->income()->create();
    $writer = treasuryWriter()->givePermissionTo(['attachments.viewAny', 'attachments.view', 'attachments.create', 'attachments.delete']);
    Sanctum::actingAs($writer);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('receipt.pdf', 32, 'application/pdf'),
        'attachable_type' => 'treasury_movement',
        'attachable_id' => $movement->id,
    ])->assertCreated();

    $this->getJson("/api/treasury-movements/{$movement->id}")->assertJsonPath('data.attachments_count', 1);

    Sanctum::actingAs(treasuryUserWith([])->givePermissionTo(['attachments.viewAny', 'attachments.view', 'attachments.create']));
    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('other.pdf', 32, 'application/pdf'),
        'attachable_type' => 'treasury_movement',
        'attachable_id' => $movement->id,
    ])->assertForbidden();
});
