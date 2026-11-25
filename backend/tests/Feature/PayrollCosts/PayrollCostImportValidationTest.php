<?php

use App\Models\PayrollCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

// Spec 0430, AC-004 (row errors) and AC-005 (whole-file refusals).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payrollSetUp();
});

it('AC-004: every kind of row error carries its row and column and nothing is written even on apply', function () {
    $actor = payrollImporterUser();
    payrollUser('TSTDUP00A01H501D');
    payrollUser('tstdup00a01h501d');
    config(['payroll.company_codes' => [...config('payroll.company_codes'), '5' => '55555555555']]);

    $rows = [
        payrollRow(),
        payrollRow(['Codice fiscale dipendente' => 'TSTNOT00A01H501N']),
        payrollRow(['Codice fiscale dipendente' => 'TSTDUP00A01H501D']),
        payrollRow(['AZIENDA' => '99', 'Codice fiscale dipendente' => PAYROLL_TAX_B]),
        payrollRow(['AZIENDA' => '5', 'Codice fiscale dipendente' => PAYROLL_TAX_B]),
        payrollRow(['Codice fiscale dipendente' => PAYROLL_TAX_B, 'Costo retribuzione' => 'abc']),
        payrollRow(['Codice fiscale dipendente' => PAYROLL_TAX_B, 'TOTALE COSTO MENSILE' => '1.547,08']),
        payrollRow(),
        payrollRow(['Codice fiscale dipendente' => PAYROLL_TAX_B, 'Mese da' => '11']),
    ];

    foreach ([true, false] as $dryRun) {
        $response = payrollImport($actor, payrollFile($rows), $dryRun)->assertOk();

        expect($response->json('data.applied'))->toBeFalse()
            ->and(array_map(fn (array $e): array => [$e['row'], $e['column']], $response->json('data.errors')))->toBe([
                [3, 'Codice fiscale dipendente'],
                [4, 'Codice fiscale dipendente'],
                [5, 'AZIENDA'],
                [6, 'AZIENDA'],
                [7, 'Costo retribuzione'],
                [8, 'TOTALE COSTO MENSILE'],
                [9, 'Codice fiscale dipendente'],
                [10, 'Mese da'],
            ])
            ->and(array_column($response->json('data.errors'), 'message'))->toContain('Codice fiscale non trovato.', 'Codice fiscale su più utenti.', 'Società non associata.', 'Importo non numerico.');
    }

    expect(PayrollCost::query()->count())->toBe(0);
});

it('AC-005: a file without a required column, an empty one, a wrong extension or too many rows is refused on file', function (string $case) {
    $actor = payrollImporterUser();
    $header = PAYROLL_HEADER;

    $file = match ($case) {
        'no-company' => payrollFile([payrollRow()], header: array_values(array_diff($header, ['AZIENDA']))),
        'no-total' => payrollFile([payrollRow()], header: array_values(array_diff($header, ['TOTALE COSTO MENSILE']))),
        'empty' => UploadedFile::fake()->createWithContent('costs.csv', ''),
        'header-only' => payrollFile([]),
        'extension' => UploadedFile::fake()->create('costs.pdf', 10, 'application/pdf'),
        'too-many' => payrollFile(array_fill(0, 2001, payrollRow())),
    };

    $response = payrollImport($actor, $file, false)->assertUnprocessable()->assertJsonValidationErrors('file');

    if (str_starts_with($case, 'no-')) {
        expect($response->json('errors.file.0'))->toContain($case === 'no-company' ? 'AZIENDA' : 'TOTALE COSTO MENSILE');
    }
    expect(PayrollCost::query()->count())->toBe(0);
})->with(['no-company', 'no-total', 'empty', 'header-only', 'extension', 'too-many']);
