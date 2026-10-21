<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The HR office's application role and the user-record data only it may see
 * (spec 0283, user directive 2026-10-19). Pure data, read by
 * QualificaRoleSeeder (the matrices) and QualificaHrOfficeSeeder (who holds
 * the role). Kept apart from OperatorRoleCatalogue, which is a mansionario
 * of building blocks; this role is a module list.
 */
final class HrOfficeRoleCatalogue
{
    public const string ROLE = 'risorse-umane';

    public const string DESCRIPTION = 'Risorse Umane';

    /** The org-chart function whose heads and members receive ROLE. */
    public const string FUNCTION = BusinessFunctionCatalogue::HUMAN_RESOURCES;

    /**
     * The HR modules, every ability included, delete too (D-1), plus the
     * training and skills modules of specs 0284/0286 (D-7).
     *
     * @var array<int, string>
     */
    public const array MODULES = [
        'users',
        'collective-agreements',
        'job-roles',
        'job-activities',
        'business-functions',
        'org-chart',
        'absence-requests',
        'absence-types',
        'contract-types',
        'staff-courses',
        'staff-trainings',
        'skills',
        'user-skills',
    ];

    /**
     * Withheld from MODULES: signing in as another user would let the office
     * act as an `admin` (D-1).
     *
     * @var array<int, string>
     */
    public const array DENIED_PERMISSIONS = [
        'users.impersonate',
    ];

    /**
     * Outside MODULES (D-1): everyone's time entries read-only, with the
     * monthly export, and the companies the contract conditions point at.
     *
     * @var array<int, string>
     */
    public const array EXTRA_PERMISSIONS = [
        'time-entries.viewAll',
        'time-entries.exportMonthly',
        'companies.viewAny',
        'companies.view',
    ];

    /**
     * The contractual data of the user record (D-2): hidden from every
     * mansione, granted to this role and to `admin`.
     *
     * @var array<string, array<int, string>>
     */
    public const array SENSITIVE_FIELDS = [
        'users' => [
            'employment.relationship_type',
            'employment.company_id',
            'employment.contracts',
            'employment.hired_at',
            'employment.terminated_at',
            'employment.standard_daily_minutes',
            'employment.break_daily_minutes',
            'employment.iban',
            'employment.iban_holder',
        ],
    ];

    /**
     * Seen but not changeable by the office (D-1): it may not grant itself, or
     * anyone, a wider role.
     *
     * @var array<string, array<int, string>>
     */
    public const array READONLY_FIELDS = [
        'users' => ['roles'],
    ];
}
