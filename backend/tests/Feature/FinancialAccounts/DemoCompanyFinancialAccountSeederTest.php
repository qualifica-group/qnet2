<?php

use App\Enums\FinancialAccountType;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Rules\ValidIban;
use Database\Seeders\DemoCompanyFinancialAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds two valid bank accounts and a cash box on every company', function (): void {
    $companies = Company::factory()->count(3)->create();

    test()->seed(DemoCompanyFinancialAccountSeeder::class);

    expect(FinancialAccount::query()->count())->toBe(9);

    $companies->each(function (Company $company): void {
        $banks = FinancialAccount::query()
            ->where('company_id', $company->id)
            ->where('type', FinancialAccountType::BankAccount)
            ->get();
        $cash = FinancialAccount::query()
            ->where('company_id', $company->id)
            ->where('type', FinancialAccountType::Cash)
            ->get();

        expect($banks)->toHaveCount(2);
        expect($cash)->toHaveCount(1);
        $banks->each(function (FinancialAccount $bank): void {
            expect(ValidIban::mod97(substr($bank->iban, 4).substr($bank->iban, 0, 4)))->toBe(1);
            expect((float) $bank->opening_balance)->toBeGreaterThan(0);
        });
        expect((float) $cash->first()->opening_balance)->toBeGreaterThan(0);
    });
});

it('is idempotent and keeps edits on a re-run', function (): void {
    Company::factory()->count(2)->create();
    test()->seed(DemoCompanyFinancialAccountSeeder::class);
    $edited = FinancialAccount::query()->where('type', FinancialAccountType::Cash)->firstOrFail();
    $edited->update(['opening_balance' => '999.00']);

    test()->seed(DemoCompanyFinancialAccountSeeder::class);

    expect(FinancialAccount::query()->count())->toBe(6);
    expect($edited->fresh()->opening_balance)->toBe('999.00');
});
