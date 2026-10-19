<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's job roles (Mansioni, spec 0271) with the activities each one
 * carries, from the "Pianta Organica" workbook — the union of the four area
 * classes below, merged per the user directive 2026-10-19:
 *   - a role name never carries a site or a region (one "Operatore Politiche
 *     Attive", not one per region): the site lives on the user's record;
 *   - a role made of two ("Operatore Politiche Attive + R&S") is split in two
 *     roles, the person is then given both;
 *   - an activity shared by several roles is written with the SAME name in
 *     each of them and is created once; near-identical bullets of the sheet
 *     are merged into one activity, person names and years are dropped.
 *
 * An activity entry is either a bare name or `name => description`; the
 * description is written once, on any one of the roles carrying it.
 */
final class JobRoleCatalogue
{
    /** @var array<string, array{description: string, activities: array<int|string, string>}> */
    public const array ROLES = [
        ...AdministrationJobRoles::ROLES,
        ...CommercialJobRoles::ROLES,
        ...TrainingJobRoles::ROLES,
        ...ConsultancyJobRoles::ROLES,
    ];

    /**
     * Every activity once, in catalogue order: the first description found
     * for a name wins.
     *
     * @return array<string, string|null>
     */
    public static function activities(): array
    {
        $activities = [];

        foreach (self::ROLES as $role) {
            foreach ($role['activities'] as $key => $value) {
                [$name, $description] = is_int($key) ? [$value, null] : [$key, $value];
                $activities[$name] ??= $description;
            }
        }

        return $activities;
    }

    /**
     * @param  array<int|string, string>  $activities  one role's `activities` entry
     * @return list<string>
     */
    public static function activityNames(array $activities): array
    {
        return array_map(
            fn (int|string $key, string $value): string => is_int($key) ? $value : $key,
            array_keys($activities),
            $activities,
        );
    }
}
