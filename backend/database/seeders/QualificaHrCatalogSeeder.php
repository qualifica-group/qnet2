<?php

namespace Database\Seeders;

use App\DataObjects\JobActivities\JobActivityData;
use App\DataObjects\JobRoles\JobRoleData;
use App\DataObjects\PersonalData\CreateContact;
use App\Enums\ContactTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\JobActivity;
use App\Models\JobRole;
use App\Models\User;
use App\Services\ContactService;
use App\Services\EmploymentContracts\EmploymentContractService;
use App\Services\JobActivityService;
use App\Services\JobRoleService;
use Database\Seeders\QualificaCatalog\BusinessFunctionCatalogue;
use Database\Seeders\QualificaCatalog\JobRoleCatalogue;
use Database\Seeders\QualificaCatalog\OrgChartFunctions;
use Database\Seeders\QualificaCatalog\OrgChartRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The client's job activities and job roles (Mansioni, spec 0271), from the
 * "Pianta Organica": hard-coded reference data, so a step of
 * QualificaProductionDataSeeder with no `Demo` prefix.
 *
 * Create-only on the name: an activity or a role already present is left as
 * it is — description and activity links included — so an edit made from the
 * modules survives a re-run.
 *
 * Then the people of OrgChartRoster (user directive 2026-10-19) get their job
 * roles and their shared mailboxes, ADDITIVELY: a role or an email already
 * there is kept, a missing one added, nothing removed. A profile without any
 * condition gets a first one starting today. So it runs after the accounts
 * exist, and an account the roster names but the database lacks is skipped
 * with a warning.
 *
 * Last, the business functions of OrgChartFunctions, ADDITIVELY too: members
 * added, never removed; the first head becomes the manager of a function
 * that has none, so a manager set from the module survives; every head is
 * added to the "Risponde a" of the function's other members, next to the
 * superiors they already have; the heads of "Direzione Generale" are added
 * to the "Risponde a" of every other head.
 */
class QualificaHrCatalogSeeder extends Seeder
{
    private const string WORK_EMAIL_LABEL = 'Email aziendale';

    public function __construct(
        private readonly JobActivityService $activities,
        private readonly JobRoleService $roles,
        private readonly EmploymentContractService $contracts,
        private readonly ContactService $contacts,
    ) {}

    public function run(): void
    {
        // Step 1: every activity once, before the roles that link it
        $this->seedActivities();
        // Step 2: the roles, each linked to its activities
        $this->seedRoles(JobActivity::query()->pluck('id', 'name')->all());
        // Step 3: each listed person's roles, first condition and mailboxes
        $this->assignPeople();
        // Step 4: each function's members and manager, its heads as the members' superiors
        $this->assignFunctions();
    }

    private function assignPeople(): void
    {
        $roleIds = JobRole::query()->pluck('id', 'name')->all();
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
            $this->ensureCondition($employment, in_array($email, OrgChartRoster::EXTERNAL, true));
            $this->addWorkEmails($user, $person['emails']);
            $assigned++;
        }

        $this->command?->info(sprintf('%d people assigned from the org chart.', $assigned));
    }

    private function assignFunctions(): void
    {
        $emails = collect(OrgChartFunctions::FUNCTIONS)->flatMap(fn (array $function): array => [...$function['heads'], ...$function['members']]);
        $userIds = User::query()->whereIn('email', $emails->unique())->pluck('id', 'email');
        $superiorIds = [];
        $allHeadIds = [];

        foreach (OrgChartFunctions::FUNCTIONS as $name => $function) {
            $businessFunction = $this->functionNamed($name);

            if ($businessFunction === null) {
                $this->command?->warn(sprintf('Business function "%s" missing, org chart skipped.', $name));

                continue;
            }

            $headIds = $this->userIdsOf($userIds, $function['heads']);
            $memberIds = [...$headIds, ...$this->userIdsOf($userIds, $function['members'])];
            $businessFunction->users()->syncWithoutDetaching($memberIds);

            if ($businessFunction->manager_id === null && $headIds !== []) {
                // A per-model update, so the activity log records the manager.
                $businessFunction->update(['manager_id' => $headIds[0]]);
            }

            foreach ($memberIds as $memberId) {
                $superiorIds[$memberId] = [...$superiorIds[$memberId] ?? [], ...array_diff($headIds, [$memberId])];
            }

            $allHeadIds = [...$allHeadIds, ...$headIds];
        }

        $directorIds = $this->userIdsOf($userIds, OrgChartFunctions::FUNCTIONS[BusinessFunctionCatalogue::GENERAL_MANAGEMENT]['heads']);

        foreach (array_unique($allHeadIds) as $headId) {
            $superiorIds[$headId] = [...$superiorIds[$headId] ?? [], ...array_diff($directorIds, [$headId])];
        }

        $this->addSuperiors(array_filter($superiorIds));
    }

    /**
     * @param  Collection<string, int>  $userIds  email => id
     * @param  list<string>  $emails
     * @return list<int>
     */
    private function userIdsOf(Collection $userIds, array $emails): array
    {
        $ids = [];

        foreach ($emails as $email) {
            if (! $userIds->has($email)) {
                $this->command?->warn(sprintf('%s: no account, business function skipped.', $email));

                continue;
            }

            $ids[] = $userIds->get($email);
        }

        return $ids;
    }

    /**
     * @param  array<int, list<int>>  $superiorIds  user id => superior ids
     */
    private function addSuperiors(array $superiorIds): void
    {
        foreach (User::query()->whereIn('id', array_keys($superiorIds))->with('employment')->get() as $user) {
            // A staff account may have no profile yet: the pivot hangs off one.
            $employment = $user->employment ?? $user->employment()->create();
            $employment->reportsTo()->syncWithoutDetaching(array_unique($superiorIds[$user->id]));
        }
    }

    /**
     * `name` is not unique: the lowest id wins, compared without case, as in
     * QualificaBusinessFunctionSeeder.
     */
    private function functionNamed(string $name): ?BusinessFunction
    {
        return BusinessFunction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->orderBy('id')
            ->first();
    }

    private function ensureCondition(EmploymentProfile $employment, bool $isExternal): void
    {
        if ($employment->contracts()->exists()) {
            return;
        }

        $this->contracts->create($employment, [
            'starts_on' => today()->toDateString(),
            'relationship_type' => $isExternal ? RelationshipTypeEnum::Other : RelationshipTypeEnum::Employee,
        ]);
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
