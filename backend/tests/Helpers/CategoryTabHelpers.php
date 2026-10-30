<?php

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Spatie\Permission\Models\Permission;

// Shared by tests/Feature/CategoryTabs (spec 0335).

if (! function_exists('categoryTabGridActor')) {
    /**
     * An actor with `{grid}.viewAny` (+ `viewAll` when $viewAll) on a grid.
     */
    function categoryTabGridActor(string $grid, bool $viewAll = true, bool $viewAny = true): User
    {
        $user = User::factory()->create();
        $abilities = array_filter([$viewAny ? 'viewAny' : null, $viewAll ? 'viewAll' : null]);

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("{$grid}.{$ability}", 'web'));
        }

        return $user;
    }
}

if (! function_exists('categoryTabOpportunity')) {
    /**
     * An Opportunity with one product line per given category.
     */
    function categoryTabOpportunity(ProductCategory ...$categories): Opportunity
    {
        $opportunity = Opportunity::factory()->create();

        foreach ($categories as $category) {
            OpportunityProductLine::factory()->create([
                'opportunity_id' => $opportunity->id,
                'business_function_id' => BusinessFunction::factory()->create()->id,
                'product_category_id' => $category->id,
            ]);
        }

        return $opportunity;
    }
}

if (! function_exists('categoryTabQuote')) {
    /**
     * A Quote with one REVENUE line per given category ($cost: COST lines
     * instead), on a fresh Opportunity (or $opportunity).
     */
    function categoryTabQuote(array $categories, bool $cost = false, ?Opportunity $opportunity = null): Quote
    {
        $quote = Quote::factory()->create(['opportunity_id' => ($opportunity ?? Opportunity::factory()->create())->id]);

        foreach ($categories as $category) {
            QuoteLine::factory()
                ->state(fn () => $cost ? ['line_type' => 'COST'] : [])
                ->create([
                    'quote_id' => $quote->id,
                    'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
                ]);
        }

        return $quote;
    }
}

if (! function_exists('categoryTabContract')) {
    /**
     * A Contract on a Quote with one REVENUE line per given category ($cost: COST lines instead).
     */
    function categoryTabContract(array $categories, bool $cost = false): Contract
    {
        return Contract::factory()->create(['quote_id' => categoryTabQuote($categories, $cost)->id]);
    }
}
