<?php

declare(strict_types=1);

use App\Models\AgentFiscalProfile;
use App\Models\CommissionAccrual;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

if (! function_exists('enasarcoParameterUserWith')) {
    /**
     * A user holding exactly the given enasarco-parameters abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function enasarcoParameterUserWith(array $abilities): User
    {
        return settlementResourceUserWith('enasarco-parameters', $abilities);
    }
}

if (! function_exists('commissionSettlementUserWith')) {
    /**
     * A user holding exactly the given commission-settlements abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function commissionSettlementUserWith(array $abilities): User
    {
        return settlementResourceUserWith('commission-settlements', $abilities);
    }
}

if (! function_exists('settlementResourceUserWith')) {
    /**
     * Creates every ability of the resource, grants only the requested ones.
     *
     * @param  array<int, string>  $abilities
     */
    function settlementResourceUserWith(string $resource, array $abilities): User
    {
        $all = $resource === 'commission-settlements'
            ? ['viewAny', 'view', 'create', 'update', 'delete', 'issue', 'cancel', 'receiveDocument', 'approve', 'adjust', 'export', 'viewActivity']
            : ['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'];

        foreach ($all as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('enasarcoParameterPayload')) {
    /**
     * A valid write payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function enasarcoParameterPayload(array $overrides = []): array
    {
        return array_merge([
            'year' => 2026,
            'total_rate' => '17.00',
            'agent_share_percent' => '50.00',
            'single_firm_ceiling' => '45000.00',
            'multi_firm_ceiling' => '26000.00',
            'notes' => null,
        ], $overrides);
    }
}

if (! function_exists('settlementAgent')) {
    /**
     * A referent with a fiscal profile valid from 2026-01-01 (withholding 23% on 50%, no ENASARCO, no VAT).
     *
     * @param  array<string, mixed>  $profile  overrides of the profile
     */
    function settlementAgent(array $profile = []): Referent
    {
        $referent = Referent::factory()->create();
        AgentFiscalProfile::factory()->create(['referent_id' => $referent->id, ...$profile]);

        return $referent;
    }
}

if (! function_exists('settlementMovement')) {
    /**
     * An open ledger movement owed to the referent for the company.
     *
     * @param  array<string, mixed>  $overrides
     */
    function settlementMovement(Referent $referent, Company $company, string $amount, string $accruedOn = '2026-09-10', array $overrides = []): CommissionAccrual
    {
        return CommissionAccrual::factory()->create([
            'recipient_id' => $referent->id,
            'company_id' => $company->id,
            'amount' => $amount,
            'accrued_on' => $accruedOn,
            ...$overrides,
        ]);
    }
}

if (! function_exists('settlementRunBody')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function settlementRunBody(array $overrides = []): array
    {
        return ['period_from' => '2026-09-01', 'period_to' => '2026-09-30', ...$overrides];
    }
}

if (! function_exists('settlementAdmin')) {
    /** A user holding every commission-settlements ability. */
    function settlementAdmin(): User
    {
        return commissionSettlementUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'issue', 'cancel', 'receiveDocument', 'approve', 'adjust', 'export', 'viewActivity']);
    }
}

if (! function_exists('draftSettlementFor')) {
    /**
     * Launches a run through the API as an admin and returns the created draft.
     * The movements must exist already.
     *
     * @param  array<string, mixed>  $body
     */
    function draftSettlementFor(Referent $referent, Company $company, array $body = []): CommissionSettlement
    {
        Sanctum::actingAs(settlementAdmin());
        test()->postJson('/api/commission-settlement-runs', settlementRunBody([
            'company_id' => $company->id,
            'referent_ids' => [$referent->id],
            ...$body,
        ]))->assertCreated();

        return CommissionSettlement::query()->where('referent_id', $referent->id)->where('company_id', $company->id)->latest('id')->firstOrFail();
    }
}

if (! function_exists('settlementRow')) {
    /**
     * A settlement row of the agent, linked to the agent's own profile (the
     * factory default would create a second one).
     *
     * @param  'draft'|'issued'|'document_received'|'approved'|'cancelled'  $state
     * @param  array<string, mixed>  $attributes
     */
    function settlementRow(Referent $referent, Company $company, string $state = 'draft', array $attributes = []): CommissionSettlement
    {
        $factory = CommissionSettlement::factory();
        $factory = match ($state) {
            'issued' => $factory->issued(),
            'document_received' => $factory->documentReceived(),
            'approved' => $factory->approved(),
            'cancelled' => $factory->cancelled(),
            default => $factory,
        };

        return $factory->create([
            'referent_id' => $referent->id,
            'company_id' => $company->id,
            'agent_fiscal_profile_id' => AgentFiscalProfile::query()->where('referent_id', $referent->id)->value('id'),
            ...$attributes,
        ]);
    }
}

if (! function_exists('settlementDocumentBody')) {
    /**
     * A valid agent document for a settlement of taxable 1000.00, VAT 220.00, withholding 115.00, ENASARCO 85.00.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function settlementDocumentBody(array $overrides = []): array
    {
        return [
            'document_number' => 'FA-77',
            'document_date' => '2026-10-05',
            'document_taxable_amount' => '1000.00',
            'document_vat_amount' => '220.00',
            'document_withholding_amount' => '115.00',
            'document_enasarco_amount' => '85.00',
            'document_total_amount' => '1220.00',
            ...$overrides,
        ];
    }
}

if (! function_exists('amountedSettlement')) {
    /**
     * A settlement in the state with the amounts settlementDocumentBody() matches.
     *
     * @param  'draft'|'issued'|'document_received'|'approved'|'cancelled'  $state
     * @param  array<string, mixed>  $attributes
     */
    function amountedSettlement(string $state = 'issued', array $attributes = []): CommissionSettlement
    {
        $company = Company::factory()->create();
        $agent = settlementAgent();
        $amounts = ['taxable_amount' => '1000.00', 'vat_amount' => '220.00', 'withholding_amount' => '115.00', 'enasarco_agent_amount' => '85.00', 'net_payable' => '1020.00'];
        $document = in_array($state, ['document_received', 'approved'], true)
            ? ['document_taxable_amount' => '1000.00', 'document_vat_amount' => '220.00', 'document_withholding_amount' => '115.00', 'document_enasarco_amount' => '85.00', 'document_total_amount' => '1220.00']
            : [];

        return settlementRow($agent, $company, $state, [...$amounts, ...$document, ...$attributes]);
    }
}

if (! function_exists('approvalUser')) {
    /**
     * A user who may approve (settlement approve + view, purchase-invoices.create and, unless
     * told otherwise, registries.create).
     *
     * @param  array<int, string>  $without  permissions to leave out
     */
    function approvalUser(array $without = []): User
    {
        $user = commissionSettlementUserWith(in_array('commission-settlements.approve', $without, true) ? ['view'] : ['view', 'approve']);

        foreach (['purchase-invoices.create', 'registries.create'] as $permission) {
            Permission::findOrCreate($permission, 'web');

            if (! in_array($permission, $without, true)) {
                $user->givePermissionTo($permission);
            }
        }

        return $user;
    }
}

if (! function_exists('approvalChoices')) {
    /**
     * The body of the approval window: an active payment method and cost type and a 22% VAT rate.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function approvalChoices(array $overrides = []): array
    {
        return [
            'payment_method_id' => PaymentMethod::factory()->create(['payment_days' => 30])->id,
            'cost_type_id' => CostType::factory()->create()->id,
            'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id,
            ...$overrides,
        ];
    }
}

if (! function_exists('approvableSettlement')) {
    /**
     * A DOCUMENT_RECEIVED settlement (taxable 1000, VAT 220, withholding 115, ENASARCO 85) whose agent has a supplier registry.
     *
     * @param  array<string, mixed>  $attributes
     */
    function approvableSettlement(array $attributes = []): CommissionSettlement
    {
        $settlement = amountedSettlement('document_received', $attributes);
        $settlement->referent->update(['supplier_registry_id' => Registry::factory()->supplier()->create()->id]);

        return $settlement;
    }
}
