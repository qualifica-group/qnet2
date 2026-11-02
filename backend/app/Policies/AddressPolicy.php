<?php

namespace App\Policies;

use App\Authorization\PersonalDataChildAuthorization;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for the Addresses endpoints, derived from the record that
 * owns the address's personal-data card (spec 0328): no `addresses.*`
 * permission of its own, so nothing to assign on the Role form.
 */
class AddressPolicy
{
    private const string SECTION = 'addresses';

    public function __construct(private readonly PersonalDataChildAuthorization $children) {}

    public function view(User $user, Address $address): bool
    {
        return $this->children->canView($user, $address->addressable, self::SECTION);
    }

    /**
     * @param  Model  $card  the owner the new address is attached to
     */
    public function create(User $user, Model $card): bool
    {
        return $this->children->canWrite($user, $card, self::SECTION);
    }

    public function update(User $user, Address $address): bool
    {
        return $this->children->canWrite($user, $address->addressable, self::SECTION);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->children->canWrite($user, $address->addressable, self::SECTION);
    }
}
