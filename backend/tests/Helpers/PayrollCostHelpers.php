<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Spatie\Permission\PermissionRegistrar;

// Synthetic data only (spec 0430): invented tax codes, names and amounts.

const PAYROLL_HEADER = [
    'AZIENDA', 'Denominazione', 'Codice fiscale dipendente', 'Cognome', 'Nome', 'Data assunzione', 'Data cessazione',
    'Mese da', 'Anno da', 'Costo retribuzione', 'Costo contributivo', 'Costo inail', 'Ore ordinarie',
    'costo orario medio', 'TOTALE COSTO MENSILE',
];

const PAYROLL_TAX_A = 'TSTAAA80A01H501A';

const PAYROLL_TAX_B = 'TSTBBB85B02H501B';

/**
 * Seeds permissions, two companies mapped in config (codes 1 and 3) and two
 * people holding the tax codes of PAYROLL_TAX_A / PAYROLL_TAX_B.
 *
 * @return array{companies: array<string, Company>, users: array<string, User>}
 */
function payrollSetUp(): array
{
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $companies = ['1' => Company::factory()->create(['vat_number' => ' 11111111111']), '3' => Company::factory()->create(['vat_number' => '33333333333'])];
    config(['payroll.company_codes' => ['1' => '11111111111', '3' => '33333333333', '5' => '55555555555']]);

    return ['companies' => $companies, 'users' => ['a' => payrollUser(PAYROLL_TAX_A), 'b' => payrollUser(PAYROLL_TAX_B)]];
}

function payrollUser(string $taxCode, bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    PersonalData::factory()->individual()->for($user, 'personable')->create(['tax_code' => $taxCode]);

    return $user;
}

function payrollImporterUser(): User
{
    return User::factory()->create()->givePermissionTo('payroll-costs.view', 'payroll-costs.import');
}

/**
 * A valid October 2026 row; $overrides replace cells by header.
 *
 * @param  array<string, string>  $overrides
 * @return array<int, string>
 */
function payrollRow(array $overrides = []): array
{
    $row = array_combine(PAYROLL_HEADER, [
        '1', 'Alfa Srl', PAYROLL_TAX_A, 'Zanchè', 'Mario', '01/03/2020', '', '10', '2026', '1.234,56', '300,00', '12,50',
        '160,00', '12,3', '1.547,06',
    ]);

    return array_values([...$row, ...$overrides]);
}

/**
 * The reference file of the spec: one person on two companies, a negative
 * contribution, a #DIV/0! hourly average, accented names.
 *
 * @return list<array<int, string>>
 */
function payrollReferenceRows(): array
{
    return [
        payrollRow(),
        payrollRow(['AZIENDA' => '3', 'Data assunzione' => '', 'Costo retribuzione' => '500,00', 'Costo contributivo' => '-50,00', 'Costo inail' => '5,00', 'Ore ordinarie' => '40,00', 'costo orario medio' => '#DIV/0!', 'TOTALE COSTO MENSILE' => '455,00']),
        payrollRow(['Codice fiscale dipendente' => PAYROLL_TAX_B, 'Cognome' => 'Perù', 'Nome' => 'Anna', 'Data assunzione' => '', 'Data cessazione' => '15/10/2026', 'Costo retribuzione' => '2000.00', 'Costo contributivo' => '600,00', 'Costo inail' => '20,00', 'Ore ordinarie' => '168,00', 'TOTALE COSTO MENSILE' => '2620,00']),
    ];
}

/**
 * @param  array<int, array<int, string>>  $rows
 */
function payrollFile(array $rows, string $delimiter = "\t", string $name = 'costs.csv', bool $windows1252 = false, array $header = PAYROLL_HEADER): UploadedFile
{
    $lines = array_map(static fn (array $cells): string => implode($delimiter, array_map(
        static fn (string $cell): string => str_contains($cell, $delimiter) ? '"'.$cell.'"' : $cell,
        $cells,
    )), [$header, ...$rows]);
    $content = implode("\r\n", $lines);

    return UploadedFile::fake()->createWithContent($name, $windows1252 ? mb_convert_encoding($content, 'Windows-1252', 'UTF-8') : $content);
}

/**
 * @param  array<int, array<int, string>>  $rows
 */
function payrollXlsx(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'payroll').'.xlsx';
    $writer = new XlsxWriter;
    $writer->openToFile($path);
    foreach ([PAYROLL_HEADER, ...$rows] as $cells) {
        $writer->addRow(Row::fromValues($cells));
    }
    $writer->close();

    return UploadedFile::fake()->createWithContent('costs.xlsx', (string) file_get_contents($path));
}

function payrollImport(User $user, UploadedFile $file, bool $dryRun): TestResponse
{
    Sanctum::actingAs($user);

    return test()->post('/api/payroll-costs/import', ['file' => $file, 'dry_run' => $dryRun ? '1' : '0'], ['Accept' => 'application/json']);
}
