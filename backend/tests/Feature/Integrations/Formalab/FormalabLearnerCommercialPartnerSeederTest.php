<?php

use App\Enums\ContactTypeEnum;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Integrations\Formalab\Models\FormalabLearnerCommission;
use Integrations\Formalab\Seeders\LearnerCommercialPartnerSeeder;

// Spec 0427 D-8 / AC-012: the hand-written partner of the legacy learners is
// their opportunity's G.A.3; the seeder makes its referent the "Partner
// commerciale" of the opportunity and its quotes and copies the commissions.
uses(RefreshDatabase::class);

const LCP_SUPPLIER_OLD_ID = 19839;

function lcpWithOldId(object $model, int $oldId): object
{
    $model->forceFill(['old_id' => $oldId])->save();

    return $model;
}

function lcpUser(int $oldId, string $firstName, string $lastName): User
{
    $user = lcpWithOldId(User::factory()->create(['name' => "{$firstName} {$lastName}", 'email' => "user{$oldId}@example.test"]), $oldId);
    PersonalData::factory()->individual()->for($user, 'personable')->create(['first_name' => $firstName, 'last_name' => $lastName]);

    return $user;
}

/** A migrated opportunity with one quote, optionally carrying a partner already. */
function lcpOpportunity(int $oldId, ?int $quotePartnerId = null): Opportunity
{
    $opportunity = lcpWithOldId(Opportunity::factory()->create(), $oldId);
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'commercial_partner_id' => $quotePartnerId, 'updated_at' => '2026-01-01 10:00:00']);

    return $opportunity;
}

/**
 * @return array<string, mixed>
 */
function lcpItem(int $id, int $opportunityId, ?int $userId, ?array $commission = null, array $warnings = []): array
{
    return ['id' => $id, 'opportunity_id' => $opportunityId, 'tax_code' => sprintf('rssmra80a01h703%s ', chr(64 + $id)),
        'commercial_partner_user_id' => $userId, 'commission' => $commission, 'warnings' => $warnings];
}

/**
 * The legacy endpoint: the first item on the first page, the rest on the
 * second, so the seeder has to page.
 *
 * @param  list<array<string, mixed>>  $items
 */
function lcpFakeLegacy(array $items): void
{
    seedMigrationsConfig();
    $total = LearnerCommercialPartnerSeeder::PAGE_SIZE + count($items) - 1;

    Http::fake(static function (Request $request) use ($items, $total) {
        $firstPage = (int) ($request->data()['offset'] ?? 0) === 0;

        return Http::response(['items' => $firstPage ? array_slice($items, 0, 1) : array_slice($items, 1), 'pagination' => ['total' => $total]]);
    });
}

function lcpPartnerOf(Opportunity $opportunity): array
{
    return [$opportunity->fresh()->commercial_partner_id, Quote::query()->where('opportunity_id', $opportunity->id)->value('commercial_partner_id')];
}

beforeEach(function (): void {
    migrationsSuperAdminActor();
    ReferentType::factory()->create(['name' => 'Commerciale']);
});

it('makes the G.A.3 referent the partner of the opportunity and its quotes, creating it once when missing', function () {
    $annunziata = lcpUser(35, 'Fernando', 'Annunziata');
    lcpUser(135, 'Marilisa', 'Scafati Taglialatela');
    $migrated = lcpWithOldId(Referent::factory()->create(['name' => 'ANNUNZIATA Fernando']), 5295);
    Referent::factory()->create(['name' => 'Fernando Annunziata']);
    $fernando = lcpOpportunity(1001);
    $marilisa = lcpOpportunity(1002);
    $marilisaAgain = lcpOpportunity(1003);
    $interno = lcpOpportunity(1004);
    $unknownUser = lcpOpportunity(1005);

    lcpFakeLegacy([
        lcpItem(1, 1001, 35),
        lcpItem(2, 1002, 135),
        lcpItem(3, 1003, 135),
        lcpItem(4, 1004, null, null, ['Partner "Interno" senza G.A.3 sull\'opportunita\'.']),
        lcpItem(5, 1005, 999),
    ]);
    $referentsBefore = Referent::query()->count();

    test()->seed(LearnerCommercialPartnerSeeder::class);

    $created = Referent::query()->where('name', 'Marilisa Scafati Taglialatela')->sole();
    expect(lcpPartnerOf($fernando))->toBe([$migrated->id, $migrated->id])
        ->and(lcpPartnerOf($marilisa))->toBe([$created->id, $created->id])
        ->and(lcpPartnerOf($marilisaAgain))->toBe([$created->id, $created->id])
        ->and(lcpPartnerOf($interno))->toBe([null, null])
        ->and(lcpPartnerOf($unknownUser))->toBe([null, null])
        ->and(Referent::query()->count())->toBe($referentsBefore + 1)
        ->and($created->referentType?->name)->toBe('Commerciale')
        ->and($created->contact_scope)->toBe(ReferentContactScopeEnum::Internal)
        ->and($created->user_id)->toBeNull()
        ->and($created->personalData->contacts()->where('type', ContactTypeEnum::Email->value)->value('value'))->toBe('user135@example.test')
        ->and($annunziata->fresh()->name)->toBe('Fernando Annunziata')
        ->and(Quote::query()->where('opportunity_id', $fernando->id)->value('updated_at')->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('never overwrites a partner already set in qnet-2', function () {
    lcpUser(35, 'Fernando', 'Annunziata');
    $chosen = Referent::factory()->create(['name' => 'Partner Scelto']);
    $opportunity = lcpOpportunity(1001, $chosen->id);

    lcpFakeLegacy([lcpItem(1, 1001, 35)]);
    test()->seed(LearnerCommercialPartnerSeeder::class);

    [$opportunityPartner, $quotePartner] = lcpPartnerOf($opportunity);
    expect($quotePartner)->toBe($chosen->id)
        ->and($opportunityPartner)->toBe(Referent::query()->where('name', 'Fernando Annunziata')->value('id'));
});

it('stores the commissions by tax code filling only empty values, with the supplier by legacy id', function () {
    $supplier = lcpWithOldId(Registry::factory()->create(), LCP_SUPPLIER_OLD_ID);
    lcpOpportunity(1001);
    lcpOpportunity(1002);
    lcpOpportunity(1003);
    FormalabLearnerCommission::query()->create(['tax_code' => 'RSSMRA80A01H703C', 'percentage' => 30]);

    lcpFakeLegacy([
        lcpItem(1, 1001, null, ['percentage' => 50, 'amount' => null, 'supplier_registry_id' => LCP_SUPPLIER_OLD_ID]),
        lcpItem(2, 1002, null, ['percentage' => null, 'amount' => 120.5, 'supplier_registry_id' => 4242]),
        lcpItem(3, 1003, null, ['percentage' => 15, 'amount' => null, 'supplier_registry_id' => LCP_SUPPLIER_OLD_ID]),
    ]);
    test()->seed(LearnerCommercialPartnerSeeder::class);

    $rows = FormalabLearnerCommission::query()->get()->keyBy('tax_code');
    expect($rows)->toHaveCount(3)
        ->and($rows['RSSMRA80A01H703A']->only(['percentage', 'amount', 'supplier_registry_id']))
        ->toBe(['percentage' => 50.0, 'amount' => null, 'supplier_registry_id' => $supplier->id])
        ->and($rows['RSSMRA80A01H703B']->only(['percentage', 'amount', 'supplier_registry_id']))
        ->toBe(['percentage' => null, 'amount' => 120.5, 'supplier_registry_id' => null])
        ->and($rows['RSSMRA80A01H703C']->only(['percentage', 'amount', 'supplier_registry_id']))
        ->toBe(['percentage' => 30.0, 'amount' => null, 'supplier_registry_id' => $supplier->id]);
});

it('changes nothing on a second run', function () {
    lcpUser(135, 'Marilisa', 'Scafati Taglialatela');
    $opportunity = lcpOpportunity(1002);
    lcpFakeLegacy([lcpItem(2, 1002, 135, ['percentage' => 50, 'amount' => null, 'supplier_registry_id' => null])]);

    test()->seed(LearnerCommercialPartnerSeeder::class);
    $snapshot = [Referent::query()->count(), lcpPartnerOf($opportunity), FormalabLearnerCommission::query()->get(['tax_code', 'percentage', 'updated_at'])->toArray()];

    test()->seed(LearnerCommercialPartnerSeeder::class);

    expect([Referent::query()->count(), lcpPartnerOf($opportunity), FormalabLearnerCommission::query()->get(['tax_code', 'percentage', 'updated_at'])->toArray()])
        ->toBe($snapshot);
});
