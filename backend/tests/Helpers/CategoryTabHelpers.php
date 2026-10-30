<?php

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

// Shared by the root-level tests (spec 0337).

if (! function_exists('categoryTabTree')) {
    /**
     * Root R with child F and grandchild N, plus an unrelated root S.
     *
     * @return array{0: ProductCategory, 1: ProductCategory, 2: ProductCategory, 3: ProductCategory}
     */
    function categoryTabTree(): array
    {
        $root = ProductCategory::factory()->create(['name' => 'R root']);
        $child = ProductCategory::factory()->create(['name' => 'F child', 'parent_id' => $root->id]);
        $grandchild = ProductCategory::factory()->create(['name' => 'N grandchild', 'parent_id' => $child->id]);
        $other = ProductCategory::factory()->create(['name' => 'S other root']);

        return [$root, $child, $grandchild, $other];
    }
}

if (! function_exists('categoryTabRequestQuote')) {
    /**
     * A Gestione Richieste / Iscritti row: a Quote whose opportunity carries one
     * product line per category; $statusKey picks the workflow status.
     *
     * @param  array<int, ProductCategory>  $categories
     */
    function categoryTabRequestQuote(array $categories, string $statusKey = 'open'): Quote
    {
        $statusId = QuoteWorkflowStatus::query()->whereNull('quote_workflow_id')->where('system_key', $statusKey)->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($statusKey)->create()->id;

        return Quote::factory()
            ->for(categoryTabOpportunity(...$categories))
            ->create(['quote_workflow_status_id' => $statusId]);
    }
}

if (! function_exists('categoryTabRecord')) {
    /**
     * One visible record of the module's grid on the given categories (the
     * Iscritti module only shows closed_won/validated quotes).
     *
     * @param  array<int, ProductCategory>  $categories
     */
    function categoryTabRecord(string $module, array $categories): Model
    {
        return match ($module) {
            'request-management' => categoryTabRequestQuote($categories),
            'enrollee-management' => categoryTabRequestQuote($categories, 'closed_won'),
            'opportunities' => categoryTabOpportunity(...$categories),
            'quotes' => categoryTabQuote($categories),
            'contracts' => categoryTabContract($categories),
        };
    }
}

if (! function_exists('categoryTabCountKey')) {
    /** The count field of the strip payload: requests for the two request modules, records otherwise. */
    function categoryTabCountKey(string $module): string
    {
        return in_array($module, ['request-management', 'enrollee-management'], true) ? 'requests_count' : 'records_count';
    }
}

if (! function_exists('categoryTabModules')) {
    /** @return array<int, string> the five modules with a category tab strip */
    function categoryTabModules(): array
    {
        return ['request-management', 'enrollee-management', 'opportunities', 'quotes', 'contracts'];
    }
}
