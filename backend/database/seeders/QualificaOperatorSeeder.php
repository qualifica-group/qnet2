<?php

namespace Database\Seeders;

use App\Enums\LocaleEnum;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\AssignsSeedPassword;
use Database\Seeders\Concerns\SyncsPersonName;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The client's real operators (OperatorRoster, user directive 2026-09-15):
 * one account per roster row, with its anagrafica's first and last name, its
 * mansione's role, its Sedi (spec 0103:
 * the physical one plus every remote one) and its product-category
 * competence (spec 0111 / 0129), and its "Assegnabile" switch (spec 0194):
 * on for a row with categories or a function-wide competence, off for the
 * others, which get every category of OperatorRoster::UNASSIGNABLE_FUNCTION.
 *
 * Runs AFTER QualificaLegacyImportSeeder and QualificaBusinessFunctionLinkSeeder:
 * the operational sites and the business functions are imported from the
 * external qnet CRM, and a competence row needs the category's EFFECTIVE
 * function. It seeds the roles itself (QualificaRoleSeeder) so it stays
 * runnable on its own.
 *
 * Idempotent and CONVERGENT: role, Sedi, competence and switch are re-synced from the
 * roster on every run — the roster is the mansionario. The password is the one
 * exception: set only when the account is created (user decision 2026-09-15),
 * so a password changed by the operator survives a re-seed.
 *
 * NEVER fatal: a city with no imported site, a category with no effective
 * business function, or a missing business function, is skipped with a
 * warning and the account still lands.
 */
class QualificaOperatorSeeder extends Seeder
{
    use AssignsSeedPassword, SyncsPersonName;

    /** @var Collection<int, OperationalSite> */
    private Collection $sites;

    /** @var array<string, array{product_category_id: int, business_function_id: int}> */
    private array $competenceByCategoryName = [];

    /** @var array<string, int> */
    private array $functionIdByName = [];

    public function run(): void
    {
        // Step 1: the roles the roster points at.
        $this->call(QualificaRoleSeeder::class);

        // Step 2: the lookups every row resolves against, read once.
        $this->sites = OperationalSite::query()->whereNotNull('alias')->orderBy('alias')->orderBy('id')->get();
        $this->competenceByCategoryName = $this->resolveCompetencePairs();
        $this->functionIdByName = $this->resolveFunctionIds();

        // Step 3: one account per row, with its employment profile.
        foreach (OperatorRoster::OPERATORS as [$firstName, $lastName, $email, $job, $role, $physicalCity, $cities, $categories]) {
            $user = $this->seedAccount("{$firstName} {$lastName}", $email, $role);
            $this->syncPersonName($user, $firstName, $lastName);

            $wholeFunction = OperatorRoster::FUNCTION_WIDE_COMPETENCE[$email] ?? null;
            $isAssignable = $wholeFunction !== null || $categories !== [];

            // Never the wildcard: a profile seeded as "Tutte" before converges.
            $employment = $user->employment()->updateOrCreate([], [
                'job_description' => $job,
                'covers_all_product_categories' => false,
                'is_assignable' => $isAssignable,
            ]);

            $this->syncSites($employment, $email, $physicalCity, $cities);
            $this->syncCompetence(
                $employment,
                $email,
                $categories,
                $isAssignable ? $wholeFunction : OperatorRoster::UNASSIGNABLE_FUNCTION,
            );
        }

        $this->command?->info(sprintf('%d operators seeded.', count(OperatorRoster::OPERATORS)));
    }

    private function seedAccount(string $name, string $email, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->locale ??= LocaleEnum::It->value;
        $user->email_verified_at ??= now();

        if (! $user->exists) {
            $this->assignSeedPassword($user);
        }

        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }

    /**
     * The physical Sede is the FIRST site of the physical city (by alias, so
     * "FRATTAMAGGIORE 1 (HQ)" before "Frattamaggiore 2"); every other site of
     * the enabled cities is a remote membership.
     *
     * @param  array<int, string>  $cities
     */
    private function syncSites(EmploymentProfile $employment, string $email, string $physicalCity, array $cities): void
    {
        $physical = $this->sitesOfCity($physicalCity, $email)->first();

        $membership = collect($cities)
            ->flatMap(fn (string $city): Collection => $this->sitesOfCity($city, $email))
            ->reject(fn (OperationalSite $site): bool => $site->is($physical))
            ->mapWithKeys(fn (OperationalSite $site): array => [$site->id => ['is_primary' => false]]);

        if ($physical !== null) {
            $membership->put($physical->id, ['is_primary' => true]);
        }

        $employment->operationalSites()->sync($membership->all());
    }

    /**
     * Every site whose alias IS the city, or the city followed by a qualifier
     * ("Roma - 1", "Palermo (ex?)", "FRATTAMAGGIORE 1 (HQ)"): the legacy
     * aliases number the Sedi of one city, they do not rename it.
     *
     * @return Collection<int, OperationalSite>
     */
    private function sitesOfCity(string $city, string $email): Collection
    {
        $key = Str::lower($city);

        $matches = $this->sites->filter(function (OperationalSite $site) use ($key): bool {
            $alias = Str::lower(trim((string) $site->alias));

            return $alias === $key || Str::startsWith($alias, $key.' ');
        })->values();

        if ($matches->isEmpty()) {
            $this->command?->warn(sprintf('%s: no operational site found for "%s".', $email, $city));
        }

        return $matches;
    }

    /**
     * Delete-and-recreate from the roster: one "every category" row on
     * $wholeFunctionName when given (spec 0129 D-3), the roster's categories
     * otherwise.
     *
     * @param  array<int, string>  $categories
     */
    private function syncCompetence(EmploymentProfile $employment, string $email, array $categories, ?string $wholeFunctionName): void
    {
        $employment->productLines()->delete();

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
