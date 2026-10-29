<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Authorizes a child row of a personal-data card (an address, a contact)
 * through the record that owns the card (spec 0328): the parent's own policy
 * plus the `personal_data.{section}` row of the parent's field-permission
 * matrix, the same row the parent form reads to show or lock the section.
 *
 * Fails closed: a row not owned by a card, a card without an owner, or an
 * owner type with no authorization resource is never authorized.
 */
final class PersonalDataChildAuthorization
{
    public function __construct(private readonly AuthorizationRegistry $registry) {}

    /**
     * @param  Model|null  $card  the row's owner (`addressable` / `contactable`)
     */
    public function canView(User $actor, ?Model $card, string $section): bool
    {
        $permission = $this->sectionPermission($actor, $card, $section, 'view');

        return $permission !== null && $permission->visible;
    }

    /**
     * @param  Model|null  $card  the row's owner (`addressable` / `contactable`)
     */
    public function canWrite(User $actor, ?Model $card, string $section): bool
    {
        $permission = $this->sectionPermission($actor, $card, $section, 'update');

        return $permission !== null && $permission->editable && ! $permission->disabled;
    }

    private function sectionPermission(User $actor, ?Model $card, string $section, string $parentAbility): ?FieldPermission
    {
        // Step 1: the parent record that owns the card, and its resource.
        $owner = $card instanceof PersonalData ? $card->personable : null;
        $resource = $owner === null ? null : $this->resourceOf($owner);

        if ($resource === null) {
            return null;
        }

        // Step 2: the parent's own policy (record-level rules included).
        if (! Gate::forUser($actor)->allows($parentAbility, $owner)) {
            return null;
        }

        // Step 3: the section row of the parent's field matrix.
        return $this->registry->resolve($resource)->fieldPermissions($actor, $owner)["personal_data.{$section}"] ?? null;
    }

    private function resourceOf(Model $owner): ?string
    {
        /** @var array<string, string> $resources */
        $resources = config('personal_data.owner_resources', []);

        return $resources[$owner->getMorphClass()] ?? null;
    }
}
