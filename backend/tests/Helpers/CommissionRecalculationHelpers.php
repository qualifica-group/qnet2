<?php

declare(strict_types=1);

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// Spec 0308: shared fixtures for the quote and rule recalculation tests.

if (! function_exists('recalcActor')) {
    /** @param  array<int, string>  $permissions */
    function recalcActor(array $permissions): User
    {
        // Every permission the endpoints check must exist, or a policy lookup throws.
        foreach (['quotes.view', 'quotes.update', 'quotes.viewAll', 'commission-configurations.view', 'commission-configurations.update'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        // Spec 0316: these tests are not about record visibility.
        $user->givePermissionTo([...$permissions, 'quotes.viewAll']);

        return $user;
    }
}

if (! function_exists('recalcProduct')) {
    function recalcProduct(bool $supplierEnabled = false): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
        $typology = $supplierEnabled ? ProductTypology::factory()->supplierCommission()->create() : ProductTypology::factory()->create();

        return Product::factory()->create([
            'category_id' => $category->id,
            'supplier_id' => Registry::factory()->create()->id,
            'product_typology_id' => $typology->id,
        ]);
    }
}

if (! function_exists('recalcRule')) {
    function recalcRule(Product $product, CommissionRecipientRole $role, float $value, array $attributes = []): CommissionConfiguration
    {
        return CommissionConfiguration::factory()->create([
            'recipient_role' => $role,
            'application_scope' => CommissionApplicationScope::Product,
            'product_id' => $product->id,
            'product_category_id' => null,
            'commission_type' => CommissionType::Percentage,
            'value' => $value,
            'valid_from' => '2026-01-01',
            ...$attributes,
        ]);
    }
}

if (! function_exists('recalcQuote')) {
    /**
     * An Offerta in a status of $group with ONE REVENUE line (net 100) on
     * $product and a Commerciale referent on the quote.
     *
     * @return array{0: Quote, 1: QuoteLine}
     */
    function recalcQuote(Product $product, WorkflowStatusGroup $group = WorkflowStatusGroup::Open, ?SupplierCommissionDirection $direction = null, array $quoteAttributes = []): array
    {
        $status = QuoteWorkflowStatus::factory()->global()->create(['group' => $group]);
        $quote = Quote::factory()->create([
            'quote_workflow_status_id' => $status->id,
            'commercial_id' => Referent::factory()->create()->id,
            ...$quoteAttributes,
        ]);
        $line = QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'net_amount' => 100,
            'total_amount' => 100,
            'supplier_commission_direction' => $direction,
        ]);

        return [$quote, $line];
    }
}

if (! function_exists('recalcCommission')) {
    function recalcCommission(QuoteLine $line, CommissionRecipientRole $role, float $value, ?CommissionConfiguration $rule = null, array $attributes = []): QuoteLineCommission
    {
        return $line->commissions()->create([
            'commission_configuration_id' => $rule?->id,
            'recipient_role' => $role,
            'recipient_type' => $role === CommissionRecipientRole::Supplier ? 'registry' : 'referent',
            'recipient_id' => $role === CommissionRecipientRole::Supplier ? $line->product->supplier_id : $line->quote->commercial_id,
            'commission_type' => CommissionType::Percentage,
            'value' => $value,
            'calculated_amount' => $value,
            'origin' => $rule === null ? CommissionOrigin::ManualOverride : CommissionOrigin::Product,
            ...$attributes,
        ]);
    }
}
