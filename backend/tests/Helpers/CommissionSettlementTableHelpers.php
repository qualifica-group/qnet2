<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

if (! function_exists('settlementTableRows')) {
    /**
     * Rows endpoint of a settlement table, decoded.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    function settlementTableRows(string $domain, array $body = []): array
    {
        return test()->postJson("/api/tables/{$domain}/rows", $body + ['startRow' => 0, 'endRow' => 50])->assertOk()->json();
    }
}

if (! function_exists('settlementTableIds')) {
    /**
     * Ids of the rows a request returns, in order.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, int>
     */
    function settlementTableIds(string $domain, array $body = []): array
    {
        return array_column(settlementTableRows($domain, $body)['items'], 'id');
    }
}

if (! function_exists('hideSettlementFields')) {
    /**
     * Gives the user a role that hides the given `commission-settlements` fields.
     *
     * @param  array<int, string>  $fields
     */
    function hideSettlementFields(User $user, array $fields): void
    {
        $role = Role::create(['name' => 'hide-'.implode('-', $fields), 'guard_name' => 'web']);
        $user->assignRole($role);

        foreach ($fields as $field) {
            $role->fieldPermissions()->create(['resource' => 'commission-settlements', 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
        }
    }
}
