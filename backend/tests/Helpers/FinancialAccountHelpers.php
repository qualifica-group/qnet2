<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\FinancialAccountFactory;
use Spatie\Permission\Models\Permission;

if (! function_exists('financialAccountUserWith')) {
    /**
     * A user holding exactly the given financial-accounts abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function financialAccountUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'revealCardNumber'] as $ability) {
            Permission::findOrCreate("financial-accounts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("financial-accounts.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('bankAccountPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function bankAccountPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'bank_account',
            'name' => 'Banca Test',
            'iban' => FinancialAccountFactory::fakeIban(900001),
            'account_number' => '000123456789',
        ], $overrides);
    }
}

if (! function_exists('cardPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function cardPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'card',
            'card_type' => 'prepaid',
            'name' => 'Banca Carta',
            'card_circuit' => 'visa',
            'card_holder' => 'Mario Rossi',
            'card_number' => '4111 1111 1111 1111',
            'card_expiry' => '12/2030',
        ], $overrides);
    }
}

if (! function_exists('cashPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function cashPayload(array $overrides = []): array
    {
        return array_merge(['type' => 'cash', 'name' => 'Cassa Test'], $overrides);
    }
}
