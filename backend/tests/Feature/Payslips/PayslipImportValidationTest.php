<?php

use App\Models\Payslip;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

// Spec 0431, AC-004 (reload) and AC-005 (row errors, 422 on file and payment_date).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payslipSetUp();
    $this->actor = payslipImporterUser();
});

/** Italian text of an import error, as the request locale produces it. */
function payslipError(string $key, array $replace = []): string
{
    return trans("payslips.import.errors.{$key}", $replace, 'it');
}

it('AC-004: an unpaid payslip is updated with the new net and due date, without a new document', function () {
    payslipImport($this->actor, payslipFile([payslipRow()]), false)->assertOk();

    $reload = payslipImport($this->actor, payslipFile([payslipRow(['IMPORTO' => '900,00', 'CODICE DIPENDENTE' => '202'])]), false, ['payment_date' => '2026-12-15'])->assertOk();

    expect($reload->json('data.summary.created'))->toBe(0)
        ->and($reload->json('data.summary.updated'))->toBe(1)
        ->and(PurchaseInvoice::query()->count())->toBe(1)
        ->and(Payslip::query()->count())->toBe(1);

    $document = PurchaseInvoice::query()->with(['lines', 'installments'])->sole();
    expect((string) $document->net_amount)->toBe('900.00')
        ->and((string) $document->total_amount)->toBe('900.00')
        ->and($document->lines)->toHaveCount(1)
        ->and((string) $document->lines[0]->unit_price)->toBe('900.00')
        ->and((string) $document->lines[0]->net_amount)->toBe('900.00')
        ->and($document->installments)->toHaveCount(1)
        ->and((string) $document->installments[0]->amount)->toBe('900.00')
        ->and($document->installments[0]->due_date->toDateString())->toBe('2026-12-15')
        ->and(Payslip::query()->sole()->employee_code)->toBe('202');
});

it('AC-004: a payslip paid in part is a row error and nothing is written', function () {
    payslipImport($this->actor, payslipFile([payslipRow()]), false)->assertOk();
    PurchaseInvoice::query()->with('installments')->sole()->installments[0]->update(['paid_amount' => '100.00']);

    $reload = payslipImport($this->actor, payslipFile([payslipRow(['IMPORTO' => '900,00']), payslipRow(['RIP.1: CODICE' => PAYSLIP_TAX_B])]), false)->assertOk();

    expect($reload->json('data.applied'))->toBeFalse()
        ->and($reload->json('data.errors'))->toBe([['row' => 2, 'column' => null, 'message' => payslipError('already_paid')]])
        ->and(PurchaseInvoice::query()->count())->toBe(1)
        ->and((string) PurchaseInvoice::query()->sole()->net_amount)->toBe('1234.56');
});

it('AC-005: a row error blocks the whole file', function (array $overrides, ?string $column, string $key, array $replace = []) {
    $rows = [payslipRow(['RIP.1: CODICE' => PAYSLIP_TAX_B]), payslipRow($overrides)];

    $response = payslipImport($this->actor, payslipFile($rows), false)->assertOk();

    expect($response->json('data.applied'))->toBeFalse()
        ->and($response->json('data.errors'))->toBe([['row' => 3, 'column' => $column, 'message' => payslipError($key, $replace)]])
        ->and(PurchaseInvoice::query()->count())->toBe(0)
        ->and(Payslip::query()->count())->toBe(0);
})->with([
    'no tax code ripartition' => [['RIP.1: TIPO' => 'Centro di costo'], null, 'tax_code_missing'],
    'tax code with a blank value' => [['RIP.1: CODICE' => ''], 'RIP.1: CODICE', 'required'],
    'tax code not found' => [['RIP.1: CODICE' => 'TSTZZZ90C03H501Z'], null, 'tax_code_not_found'],
    'company without a company' => [['AZIENDA' => '5'], 'AZIENDA', 'company_unmapped'],
    'unknown company code' => [['AZIENDA' => '99'], 'AZIENDA', 'company_unmapped'],
    'zero amount' => [['IMPORTO' => '0,00'], 'IMPORTO', 'non_positive_amount'],
    'negative amount' => [['IMPORTO' => '-10,00'], 'IMPORTO', 'non_positive_amount'],
    'non numeric amount' => [['IMPORTO' => 'abc'], 'IMPORTO', 'invalid_amount'],
    'another month' => [['MESE' => '11'], 'MESE', 'other_period', ['period' => '2026-10']],
    'invalid month' => [['MESE' => '13'], 'MESE', 'invalid_month'],
    'repeated key' => [['RIP.1: CODICE' => PAYSLIP_TAX_B], 'TIPO CEDOLINO', 'duplicate'],
]);

it('AC-005: the tax code is the first Codice fiscale ripartition, whatever its position and spacing', function () {
    $row = payslipRow(['RIP.1: TIPO' => 'Centro di costo', 'RIP.1: CODICE' => '12', 'RIP.2: TIPO' => '  codice   FISCALE ', 'RIP.2: CODICE' => PAYSLIP_TAX_B]);

    payslipImport($this->actor, payslipFile([$row]), false)->assertOk();

    expect(Payslip::query()->sole()->user_id)->toBe($this->fixture['users']['b']->id);
});

it('AC-005: a tax code held by several users is a row error', function () {
    payslipUser(PAYSLIP_TAX_B);

    $response = payslipImport($this->actor, payslipFile([payslipRow(['RIP.1: CODICE' => PAYSLIP_TAX_B])]), true)->assertOk();

    expect($response->json('data.errors'))->toBe([['row' => 2, 'column' => null, 'message' => payslipError('tax_code_many_users')]]);
});

it('AC-005: the same person and type on one company is fine for two different payslip types', function () {
    $rows = [payslipRow(), payslipRow(['TIPO CEDOLINO' => 'TREDICESIMA'])];

    payslipImport($this->actor, payslipFile($rows), false)->assertOk();

    expect(Payslip::query()->count())->toBe(2);
});

it('AC-005: the file is refused with 422 on file', function (string $case) {
    $file = match ($case) {
        'missing required column' => payslipFile([payslipRow()], drop: ['IMPORTO']),
        'missing ripartition columns' => payslipFile([payslipRow()], drop: ['RIP.1: TIPO', 'RIP.1: CODICE', 'RIP.1: DESCRIZIONE', 'RIP.2: TIPO', 'RIP.2: CODICE', 'RIP.2: DESCRIZIONE']),
        'header only' => payslipFile([]),
        'empty file' => UploadedFile::fake()->createWithContent('net.txt', ''),
        'extension not allowed' => payslipFile([payslipRow()], 'net.pdf'),
    };

    payslipImport($this->actor, $file, false)->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(PurchaseInvoice::query()->count())->toBe(0);
})->with(['missing required column', 'missing ripartition columns', 'header only', 'empty file', 'extension not allowed']);

it('AC-005: a missing or invalid payment date is a 422 on payment_date', function (?string $date) {
    $response = payslipImport($this->actor, payslipFile([payslipRow()]), true, ['payment_date' => $date]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['payment_date']);
})->with([null, '30/11/2026', 'abc', '2026-13-45']);
