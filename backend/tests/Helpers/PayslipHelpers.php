<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// Synthetic data only (spec 0431): invented tax codes, names, IBANs and amounts.

const PAYSLIP_HEADER = [
    'AZIENDA', 'DENOMINAZIONE', 'MESE', 'ANNO', 'TIPO CEDOLINO', 'CODICE DIPENDENTE', 'COGNOME E NOME',
    'DATA ASSUNZIONE', 'DATA CESSAZIONE', 'IMPORTO', 'CODICE IBAN', 'CODICE BANCA', 'DESCRIZIONE BANCA',
    'CODICE IBAN ESTERO', 'CODICE BIC', "LOCALITA' BANCA ESTERA", 'BENEFICIARIO', 'RIPARTIZIONE PREDEFINITA N.',
    'RIP.1: TIPO', 'RIP.1: CODICE', 'RIP.1: DESCRIZIONE', 'RIP.2: TIPO', 'RIP.2: CODICE', 'RIP.2: DESCRIZIONE',
];

const PAYSLIP_TAX_A = 'TSTAAA80A01H501A';

const PAYSLIP_TAX_B = 'TSTBBB85B02H501B';

const PAYSLIP_IBAN = 'IT60X0542811101000000123456';

const PAYSLIP_PAYMENT_DATE = '2026-11-30';

/**
 * Seeds permissions, two companies mapped in config (codes 1 and 3), the
 * `bonifico_bancario` payment method and two people holding PAYSLIP_TAX_A / _B.
 * The payslip cost type comes from the data migration.
 *
 * @return array{companies: array<string, Company>, users: array<string, User>}
 */
function payslipSetUp(): array
{
    Artisan::call('permissions:sync');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    PaymentMethod::factory()->create(['code' => 'bonifico_bancario', 'payment_method_code' => 'MP05']);
    $companies = ['1' => Company::factory()->create(['vat_number' => '11111111111']), '3' => Company::factory()->create(['vat_number' => '33333333333'])];
    config(['payroll.company_codes' => ['1' => '11111111111', '3' => '33333333333', '5' => '55555555555']]);

    return ['companies' => $companies, 'users' => ['a' => payslipUser(PAYSLIP_TAX_A), 'b' => payslipUser(PAYSLIP_TAX_B)]];
}

function payslipUser(string $taxCode): User
{
    $user = User::factory()->create();
    PersonalData::factory()->individual()->for($user, 'personable')->create(['tax_code' => $taxCode]);

    return $user;
}

function payslipImporterUser(): User
{
    return User::factory()->create()->givePermissionTo('purchase-invoices.import', 'purchase-invoices.create');
}

/**
 * A valid October 2026 row; $overrides replace cells by header.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function payslipRow(array $overrides = []): array
{
    return [...array_combine(PAYSLIP_HEADER, [
        '1', 'Alfa Srl', '10', '2026', 'MENSILITA_NORMALE', '101', 'Zanchè Mario', '01/03/2020', '', '1.234,56', '', '05428',
        'INTESA SANPAOLO SPA PALAZZOLO SULL"OGLIO', '', '', '', '', '1', 'Codice fiscale', PAYSLIP_TAX_A, 'ZANCHE MARIO',
        'Centro di costo', '77', 'SEDE',
    ]), ...$overrides];
}

/**
 * @param  list<array<string, string>>  $rows  keyed by header
 * @param  list<string>  $drop  headers removed from the file
 */
function payslipFile(array $rows, string $name = 'net.txt', array $drop = [], string $delimiter = "\t"): UploadedFile
{
    $header = array_values(array_diff(PAYSLIP_HEADER, $drop));
    $quote = static fn (string $cell): string => preg_match('/["\t;,\r\n]/', $cell) === 1 ? '"'.str_replace('"', '""', $cell).'"' : $cell;
    $lines = [implode($delimiter, array_map($quote, $header))];
    foreach ($rows as $row) {
        $lines[] = implode($delimiter, array_map(static fn (string $column): string => $quote($row[$column]), $header));
    }

    return UploadedFile::fake()->createWithContent($name, mb_convert_encoding(implode("\r\n", $lines), 'Windows-1252', 'UTF-8'));
}

/**
 * @param  array<string, mixed>  $extra  request fields overriding the defaults
 */
function payslipImport(User $user, UploadedFile $file, bool $dryRun, array $extra = []): TestResponse
{
    Sanctum::actingAs($user);

    return test()->post(
        '/api/purchase-invoices/payslips/import',
        ['file' => $file, 'dry_run' => $dryRun ? '1' : '0', 'payment_date' => PAYSLIP_PAYMENT_DATE, ...$extra],
        ['Accept' => 'application/json'],
    );
}
