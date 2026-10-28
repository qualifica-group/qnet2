<?php

namespace Database\Seeders\Concerns;

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Illuminate\Support\Str;

/**
 * The product-category competence ("Ambito di assegnazione", spec 0111 /
 * 0129) of an OperatorRoster row, shared by the seeders that write it: one
 * "every category" row on the row's function-wide competence, the roster's
 * categories otherwise, and every category of
 * OperatorRoster::UNASSIGNABLE_FUNCTION for a row with neither.
 *
 * NEVER fatal: a category with no effective business function, or a missing
 * business function, is skipped with a warning.
 */
trait SyncsRosterCompetence
{
    /** @var array<string, array{product_category_id: int, business_function_id: int}> */
    private array $competenceByCategoryName = [];

    /** @var array<string, int> */
    private array $functionIdByName = [];

    /**
     * Read once, before the first syncRosterCompetence().
     */
    private function loadCompetenceLookups(): void
    {
        $this->competenceByCategoryName = $this->resolveCompetencePairs();
        $this->functionIdByName = $this->resolveFunctionIds();
    }

    /**
     * Spec 0194: on for a row with categories or a function-wide competence.
     *
     * @param  array<int, string>  $categories
     */
    private function isRosterAssignable(string $email, array $categories): bool
    {
        return isset(OperatorRoster::FUNCTION_WIDE_COMPETENCE[$email]) || $categories !== [];
    }

    /**
     * Delete-and-recreate from the roster.
     *
     * @param  array<int, string>  $categories
     */
    private function syncRosterCompetence(EmploymentProfile $employment, string $email, array $categories): void
    {
        $employment->productLines()->delete();

        $wholeFunctionName = $this->isRosterAssignable($email, $categories)
            ? (OperatorRoster::FUNCTION_WIDE_COMPETENCE[$email] ?? null)
            : OperatorRoster::UNASSIGNABLE_FUNCTION;

        if ($wholeFunctionName !== null) {
            $this->createFunctionWideLine($employment, $email, $wholeFunctionName);

            return;
        }

        foreach ($categories as $categoryName) {
            $pair = $this->competenceByCategoryName[$categoryName] ?? null;

            if ($pair === null) {
                $this->command?->warn(sprintf('%s: category "%s" skipped, missing or without a business function.', $email, $categoryName));

                continue;
            }

            $employment->productLines()->create($pair);
        }
    }

    private function createFunctionWideLine(EmploymentProfile $employment, string $email, string $functionName): void
    {
        $functionId = $this->functionIdByName[Str::lower($functionName)] ?? null;

        if ($functionId === null) {
            $this->command?->warn(sprintf('%s: business function "%s" not found, no competence row.', $email, $functionName));

            return;
        }

        $employment->productLines()->create([
            'business_function_id' => $functionId,
            'product_category_id' => null,
        ]);
    }

    /**
     * lowercased business function name => id, for the functions the roster
     * names as a whole. Case-insensitive: the imported functions are upper
     * case ("FORMAZIONE"). `name` is not unique: the lowest id wins, as in
     * QualificaBusinessFunctionLinkSeeder.
     *
     * @return array<string, int>
     */
    private function resolveFunctionIds(): array
    {
        $wanted = array_map(
            Str::lower(...),
            [...array_values(OperatorRoster::FUNCTION_WIDE_COMPETENCE), OperatorRoster::UNASSIGNABLE_FUNCTION],
        );

        return BusinessFunction::query()
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->filter(static fn (BusinessFunction $function): bool => in_array(Str::lower($function->name), $wanted, true))
            ->mapWithKeys(static fn (BusinessFunction $function): array => [Str::lower($function->name) => (int) $function->id])
            ->all();
    }

    /**
     * category name => the (category, EFFECTIVE business function) pair a
     * competence row must carry (spec 0111 D-2). `name` is the catalogue's
     * natural key; on a duplicate the lowest id wins.
     *
     * @return array<string, array{product_category_id: int, business_function_id: int}>
     */
    private function resolveCompetencePairs(): array
    {
        $names = collect(OperatorRoster::OPERATORS)
            ->pluck(7)
            ->flatten()
            ->unique()
            ->all();

        $summaries = app(CategoryHierarchy::class)->effectiveBusinessFunctionSummaries();

        return ProductCategory::query()
            ->whereIn('name', $names)
            ->orderByDesc('id')
            ->get(['id', 'name'])
            ->filter(fn (ProductCategory $category): bool => isset($summaries[$category->id]))
            ->mapWithKeys(fn (ProductCategory $category): array => [$category->name => [
                'product_category_id' => $category->id,
                'business_function_id' => $summaries[$category->id]['id'],
            ]])
            ->all();
    }
}
