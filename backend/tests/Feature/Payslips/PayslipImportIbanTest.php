<?php

use App\Models\EmploymentProfile;
use App\Models\PayrollCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Spec 0431, AC-006 (IBAN, D-10) and the cost-file warning (D-12).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', 'it');
    $this->fixture = payslipSetUp();
    $this->actor = payslipImporterUser();
    $this->user = $this->fixture['users']['a'];
});

/** Messages of the warnings of a response, without any IBAN. */
function payslipWarningMessages($response): array
{
    return array_column($response->json('data.warnings'), 'message');
}

it('AC-006: a new IBAN is saved encrypted on the employment profile and never shown', function () {
    $profile = EmploymentProfile::factory()->create(['user_id' => $this->user->id, 'iban' => null]);

    $response = payslipImport($this->actor, payslipFile([payslipRow(['CODICE IBAN' => 'it60 x054 2811 1010 0000 0123 456'])]), false)->assertOk();

    expect($profile->fresh()->iban)->toBe(PAYSLIP_IBAN)
        ->and(DB::table('employment_profiles')->where('id', $profile->id)->value('iban'))->not->toContain('IT60')
        ->and($response->getContent())->not->toContain('IT60')
        ->and(payslipWarningMessages($response))->not->toContain(trans('payslips.import.warnings.iban_changed', [], 'it'));
});

it('AC-006: a different IBAN warns in the preview and replaces the stored one on apply', function () {
    $profile = EmploymentProfile::factory()->create(['user_id' => $this->user->id, 'iban' => 'IT00A0000000000000000000000']);
    $file = fn () => payslipFile([payslipRow(['CODICE IBAN' => PAYSLIP_IBAN])]);

    $preview = payslipImport($this->actor, $file(), true)->assertOk();
    expect(payslipWarningMessages($preview))->toContain(trans('payslips.import.warnings.iban_changed', [], 'it'))
        ->and($profile->fresh()->iban)->toBe('IT00A0000000000000000000000');

    $applied = payslipImport($this->actor, $file(), false)->assertOk();

    expect($profile->fresh()->iban)->toBe(PAYSLIP_IBAN)
        ->and($applied->getContent())->not->toContain('IT00A')->not->toContain('IT60');
});

it('AC-006: a user without employment profile warns and no profile is created', function () {
    $response = payslipImport($this->actor, payslipFile([payslipRow(['CODICE IBAN' => PAYSLIP_IBAN])]), false)->assertOk();

    expect(payslipWarningMessages($response))->toContain(trans('payslips.import.warnings.iban_no_profile', [], 'it'))
        ->and(EmploymentProfile::query()->count())->toBe(0);
});

it('AC-006: an empty IBAN leaves the stored one untouched, the foreign IBAN is used when the first is empty', function () {
    $profile = EmploymentProfile::factory()->create(['user_id' => $this->user->id, 'iban' => PAYSLIP_IBAN]);

    payslipImport($this->actor, payslipFile([payslipRow()]), false)->assertOk();
    expect($profile->fresh()->iban)->toBe(PAYSLIP_IBAN);

    payslipImport($this->actor, payslipFile([payslipRow(['CODICE IBAN ESTERO' => 'DE89370400440532013000', 'IMPORTO' => '10,00'])]), false)->assertOk();
    expect($profile->fresh()->iban)->toBe('DE89370400440532013000');
});

it('AC-006: a net without a cost row of the same company, person and month is a warning', function () {
    PayrollCost::factory()->create([
        'company_id' => $this->fixture['companies']['1']->id, 'user_id' => $this->user->id, 'period' => '2026-10',
    ]);
    $rows = [payslipRow(), payslipRow(['AZIENDA' => '3'])];

    $response = payslipImport($this->actor, payslipFile($rows), true)->assertOk();

    expect($response->json('data.warnings'))->toBe([
        ['row' => 3, 'column' => null, 'message' => trans('payslips.import.warnings.no_cost_row', [], 'it')],
    ])->and($response->json('data.errors'))->toBe([]);
});
