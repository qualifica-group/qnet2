<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Enums\FinancialCardCircuit;
use App\Enums\FinancialCardType;
use App\Models\FinancialAccount;
use App\Rules\ValidIban;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    /** Incrementing counter backing the unique fake IBAN. */
    private static int $nextIban = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->bankAccountAttributes();
    }

    public function bankAccount(): static
    {
        return $this->state(fn (): array => $this->bankAccountAttributes());
    }

    public function card(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialAccountType::Card,
            'name' => fake()->company().' Bank',
            'iban' => null,
            'account_number' => null,
            'card_type' => FinancialCardType::Prepaid,
            'card_circuit' => fake()->randomElement(FinancialCardCircuit::cases()),
            'card_holder' => fake()->name(),
            'card_number' => '4111111111111111',
            'card_last_four' => '1111',
            'card_expiry' => '12/2030',
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'type' => FinancialAccountType::Cash,
            'name' => 'Cash '.fake()->unique()->word(),
            'iban' => null,
            'account_number' => null,
        ]);
    }

    /**
     * A valid, unique Italian IBAN built from a numeric seed (check digits
     * computed, so it passes ValidIban).
     */
    public static function fakeIban(int $seed): string
    {
        $bban = 'X'.str_pad((string) $seed, 22, '0', STR_PAD_LEFT);
        $check = 98 - ValidIban::mod97($bban.'IT00');

        return 'IT'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$bban;
    }

    /**
     * @return array<string, mixed>
     */
    private function bankAccountAttributes(): array
    {
        return [
            'type' => FinancialAccountType::BankAccount,
            'name' => fake()->company().' Bank',
            'iban' => self::fakeIban(self::$nextIban++),
            'account_number' => (string) fake()->numerify('############'),
            'address_line' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('#####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
