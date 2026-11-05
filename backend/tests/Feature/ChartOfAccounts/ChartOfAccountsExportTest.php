<?php

use App\Imports\Support\SpreadsheetReader;
use App\Models\ChartAccount;
use App\Models\CostType;
use App\Models\RevenueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    resetChartOfAccounts();
});

if (! function_exists('chartExportTable')) {
    /**
     * Header and data rows (values only) of a downloaded export file.
     *
     * @return array{0: array<int, string>, 1: array<int, array<int, string>>}
     */
    function chartExportTable(TestResponse $response, string $extension): array
    {
        $path = sys_get_temp_dir().'/chart-export-'.uniqid().'.'.$extension;
        copy($response->getFile()->getPathname(), $path);

        $reader = new SpreadsheetReader;
        $headers = array_column($reader->analyze($path)->columns, 'name');
        $rows = array_map(fn (array $row): array => array_values($row), iterator_to_array($reader->rows($path), false));
        unlink($path);

        return [$headers, $rows];
    }
}

function seedChartForExport(): void
{
    $group = ChartAccount::factory()->create(['code' => 'G10', 'name' => 'Costi servizi', 'description' => 'Servizi', 'kind' => 'cost']);
    $account = ChartAccount::factory()->account($group)->create(['code' => 'G10.2', 'name' => 'Consulenze', 'description' => null, 'is_active' => false]);
    ChartAccount::factory()->account($group)->create(['code' => 'G10.10', 'name' => 'Viaggi', 'description' => null]);
    CostType::factory()->create(['chart_account_id' => $account->id, 'code' => 'G10.2.1', 'name' => 'Legale', 'description' => null, 'nature' => 'general', 'color' => 'teal']);
    CostType::factory()->create(['name' => 'Da collocare']);
    $revenueGroup = ChartAccount::factory()->revenue()->create(['code' => 'H10', 'name' => 'Ricavi', 'description' => null]);
    $revenueAccount = ChartAccount::factory()->account($revenueGroup)->create(['code' => 'H10.1', 'name' => 'Vendite', 'description' => null]);
    RevenueType::factory()->create(['chart_account_id' => $revenueAccount->id, 'code' => 'H10.1.1', 'name' => 'Licenze', 'description' => null]);
}

it('AC-001: exports the whole chart in tree order, without the unplaced cost types', function () {
    seedChartForExport();
    Sanctum::actingAs(chartUserWith(['chart-accounts.export']));

    $response = $this->get('/api/chart-of-accounts/export?format=xlsx', ['Accept' => 'application/json'])->assertOk();
    [$headers, $rows] = chartExportTable($response, 'xlsx');

    expect($headers)->toBe(['Livello', 'Codice', 'Codice padre', 'Nome', 'Descrizione', 'Tipo', 'Natura', 'Colore', 'Attivo'])
        ->and($rows)->toBe([
            ['Mastro', 'G10', '', 'Costi servizi', 'Servizi', 'Costo', '', '', 'Sì'],
            ['Conto', 'G10.2', 'G10', 'Consulenze', '', '', '', '', 'No'],
            ['Sottoconto', 'G10.2.1', 'G10.2', 'Legale', '', '', 'Generale', 'teal', 'Sì'],
            ['Conto', 'G10.10', 'G10', 'Viaggi', '', '', '', '', 'Sì'],
            ['Mastro', 'H10', '', 'Ricavi', '', 'Ricavo', '', '', 'Sì'],
            ['Conto', 'H10.1', 'H10', 'Vendite', '', '', '', '', 'Sì'],
            ['Sottoconto', 'H10.1.1', 'H10.1', 'Licenze', '', '', '', '', 'Sì'],
        ])
        ->and($response->headers->get('content-disposition'))->toContain('chart-of-accounts-'.now()->toDateString().'.xlsx');
});

it('AC-002: the csv export has the same content as the xlsx one', function () {
    seedChartForExport();
    Sanctum::actingAs(chartUserWith(['chart-accounts.export']));

    $xlsx = chartExportTable($this->get('/api/chart-of-accounts/export?format=xlsx')->assertOk(), 'xlsx');
    $csvResponse = $this->get('/api/chart-of-accounts/export?format=csv')->assertOk();

    expect(chartExportTable($csvResponse, 'csv'))->toBe($xlsx)
        ->and($csvResponse->headers->get('content-type'))->toContain('text/csv');
});

it('AC-002: refuses a missing or unknown format with 422', function (string $query) {
    Sanctum::actingAs(chartUserWith(['chart-accounts.export']));

    $this->getJson('/api/chart-of-accounts/export'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
})->with(['missing' => [''], 'unknown' => ['?format=pdf']]);

it('writes headers and values in the language of the user', function () {
    seedChartForExport();
    Sanctum::actingAs(chartUserWith(['chart-accounts.export']));
    $this->withHeader('Accept-Language', 'en');

    [$headers, $rows] = chartExportTable($this->get('/api/chart-of-accounts/export?format=csv')->assertOk(), 'csv');

    expect($headers)->toBe(['Level', 'Code', 'Parent code', 'Name', 'Description', 'Kind', 'Nature', 'Color', 'Active'])
        ->and($rows[0])->toBe(['Group', 'G10', '', 'Costi servizi', 'Servizi', 'Cost', '', '', 'Yes'])
        ->and($rows[2][6])->toBe('General');
});

it('AC-003: refuses the export without chart-accounts.export', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.import']));

    $this->getJson('/api/chart-of-accounts/export?format=csv')->assertForbidden();
});

it('AC-003: requires authentication', function () {
    $this->getJson('/api/chart-of-accounts/export?format=csv')->assertUnauthorized();
});

it('AC-011: the tree exposes the export and import abilities', function () {
    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.export']));

    $this->getJson('/api/chart-of-accounts/tree')->assertOk()
        ->assertJsonPath('data.abilities.chart_accounts.export', true)
        ->assertJsonPath('data.abilities.chart_accounts.import', false);

    Sanctum::actingAs(chartUserWith(['chart-accounts.view', 'chart-accounts.import']));

    $this->getJson('/api/chart-of-accounts/tree')->assertOk()
        ->assertJsonPath('data.abilities.chart_accounts.export', false)
        ->assertJsonPath('data.abilities.chart_accounts.import', true);
});
