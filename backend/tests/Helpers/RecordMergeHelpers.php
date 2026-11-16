<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Note;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

if (! function_exists('mergeSuperAdmin')) {
    function mergeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super-admin', Guard::getDefaultName(User::class)));

        return $user;
    }
}

if (! function_exists('mergeActorWith')) {
    /**
     * A non super-admin actor holding exactly the given `{module}.{ability}` permissions.
     *
     * @param  array<int, string>  $permissions
     */
    function mergeActorWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, Guard::getDefaultName(User::class)));
        }

        return $user;
    }
}

if (! function_exists('mergeCompany')) {
    /**
     * A company registry with its card.
     *
     * @param  array<string, mixed>  $registry
     * @param  array<string, mixed>  $card
     */
    function mergeCompany(string $companyName, array $registry = [], array $card = []): Registry
    {
        return Registry::factory()
            ->withPersonalData(static fn ($factory) => $factory->company()->state(['company_name' => $companyName, 'tax_code' => null, 'vat_number' => null, ...$card]))
            ->create(['agreement_notes' => null, 'general_notes' => null, 'vat_group' => null, 'employee_count' => null, ...$registry])
            ->load('personalData');
    }
}

if (! function_exists('mergeReferent')) {
    /**
     * An individual referent with its card.
     *
     * @param  array<string, mixed>  $referent
     * @param  array<string, mixed>  $card
     */
    function mergeReferent(string $firstName, string $lastName, array $referent = [], array $card = []): Referent
    {
        return Referent::factory()
            ->withoutType()
            ->withPersonalData(static fn ($factory) => $factory->individual()->state(['first_name' => $firstName, 'last_name' => $lastName, 'tax_code' => null, 'vat_number' => null, ...$card]))
            ->create(['notes' => null, ...$referent])
            ->load('personalData');
    }
}

if (! function_exists('mergeContact')) {
    function mergeContact(Registry|Referent $owner, string $type, string $value, bool $primary = false): Contact
    {
        return $owner->personalData->contacts()->create(['type' => $type, 'value' => $value, 'is_primary' => $primary, 'is_work' => false]);
    }
}

if (! function_exists('mergeNote')) {
    function mergeNote(Registry|Referent $owner, string $body, ?int $parentId = null): Note
    {
        $note = new Note(['body' => $body]);
        $note->forceFill(['notable_type' => $owner->getMorphClass(), 'notable_id' => $owner->id, 'parent_id' => $parentId, 'user_id' => User::factory()->create()->id])->save();

        return $note;
    }
}

if (! function_exists('mergeDocument')) {
    function mergeDocument(Registry $owner, string $name): Attachment
    {
        return Attachment::factory()->create(['attachable_type' => $owner->getMorphClass(), 'attachable_id' => $owner->id, 'original_name' => $name]);
    }
}
