<?php

namespace Database\Seeders;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialCardCircuit;
use App\Enums\FinancialCardType;
use App\Models\Company;
use App\Models\FinancialAccount;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Seeder;

/**
 * Seed fake financial accounts (spec 0189): two bank accounts with valid fake
 * IBANs, a credit card linked to the first one, a prepaid card without a link
 * and a cash box. Idempotent: each row is keyed by its type + name (the IBAN
 * for bank accounts), so re-running never duplicates nor overwrites edits. The
 * card numbers are well-known test PANs, never real data.
 */
class DemoFinancialAccountSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::query()->orderBy('id')->value('id');

        $main = $this->bankAccount('Banca Demo Uno', 1, '000000000001', $companyId);
        $this->bankAccount('Banca Demo Due', 2, '000000000002', null);

        $this->card('Carta di credito Demo', FinancialCardType::Credit, FinancialCardCircuit::Visa, '4111111111111111', $main->id);
        $this->card('Carta prepagata Demo', FinancialCardType::Prepaid, FinancialCardCircuit::Mastercard, '5555555555554444', null);

        FinancialAccount::firstOrCreate(
            ['type' => FinancialAccountType::Cash, 'name' => 'Cassa contanti Demo'],
            ['company_id' => $companyId, 'notes' => 'Fondo cassa della sede'],
        );
    }

    private function bankAccount(string $name, int $ibanSeed, string $accountNumber, ?int $companyId): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['iban' => FinancialAccountFactory::fakeIban($ibanSeed)],
            [
                'type' => FinancialAccountType::BankAccount,
                'name' => $name,
                'account_number' => $accountNumber,
                'company_id' => $companyId,
            ],
        );
    }

    private function card(string $name, FinancialCardType $cardType, FinancialCardCircuit $circuit, string $number, ?int $linkedAccountId): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['type' => FinancialAccountType::Card, 'name' => $name],
            [
                'card_type' => $cardType,
                'card_circuit' => $circuit,
                'card_holder' => 'Mario Rossi',
                'card_number' => $number,
                'card_last_four' => substr($number, -4),
                'card_expiry' => '12/2030',
                'linked_account_id' => $linkedAccountId,
            ],
        );
    }
}
