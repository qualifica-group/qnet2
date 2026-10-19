<?php

use App\Enums\ContactTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Http\Requests\JobActivities\StoreJobActivityRequest;
use App\Http\Requests\JobRoles\StoreJobRoleRequest;
use App\Models\CollectiveAgreement;
use App\Models\EmploymentContract;
use App\Models\JobActivity;
use App\Models\JobRole;
use App\Models\PersonalData;
use App\Models\User;
use Database\Seeders\QualificaCatalog\CollectiveAgreementCatalogue;
use Database\Seeders\QualificaCatalog\JobRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\OrgChartRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaHrCatalogSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// The client's "Pianta Organica" as Mansioni and Attivita' (spec 0271),
// merged per the user directive 2026-10-19: no site in a role name, a "+"
// role split in two, near-identical activities written once.

it('keeps the catalogue within the module limits, with one spelling and one description per activity', function () {
    $activities = JobRoleCatalogue::activities();
    $normalized = array_map(fn (string $name): string => Str::lower(Str::squish($name)), array_keys($activities));
    $descriptions = [];

    foreach (JobRoleCatalogue::ROLES as $role) {
        foreach ($role['activities'] as $key => $value) {
            if (is_string($key)) {
                $descriptions[$key][] = $value;
            }
        }
    }

    expect(array_unique($normalized))->toHaveCount(count($normalized))
        ->and(array_filter($descriptions, fn (array $texts): bool => count(array_unique($texts)) > 1))->toBe([])
        ->and(array_filter(array_keys($activities), fn (string $name): bool => mb_strlen($name) > StoreJobActivityRequest::NAME_MAX))->toBe([])
        ->and(array_filter($activities, fn (?string $text): bool => mb_strlen((string) $text) > StoreJobActivityRequest::DESCRIPTION_MAX))->toBe([])
        ->and(array_filter(array_keys(JobRoleCatalogue::ROLES), fn (string $name): bool => str_contains($name, '+')))->toBe([])
        ->and(array_filter(JobRoleCatalogue::ROLES, fn (array $role): bool => mb_strlen($role['description']) > StoreJobRoleRequest::DESCRIPTION_MAX))->toBe([]);
});

it('seeds every role linked to its activities, each shared activity created once', function () {
    $this->seed(QualificaHrCatalogSeeder::class);

    $operator = JobRole::query()->where('name', 'Operatore Politiche Attive')->with('jobActivities')->sole();
    $collection = JobActivity::query()->where('name', 'Monitoraggio degli incassi, solleciti e recupero crediti')->with('jobRoles')->sole();

    expect(JobRole::query()->count())->toBe(67)
        ->and(JobActivity::query()->count())->toBe(365)
        ->and(JobActivity::query()->doesntHave('jobRoles')->exists())->toBeFalse()
        ->and($operator->description)->toStartWith('Accompagna e supporta persone')
        ->and($operator->jobActivities->pluck('name')->all())->toContain('Accoglienza degli utenti', 'Supporto alle attività del settore Formazione')
        ->and($operator->jobActivities)->toHaveCount(8)
        ->and($collection->description)->toStartWith('Monitoraggio di incassi e scadenze')
        ->and($collection->jobRoles->pluck('name')->all())->toEqualCanonicalizing([
            'Addetto Recupero Crediti e Fatturazione',
            'Consulente Commerciale Senior',
            'Consulente Commerciale Junior',
            'Consulente Commerciale',
        ]);

    foreach (JobRoleCatalogue::ROLES as $name => $role) {
        expect(JobRole::query()->where('name', $name)->sole()->jobActivities()->pluck('name')->all())
            ->toEqualCanonicalizing(JobRoleCatalogue::activityNames($role['activities']));
    }
});

it('seeds the sheet levels as a CCNL, in level order with the legend as description', function () {
    $this->seed(QualificaHrCatalogSeeder::class);

    $agreement = CollectiveAgreement::query()->where('name', CollectiveAgreementCatalogue::NAME)->sole();

    expect($agreement->levels()->orderBy('sort_order')->get(['code', 'description'])->toArray())->toBe([
        ['code' => 'Livello I', 'description' => 'Responsabile di settore o unità'],
        ['code' => 'Livello II', 'description' => 'Coordinatore, supervisor o referente'],
        ['code' => 'Livello III', 'description' => 'Risorsa operativa senior'],
        ['code' => 'Livello IV', 'description' => 'Risorsa operativa junior'],
    ]);
});

it('a re-run duplicates nothing and keeps what was edited from the modules', function () {
    $this->seed(QualificaHrCatalogSeeder::class);
    $role = JobRole::query()->where('name', 'Tecnico Gare')->sole();
    $role->update(['description' => 'Edited']);
    $role->jobActivities()->sync([]);
    JobActivity::query()->where('name', 'Sopralluoghi in cantiere')->update(['description' => 'Edited']);
    $agreement = CollectiveAgreement::query()->where('name', CollectiveAgreementCatalogue::NAME)->sole();
    $agreement->levels()->where('code', 'Livello IV')->update(['description' => 'Edited']);
    $roles = JobRole::query()->count();
    $activities = JobActivity::query()->count();

    $this->seed(QualificaHrCatalogSeeder::class);

    expect(JobRole::query()->count())->toBe($roles)
        ->and(JobActivity::query()->count())->toBe($activities)
        ->and($role->fresh()->description)->toBe('Edited')
        ->and($role->jobActivities()->count())->toBe(0)
        ->and(JobActivity::query()->where('name', 'Sopralluoghi in cantiere')->value('description'))->toBe('Edited')
        ->and(CollectiveAgreement::query()->count())->toBe(1)
        ->and($agreement->levels()->count())->toBe(4)
        ->and($agreement->levels()->where('code', 'Livello IV')->value('description'))->toBe('Edited');
});

// Who holds which role and level (user directive 2026-10-19): the employee
// list and the "Pianta Organica", assigned additively to existing accounts.

function rosterPerson(string $email): User
{
    $user = User::factory()->withEmployment()->create(['email' => $email]);
    PersonalData::factory()->individual()->for($user, 'personable')->create();

    return $user;
}

function rosterLevelId(string $code): int
{
    return CollectiveAgreement::query()->where('name', CollectiveAgreementCatalogue::NAME)->sole()
        ->levels()->where('code', $code)->value('id');
}

it('names only seeded accounts, catalogue roles and CCNL levels, never the account email as a mailbox', function () {
    $known = collect(OperatorRoster::OPERATORS)->pluck(2)
        ->merge(collect(StaffRoster::USERS)->pluck(2))
        ->merge(collect(TestUsersSeeder::TEST_USERS)->pluck('email'));

    expect(array_diff(array_keys(OrgChartRoster::PEOPLE), $known->all()))->toBe([])
        ->and(array_diff(OrgChartRoster::EXTERNAL, array_keys(OrgChartRoster::PEOPLE)))->toBe([]);

    foreach (OrgChartRoster::PEOPLE as $email => $person) {
        expect($person['roles'])->not->toBeEmpty()
            ->and(array_diff($person['roles'], array_keys(JobRoleCatalogue::ROLES)))->toBe([])
            ->and(CollectiveAgreementCatalogue::LEVELS)->toHaveKey($person['level'])
            ->and($person['emails'])->not->toContain($email);

        foreach ($person['emails'] as $mailbox) {
            expect(filter_var($mailbox, FILTER_VALIDATE_EMAIL))->toBe($mailbox)
                ->and($mailbox)->toBe(mb_strtolower($mailbox));
        }
    }
});

it('assigns roles, a first condition with the level and the shared mailboxes', function () {
    $user = rosterPerson('anna.castaldo@qualificagroup.com');
    $external = rosterPerson('raffaele.centore@qualificagroup.it');

    $this->seed(QualificaHrCatalogSeeder::class);

    $contract = $user->employment->contracts()->sole();
    $mailboxes = $user->personalData->contacts()->where('type', ContactTypeEnum::Email)->get();

    expect($user->employment->jobRoles()->pluck('name')->all())->toBe(['HR Manager / Responsabile Settore HR'])
        ->and($contract->starts_on->toDateString())->toBe(today()->toDateString())
        ->and($contract->relationship_type)->toBe(RelationshipTypeEnum::Employee)
        ->and($contract->collective_agreement_level_id)->toBe(rosterLevelId('Livello I'))
        ->and($user->employment->fresh()->relationship_type)->toBe(RelationshipTypeEnum::Employee)
        ->and($mailboxes->pluck('value')->all())->toEqualCanonicalizing(['a.castaldo@qualificagroup.it', 'personale@qualificagroup.it', 'cv@qualificagroup.it'])
        ->and($mailboxes->every(fn ($contact): bool => $contact->is_work && ! $contact->is_primary && $contact->label === 'Email aziendale'))->toBeTrue()
        ->and($external->employment->contracts()->sole()->relationship_type)->toBe(RelationshipTypeEnum::Other);
});

it('fills the level of the condition in force only when empty, and adds without removing', function () {
    $user = rosterPerson('giuseppe.agus@qualificagroup.com');
    $other = rosterPerson('anna.castaldo@qualificagroup.com');
    $this->seed(QualificaHrCatalogSeeder::class);
    $manualRole = JobRole::query()->where('name', 'Tecnico Gare')->sole();
    $user->employment->jobRoles()->attach($manualRole);
    $user->employment->contracts()->delete();
    $empty = EmploymentContract::factory()->for($user->employment, 'profile')->create(['starts_on' => '2024-01-01']);
    $other->employment->contracts()->sole()->update(['collective_agreement_level_id' => rosterLevelId('Livello IV')]);
    $user->personalData->contacts()->where('value', 'altaformazione@qualificagroup.it')->update(['value' => 'ALTAFORMAZIONE@qualificagroup.it']);

    $this->seed(QualificaHrCatalogSeeder::class);

    expect($user->employment->contracts()->sole()->id)->toBe($empty->id)
        ->and($empty->fresh()->collective_agreement_level_id)->toBe(rosterLevelId('Livello II'))
        ->and($other->employment->contracts()->sole()->collective_agreement_level_id)->toBe(rosterLevelId('Livello IV'))
        ->and($user->employment->jobRoles()->pluck('name')->all())->toEqualCanonicalizing(['Referente Normativo', 'Addetto Progettazione', 'Tecnico Gare'])
        ->and($user->personalData->contacts()->count())->toBe(count(OrgChartRoster::PEOPLE['giuseppe.agus@qualificagroup.com']['emails']))
        ->and($other->personalData->contacts()->count())->toBe(3);
});

it('skips a listed person without an account', function () {
    $this->seed(QualificaHrCatalogSeeder::class);

    expect(EmploymentContract::query()->exists())->toBeFalse();
});
