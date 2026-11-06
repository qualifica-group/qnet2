<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Models\Company;
use App\Models\FinancialAccount;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Seeder;

/**
 * Seed fake treasury accounts for EVERY company (spec 0189): two bank accounts
 * with valid fake IBANs and one cash box each, with a deterministic opening
 * balance. Unlike DemoFinancialAccountSeeder (a handful of accounts on the
 * first company), this covers the whole company list, so a database holding
 * the client's real companies gets a bank and a cash box on each of them.
 * Idempotent: bank accounts are keyed by IBAN, cash boxes by type + name +
 * company, so re-running never duplicates nor overwrites edits.
 */
class DemoCompanyFinancialAccountSeeder extends Seeder
{
    /** Keeps the IBAN seeds clear of the ones DemoFinancialAccountSeeder uses (1, 2). */
    private const int IBAN_SEED_BASE = 1000;

    private const int BANK_ACCOUNTS_PER_COMPANY = 2;

    /** @var list<string> */
    private const array BANK_NAMES = [
        'Intesa Sanpaolo',
        'UniCredit',
        'BPER Banca',
        'Banco BPM',
        'Banca Popolare di Bari',
        'BNL',
    ];

    public function run(): void
    {
        Company::query()->orderBy('id')->each(function (Company $company): void {
            for ($slot = 1; $slot <= self::BANK_ACCOUNTS_PER_COMPANY; $slot++) {
                $this->bankAccount($company, $slot);
            }
            $this->cashBox($company);
        });
    }

    private function bankAccount(Company $company, int $slot): void
    {
        $seed = self::IBAN_SEED_BASE + $company->id * 10 + $slot;
        $bankName = self::BANK_NAMES[($company->id + $slot) % count(self::BANK_NAMES)];

        FinancialAccount::firstOrCreate(
            ['iban' => FinancialAccountFactory::fakeIban($seed)],
            [
                'type' => FinancialAccountType::BankAccount,
                'name' => sprintf('%s - c/c %d', $bankName, $slot),
                'company_id' => $company->id,
                'account_number' => str_pad((string) $seed, 12, '0', STR_PAD_LEFT),
                'opening_balance' => $this->openingBalance($company->id, $slot),
                'opening_balance_date' => now()->startOfYear()->toDateString(),
                'notes' => sprintf('Conto corrente di %s', $company->denomination),
            ],
        );
    }

    private function cashBox(Company $company): void
    {
        FinancialAccount::firstOrCreate(
            [
                'type' => FinancialAccountType::Cash,
                'name' => 'Cassa contanti',
                'company_id' => $company->id,
            ],
            [
                'opening_balance' => $this->openingBalance($company->id, 0),
                'opening_balance_date' => now()->startOfYear()->toDateString(),
                'notes' => sprintf('Fondo cassa di %s', $company->denomination),
            ],
        );
    }

    /** Deterministic, plausible balance: thousands for a bank, hundreds for the cash box. */
    private function openingBalance(int $companyId, int $slot): string
    {
        $base = $slot === 0 ? 250 : 12500 * $slot;

        return number_format($base + ($companyId * 137 % 900), 2, '.', '');
    }
}
