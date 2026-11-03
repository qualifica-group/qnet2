<?php

require_once __DIR__.'/../../../Helpers/RequestImportHelpers.php';

use App\Imports\ImportRowContext;
use App\Imports\RequestManagement\RequestRowRecognizer;
use App\Imports\RequestManagementImportDefinition;
use App\Imports\Support\ColumnMapper;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<string, string>  $mapped
 * @return array<string, mixed> the merged value set a persisted row would carry
 */
function riRecognize(array $mapped, array $globalConfig = []): array
{
    $result = app(RequestRowRecognizer::class)
        ->recognize(new ImportRowContext(1, User::factory()->create(), $globalConfig), ['first_name' => 'Test', 'last_name' => 'Row', ...$mapped]);

    return ['resolved' => $result->resolved, 'messages' => $result->messages, 'needs_review' => $result->needsReview];
}

it('AC-001: only request-management.import authorizes the domain', function () {
    $definition = app(RequestManagementImportDefinition::class);
    Permission::findOrCreate('leads.import');
    $leadsOnly = User::factory()->create()->givePermissionTo('leads.import');

    expect($definition->authorizeImport(requestImportActor()))->toBeTrue()
        ->and($definition->authorizeImport($leadsOnly))->toBeFalse();
});

it('AC-002: the DIL headers map onto the request fields', function () {
    $headers = ['Operatore APL', 'Operatore', 'Stato', 'data Richiamo', 'Nome', 'Cognome', 'Codice Fiscale', 'Cellulare', 'Email', 'Prodotto', 'Fonte', 'Comune di Residenza'];
    $columns = array_map(fn (string $name, int $index): array => ['name' => $name, 'index' => $index, 'duplicate' => false], $headers, array_keys($headers));

    $mapping = (new ColumnMapper)->suggest($columns, app(RequestManagementImportDefinition::class)->fields())->mapping;

    expect($mapping)->toEqual([
        'Operatore' => 'operator_name',
        'Stato' => 'workflow_status',
        'data Richiamo' => 'next_callback_date',
        'Nome' => 'first_name',
        'Cognome' => 'last_name',
        'Codice Fiscale' => 'tax_code',
        'Cellulare' => 'phone',
        'Email' => 'email',
        'Prodotto' => 'product_name',
        'Fonte' => 'source_name',
        'Comune di Residenza' => 'city',
    ]);
});

it('AC-002: "Nome e Cognome" and "Nome Azienda" map onto the name and the company', function () {
    $columns = [['name' => 'Nome e Cognome', 'index' => 0, 'duplicate' => false], ['name' => 'Nome Azienda', 'index' => 1, 'duplicate' => false]];

    expect((new ColumnMapper)->suggest($columns, app(RequestManagementImportDefinition::class)->fields())->mapping)
        ->toBe(['Nome e Cognome' => 'full_name', 'Nome Azienda' => 'company_name']);
});

it('AC-003: the status preview lists the workflow options and suggests a status per distinct value', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $source = Source::factory()->create(['name' => 'Social']);
    $mapped = [
        'workflow_status' => ['column_key' => 'Stato', 'values' => [
            ['value' => 'Attesa_App APL', 'count' => 2],
            ['value' => 'OK App. Fissato CPI', 'count' => 1],
            ['value' => '', 'count' => 3],
            ['value' => 'Trattativa', 'count' => 1],
        ]],
        'source_name' => ['column_key' => 'Fonte', 'values' => [['value' => 'social', 'count' => 4], ['value' => 'Altra', 'count' => 1]]],
    ];

    $preview = app(RequestManagementImportDefinition::class)->valueMappingPreview($mapped, requestImportGlobalConfig($category, ['source_id' => $source->id]));
    $status = collect($preview)->firstWhere('field', 'workflow_status');
    $suggested = collect($status['values'])->pluck('suggested_id', 'value');

    expect($status['allow_create'])->toBeTrue()
        ->and(array_column($status['options'], 'name'))->toContain('Aperta', 'In trattativa', 'Attesa _ App. APL')
        ->and($suggested['Attesa_App APL'])->toBe($statuses['wait_apl']->id)
        ->and($suggested['OK App. Fissato CPI'])->toBeNull()
        ->and($suggested[''])->toBe($statuses['open']->id)
        ->and($suggested['Trattativa'])->toBe($statuses['negotiation']->id);

    $sources = collect($preview)->firstWhere('field', 'source_name');
    expect($sources['allow_create'])->toBeFalse()
        ->and(collect($sources['values'])->pluck('suggested_id', 'value')->all())->toBe(['social' => $source->id, 'Altra' => $source->id]);
});

it('AC-003: the preview without a selectable product line is a 422 on the global field', function () {
    $definition = app(RequestManagementImportDefinition::class);

    expect(fn () => $definition->valueMappingPreview(['workflow_status' => ['column_key' => 'Stato', 'values' => []]], []))
        ->toThrow(ValidationException::class);
});

it('AC-004: unmapped values, foreign ids and a duplicate status name are reported', function () {
    ['category' => $category, 'statuses' => $statuses] = requestImportScenario();
    $foreign = QuoteWorkflowStatus::factory()->create();
    $definition = app(RequestManagementImportDefinition::class);
    $config = requestImportGlobalConfig($category);
    $mapped = fn (string ...$values) => ['workflow_status' => ['column_key' => 'Stato', 'values' => array_map(fn ($v) => ['value' => $v, 'count' => 1], $values)]];

    expect($definition->validateValueMappings($mapped('A'), ['workflow_status' => []], $config))->toHaveKey('value_mappings.workflow_status')
        ->and($definition->validateValueMappings($mapped('A'), ['workflow_status' => ['A' => ['id' => $foreign->id]]], $config))->toHaveKey('value_mappings.workflow_status')
        ->and($definition->validateValueMappings($mapped('A'), ['workflow_status' => ['A' => ['create' => ['name' => ' in TRATTATIVA ', 'group' => 'pending']]]], $config))->toHaveKey('value_mappings.workflow_status')
        ->and($definition->validateValueMappings($mapped('A'), ['workflow_status' => ['a ' => ['id' => $statuses['open']->id]]], $config))->toBe([])
        ->and($definition->validateValueMappings($mapped('A'), ['workflow_status' => ['A' => ['create' => ['name' => 'Nuovo', 'group' => 'pending']]]], $config))->toBe([]);
});

it('AC-007: an operator is recognised by name; unknown or ambiguous ones fall back with a warning', function () {
    User::factory()->create(['name' => 'Zhour El Hajiri']);
    User::factory()->create(['name' => 'Valentina Rossi']);
    User::factory()->create(['name' => 'Valentina Rossi']);
    $zhour = User::query()->where('name', 'Zhour El Hajiri')->value('id');

    $found = riRecognize(['operator_name' => 'Zhour EL  HAJIRI']);
    expect($found['resolved']['operator_id'])->toBe($zhour)->and($found['messages'])->toBe([]);

    foreach (['Valentina', 'Valentina Rossi'] as $name) {
        $missed = riRecognize(['operator_name' => $name]);
        expect($missed['resolved']['operator_id'])->toBeNull()
            ->and($missed['needs_review'])->toBeTrue()
            ->and($missed['messages'])->not->toBe([]);
    }
});

it('AC-009: "Informazioni aggiuntive" are typed per attribute; an unknown enum option goes to the notes', function () {
    ['category' => $category] = requestImportScenario();
    $price = Attribute::factory()->decimal()->create(['code' => 'price', 'name' => 'Prezzo']);
    $subsidy = Attribute::factory()->enum(0)->create(['code' => 'subsidy_type', 'name' => 'Tipologia sussidio']);
    $subsidy->options()->create(['value' => 'naspi', 'label' => 'NASPI', 'sort_order' => 0, 'is_default' => false]);
    $date = Attribute::factory()->ofType('date')->create(['code' => 'cpi_date', 'name' => 'Data CPI']);
    foreach ([$price, $subsidy, $date] as $attribute) {
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => 0, 'context' => 'quote']);
    }
    $config = ['product_category_id' => $category->id];

    $ok = riRecognize(['attribute.price' => '700,00 EUR', 'attribute.subsidy_type' => 'naspi', 'attribute.cpi_date' => '11/04/2026'], $config);
    expect($ok['resolved']['attribute_values'])->toBe(['price' => 700.0, 'subsidy_type' => 'naspi', 'cpi_date' => '2026-04-11'])
        ->and($ok['messages'])->toBe([]);

    $bad = riRecognize(['attribute.price' => '1.234,50', 'attribute.subsidy_type' => 'SFL', 'attribute.cpi_date' => 'boh'], $config);
    expect($bad['resolved']['attribute_values'])->toBe(['price' => 1234.5])
        ->and($bad['resolved']['discarded_values'])->toBe([['label' => 'Tipologia sussidio', 'value' => 'SFL'], ['label' => 'Data CPI', 'value' => 'boh']])
        ->and($bad['messages'])->toHaveCount(2);

    $definition = app(RequestManagementImportDefinition::class);
    expect($definition->validateAttributeTargets(['P' => 'attribute.price', 'X' => 'attribute.nope'], $config))->toHaveKey('column_mapping.X')
        ->and($definition->validateAttributeTargets(['P' => 'attribute.price'], $config))->toBe([]);
});

it('AC-015: an Excel date, d/m/Y and ISO are read at 09:00; an unreadable one is dropped with a warning and a note', function () {
    foreach (['2026-04-11 00:00:00', '11/04/2026', '11/4/2026', '2026-04-11'] as $raw) {
        expect(riRecognize(['next_callback_date' => $raw])['resolved']['next_callback_at'])
            ->toStartWith('2026-04-11T09:00:00');
    }

    $bad = riRecognize(['next_callback_date' => '15/102026']);
    expect($bad['resolved']['next_callback_at'])->toBeNull()
        ->and($bad['resolved']['discarded_values'])->toBe([['label' => 'Data richiamo', 'value' => '15/102026']])
        ->and($bad['needs_review'])->toBeTrue();
});

it('AC-008: the product rule resolves acronyms, abbreviations and typos inside the chosen category only', function () {
    ['category' => $category] = requestImportScenario();
    $category->update(['name' => 'DIL - Lombardia']);
    $other = requestImportScenario()['category'];
    $products = [
        'OAC' => Product::factory()->create(['category_id' => $category->id, 'name' => 'OAC - Operatore Contabile Amministrativo']),
        'AGG' => Product::factory()->create(['category_id' => $category->id, 'name' => 'Aggiornamento ASO']),
        'SEG' => Product::factory()->create(['category_id' => $category->id, 'name' => 'Segreteria Studio Medico']),
        'ASO' => Product::factory()->create(['category_id' => $category->id, 'name' => 'ASO - Assistente Studio Odontoiatrico']),
        'ELSEWHERE' => Product::factory()->create(['category_id' => $other->id, 'name' => 'Gestione Magazzino']),
    ];
    $resolve = fn (string $text): ?int => riRecognize(['product_name' => $text], ['product_category_id' => $category->id])['resolved']['product_ids'][0] ?? null;

    expect($resolve('oac'))->toBe($products['OAC']->id)
        ->and($resolve('Agg ASO'))->toBe($products['AGG']->id)
        ->and($resolve('ASO'))->toBe($products['ASO']->id)
        ->and($resolve('dil milano segretaria studio medico'))->toBe($products['SEG']->id)
        ->and($resolve('Gestione Magazzino'))->toBeNull()
        ->and($resolve('???'))->toBeNull();
});

it('product rule: category words, noise, years and line breaks are ignored; only a UNIQUE match resolves', function () {
    $dil = ProductCategory::factory()->create(['name' => 'DIL - Lombardia']);
    $auto = ProductCategory::factory()->create(['name' => 'Autofinanziato - Campania']);
    $make = fn (ProductCategory $category, string $name): int => Product::factory()->create(['category_id' => $category->id, 'name' => $name])->id;
    $canva = $make($dil, 'Canva professionale');
    $seg = $make($dil, 'Segreteria Studio Medico');
    $make($dil, 'Make-up Artist Professionale (30 ore)');
    $make($dil, 'Make-up Artist Professionale (40 ore)');
    $make($dil, 'Gestione Magazzino');
    $oac = $make($auto, 'OAC - Operatore Contabile Amministrativo');
    $pekit = $make($auto, 'PEKIT Expert');
    $aba = $make($auto, 'Tecnico del Comportamento ABA - Online');
    $osa = $make($auto, 'OSA - Operatore Socio Assistenziale');
    $asacom = $make($auto, "ASACOM - Assistente all'Autonomia e alla Comunicazione");
    $make($auto, 'Aggiornamento ASO');
    $make($auto, 'ASO - Assistente Studio Odontoiatrico');
    $resolve = fn (ProductCategory $category, string $text): ?int => riRecognize(['product_name' => $text], ['product_category_id' => $category->id])['resolved']['product_ids'][0] ?? null;

    expect($resolve($dil, 'DIL - Milano - Canva'))->toBe($canva)
        ->and($resolve($dil, 'dil milano canva'))->toBe($canva)
        ->and($resolve($dil, 'dil milano studio medico'))->toBe($seg)
        ->and($resolve($dil, "dil milano segretaria\r\nstudio_x000D_ medico"))->toBe($seg)
        ->and($resolve($auto, 'Corso OAC - Autofinanziato - 2026'))->toBe($oac)
        ->and($resolve($auto, 'Pekit'))->toBe($pekit)
        ->and($resolve($auto, 'ABA'))->toBe($aba)
        ->and($resolve($auto, 'OSA'))->toBe($osa)
        ->and($resolve($auto, 'ASACOM'))->toBe($asacom)
        ->and($resolve($auto, 'OSA/ASACOM'))->toBeNull()
        ->and($resolve($dil, 'dil milano make up'))->toBeNull()
        ->and($resolve($dil, 'dil milano generica'))->toBeNull()
        ->and($resolve($dil, 'dil milano'))->toBeNull()
        ->and($resolve($auto, "Corso Gare d'Appalto - 2026 -"))->toBeNull()
        ->and($resolve($dil, 'qualsiasi che le dia punteggio'))->toBeNull();
});

it('a company row gets no name placeholder and no warning; a nameless, companyless row does', function () {
    $company = riRecognize(['first_name' => '', 'last_name' => '', 'company_name' => 'Acme Srl', 'phone' => '0212345678']);
    expect($company['messages'])->toBe([])->and($company['needs_review'])->toBeFalse()->and($company['resolved'])->not->toHaveKey('first_name');

    $nameless = riRecognize(['first_name' => '', 'last_name' => '', 'phone' => '0212345678']);
    expect($nameless['resolved']['first_name'])->toBe(config('imports.placeholder'))->and($nameless['needs_review'])->toBeTrue();
});

it('the combined source "diretto + passaparola" is suggested the existing combined Fonte', function () {
    ['category' => $category] = requestImportScenario();
    $combined = Source::factory()->create(['name' => 'Diretto / Passaparola']);

    $preview = app(RequestManagementImportDefinition::class)->valueMappingPreview(
        ['source_name' => ['column_key' => 'Fonte', 'values' => [['value' => 'Diretto + Passaparola', 'count' => 1]]]],
        requestImportGlobalConfig($category),
    );

    expect($preview[0]['values'][0]['suggested_id'])->toBe($combined->id);
});
