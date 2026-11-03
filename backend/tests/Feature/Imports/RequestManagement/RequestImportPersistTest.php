<?php

require_once __DIR__.'/../../../Helpers/RequestImportHelpers.php';

use App\Enums\ContactTypeEnum;
use App\Models\Attribute;
use App\Models\Contact;
use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const RI_MAPPING = [
    'Operatore' => 'operator_name',
    'Stato' => 'workflow_status',
    'Richiamo' => 'next_callback_date',
    'Nome' => 'first_name',
    'Cognome' => 'last_name',
    'Cell' => 'phone',
    'Mail' => 'email',
    'Prodotto' => 'product_name',
    'Segnalatore' => '__extra__',
];

const RI_HEADER = "Operatore,Stato,Richiamo,Nome,Cognome,Cell,Mail,Prodotto,Segnalatore\n";

/**
 * @param  array<string, array{id: int}|array{create: array<string, string>}>  $statusDecisions
 * @return array<string, mixed>
 */
function riValueMappings(array $statusDecisions): array
{
    return ['workflow_status' => $statusDecisions, 'source_name' => []];
}

it('AC-006 / AC-011: a created row has the Anagrafica, the Opportunita with the line and the Offerta with status, source, operator, callback and notes; no Lead', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $actor = requestImportActor();
    $operator = User::factory()->create(['name' => 'Zhour El Hajiri']);
    $config = requestImportGlobalConfig($category);

    $csv = RI_HEADER."Zhour EL HAJIRI,Trattativa,2026-09-30 00:00:00,Anna,Verdi,3331112222,anna@example.test,,Mario Rossi\n"
        .",,,Luca,Neri,3334445555,,,\n";
    $run = requestImportRun($actor, $csv, RI_MAPPING, $config, riValueMappings([
        'Trattativa' => ['id' => $statuses['negotiation']->id],
        '' => ['id' => $statuses['open']->id],
    ]));
    $run = requestImportCommit($run);

    expect($run->imported_rows)->toBe(2)
        ->and(Lead::query()->count())->toBe(0)
        ->and(Registry::query()->count())->toBe(2)
        ->and(Opportunity::query()->count())->toBe(2)
        ->and(Quote::query()->count())->toBe(2);

    $quote = Quote::query()->with('opportunity.productLines')->whereHas('opportunity', fn ($q) => $q->whereHas('registry.personalData', fn ($p) => $p->where('first_name', 'Anna')))->sole();

    expect($quote->quote_workflow_status_id)->toBe($statuses['negotiation']->id)
        ->and($quote->source_id)->toBe($config['source_id'])
        ->and($quote->operator_id)->toBe($operator->id)
        ->and($quote->next_callback_at->format('Y-m-d H:i'))->toBe('2026-09-30 09:00')
        ->and($quote->operational_site_id)->toBe($config['operational_site_id'])
        ->and($quote->opportunity->productLines->pluck('product_category_id')->all())->toBe([$category->id])
        ->and($quote->opportunity->general_notes)->toContain('Segnalatore: Mario Rossi');

    $other = Quote::query()->where('id', '!=', $quote->id)->sole();
    expect($other->quote_workflow_status_id)->toBe($statuses['open']->id)
        ->and($other->opportunity->general_notes)->toBeNull();
});

it('the Sede comes from the operator when the run sets none, the run fallback covers an operator without one', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $actor = requestImportActor();
    $operator = User::factory()->create(['name' => 'Zhour El Hajiri']);
    $operatorSite = OperationalSite::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $operator->id])
        ->operationalSites()->attach([$operatorSite->id => ['is_primary' => true]]);
    User::factory()->create(['name' => 'Anna Senza Sede']);
    $fallbackSite = OperationalSite::factory()->create();
    $config = requestImportGlobalConfig($category, ['operational_site_id' => $fallbackSite->id]);

    $csv = RI_HEADER."Zhour EL HAJIRI,,,Anna,Verdi,3331112222,,,\n"
        ."Anna Senza Sede,,,Luca,Neri,3334445555,,,\n";
    $run = requestImportRun($actor, $csv, RI_MAPPING, $config, riValueMappings(['' => ['id' => $statuses['open']->id]]));
    requestImportCommit($run);

    $siteOf = fn (string $firstName): ?int => Quote::query()
        ->whereHas('opportunity.registry.personalData', fn ($q) => $q->where('first_name', $firstName))
        ->sole()->operational_site_id;

    expect($siteOf('Anna'))->toBe($operatorSite->id)
        ->and($siteOf('Luca'))->toBe($fallbackSite->id);
});

it('AC-005: a "create" decision adds ONE status to the workflow, gives it to the rows, and a second run reuses it', function () {
    ['category' => $category, 'workflow' => $workflow, 'statuses' => $statuses] = requestImportScenario();
    $actor = requestImportActor();
    $config = requestImportGlobalConfig($category);
    $decisions = [
        'OK App. Fissato CPI' => ['create' => ['name' => 'OK App. Fissato CPI', 'group' => 'pending']],
        '' => ['id' => $statuses['open']->id],
    ];
    $csv = RI_HEADER.",OK App. Fissato CPI,,Anna,Verdi,3331112222,,,\n,ok app. fissato cpi,,Luca,Neri,3334445555,,,\n";

    requestImportCommit(requestImportRun($actor, $csv, RI_MAPPING, $config, riValueMappings($decisions)));

    $created = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->where('name', 'OK App. Fissato CPI')->get();
    expect($created)->toHaveCount(1)
        ->and($created->first()->group->value)->toBe('pending')
        ->and($created->first()->system_key)->toBeNull()
        ->and(Quote::query()->where('quote_workflow_status_id', $created->first()->id)->count())->toBe(2);

    $csv2 = RI_HEADER.",OK App. Fissato CPI,,Gino,Blu,3336667777,,,\n";
    requestImportCommit(requestImportRun($actor, $csv2, RI_MAPPING, $config, riValueMappings($decisions)));

    expect(QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->where('name', 'OK App. Fissato CPI')->count())->toBe(1)
        ->and(Quote::query()->where('quote_workflow_status_id', $created->first()->id)->count())->toBe(3);
});

it('AC-014: a mapped status outside the resolved workflow fails that row only and leaves nothing of it behind', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $foreign = QuoteWorkflowStatus::factory()->create(['name' => 'Altro flusso']);
    $actor = requestImportActor();
    $csv = RI_HEADER.",Buono,,Anna,Verdi,3331112222,,,\n,Foreign,,Luca,Neri,3334445555,,,\n";

    $run = requestImportCommit(requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings([
        'Buono' => ['id' => $statuses['open']->id],
        'Foreign' => ['id' => $foreign->id],
    ])));

    expect($run->imported_rows)->toBe(1)
        ->and($run->error_count)->toBe(1)
        ->and(Registry::query()->count())->toBe(1)
        ->and(Opportunity::query()->count())->toBe(1)
        ->and(Quote::query()->count())->toBe(1);
});

it('AC-013: no assignment notification leaves the import, and a normal creation afterwards still notifies', function () {
    Notification::fake();
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $actor = requestImportActor();
    $operator = User::factory()->create(['name' => 'Giulia Rossi']);
    $csv = RI_HEADER."Giulia Rossi,,,Anna,Verdi,3331112222,,,\n";

    requestImportCommit(requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings(['' => ['id' => $statuses['open']->id]])));

    expect(Quote::query()->sole()->operator_id)->toBe($operator->id);
    Notification::assertNotSentTo($operator, RecordAssignmentNotification::class);
    Notification::assertSentTimes(RecordAssignmentNotification::class, 0);
});

it('AC-012: update_existing attaches to the matched Anagrafica, ignore and manual-skip write nothing, manual-create makes a new one', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $actor = requestImportActor();
    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create(['first_name' => 'Vecchio', 'last_name' => 'Nome']);
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '3331112222', 'is_primary' => true]);
    $csv = RI_HEADER.",,,Anna,Verdi,3331112222,,,\n";
    $decisions = ['' => ['id' => $statuses['open']->id]];
    $mappings = fn () => riValueMappings($decisions);

    requestImportCommit(requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), $mappings(), 'update_existing'));
    expect(Registry::query()->count())->toBe(1)
        ->and(Quote::query()->sole()->opportunity->registry_id)->toBe($existing->id)
        ->and($card->fresh()->first_name)->toBe('Anna');

    requestImportCommit(requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), $mappings(), 'ignore'));
    expect(Quote::query()->count())->toBe(1);

    $manual = requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), $mappings(), 'manual');
    requestImportCommit($manual);
    expect(Quote::query()->count())->toBe(1);

    $manual = requestImportRun($actor, $csv, RI_MAPPING, requestImportGlobalConfig($category), $mappings(), 'manual');
    $manual->rows()->update(['resolution' => 'create']);
    requestImportCommit($manual);
    expect(Registry::query()->count())->toBe(2)->and(Quote::query()->count())->toBe(2);
});

it('AC-010: a malformed email is not stored, several phones become several contacts, the first principal', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $csv = RI_HEADER.",,,Anna,Verdi,3356261193 / 0819761955,\"miguelcalla9@gmail,com\",,\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings(['' => ['id' => $statuses['open']->id]]));
    $row = $run->rows()->sole();
    expect($row->status->value)->toBe('warning')->and($row->messages)->not->toBe([]);

    requestImportCommit($run);

    $contacts = Registry::query()->sole()->personalData->contacts;
    expect($contacts->where('type', ContactTypeEnum::Email))->toHaveCount(0)
        ->and($contacts->where('type', ContactTypeEnum::Phone))->toHaveCount(2)
        ->and($contacts->firstWhere('value', '3356261193')->is_primary)->toBeTrue()
        ->and($contacts->firstWhere('value', '0819761955')->is_primary)->toBeFalse()
        ->and(Opportunity::query()->sole()->general_notes)->toContain('Email: miguelcalla9@gmail,com');
});

it('AC-008: a product is matched by similarity, an unmatched one leaves the offer without lines and goes to the notes', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $oac = Product::factory()->saleOnly()->create(['category_id' => $category->id, 'name' => 'OAC - Operatore Contabile Amministrativo']);
    $agg = Product::factory()->saleOnly()->create(['category_id' => $category->id, 'name' => 'Aggiornamento ASO']);
    Product::factory()->saleOnly()->create(['category_id' => $category->id, 'name' => 'ASO - Assistente Studio Odontoiatrico']);
    $csv = RI_HEADER.",,,A,Uno,3330000001,,OAC,\n,,,B,Due,3330000002,,Agg ASO,\n,,,C,Tre,3330000003,,???,\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings(['' => ['id' => $statuses['open']->id]]));
    expect($run->rows()->orderBy('row_number')->get()->map(fn ($row) => $row->resolved['product_ids'])->all())->toBe([[$oac->id], [$agg->id], []]);
    requestImportCommit($run);

    $linesOf = fn (string $first) => Quote::query()->whereHas('opportunity.registry.personalData', fn ($p) => $p->where('first_name', $first))->sole()->offerLines()->pluck('product_id')->all();
    expect($linesOf('A'))->toBe([$oac->id])
        ->and($linesOf('B'))->toBe([$agg->id])
        ->and($linesOf('C'))->toBe([])
        ->and(Opportunity::query()->whereHas('registry.personalData', fn ($p) => $p->where('first_name', 'C'))->sole()->general_notes)->toContain('Prodotto: ???');
});

it('the recognised operator and products fill the review columns of the staged row; an unrecognised one leaves them empty', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $operator = User::factory()->create(['name' => 'Zhour El Hajiri']);
    $oac = Product::factory()->saleOnly()->create(['category_id' => $category->id, 'name' => 'OAC - Operatore Contabile Amministrativo']);
    $csv = RI_HEADER."Zhour EL HAJIRI,,,A,Uno,3330000001,,OAC,\nNessuno Conosciuto,,,B,Due,3330000002,,???,\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings(['' => ['id' => $statuses['open']->id]]));
    [$known, $unknown] = $run->rows()->orderBy('row_number')->get()->all();

    expect($known->operator_id)->toBe($operator->id)
        ->and($known->product_ids)->toBe([$oac->id])
        ->and($unknown->operator_id)->toBeNull()
        ->and($unknown->product_ids)->toBeNull();
});

it('AC-009 / AC-007: attribute values land on the Offerta and the operator falls back to the run default, then to the actor', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $price = Attribute::factory()->decimal()->create(['code' => 'price', 'name' => 'Prezzo']);
    $category->attributes()->attach($price->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    $actor = requestImportActor();
    $fallback = User::factory()->create();
    $mapping = [...RI_MAPPING, 'Prezzo' => 'attribute.price'];
    $csv = "Operatore,Stato,Richiamo,Nome,Cognome,Cell,Mail,Prodotto,Segnalatore,Prezzo\nSconosciuta,,,Anna,Verdi,3331112222,,,,\"700,00 EUR\"\n";
    $decisions = riValueMappings(['' => ['id' => $statuses['open']->id]]);

    requestImportCommit(requestImportRun($actor, $csv, $mapping, requestImportGlobalConfig($category, ['operator_id' => $fallback->id]), $decisions));
    $quote = Quote::query()->sole();
    expect($quote->attribute_values)->toEqual(['price' => 700.0])->and($quote->operator_id)->toBe($fallback->id);

    Quote::query()->delete();
    requestImportCommit(requestImportRun($actor, $csv, $mapping, requestImportGlobalConfig($category), $decisions));
    expect(Quote::query()->sole()->operator_id)->toBe($actor->id);
});

it('a closing status on a row with no offer line fails that row alone, with the reason reported', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $csv = RI_HEADER.",Chiusa,,Anna,Verdi,3331112222,,,\n";

    $run = requestImportCommit(requestImportRun(requestImportActor(), $csv, RI_MAPPING, requestImportGlobalConfig($category), riValueMappings([
        'Chiusa' => ['id' => $statuses['won']->id],
    ])));

    expect($run->error_count)->toBe(1)->and($run->imported_rows)->toBe(0)->and(Quote::query()->count())->toBe(0)->and(Registry::query()->count())->toBe(0);
});

const RI_REPORTER_MAPPING = ['Nome' => 'first_name', 'Cognome' => 'last_name', 'Cell' => 'phone', 'Segnalatore' => 'reporter_name'];

it('reporter: an existing referent becomes reporter_id of the Opportunita and the Offerta, and the column stays out of the notes', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $reporter = Referent::factory()->create(['name' => 'Mario Bianchi']);
    $csv = "Nome,Cognome,Cell,Segnalatore\nAnna,Verdi,3331112222,MARIO  bianchi\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_REPORTER_MAPPING, requestImportGlobalConfig($category));
    expect($run->rows()->sole()->messages ?? [])->toBe([]);
    requestImportCommit($run);

    $quote = Quote::query()->with('opportunity')->sole();
    expect($quote->reporter_id)->toBe($reporter->id)
        ->and($quote->opportunity->reporter_id)->toBe($reporter->id)
        ->and($quote->opportunity->general_notes)->toBeNull()
        ->and(Referent::query()->count())->toBe(1);
});

it('reporter: a missing one is left empty and noted, and no referent is ever created', function () {
    ['category' => $category] = requestImportScenario();
    $csv = "Nome,Cognome,Cell,Segnalatore\nAnna,Verdi,3331112222,Luisa De Santis\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_REPORTER_MAPPING, requestImportGlobalConfig($category));
    expect($run->rows()->sole()->status->value)->toBe('warning');
    requestImportCommit($run);

    expect(Quote::query()->sole()->reporter_id)->toBeNull()
        ->and(Opportunity::query()->sole()->general_notes)->toContain('Segnalatore: Luisa De Santis')
        ->and(Referent::query()->count())->toBe(0);
});

it('reporter: an ambiguous name is left empty, noted and never created', function () {
    ['category' => $category] = requestImportScenario();
    Referent::factory()->count(2)->create(['name' => 'Paolo Gialli']);
    $csv = "Nome,Cognome,Cell,Segnalatore\nAnna,Verdi,3331112222,Paolo Gialli\n";

    $run = requestImportRun(requestImportActor(), $csv, RI_REPORTER_MAPPING, requestImportGlobalConfig($category));
    expect($run->rows()->sole()->status->value)->toBe('warning');
    requestImportCommit($run);

    expect(Quote::query()->sole()->reporter_id)->toBeNull()
        ->and(Opportunity::query()->sole()->general_notes)->toContain('Segnalatore: Paolo Gialli')
        ->and(Referent::query()->count())->toBe(2);
});
