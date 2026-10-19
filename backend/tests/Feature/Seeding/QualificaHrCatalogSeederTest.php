<?php

use App\Http\Requests\JobActivities\StoreJobActivityRequest;
use App\Http\Requests\JobRoles\StoreJobRoleRequest;
use App\Models\CollectiveAgreement;
use App\Models\JobActivity;
use App\Models\JobRole;
use Database\Seeders\QualificaCatalog\CollectiveAgreementCatalogue;
use Database\Seeders\QualificaCatalog\JobRoleCatalogue;
use Database\Seeders\QualificaHrCatalogSeeder;
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
