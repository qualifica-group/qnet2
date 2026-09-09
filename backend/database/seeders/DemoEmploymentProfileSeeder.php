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
 * users become managers (is_manager=true, no reports_to), every other seeded
 * user is a subordinate reporting to one of them, round-robin. Idempotent:
 * the profile is upserted by owner, its site membership and its competence
 * rows recomputed from the same reseeded Faker sequence.
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
        $employment = $manager->employment()->updateOrCreate([], [
            'is_manager' => true,
            'reports_to_id' => null,
            'relationship_type' => RelationshipTypeEnum::Employee->value,
            'qualification_type' => QualificationTypeEnum::Coordinator->value,
            'hired_at' => $faker->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            'company_id' => $this->maybePick($faker, $this->companyIds),
        ]);

        $this->assignOperationalSites($faker, $employment);
        $this->assignProductLines($faker, $employment);
    }

    /**
     * @param  Collection<int, User>  $managers
     */
    private function seedSubordinate(Generator $faker, User $user, Collection $managers, int $index): void
    {
        /** @var User $manager */
        $manager = $managers[$index % $managers->count()];

        $employment = $user->employment()->updateOrCreate([], [
            'is_manager' => false,
            'reports_to_id' => $manager->id,
            'relationship_type' => $faker->randomElement(RelationshipTypeEnum::values()),
            'qualification_type' => $faker->randomElement(QualificationTypeEnum::values()),
            'hired_at' => $faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            'company_id' => $this->maybePick($faker, $this->companyIds),
        ]);

        $this->assignOperationalSites($faker, $employment);
        $this->assignProductLines($faker, $employment);
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
     */
    private function assignProductLines(Generator $faker, EmploymentProfile $employment): void
    {
        if ($this->competencePairs->isEmpty()) {
            return;
        }

        $lines = $this->competencePairs->filter(fn (): bool => $faker->boolean(self::COMPETENCE_LINE_ODDS));

        $employment->productLines()->delete();

        $lines->each(fn (array $line) => $employment->productLines()->create($line));
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
