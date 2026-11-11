<?php

namespace Database\Seeders;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategoryService;
use Database\Seeders\Concerns\SpreadsCompetenceShares;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Avvalimenti set up like ISO (user directive 2026-11-11): the legacy
 * "[OLD] AVVALIMENTI" function stays inactive, a new active "Avvalimenti"
 * function gets a new "Avvalimenti" root category, and the two [OLD]
 * AVVALIMENTI roots move under the new function and link to the new root
 * (`current_category_id`, spec 0374). The function move is what makes a
 * "Avvalimenti - Tutte" assignment scope reach the old offers: competence
 * matches the category's EFFECTIVE function (OperatorCompetence), the link
 * only groups the tables.
 *
 * The legacy function's only member and manager, Maria Iannotta, becomes the
 * new function's member and manager and gets the function-wide scope row
 * (shares re-split evenly, spec 0346), as QualificaFunctionCompetenceSeeder
 * does for every member.
 *
 * `php artisan db:seed --class=QualificaAvvalimentiFunctionSeeder`. One
 * transaction; a re-run converges.
 */
class QualificaAvvalimentiFunctionSeeder extends Seeder
{
    use SpreadsCompetenceShares;

    public const string FUNCTION_NAME = 'Avvalimenti';

    public const string CATEGORY_NAME = 'Avvalimenti';

    public const string MEMBER_EMAIL = 'maria.iannotta@qualificagroup.com';

    /** Legacy ids of the "[OLD] AVVALIMENTI" and "[OLD] AVVALIMENTI-" roots. */
    public const array LEGACY_CATEGORY_OLD_IDS = [7, 62];

    public function __construct(private readonly ProductCategoryService $categories) {}

    public function run(): void
    {
        $member = User::query()->where('email', self::MEMBER_EMAIL)->first();
        $legacyRoots = ProductCategory::query()
            ->whereIn('old_id', self::LEGACY_CATEGORY_OLD_IDS)
            ->where('is_legacy', true)
            ->whereNull('parent_id')
            ->orderBy('old_id')
            ->get();

        DB::transaction(function () use ($member, $legacyRoots): void {
            // Step 1: the new active function, managed by the member.
            $function = $this->ensureFunction($member);

            // Step 2: the new root category on that function.
            $category = $this->ensureCategory($function, $legacyRoots->first());

            // Step 3: the [OLD] roots move under the function and link to the new root.
            $this->relinkLegacyRoots($legacyRoots, $function, $category);

            // Step 4: membership and the function-wide assignment scope.
            $this->enrolMember($member, $function);
        });
    }

    private function ensureFunction(?User $member): BusinessFunction
    {
        $function = BusinessFunction::query()->where('name', self::FUNCTION_NAME)->orderBy('id')->first();

        if ($function === null) {
            $function = BusinessFunction::query()->create(['name' => self::FUNCTION_NAME, 'manager_id' => $member?->id]);
            $this->command?->info(sprintf('Business function "%s" created.', self::FUNCTION_NAME));
        }

        return $function;
    }

    /**
     * The new root takes the root-owned settings of the [OLD] root it
     * replaces, so offers on it follow the same quote and contract flow.
     */
    private function ensureCategory(BusinessFunction $function, ?ProductCategory $template): ProductCategory
    {
        $category = ProductCategory::query()
            ->where('name', self::CATEGORY_NAME)
            ->whereNull('parent_id')
            ->where('is_legacy', false)
            ->first();

        if ($category !== null) {
            return $category;
        }

        $this->command?->info(sprintf('Category "%s" created.', self::CATEGORY_NAME));

        return $this->categories->create(new CreateProductCategoryData(
            name: self::CATEGORY_NAME,
            businessFunctionId: $function->id,
            requiresQuote: $template?->requires_quote,
            managementMode: $template?->management_mode,
            singleQuotePerOpportunity: $template?->single_quote_per_opportunity,
            generatesContract: $template?->generates_contract,
            multiQuoteWorkOrder: $template?->multi_quote_work_order,
        ));
    }

    /**
     * @param  Collection<int, ProductCategory>  $legacyRoots
     */
    private function relinkLegacyRoots(Collection $legacyRoots, BusinessFunction $function, ProductCategory $category): void
    {
        // A per-model update, so the activity log records the move.
        $legacyRoots->each(fn (ProductCategory $root) => $root->update([
            'business_function_id' => $function->id,
            'current_category_id' => $category->id,
        ]));
    }

    private function enrolMember(?User $member, BusinessFunction $function): void
    {
        if ($member === null) {
            $this->command?->warn(sprintf('%s: no account, membership and assignment scope skipped.', self::MEMBER_EMAIL));

            return;
        }

        $function->users()->syncWithoutDetaching([$member->id]);

        $employment = $member->employment ?? $member->employment()->create();

        if ($employment->covers_all_product_categories || $this->hasFunctionWideLine($employment, $function)) {
            return;
        }

        $employment->productLines()->where('business_function_id', $function->id)->delete();
        $employment->productLines()->create(['business_function_id' => $function->id, 'product_category_id' => null]);
        $this->spreadCompetenceShares($employment);
    }

    private function hasFunctionWideLine(EmploymentProfile $employment, BusinessFunction $function): bool
    {
        return $employment->productLines()
            ->where('business_function_id', $function->id)
            ->whereNull('product_category_id')
            ->exists();
    }
}
