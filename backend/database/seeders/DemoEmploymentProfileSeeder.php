<?php

namespace Database\Seeders;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\Concerns\ResolvesCategoryBusinessFunction;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seed an employment profile for every deterministic development user (spec
 * 0015): a small responsible/subordinate hierarchy — the first 2 seeded
 * users become managers (is_manager=true, no reports-to), every other seeded
 * user is a subordinate reporting to one of them, round-robin. Idempotent:
 * the profile is upserted by owner, its site membership, its reports-to
 * managers and its competence rows recomputed from the same reseeded Faker
 * sequence.
 *
 * Spec 0166: the FIRST subordinate additionally reports to BOTH managers
 * (D-2, "at least one demo user with two responsible managers"), on top of
 * its round-robin manager — demoing that a user may report to more than one.
 *
 * Spec 0129: the canonical demo account (`demo@app.com`) is the one profile
 * that demonstrates the wildcard flag (D-1) — carrying no competence rows,
 * D-2 — and the first subordinate demonstrates the "every category of this
 * function" row (D-3), on top of its own random per-category rows.
 */
class DemoEmploymentProfileSeeder extends Seeder
{
    use ResolvesCategoryBusinessFunction, SeedsDevelopmentUsers;

    private const int MANAGER_COUNT = 2;

    /**
     * Odds a given (function, category) pair becomes a competence row of a
     * profile: low enough that the demo covers users with no competence at
     * all (the assignment wildcard, spec 0111 D-8) as well as users with
     * several rows.
     */
    private const int COMPETENCE_LINE_ODDS = 20;

    /** @var Collection<int, int> */
    private Collection $companyIds;

    /** @var Collection<int, int> */
    private Collection $operationalSiteIds;

    /** @var Collection<int, array{product_category_id: int, business_function_id: int}> */
    private Collection $competencePairs;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260704);

        $this->companyIds = Company::query()->pluck('id');
        $this->operationalSiteIds = OperationalSite::query()->pluck('id');
        $this->competencePairs = $this->coherentClassificationPairs(ProductCategory::query()->orderBy('id')->get());

        $users = $this->seededUsersQuery()->orderBy('email')->get();

        if ($users->count() < self::MANAGER_COUNT + 1) {
            // Not enough seeded users to form a hierarchy (e.g. a partial/test
            // seed run) — skip rather than seed a degenerate one.
            return;
        }

        $managers = $users->take(self::MANAGER_COUNT);
        $subordinates = $users->slice(self::MANAGER_COUNT)->values();

        $managers->each(fn (User $manager) => $this->seedManager($faker, $manager));
        $subordinates->each(fn (User $user, int $index) => $this->seedSubordinate($faker, $user, $managers, $index));
    }

    private function seedManager(Generator $faker, User $manager): void
    {
        $coversAll = $this->isCompetenceWildcardUser($manager);

        $employment = $manager->employment()->updateOrCreate([], [
            'is_manager' => true,
            'covers_all_product_categories' => $coversAll,
            'relationship_type' => RelationshipTypeEnum::Employee->value,
            'qualification_type' => QualificationTypeEnum::Coordinator->value,
            'hired_at' => $faker->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            'company_id' => $this->maybePick($faker, $this->companyIds),
        ]);

        // D-5: a Responsible reports to no one — sync([]) on every run keeps
        // this true even if a manager was previously demoted from/promoted
        // to the role across re-seeds.
        $employment->reportsTo()->sync([]);
        $this->assignOperationalSites($faker, $employment);
        $this->assignProductLines($faker, $employment, coversAll: $coversAll);
    }

    /**
     * @param  Collection<int, User>  $managers
     */
    private function seedSubordinate(Generator $faker, User $user, Collection $managers, int $index): void
    {
        /** @var User $manager */
        $manager = $managers[$index % $managers->count()];
        $coversAll = $this->isCompetenceWildcardUser($user);

        $employment = $user->employment()->updateOrCreate([], [
            'is_manager' => false,
            'covers_all_product_categories' => $coversAll,
            'relationship_type' => $faker->randomElement(RelationshipTypeEnum::values()),
            'qualification_type' => $faker->randomElement(QualificationTypeEnum::values()),
            'hired_at' => $faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            'company_id' => $this->maybePick($faker, $this->companyIds),
        ]);

        // Spec 0166 D-2: reports to its round-robin manager; the FIRST
        // subordinate reports to EVERY seeded manager, demoing that a user
        // may report to more than one. sync() on every run, so a re-seed
        // recomputes the identical set instead of stacking rows.
        $managerIds = $index === 0 ? $managers->pluck('id')->all() : [$manager->id];
        $employment->reportsTo()->sync($managerIds);

        $this->assignOperationalSites($faker, $employment);
        // Spec 0129 D-3: the FIRST subordinate also demonstrates a
        // "every category of this function" row, on top of its random ones —
        // skipped for the wildcard user above, whose rows stay empty (D-2).
        $this->assignProductLines($faker, $employment, coversAll: $coversAll, allCategoriesRow: $index === 0 && ! $coversAll);
    }

    /**
     * The one deterministic demo account (spec 0129): the sole profile
     * carrying the wildcard flag, so re-seeding always produces at least one
     * (AC-019) regardless of where `demo@app.com` lands in the manager/
     * subordinate split.
     */
    private function isCompetenceWildcardUser(User $user): bool
    {
        return $user->email === self::DEMO_EMAIL;
    }

    /**
     * Site membership (spec 0103): at most one PHYSICAL pick, same 75% odds
     * as the other contractual FKs, plus a handful of REMOTE ones (25% odds
     * each, excluding the physical) so the demo data exercises the multi-site
     * case, not just the single-site one. `sync()` on every run, keyed off
     * the reseeded Faker sequence, so a re-run recomputes the identical set
     * instead of stacking rows (idempotent, spec 0015/0103).
     */
    private function assignOperationalSites(Generator $faker, EmploymentProfile $employment): void
    {
        if ($this->operationalSiteIds->isEmpty()) {
            return;
        }

        $physicalId = $this->maybePick($faker, $this->operationalSiteIds);

        $membership = $this->operationalSiteIds
            ->reject(fn (int $id): bool => $id === $physicalId)
            ->filter(fn (): bool => $faker->boolean(25))
            ->mapWithKeys(fn (int $id): array => [$id => ['is_primary' => false]]);

        if ($physicalId !== null) {
            $membership->put($physicalId, ['is_primary' => true]);
        }

        $employment->operationalSites()->sync($membership);
    }

    /**
     * Competence (spec 0111): N rows pairing a business function with a
     * product category, replacing the single `business_function_id` column.
     * The pairs come from coherentClassificationPairs(), so a row never
     * carries anything but the category's EFFECTIVE function — exactly what
     * the user form's validation demands. Delete-and-recreate on every run,
     * keyed off the reseeded Faker sequence, so a re-run recomputes the
     * identical set instead of stacking rows (idempotent, same rule as
     * assignOperationalSites() above).
     *
     * Spec 0129: $coversAll (D-2) leaves the profile rowless — the flag alone
     * covers everything, so there is nothing to keep in sync with it.
     * $allCategoriesRow (D-3) demoes one (function, null) row, on the FIRST
     * pair's function, with the random rows on that same function excluded
     * so the two demo states never collide on spec 0129's own redundancy
     * rule (D-4).
     */
    private function assignProductLines(Generator $faker, EmploymentProfile $employment, bool $coversAll = false, bool $allCategoriesRow = false): void
    {
        $employment->productLines()->delete();

        if ($coversAll || $this->competencePairs->isEmpty()) {
            return;
        }

        $allCategoriesFunctionId = $allCategoriesRow ? $this->competencePairs->first()['business_function_id'] : null;

        $this->competencePairs
            ->reject(fn (array $pair): bool => $pair['business_function_id'] === $allCategoriesFunctionId)
            ->filter(fn (): bool => $faker->boolean(self::COMPETENCE_LINE_ODDS))
            ->each(fn (array $line) => $employment->productLines()->create($line));

        if ($allCategoriesFunctionId !== null) {
            $employment->productLines()->create([
                'business_function_id' => $allCategoriesFunctionId,
                'product_category_id' => null,
            ]);
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function maybePick(Generator $faker, Collection $ids): ?int
    {
        return $ids->isNotEmpty() && $faker->boolean(75)
            ? $faker->randomElement($ids->all())
            : null;
    }
}
