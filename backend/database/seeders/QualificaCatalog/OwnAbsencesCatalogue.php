<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The Assenze building block every mansione holds (spec 0292, user directive
 * 2026-10-21: "assenze come segnatempo visibili a tutti"), listed in
 * OperatorRoleCatalogue::EVERY_ROLE_BLOCKS. Kept apart because that catalogue
 * sits at the file size limit. Pure data, read by QualificaRoleSeeder.
 */
final class OwnAbsencesCatalogue
{
    public const string BLOCK = 'own-absences';

    /**
     * The block's grants (D-1): open Assenze, file one's own requests, the
     * calendar of oneself and of the people reporting to the actor, also
     * indirectly (D-2). Everyone's absences — `manageAll`, `calendarAll` —
     * stay with the HR office and `admin` (D-3). An allow-list, so an ability
     * added to the module later does not reach every role by default.
     *
     * @var array<int, string>
     */
    public const array PERMISSIONS = [
        'absence-requests.viewAny',
        'absence-requests.create',
        'absence-requests.calendarOwn',
        'absence-requests.calendarTeam',
    ];
}
