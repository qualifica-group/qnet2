<?php

use App\Exports\ChartOfAccounts\ChartOfAccountsLayout;
use App\Imports\ChartOfAccounts\ChartOfAccountsApplier;
use App\Imports\ChartOfAccounts\ChartOfAccountsFileReader;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use App\Models\User;
use App\Support\BadgeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    resetChartOfAccounts();
});

const CHART_IT_HEADER = ['Livello', 'Codice', 'Codice padre', 'Nome', 'Descrizione', 'Tipo', 'Natura', 'Colore', 'Attivo'];

if (! function_exists('chartImportFile')) {
    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>  $header
     */
    function chartImportFile(array $rows, array $header = CHART_IT_HEADER, string $name = 'plan.csv'): UploadedFile
    {
        $lines = array_map(fn (array $cells): string => implode(',', array_map(fn (string $cell): string => '"'.str_replace('"', '""', $cell).'"', $cells)), [$header, ...$rows]);

        return UploadedFile::fake()->createWithContent($name, implode("\n", $lines));
    }

    function chartImportAs(User $user, UploadedFile $file, bool $dryRun): TestResponse
    {
        Sanctum::actingAs($user);

        return test()->post('/api/chart-of-accounts/import', ['file' => $file, 'dry_run' => $dryRun ? '1' : '0'], ['Accept' => 'application/json']);
    }

    function chartImporter(): User
    {
        return chartUserWith(['chart-accounts.import', 'cost-types.create', 'cost-types.update']);
    }

    /** Counts of every table the import writes to, to prove a request wrote nothing. */
    function chartRowCounts(): array
    {
        return [ChartAccount::count(), CostType::count(), RevenueType::count()];
    }
}

/** A new revenue branch: group, account, sub-account. */
function newRevenueBranch(): array
{
    return [
        ['Mastro', 'NR1', '', 'Ricavi nuovi', 'Desc', 'Ricavo', '', '', 'Sì'],
        ['Conto', 'NR1.1', 'NR1', 'Conto nuovo', '', '', '', '', 'Sì'],
        ['Sottoconto', 'NR1.1.1', 'NR1.1', 'Sotto nuovo', '', '', '', '', 'No'],
    ];
}

it('AC-005: a dry run counts what would be created and writes nothing', function () {
    $before = chartRowCounts();

    $response = chartImportAs(chartImporter(), chartImportFile(newRevenueBranch()), true)->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.errors', []);

    expect($response->json('data.summary'))->toBe([
        'rows' => 3,
        'create' => ['groups' => 1, 'accounts' => 1, 'cost_sub_accounts' => 0, 'revenue_sub_accounts' => 1],
        'update' => ['groups' => 0, 'accounts' => 0, 'cost_sub_accounts' => 0, 'revenue_sub_accounts' => 0],
    ])->and(chartRowCounts())->toBe($before);
});

it('AC-005: applying creates the entries under the right parents', function () {
    chartImportAs(chartImporter(), chartImportFile(newRevenueBranch()), false)->assertOk()
        ->assertJsonPath('data.applied', true);

    $group = ChartAccount::where('code', 'NR1')->firstOrFail();
    $account = ChartAccount::where('code', 'NR1.1')->firstOrFail();
    $sub = RevenueType::where('code', 'NR1.1.1')->firstOrFail();

    expect($group->kind->value)->toBe('revenue')
        ->and($group->description)->toBe('Desc')
        ->and($account->parent_id)->toBe($group->id)
        ->and($account->level->value)->toBe('account')
        ->and($sub->chart_account_id)->toBe($account->id)
        ->and($sub->is_active)->toBeFalse();
});

it('AC-006: a known code updates the entry and absent entries stay untouched', function () {
    $group = ChartAccount::factory()->create(['code' => 'G1', 'name' => 'Old', 'description' => 'Old desc', 'kind' => 'cost']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'G1.1', 'name' => 'Old account']);
    $absent = ChartAccount::factory()->account($group)->create(['code' => 'G1.2', 'name' => 'Absent']);
    $revenue = RevenueType::factory()->create(['code' => 'R1', 'name' => 'Old revenue']);

    $response = chartImportAs(chartImporter(), chartImportFile([
        ['Mastro', 'G1', '', 'New group', '', 'Costo', '', '', 'No'],
        ['Conto', 'G1.1', 'G1', 'New account', 'Now described', '', '', '', 'Sì'],
    ]), false)->assertOk()->assertJsonPath('data.applied', true);

    expect($response->json('data.summary.update.groups'))->toBe(1)
        ->and($response->json('data.summary.create.groups'))->toBe(0)
        ->and($group->fresh())->name->toBe('New group')->description->toBeNull()->is_active->toBeFalse()
        ->and($account->fresh())->name->toBe('New account')->description->toBe('Now described')
        ->and($absent->fresh()->name)->toBe('Absent')
        ->and($revenue->fresh()->name)->toBe('Old revenue');
});

it('AC-006: an existing sub-account can move to another account of the same kind', function () {
    $group = ChartAccount::factory()->revenue()->create(['code' => 'RG']);
    $first = ChartAccount::factory()->account($group)->create(['code' => 'RG.1']);
    $second = ChartAccount::factory()->account($group)->create(['code' => 'RG.2']);
    $sub = RevenueType::factory()->create(['chart_account_id' => $first->id, 'code' => 'RG.1.1', 'name' => 'Sub']);

    chartImportAs(chartImporter(), chartImportFile([['Sottoconto', 'RG.1.1', 'RG.2', 'Sub', '', '', '', '', 'Sì']]), false)
        ->assertOk()->assertJsonPath('data.applied', true);

    expect($sub->fresh()->chart_account_id)->toBe($second->id);
});

it('AC-007: a new code with the name of an unplaced cost type places it instead of duplicating it', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);
    $unplaced = CostType::factory()->create(['name' => 'Consulenze legali', 'nature' => 'direct', 'color' => 'teal']);
    $costTypesBefore = CostType::count();

    $response = chartImportAs(chartImporter(), chartImportFile([
        ['Sottoconto', 'CG.1.1', 'CG.1', 'consulenze LEGALI', '', '', 'Indiretto', '', 'Sì'],
    ]), false)->assertOk()->assertJsonPath('data.applied', true);

    expect(CostType::count())->toBe($costTypesBefore)
        ->and($response->json('data.summary.update.cost_sub_accounts'))->toBe(1)
        ->and($unplaced->fresh())
        ->code->toBe('CG.1.1')
        ->chart_account_id->toBe($account->id)
        ->nature->value->toBe('indirect')
        ->color->toBe('teal');
});

it('AC-007: a new name creates a cost type with the file nature and the default color', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);

    chartImportAs(chartImporter(), chartImportFile([
        ['Sottoconto', 'CG.1.1', 'CG.1', 'Trasferte', '', '', 'Generale', '', 'Sì'],
        ['Sottoconto', 'CG.1.2', 'CG.1', 'Hardware', '', '', 'Diretto', 'blue', 'Sì'],
    ]), false)->assertOk()->assertJsonPath('data.applied', true);

    $created = CostType::where('code', 'CG.1.1')->firstOrFail();

    expect(ChartOfAccountsApplier::DEFAULT_COLOR)->toBe(BadgeTokens::colors()[0])
        ->and($created)
        ->name->toBe('Trasferte')
        ->nature->value->toBe('general')
        ->color->toBe(ChartOfAccountsApplier::DEFAULT_COLOR)
        ->chart_account_id->toBe($account->id)
        ->and(CostType::where('code', 'CG.1.2')->firstOrFail()->color)->toBe('blue');
});

it('AC-008: reports every invalid row with row and column and writes nothing, even when applying', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);
    RevenueType::factory()->create(['code' => 'USED', 'chart_account_id' => ChartAccount::factory()->account(ChartAccount::factory()->revenue()->create())->create()->id]);
    $before = chartRowCounts();

    $response = chartImportAs(chartImporter(), chartImportFile([
        ['Mastro', 'OK1', '', 'Valid group', '', 'Costo', '', '', 'Sì'],           // row 2: valid
        ['Conto', 'BAD1', 'NOPE', 'Orphan account', '', '', '', '', 'Sì'],          // row 3: parent not found
        ['Mastro', 'OK1', '', 'Repeated code', '', 'Costo', '', '', 'Sì'],          // row 4: duplicate in file
        ['Conto', 'USED', 'CG', 'Code of another level', '', '', '', '', 'Sì'],     // row 5: code used elsewhere
        ['Conto', 'BAD2', 'CG', 'Account with kind', '', 'Costo', '', '', 'Sì'],    // row 6: kind on an account
        ['Sottoconto', 'BAD3', 'CG.1', 'No nature', '', '', '', '', 'Sì'],          // row 7: nature missing
        ['Mastro', 'BAD4', '', 'Wrong value', '', 'Boh', '', '', 'Sì'],             // row 8: invalid kind
        ['Conto', 'bad code!', 'CG', 'Bad code', '', '', '', '', 'Sì'],             // row 9: code format
        ['Sottoconto', 'BAD5', 'CG', 'Under a group', '', '', 'Diretto', '', 'Sì'], // row 10: parent wrong level
    ]), false)->assertOk()->assertJsonPath('data.applied', false);

    $errors = collect($response->json('data.errors'))->map(fn (array $e): string => "{$e['row']}:{$e['column']}")->all();

    expect($errors)->toBe(['3:Codice padre', '4:Codice', '5:Codice', '6:Tipo', '7:Natura', '8:Tipo', '9:Codice', '10:Codice padre'])
        ->and($response->json('data.errors.0.message'))->toBeString()->not->toBeEmpty()
        ->and(chartRowCounts())->toBe($before)
        ->and($account->exists)->toBeTrue();
});

it('AC-008: the kind of a group that has accounts cannot change', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG', 'kind' => 'cost']);
    ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);

    $response = chartImportAs(chartImporter(), chartImportFile([['Mastro', 'CG', '', 'Name', '', 'Ricavo', '', '', 'Sì']]), false)->assertOk();

    expect($response->json('data.applied'))->toBeFalse()
        ->and($response->json('data.errors.0'))->toMatchArray(['row' => 2, 'column' => 'Tipo'])
        ->and($group->fresh()->kind->value)->toBe('cost');
});

it('AC-008: a sub-account name already taken by a cost type is an error', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);
    CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'CG.1.1', 'name' => 'Alpha']);
    CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'CG.1.2', 'name' => 'Beta']);

    $response = chartImportAs(chartImporter(), chartImportFile([['Sottoconto', 'CG.1.2', 'CG.1', 'alpha', '', '', 'Diretto', '', 'Sì']]), false)->assertOk();

    expect($response->json('data.applied'))->toBeFalse()
        ->and($response->json('data.errors.0'))->toMatchArray(['row' => 2, 'column' => 'Nome']);
});

it('AC-009: without cost-types.create a row that creates a cost type is a row error and nothing is applied', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);
    $before = chartRowCounts();

    $response = chartImportAs(chartUserWith(['chart-accounts.import', 'cost-types.update']), chartImportFile([
        ['Mastro', 'NEW', '', 'Valid other row', '', 'Costo', '', '', 'Sì'],
        ['Sottoconto', 'CG.1.1', 'CG.1', 'Brand new', '', '', 'Diretto', '', 'Sì'],
    ]), false)->assertOk();

    expect($response->json('data.applied'))->toBeFalse()
        ->and($response->json('data.errors'))->toHaveCount(1)
        ->and($response->json('data.errors.0'))->toMatchArray(['row' => 3, 'column' => null])
        ->and(chartRowCounts())->toBe($before);
});

it('AC-009: without cost-types.update a row that updates a cost type is a row error', function () {
    $group = ChartAccount::factory()->create(['code' => 'CG']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'CG.1']);
    $type = CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'CG.1.1', 'name' => 'Old']);

    $response = chartImportAs(chartUserWith(['chart-accounts.import', 'cost-types.create']), chartImportFile([
        ['Sottoconto', 'CG.1.1', 'CG.1', 'New name', '', '', 'Diretto', '', 'Sì'],
    ]), false)->assertOk();

    expect($response->json('data.applied'))->toBeFalse()
        ->and($response->json('data.errors.0.column'))->toBeNull()
        ->and($type->fresh()->name)->toBe('Old');
});

it('AC-003: refuses the import without chart-accounts.import', function () {
    chartImportAs(chartUserWith(['chart-accounts.export', 'cost-types.create']), chartImportFile(newRevenueBranch()), true)->assertForbidden();

    expect(ChartAccount::count())->toBe(0);
});

it('AC-003: requires authentication', function () {
    $this->postJson('/api/chart-of-accounts/import')->assertUnauthorized();
});

it('AC-010: accepts English headers and values', function () {
    $response = chartImportAs(chartImporter(), chartImportFile([
        ['Group', 'EG', '', 'English group', '', 'Cost', '', '', 'Yes'],
        ['Account', 'EG.1', 'EG', 'English account', '', '', '', '', 'yes'],
        ['Sub-account', 'EG.1.1', 'EG.1', 'English sub', '', '', 'Indirect', '', ' NO '],
    ], ['level', ' CODE ', 'Parent Code', 'name', 'Description', 'KIND', 'Nature', 'Color', 'Active']), false)->assertOk();

    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.applied'))->toBeTrue()
        ->and(CostType::where('code', 'EG.1.1')->firstOrFail())->nature->value->toBe('indirect')->is_active->toBeFalse();
});

it('AC-010: missing columns are a 422 on file naming them', function () {
    $response = chartImportAs(chartImporter(), chartImportFile([['Mastro', 'X1', 'Name']], ['Livello', 'Codice', 'Nome']), true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect($response->json('errors.file.0'))->toContain('Codice padre')->toContain('Attivo');
});

it('refuses a missing file, a wrong extension and a missing dry_run with 422', function () {
    Sanctum::actingAs(chartImporter());

    $this->post('/api/chart-of-accounts/import', ['dry_run' => '1'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->post('/api/chart-of-accounts/import', ['file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'), 'dry_run' => '1'], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->post('/api/chart-of-accounts/import', ['file' => chartImportFile(newRevenueBranch())], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('dry_run');
});

it('refuses a file with more rows than the limit', function () {
    $rows = array_map(fn (int $i): array => ['Mastro', "M{$i}", '', "Group {$i}", '', 'Costo', '', '', 'Sì'], range(1, ChartOfAccountsFileReader::MAX_ROWS + 1));

    chartImportAs(chartImporter(), chartImportFile($rows), true)->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(ChartAccount::count())->toBe(0);
});

it('AC-004: an exported and re-imported chart is N updates, no creations and no errors', function () {
    seedChartForRoundTrip();
    $user = chartUserWith(['chart-accounts.export', 'chart-accounts.import', 'cost-types.create', 'cost-types.update']);
    Sanctum::actingAs($user);

    $export = $this->get('/api/chart-of-accounts/export?format=xlsx')->assertOk();
    $path = sys_get_temp_dir().'/roundtrip-'.uniqid().'.xlsx';
    copy($export->getFile()->getPathname(), $path);
    $before = chartRowCounts();

    $response = chartImportAs($user, new UploadedFile($path, 'plan.xlsx', null, null, true), true)->assertOk();

    expect($response->json('data.errors'))->toBe([])
        ->and($response->json('data.summary'))->toBe([
            'rows' => 7,
            'create' => ['groups' => 0, 'accounts' => 0, 'cost_sub_accounts' => 0, 'revenue_sub_accounts' => 0],
            'update' => ['groups' => 2, 'accounts' => 3, 'cost_sub_accounts' => 1, 'revenue_sub_accounts' => 1],
        ])
        ->and(chartRowCounts())->toBe($before);
});

function seedChartForRoundTrip(): void
{
    $group = ChartAccount::factory()->create(['code' => 'G10', 'name' => 'Costi servizi', 'kind' => 'cost']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'G10.2', 'is_active' => false]);
    ChartAccount::factory()->account($group)->create(['code' => 'G10.10']);
    CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'G10.2.1', 'name' => 'Legale', 'nature' => 'general', 'color' => 'teal']);
    CostType::factory()->create(['name' => 'Da collocare']);
    $revenueAccount = ChartAccount::factory()->account(ChartAccount::factory()->revenue()->create(['code' => 'H10']))->create(['code' => 'H10.1']);
    RevenueType::factory()->create(['chart_account_id' => $revenueAccount->id, 'code' => 'H10.1.1']);
}

it('keeps the layout vocabulary in both languages parseable', function () {
    foreach (['it', 'en'] as $locale) {
        app()->setLocale($locale);

        expect(ChartOfAccountsLayout::parseValue(ChartOfAccountsLayout::LEVELS, ChartOfAccountsLayout::value(ChartOfAccountsLayout::LEVELS, 'sub_account')))->toBe('sub_account')
            ->and(ChartOfAccountsLayout::parseBoolean(ChartOfAccountsLayout::booleanLabel(true)))->toBeTrue()
            ->and(ChartOfAccountsLayout::parseBoolean(ChartOfAccountsLayout::booleanLabel(false)))->toBeFalse();
    }
});
