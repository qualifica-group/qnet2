<?php

namespace Database\Seeders;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\Models\Campaign;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Referent;
use App\Models\State;
use App\Services\CampaignService;
use Database\Seeders\Concerns\ResolvesCategoryBusinessFunction;
use Database\Seeders\Concerns\SeedsItalianGeo;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the campaigns module (spec 0023), covering both
 * shapes: LINKED (project_id set, the 4 classification columns forced NULL —
 * BR-2, read-through from the project) and STANDALONE (project_id null, the 4
 * columns are the campaign's own and required).
 *
 * Every campaign is created through CampaignService::create() — the same
 * path POST /api/campaigns uses — so the BR-1 sequential `code`
 * (CMP-0001...) and the BR-2 null-forcing on the derived columns come from
 * the real mechanism. Linked campaign budgets are computed as a fraction of
 * each project's OWN remaining budget (never the full amount), so BR-3
 * (`SUM(campaigns.total_budget) <= project.total_budget`) holds with margin
 * by construction — CampaignService::create() still runs the real guard, so
 * any seeding mistake here fails loudly rather than silently violating it.
 *
 * Geo (spec 0027, BR-5): `SeedsItalianGeo::refineGeo()` fills a linked
 * campaign's geo with ONLY the levels its project leaves empty (never one
 * the project already fills — that stays absent, exactly what BR-5 requires),
 * alternating deterministically with "no refinement at all" so both outcomes
 * are visible in the demo data. Standalone campaigns get their own full,
 * BR-4-coherent tuple via `geoTuple()`, cycling the same four scope depths as
 * DemoProjectSeeder.
 *
 * Depends on DemoProjectSeeder (linked campaigns) plus the same
 * classification lookups it uses (standalone campaigns need their own
 * pipeline-status/business-function/state/product-category). Idempotent:
 * existing campaigns are cleared first — harmless if DemoProjectSeeder
 * already did it, and keeps this seeder runnable on its own too.
 */
class DemoCampaignSeeder extends Seeder
{
    use ResolvesCategoryBusinessFunction;
    use SeedsItalianGeo;

    private const int STANDALONE_CAMPAIGNS = 8;

    public function __construct(private readonly CampaignService $campaigns) {}

    public function run(): void
    {
        // Idempotent even if run standalone (DemoProjectSeeder already clears
        // campaigns before recreating its projects).
        Campaign::query()->delete();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260714);

        $projects = Project::query()->orderBy('id')->get();
        $statuses = PipelineStatus::query()->orderBy('sort_order')->get();
        $productCategories = ProductCategory::query()->orderBy('name')->get();
        $states = $this->italianStates();
        $partners = Referent::query()->orderBy('name')->get();

        // The business function is DERIVED from each category's effective one
        // (spec 0023 REV), so every standalone campaign carries a coherent pair.
        $classificationPairs = $this->coherentClassificationPairs($productCategories);

        $this->seedLinkedCampaigns($faker, $projects, $partners);

        if ($statuses->isEmpty() || $states->isEmpty() || $classificationPairs->isEmpty()) {
            // Nothing sensible to seed for the standalone shape without all
            // required classification lookups (BR-2) plus a coherent
            // category/function pair.
            return;
        }

        $this->seedStandaloneCampaigns($faker, $statuses, $states, $classificationPairs, $partners);
    }

    /**
     * One or two campaigns per project, budgeted as a fraction of that
     * project's OWN remaining budget so BR-3 holds with margin. Every 4th
     * project deliberately gets none (exercises BR-5's "no campaigns" delete
     * path). partner defaults to the project's own but is
     * occasionally varied (D-2: "copiati dal progetto ma
     * eventualmente variati").
     *
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Referent>  $partners
     */
    private function seedLinkedCampaigns(Generator $faker, Collection $projects, Collection $partners): void
    {
        foreach ($projects as $index => $project) {
            $campaignCount = $this->linkedCampaignCount($index);

            $remainingBudget = $project->total_budget !== null ? (float) $project->total_budget : null;

            for ($slot = 0; $slot < $campaignCount; $slot++) {
                $isLastSlot = $slot === $campaignCount - 1;
                $budget = $this->nextLinkedBudget($faker, $remainingBudget, $isLastSlot);

                if ($remainingBudget !== null && $budget !== null) {
                    $remainingBudget -= $budget;
                }

                $partnerId = $project->partner_id;

                if ($faker->boolean(30) && $partners->isNotEmpty()) {
                    $partnerId = $partners[($index + $slot + 1) % $partners->count()]->id;
                }

                $geo = $this->refineGeo($project, $index * 10 + $slot);

                $this->createCampaign($faker, [
                    'project_id' => $project->id,
                    'name' => $faker->unique()->bs(),
                    'partner_id' => $partnerId,
                    'total_budget' => $budget,
                    'country_id' => $geo['country_id'] ?? null,
                    'state_id' => $geo['state_id'] ?? null,
                    'province_id' => $geo['province_id'] ?? null,
                    'city_id' => $geo['city_id'] ?? null,
                ]);
            }
        }
    }

    /**
     * 0 campaigns on every 4th project (BR-5 no-campaigns case), 2 on every
     * 3rd of the rest, 1 otherwise.
     */
    private function linkedCampaignCount(int $index): int
    {
        if ($index % 4 === 0) {
            return 0;
        }

        return $index % 3 === 0 ? 2 : 1;
    }

    /**
     * Without a budget cap (A-1), any amount is valid. With a cap, take 60%
     * of what remains on the last slot and 40% on any earlier one — always
     * strictly less than the remainder, so BR-3 holds with margin.
     */
    private function nextLinkedBudget(Generator $faker, ?float $remainingBudget, bool $isLastSlot): ?float
    {
        if ($remainingBudget === null) {
            return $faker->boolean(70) ? $faker->randomFloat(2, 1000, 40000) : null;
        }

        $share = $isLastSlot ? 0.6 : 0.4;

        return round($remainingBudget * $share, 2);
    }

    /**
     * Campaigns with no project (project_id null): `pipeline_status_id` and
     * `product_lines` (spec 0094, D-1/D-2 — REPLACING the former
     * `business_function_id`/`product_category_id` scalars, one coherent row)
     * are the campaign's own and required (BR-2), drawn round-robin across
     * the same lookups DemoProjectSeeder uses.
     *
     * @param  Collection<int, PipelineStatus>  $statuses
     * @param  Collection<int, State>  $states
     * @param  Collection<int, array{product_category_id: int, business_function_id: int}>  $classificationPairs
     * @param  Collection<int, Referent>  $partners
     */
    private function seedStandaloneCampaigns(
        Generator $faker,
        Collection $statuses,
        Collection $states,
        Collection $classificationPairs,
        Collection $partners,
    ): void {
        for ($index = 0; $index < self::STANDALONE_CAMPAIGNS; $index++) {
            $geo = $this->geoTuple($states, $index);
            $pair = $classificationPairs[$index % $classificationPairs->count()];

            $this->createCampaign($faker, [
                'project_id' => null,
                'name' => $faker->unique()->bs(),
                'partner_id' => $this->pick($partners, $index)?->id,
                'pipeline_status_id' => $statuses[$index % $statuses->count()]->id,
                'product_lines' => [$pair],
                'country_id' => $geo['country_id'],
                'state_id' => $geo['state_id'],
                'province_id' => $geo['province_id'],
                'city_id' => $geo['city_id'],
                'total_budget' => $faker->boolean(75) ? $faker->randomFloat(2, 500, 60000) : null,
            ]);
        }
    }

    /**
     * Build the DTO from the given overrides plus shared defaults (dates,
     * target_lead) and create the campaign through the real Service — the
     * derived fields absent from $overrides for a linked campaign are simply
     * left null, since CreateCampaignData::attributes() forces
     * `pipeline_status_id` null regardless (BR-2), and CampaignService never
     * syncs `product_lines` when it is null (spec 0094).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCampaign(Generator $faker, array $overrides): void
    {
        $startDate = $faker->dateTimeBetween('-12 months', '+1 month');
        $endDate = $faker->boolean(65)
            ? (clone $startDate)->modify('+'.$faker->numberBetween(1, 10).' months')
            : null;

        $data = new CreateCampaignData(
            code: null,
            projectId: $overrides['project_id'] ?? null,
            name: $overrides['name'],
            description: $faker->boolean(50) ? $faker->sentence() : null,
            partnerId: $overrides['partner_id'] ?? null,
            operationalSiteId: $overrides['operational_site_id'] ?? null,
            pipelineStatusId: $overrides['pipeline_status_id'] ?? null,
            productLines: $overrides['product_lines'] ?? null,
            stateId: $overrides['state_id'] ?? null,
            startDate: $startDate->format('Y-m-d'),
            endDate: $endDate?->format('Y-m-d'),
            totalBudget: $overrides['total_budget'] ?? null,
            targetLead: $faker->boolean(75) ? $faker->numberBetween(1, 150) : null,
            countryId: $overrides['country_id'] ?? null,
            provinceId: $overrides['province_id'] ?? null,
            cityId: $overrides['city_id'] ?? null,
        );

        $this->campaigns->create($data);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function pick(Collection $items, int $index): mixed
    {
        return $items->isNotEmpty() ? $items[$index % $items->count()] : null;
    }
}
