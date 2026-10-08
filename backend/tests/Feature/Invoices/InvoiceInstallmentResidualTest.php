<?php

use App\Enums\InstallmentStatus;
use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('AC-003: the generated residual and the SQL status agree with InstallmentStatus, collected above amount included', function (?string $collected, string $residual, InstallmentStatus $status) {
    $installment = InvoiceInstallment::factory()->create(['amount' => '100.00', 'collected_amount' => $collected]);

    $sqlStatus = DB::table('invoice_installments')
        ->where('id', $installment->id)
        ->selectRaw("CASE WHEN COALESCE(collected_amount, 0) <= 0 THEN 'unpaid' WHEN collected_amount >= amount THEN 'paid' ELSE 'partially_paid' END AS status")
        ->value('status');

    expect(DB::table('invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual($residual)
        ->and($sqlStatus)->toBe($status->value)
        ->and($installment->fresh()->status())->toBe($status);
})->with([
    'unpaid' => [null, '100', InstallmentStatus::Unpaid],
    'partially paid' => ['40.00', '60', InstallmentStatus::PartiallyPaid],
    'paid' => ['100.00', '0', InstallmentStatus::Paid],
    'collected above amount' => ['120.00', '-20', InstallmentStatus::Paid],
]);

it('keeps the residual in step when the collection changes', function () {
    $installment = InvoiceInstallment::factory()->create(['amount' => '50.00']);
    $installment->update(['collected_amount' => '20.00']);

    expect(DB::table('invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual('30');
});
