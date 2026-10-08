<?php

use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('AC-003: the generated residual and the SQL status agree with InstallmentStatus: any collection is paid, even below or above the amount', function (?string $collected, string $residual, InstallmentStatus $status) {
    $installment = InvoiceInstallment::factory()->create(['amount' => '100.00', 'collected_amount' => $collected]);

    $sqlStatus = DB::table('invoice_installments')
        ->where('id', $installment->id)
        ->selectRaw("CASE WHEN COALESCE(collected_amount, 0) <= 0 THEN 'unpaid' ELSE 'paid' END AS status")
        ->value('status');

    expect(DB::table('invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual($residual)
        ->and($sqlStatus)->toBe($status->value)
        ->and($installment->fresh()->status())->toBe($status);
})->with([
    'unpaid' => [null, '100', InstallmentStatus::Unpaid],
    'collected below amount' => ['40.00', '60', InstallmentStatus::Paid],
    'paid' => ['100.00', '0', InstallmentStatus::Paid],
    'collected above amount' => ['120.00', '-20', InstallmentStatus::Paid],
]);

it('keeps the residual in step when the collection changes', function () {
    $installment = InvoiceInstallment::factory()->create(['amount' => '50.00']);
    $installment->update(['collected_amount' => '20.00']);

    expect(DB::table('invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual('30');
});

it('D-9: the status has only unpaid and paid, labelled per locale', function () {
    expect(InstallmentStatus::values())->toBe(['unpaid', 'paid'])
        ->and(InstallmentStatus::Unpaid->label())->toBe('Not collected')
        ->and(InstallmentStatus::Paid->label())->toBe('Collected');

    app()->setLocale('it');

    expect(InstallmentStatus::Unpaid->label())->toBe('Da incassare')
        ->and(InstallmentStatus::Paid->label())->toBe('Incassata');
});
