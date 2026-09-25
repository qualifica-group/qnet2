<?php

use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\ReportsToRoster;
use Database\Seeders\QualificaCatalog\StaffRoster;
use Database\Seeders\QualificaOperatorSeeder;
use Database\Seeders\QualificaReportsToSeeder;
use Database\Seeders\QualificaStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Who each operator reports to (user directive 2026-09-25): the mansionario's
// supervision sheet, synced onto the spec 0166 pivot.
uses(RefreshDatabase::class);

function managerEmailsOf(string $email): array
{
    return User::query()->where('email', $email)->with('employment.reportsTo')->sole()
        ->employment->reportsTo->pluck('email')->sort()->values()->all();
}

function seedAccountsAndReportsTo(): void
{
    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaStaffSeeder::class);
    test()->seed(QualificaReportsToSeeder::class);
}

it('names only accounts the operator or staff roster seeds, and never the operator itself', function (): void {
    $known = collect(OperatorRoster::OPERATORS)->pluck(2)->merge(collect(StaffRoster::USERS)->pluck(2));

    foreach (ReportsToRoster::MANAGERS as $email => $managers) {
        expect($known)->toContain($email)
            ->and($managers)->not->toContain($email);

        foreach ($managers as $manager) {
            expect($known)->toContain($manager);
        }
    }

    // Every operator of the mansionario has its supervision row.
    expect(collect(OperatorRoster::OPERATORS)->pluck(2)->diff(array_keys(ReportsToRoster::MANAGERS))->all())->toBe([]);
});

it('syncs every listed account onto its managers', function (): void {
    seedAccountsAndReportsTo();

    expect(managerEmailsOf('michela.fabozzi@qualificagroup.com'))->toBe([])
        ->and(managerEmailsOf('rosa.falzarano@qualificagroup.com'))->toBe(['michela.fabozzi@qualificagroup.com'])
        ->and(managerEmailsOf('simona.chiacchio@qualificagroup.com'))->toBe([
            'fabrizio.aliberti@qualificagroup.com',
            'rosa.falzarano@qualificagroup.com',
            'umberto.santamaria@qualificagroup.com',
        ])
        ->and(managerEmailsOf('sabino.figurelli@qualificagroup.com'))->toBe(['umberto.santamaria@qualificagroup.com'])
        ->and(managerEmailsOf('sara.armerini@qualificagroup.com'))->toBe([
            'fabrizio.aliberti@qualificagroup.com',
            'rosa.falzarano@qualificagroup.com',
        ])
        // A staff account: its employment profile is created to hold the link.
        ->and(managerEmailsOf('jessica.virgolini@qualificagroup.com'))->toBe([
            'fabrizio.aliberti@qualificagroup.com',
            'rosa.falzarano@qualificagroup.com',
        ]);
});

it('converges on a re-run and leaves unlisted accounts alone', function (): void {
    seedAccountsAndReportsTo();

    $operator = User::query()->where('email', 'marco.fedele@qualificagroup.com')->with('employment')->sole();
    $staff = User::query()->where('email', 'nicola.eliseo@qualificagroup.com')->sole();
    $staffProfile = $staff->employment()->create();
    $operator->employment->reportsTo()->sync([$staff->id]);
    $staffProfile->reportsTo()->sync([$operator->id]);

    test()->seed(QualificaReportsToSeeder::class);

    expect(managerEmailsOf('marco.fedele@qualificagroup.com'))->toBe([
        'fabrizio.aliberti@qualificagroup.com',
        'rosa.falzarano@qualificagroup.com',
    ])
        ->and(managerEmailsOf('nicola.eliseo@qualificagroup.com'))->toBe(['marco.fedele@qualificagroup.com']);
});

it('skips a profile flagged as manager, which reports to no one', function (): void {
    test()->seed(QualificaOperatorSeeder::class);
    User::query()->where('email', 'rosa.falzarano@qualificagroup.com')->sole()->employment->update(['is_manager' => true]);

    test()->seed(QualificaReportsToSeeder::class);

    expect(managerEmailsOf('rosa.falzarano@qualificagroup.com'))->toBe([])
        ->and(managerEmailsOf('marco.fedele@qualificagroup.com'))->toBe([
            'fabrizio.aliberti@qualificagroup.com',
            'rosa.falzarano@qualificagroup.com',
        ]);
});

it('is never fatal on missing accounts', function (): void {
    // No staff seeded: Jessica Virgolini has no account, the rest still lands.
    test()->seed(QualificaOperatorSeeder::class);
    test()->seed(QualificaReportsToSeeder::class);

    expect(User::query()->where('email', 'jessica.virgolini@qualificagroup.com')->exists())->toBeFalse()
        ->and(managerEmailsOf('lea.pellegrino@qualificagroup.com'))->toBe([
            'fabrizio.aliberti@qualificagroup.com',
            'rosa.falzarano@qualificagroup.com',
        ]);
});
