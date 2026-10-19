<?php

namespace Database\Seeders;

use App\DataObjects\CollectiveAgreements\CollectiveAgreementData;
use App\DataObjects\JobActivities\JobActivityData;
use App\DataObjects\JobRoles\JobRoleData;
use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\CollectiveAgreement;
use App\Models\EmploymentProfile;
use App\Models\JobActivity;
use App\Models\JobRole;
use App\Models\User;
use App\Services\CollectiveAgreementService;
use App\Services\ContactService;
use App\Services\EmploymentContracts\EmploymentContractService;
use App\Services\JobActivityService;
use App\Services\JobRoleService;
use Database\Seeders\QualificaCatalog\CollectiveAgreementCatalogue;
use Database\Seeders\QualificaCatalog\JobRoleCatalogue;
use Database\Seeders\QualificaCatalog\OrgChartRoster;
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
 *
 * Then the people of OrgChartRoster (user directive 2026-10-19) get their job
 * roles, their level and their shared mailboxes, ADDITIVELY: a role or an
 * email already there is kept, a missing one added, nothing removed. The level
 * lands on the condition in force when it has none; a profile without any
 * condition gets a first one starting today. So it runs after the accounts
 * exist, and an account the roster names but the database lacks is skipped
 * with a warning.
 */
class QualificaHrCatalogSeeder extends Seeder
{
    private const string WORK_EMAIL_LABEL = 'Email aziendale';

    public function __construct(
        private readonly JobActivityService $activities,
        private readonly JobRoleService $roles,
        private readonly CollectiveAgreementService $agreements,
        private readonly EmploymentContractService $contracts,
        private readonly ContactService $contacts,
    ) {}

    public function run(): void
    {
        // Step 1: every activity once, before the roles that link it
        $this->seedActivities();
        // Step 2: the roles, each linked to its activities
        $this->seedRoles(JobActivity::query()->pluck('id', 'name')->all());
        // Step 3: the sheet's levels, as a CCNL
        $this->seedCollectiveAgreement();
        // Step 4: each listed person's roles, level and mailboxes
        $this->assignPeople();
    }

    private function assignPeople(): void
    {
        $roleIds = JobRole::query()->pluck('id', 'name')->all();
        $levelIds = CollectiveAgreement::query()->where('name', CollectiveAgreementCatalogue::NAME)->sole()
            ->levels()->pluck('id', 'code')->all();
        $users = User::query()->whereIn('email', array_keys(OrgChartRoster::PEOPLE))
            ->with(['employment.jobRoles', 'personalData.contacts'])->get()->keyBy('email');
        $assigned = 0;

        foreach (OrgChartRoster::PEOPLE as $email => $person) {
            $user = $users->get($email);

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, org chart skipped.', $email));

                continue;
            }

            // A staff account may have no profile yet: roles and conditions hang off one.
            $employment = $user->employment ?? $user->employment()->create();
            $employment->jobRoles()->syncWithoutDetaching(array_map(fn (string $role): int => $roleIds[$role], $person['roles']));
            $this->assignLevel($employment, $levelIds[$person['level']], in_array($email, OrgChartRoster::EXTERNAL, true));
            $this->addWorkEmails($user, $person['emails']);
            $assigned++;
        }

        $this->command?->info(sprintf('%d people assigned from the org chart.', $assigned));
    }

    private function assignLevel(EmploymentProfile $employment, int $levelId, bool $isExternal): void
    {
        if (! $employment->contracts()->exists()) {
            $this->contracts->create($employment, [
                'starts_on' => today()->toDateString(),
                'relationship_type' => $isExternal ? RelationshipTypeEnum::Other : RelationshipTypeEnum::Employee,
                'collective_agreement_level_id' => $levelId,
            ]);

            return;
        }

        $current = $employment->contracts()->whereDate('starts_on', '<=', today()->toDateString())->first();

        if ($current !== null && $current->collective_agreement_level_id === null) {
            $current->update(['collective_agreement_level_id' => $levelId]);
        }
    }

    /**
     * @param  list<string>  $emails
     */
    private function addWorkEmails(User $user, array $emails): void
    {
        $card = $user->personalData;

        if ($card === null) {
            $this->command?->warn(sprintf('%s: no personal data, mailboxes skipped.', $user->email));

            return;
        }

        $existing = $card->contacts->where('type', ContactTypeEnum::Email)->pluck('normalized_value')->all();

        foreach (array_diff($emails, $existing) as $email) {
            $this->contacts->createFor($card, new CreateContact(ContactTypeEnum::Email, $email, self::WORK_EMAIL_LABEL, isWork: true));
        }
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
