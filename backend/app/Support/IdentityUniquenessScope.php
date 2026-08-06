<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The entities whose personal-data cards share ONE identity namespace (user
 * directive 2026-08-06): a codice fiscale, a partita IVA or a phone number
 * already held by a USER, by an ANAGRAFICA or by a REFERENTE blocks the
 * creation of another anagrafica or referente carrying it.
 *
 * Supersedes the per-module scope of 2026-08-03, under which each module was
 * checked only against itself and the user accounts were never consulted.
 *
 * `CompanySite` also owns cards through the `personable` morph and is
 * deliberately OUT: a site is a location of a company that its anagrafica
 * already represents, so its switchboard number is not a second person.
 *
 * The scope is a single source of truth for both surfaces that enforce the
 * constraint — `UniquePersonalDataIdentifier` (fiscal columns) and
 * `ValidatesPhoneUniqueness` (contact rows) — so the two can never drift apart.
 */
final class IdentityUniquenessScope
{
    /** @var array<int, class-string<Model>> */
    private const array OWNERS = [User::class, Registry::class, Referent::class];

    /**
     * The cards inside the namespace, minus the one owned by the record under
     * edit — keeping its own values must stay a no-op, not a self-collision.
     *
     * The owner is excluded as a (type, id) PAIR, never by id alone: the three
     * morphs have independent primary keys, so referent #5 and registry #5 both
     * exist and dropping "personable_id = 5" would blind the check to the other.
     *
     * @param  class-string<Model>|null  $ignoreOwnerClass
     * @return Builder<PersonalData>
     */
    public static function cards(?string $ignoreOwnerClass = null, ?int $ignoreOwnerId = null): Builder
    {
        return PersonalData::query()
            ->whereIn('personable_type', self::morphClasses())
            ->when(
                $ignoreOwnerClass !== null && $ignoreOwnerId !== null,
                fn (Builder $query) => $query->whereNot(
                    fn (Builder $owned) => $owned
                        ->where('personable_type', self::morphClass($ignoreOwnerClass))
                        ->where('personable_id', $ignoreOwnerId),
                ),
            );
    }

    /**
     * @return array<int, string>
     */
    private static function morphClasses(): array
    {
        return array_map(self::morphClass(...), self::OWNERS);
    }

    /**
     * @param  class-string<Model>  $ownerClass
     */
    private static function morphClass(string $ownerClass): string
    {
        /** @var Model $owner */
        $owner = new $ownerClass;

        return $owner->getMorphClass();
    }
}
