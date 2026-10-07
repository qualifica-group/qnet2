<?php

declare(strict_types=1);

use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

if (! function_exists('workOrderEmailActor')) {
    /**
     * A user holding the given work-orders abilities on ONE specific
     * commessa, WITHOUT work-orders.viewAll (unlike workOrderUserWith()
     * above) — spec 0175 BE-05's own membership-scope enforcement
     * (WorkOrderPolicy::viewEmails/sendEmail, D-14) needs actors that are
     * genuinely in or out of a commessa's team, not ones the blanket
     * viewAll permission would let through regardless.
     *
     * @param  array<int, string>  $abilities  e.g. ['viewEmails'], ['sendEmail']
     */
    function workOrderEmailActor(WorkOrder $workOrder, array $abilities, bool $inScope = true): User
    {
        foreach (['view', 'viewEmails', 'sendEmail', 'viewDocuments'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        if ($inScope) {
            $workOrder->supervisors()->attach($user->id);
        }

        return $user;
    }
}

if (! function_exists('workOrderCostsUserWith')) {
    /**
     * A user holding the given work-orders abilities plus `viewAll` (so the
     * membership scoping never interferes), spec 0190.
     *
     * @param  array<int, string>  $abilities  e.g. ['viewCosts', 'manageCosts']
     */
    function workOrderCostsUserWith(array $abilities): User
    {
        foreach (['view', 'viewCosts', 'manageCosts', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(fn (string $ability): string => "work-orders.{$ability}", [...$abilities, 'viewAll']));

        return $user;
    }
}

if (! function_exists('workOrderPaymentStatusUserWith')) {
    /**
     * A user holding the given work-order-payment-statuses abilities (spec 0201).
     *
     * @param  array<int, string>  $abilities
     */
    function workOrderPaymentStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("work-order-payment-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-order-payment-statuses.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('workOrderPaymentsUserWith')) {
    /**
     * A user holding the given work-orders abilities plus `viewAll` (so the
     * membership scoping never interferes), spec 0201.
     *
     * @param  array<int, string>  $abilities  e.g. ['viewContractData', 'managePayments']
     */
    function workOrderPaymentsUserWith(array $abilities, bool $viewAll = true): User
    {
        foreach (['view', 'viewContractData', 'managePayments', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(array_map(fn (string $ability): string => "work-orders.{$ability}", $viewAll ? [...$abilities, 'viewAll'] : $abilities));

        return $user;
    }
}

if (! function_exists('contractDataLine')) {
    /**
     * Programs a REVENUE line of the given typology into $workOrder (spec 0201).
     *
     * @param  string  $typologyCode  'institution' (Ente) or any other code (Consulenza)
     */
    function contractDataLine(WorkOrder $workOrder, string $typologyCode, float $net): QuoteLine
    {
        $typology = ProductTypology::firstOrCreate(['code' => $typologyCode], ['name' => ucfirst($typologyCode)]);
        $product = Product::factory()->create(['product_typology_id' => $typology->id]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $workOrder->quote_id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $net,
            'net_amount' => $net,
            'total_amount' => $net,
        ]);
        $workOrder->quoteLines()->attach($line->id);

        return $line;
    }
}

if (! function_exists('contractDataCommission')) {
    /**
     * A commission on a line with an explicitly persisted amount (spec 0201).
     */
    function contractDataCommission(QuoteLine $line, CommissionRecipientRole $role, CommissionType $type, float $value, float $amount): QuoteLineCommission
    {
        return QuoteLineCommission::factory()->create([
            'quote_line_id' => $line->id,
            'recipient_role' => $role,
            'commission_type' => $type,
            'value' => $value,
            'calculated_amount' => $amount,
        ]);
    }
}
