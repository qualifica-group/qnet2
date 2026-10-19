<?php

namespace Database\Seeders;

use App\DataObjects\CollectiveAgreements\CollectiveAgreementData;
use App\DataObjects\JobActivities\JobActivityData;
use App\DataObjects\JobRoles\JobRoleData;
use App\Models\CollectiveAgreement;
use App\Models\JobActivity;
use App\Models\JobRole;
use App\Services\CollectiveAgreementService;
use App\Services\JobActivityService;
use App\Services\JobRoleService;
use Database\Seeders\QualificaCatalog\CollectiveAgreementCatalogue;
use Database\Seeders\QualificaCatalog\JobRoleCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's job activities and job roles (Mansioni, spec 0271) and its
 * levels as a CCNL (spec 0272), from the "Pianta Organica": hard-coded
 * reference data, so a step of QualificaProductionDataSeeder with no `Demo`
 * prefix.
 *
 * Create-only on the name: an activity, a role or the CCNL already present is
 * left as it is — description, activity links and levels included — so an
 * edit made from the modules survives a re-run.
 */
class QualificaHrCatalogSeeder extends Seeder
{
    public function __construct(
        private readonly JobActivityService $activities,
        private readonly JobRoleService $roles,
        private readonly CollectiveAgreementService $agreements,
    ) {}

    public function run(): void
    {
        // Step 1: every activity once, before the roles that link it
        $this->seedActivities();
        // Step 2: the roles, each linked to its activities
        $this->seedRoles(JobActivity::query()->pluck('id', 'name')->all());
        // Step 3: the sheet's levels, as a CCNL
        $this->seedCollectiveAgreement();
    }

    private function seedCollectiveAgreement(): void
    {
        if (CollectiveAgreement::query()->where('name', CollectiveAgreementCatalogue::NAME)->exists()) {
            return;
        }

        $this->agreements->create(CollectiveAgreementData::fromValidated([
            'name' => CollectiveAgreementCatalogue::NAME,
            'levels' => array_map(
                fn (string $code, string $description): array => ['code' => $code, 'description' => $description],
                array_keys(CollectiveAgreementCatalogue::LEVELS),
                CollectiveAgreementCatalogue::LEVELS,
            ),
        ]));
    }

    private function seedActivities(): void
    {
        $existing = JobActivity::query()->pluck('name')->all();

        foreach (JobRoleCatalogue::activities() as $name => $description) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->activities->create(JobActivityData::fromValidated(['name' => $name, 'description' => $description]));
        }
    }

    /**
     * @param  array<string, int>  $activityIds  name => id
     */
    private function seedRoles(array $activityIds): void
    {
        $existing = JobRole::query()->pluck('name')->all();

        foreach (JobRoleCatalogue::ROLES as $name => $role) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->roles->create(JobRoleData::fromValidated([
                'name' => $name,
                'description' => $role['description'],
                'job_activity_ids' => array_map(
                    fn (string $activity): int => $activityIds[$activity],
                    JobRoleCatalogue::activityNames($role['activities']),
                ),
            ]));
        }
    }
}
