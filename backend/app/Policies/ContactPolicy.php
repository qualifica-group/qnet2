<?php

namespace App\Policies;

use App\Authorization\PersonalDataChildAuthorization;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for the Contacts endpoints, derived from the record that owns
 * the contact's personal-data card (spec 0328): no `contacts.*` permission of
 * its own, so nothing to assign on the Role form.
 */
class ContactPolicy
{
    private const string SECTION = 'contacts';

    public function __construct(private readonly PersonalDataChildAuthorization $children) {}

    public function view(User $user, Contact $contact): bool
    {
        return $this->children->canView($user, $contact->contactable, self::SECTION);
    }

    /**
     * @param  Model  $card  the owner the new contact is attached to
     */
    public function create(User $user, Model $card): bool
    {
        return $this->children->canWrite($user, $card, self::SECTION);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->children->canWrite($user, $contact->contactable, self::SECTION);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->children->canWrite($user, $contact->contactable, self::SECTION);
    }
}
