<?php

namespace Database\Seeders\QualificaCatalog;

use Database\Seeders\QualificaCatalog\DepartmentGrantSets as Sets;

/**
 * The department roles (spec 0408): two per department, `reparto-<key>` and
 * `reparto-<key>-senior`, composed from the grant keys of
 * DepartmentPermissionGrants as the department answered its questionnaire.
 * The senior role holds the base grants plus `senior_grants` (empty but for
 * ISO, Finanza Agevolata, Progettazione, APL, Contabilita' and Back Office,
 * D-1), or `senior_grants` alone where the questionnaire's senior level is
 * not a widening of the base one (`standalone_senior`, Commerciale). A person of several departments or offices holds one role each, even
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

    public const string BACK_OFFICE = 'reparto-backoffice';

    public const string COMMERCIAL = 'reparto-commerciale';

    public const string COMMERCIAL_FISCAL_PROFILES = 'reparto-commerciale-profili-fiscali';

    public const string BACK_OFFICE_TEAM_NOTIFICATIONS = 'reparto-backoffice-notifiche-team';

    /** The grant whose role carries the HR office field matrix (D-7). */
    public const string HR_OFFICE_GRANT = 'hr_office';

    /**
     * `request_sight_from_quotes => false`: the roles keep the request modules
     * the questionnaire answered, instead of the sight derived from the quotes
     * (rule of the roles 2026-10-27, user directive 2026-11-18, Back Office).
     *
     * @var array<string, array{description: string, grants: array<int, string>, senior_grants: array<int, string>, standalone_senior?: bool, request_sight_from_quotes?: bool}>
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
        self::BACK_OFFICE => [
            'description' => 'Reparto Back Office',
            'grants' => Sets::BACK_OFFICE,
            'senior_grants' => Sets::BACK_OFFICE_SENIOR,
            'request_sight_from_quotes' => false,
        ],
        self::COMMERCIAL => [
            'description' => 'Reparto Commerciale',
            'grants' => Sets::COMMERCIAL,
            'senior_grants' => Sets::COMMERCIAL_SUPERVISOR,
            'standalone_senior' => true,
        ],
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
        self::COMMERCIAL_FISCAL_PROFILES => ['description' => 'Reparto Commerciale (profili fiscali agenti)', 'grants' => Sets::COMMERCIAL_FISCAL_PROFILES],
        self::BACK_OFFICE_TEAM_NOTIFICATIONS => ['description' => 'Reparto Back Office (notifiche del team)', 'grants' => Sets::BACK_OFFICE_TEAM_NOTIFICATIONS],
    ];

    public static function senior(string $role): string
    {
        return $role.self::SENIOR_SUFFIX;
    }

    /**
     * The grant keys of a department's senior role.
     *
     * @param  array{grants: array<int, string>, senior_grants: array<int, string>, standalone_senior?: bool}  $department
     * @return array<int, string>
     */
    public static function seniorGrants(array $department): array
    {
        return ($department['standalone_senior'] ?? false)
            ? $department['senior_grants']
            : [...$department['grants'], ...$department['senior_grants']];
    }
}
