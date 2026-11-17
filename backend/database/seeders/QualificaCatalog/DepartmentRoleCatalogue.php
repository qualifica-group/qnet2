<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\DepartmentGrantSets as Sets;

/**
 * The department roles (spec 0408): two per department, `reparto-<key>` and
 * `reparto-<key>-senior`, composed from the grant keys of
 * DepartmentPermissionGrants as the department answered its questionnaire.
 * Today the senior role holds the base grants plus `senior_grants` (empty but
 * for ISO, Finanza Agevolata, Progettazione, APL and Contabilita', D-1): the two levels diverge by editing this
 * file. A person of several departments or offices holds one role each, even
 * when their permissions overlap (D-8); what a single person holds on top of
 * their department is an add-on role too, never a direct permission (D-9).
 */
final class DepartmentRoleCatalogue
{
    public const string ROLE_PREFIX = 'reparto-';

    public const string SENIOR_SUFFIX = '-senior';

    public const string SENIOR_DESCRIPTION_SUFFIX = ' (senior)';

    public const string ISO = 'reparto-iso';

    public const string PLANNING = 'reparto-pianificazione';

    public const string SAFETY = 'reparto-sicurezza';

    public const string SAFETY_HEAD = 'reparto-sicurezza-responsabile';

    public const string TRAVEL = 'reparto-travel';

    public const string ACCREDITATION = 'reparto-accreditamenti';

    public const string SUBSIDISED_FINANCE = 'reparto-finanza-agevolata';

    public const string PROJECT_DESIGN = 'reparto-progettazione';

    public const string HUMAN_RESOURCES = 'reparto-hr';

    public const string TENDERS = 'reparto-gare';

    public const string MARKETING = 'reparto-marketing';

    public const string SOA = 'reparto-soa';

    public const string APL = 'reparto-apl';

    public const string ACCOUNTING = 'reparto-contabilita';

    /** The grant whose role carries the HR office field matrix (D-7). */
    public const string HR_OFFICE_GRANT = 'hr_office';

    /**
     * @var array<string, array{description: string, grants: array<int, string>, senior_grants: array<int, string>}>
     */
    public const array DEPARTMENTS = [
        self::ISO => ['description' => 'Reparto ISO/GDPR', 'grants' => Sets::ISO, 'senior_grants' => Sets::PLANNING],
        self::PLANNING => ['description' => 'Ufficio Pianificazione', 'grants' => [...Sets::ISO, ...Sets::PLANNING], 'senior_grants' => []],
        self::SAFETY => ['description' => 'Reparto Sicurezza', 'grants' => Sets::SAFETY, 'senior_grants' => []],
        self::TRAVEL => ['description' => 'Reparto Travel', 'grants' => Sets::TRAVEL, 'senior_grants' => []],
        self::ACCREDITATION => ['description' => 'Reparto Accreditamenti e Gestione Sedi', 'grants' => Sets::TECHNICAL_OFFICE, 'senior_grants' => []],
        self::SUBSIDISED_FINANCE => [
            'description' => 'Reparto Finanza Agevolata, Istruzione e Progettazione',
            'grants' => Sets::SUBSIDISED_FINANCE,
            'senior_grants' => Sets::SUBSIDISED_FINANCE_SENIOR,
        ],
        // Progettazione answered exactly as Finanza Agevolata (same levels).
        self::PROJECT_DESIGN => [
            'description' => 'Reparto Progettazione',
            'grants' => Sets::SUBSIDISED_FINANCE,
            'senior_grants' => Sets::SUBSIDISED_FINANCE_SENIOR,
        ],
        self::HUMAN_RESOURCES => ['description' => 'Reparto HR', 'grants' => Sets::HUMAN_RESOURCES, 'senior_grants' => []],
        self::TENDERS => ['description' => 'Reparto Gare e Avvalimenti', 'grants' => Sets::TECHNICAL_OFFICE, 'senior_grants' => []],
        self::MARKETING => ['description' => 'Reparto Marketing', 'grants' => Sets::MARKETING, 'senior_grants' => []],
        self::SOA => ['description' => 'Reparto SOA', 'grants' => Sets::TECHNICAL_OFFICE, 'senior_grants' => []],
        self::APL => ['description' => 'Reparto APL', 'grants' => Sets::APL, 'senior_grants' => Sets::APL_SENIOR],
        self::ACCOUNTING => ['description' => "Reparto Contabilita'", 'grants' => Sets::ACCOUNTING, 'senior_grants' => Sets::ACCOUNTING_SENIOR],
    ];

    /**
     * Roles held on top of a department role, one level only and without the
     * common baseline: they carry just what the questionnaire grants to one
     * person.
     *
     * @var array<string, array{description: string, grants: array<int, string>}>
     */
    public const array ADD_ON_ROLES = [
        self::SAFETY_HEAD => ['description' => 'Reparto Sicurezza (responsabile)', 'grants' => Sets::SAFETY_HEAD],
    ];

    public static function senior(string $role): string
    {
        return $role.self::SENIOR_SUFFIX;
    }
}
