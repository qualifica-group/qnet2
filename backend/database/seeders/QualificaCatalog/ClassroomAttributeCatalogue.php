<?php

namespace Database\Seeders\QualificaCatalog;

/**
 * The client's "Dati Aula (Formalab)" attributes: the classroom-edition data
 * tracked on every Formazione offer — the teacher, the classroom state, the
 * course and its edition, the course/exam dates and the internship placement.
 * Pure data, like CourseDataAttributeCatalogue: QualificaCatalogSeeder creates
 * and assigns them (OFFERTA context, on the Formazione root, so the whole
 * branch inherits them), QualificaQuoteLayoutSeeder groups them into the form
 * section named below. SELF_EMPLOYMENT_ATTRIBUTES is the exception: it belongs
 * to the same section but is assigned on "Autoimpiego" alone.
 *
 * OFFERTA, NOT PRODUCT (user directive 2026-09-08): a classroom edition is
 * what a single deal delivers, not a property of the catalogue entry, so these
 * moved off the product form together with the "Dati corso" pair.
 *
 * `code` is the English identifier (the catalogue's natural key, and its
 * `^[a-z0-9_]+$` format); `name` is the user-facing label, kept in its
 * original language. `teacher` is a RELATION to a referent: `referents` is
 * registered in BOTH config/tables.php and config/authorization.php, which is
 * what makes it a valid custom-fieldable relation target, and it has the
 * `referents/for-select` endpoint the relation control feeds on.
 */
final class ClassroomAttributeCatalogue
{
    /**
     * The title of the layout section grouping every attribute below in the
     * product form (spec 0062). User-facing, kept in its original language.
     */
    public const string SECTION_TITLE = 'Dati Aula';

    /**
     * The `classroom_status` option values (stored keys, English; their
     * user-facing labels live on the attribute options).
     */
    public const string OPEN = 'open';

    public const string CLOSED = 'closed';

    /**
     * `teacher` points at a single referent — the "Referente relazione" of the
     * client's field list.
     *
     * @var array<string, mixed>
     */
    private const array TEACHER_RELATION_TARGET = [
        'entity_type' => 'referents',
        'cardinality' => 'one',
        'for_select_resource' => 'referents',
    ];

    /**
     * @var list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>
     */
    public const array ATTRIBUTES = [
        ['code' => 'teacher', 'name' => 'Docente', 'type' => 'relation', 'relation_target' => self::TEACHER_RELATION_TARGET],
        ['code' => 'classroom_status', 'name' => 'Stato Aula', 'type' => 'enum', 'options' => [
            ['value' => self::OPEN, 'label' => 'Aperta'],
            ['value' => self::CLOSED, 'label' => 'Chiusa'],
        ]],
        ['code' => 'course_name', 'name' => 'Corso', 'type' => 'text'],
        ['code' => 'course_edition', 'name' => 'Edizione', 'type' => 'text'],
        ['code' => 'course_start_date', 'name' => 'Data inizio corso', 'type' => 'date'],
        ['code' => 'course_end_date', 'name' => 'Data fine corso', 'type' => 'date'],
        ['code' => 'exam_date', 'name' => 'Data esame', 'type' => 'date'],
        ['code' => 'internship_company', 'name' => 'Azienda tirocinio', 'type' => 'text'],
        ['code' => 'internship_start_date', 'name' => 'Data inizio tirocinio', 'type' => 'date'],
        ['code' => 'internship_end_date', 'name' => 'Data fine tirocinio', 'type' => 'date'],
    ];

    /**
     * The category selling the self-employment offer — a node of
     * QualificaCatalogSeeder::CATALOG, bound by identity so a rename there
     * breaks loudly here.
     */
    public const string SELF_EMPLOYMENT_CATEGORY = 'Autoimpiego';

    /**
     * The one field of this section scoped to a single subcategory instead of
     * the whole Formazione branch (user directive 2026-09-10): the flag the
     * operator ticks once the candidate has expressed interest. Assigned on
     * SELF_EMPLOYMENT_CATEGORY, so nothing else in the branch resolves the code
     * and QualificaQuoteLayoutSeeder prunes it out of every other "Dati Aula".
     *
     * @var list<array{code: string, name: string, type: string}>
     */
    public const array SELF_EMPLOYMENT_ATTRIBUTES = [
        ['code' => 'interest_expression', 'name' => "Manifestazione d'Interesse", 'type' => 'boolean'],
    ];

    /**
     * The section's rows, paired by meaning (who/what, then the course dates,
     * then the internship, then the self-employment flag): the seeded section
     * is two columns wide and every item is half a row, so a pair renders side
     * by side and collapses to one column on a narrow panel.
     *
     * @var list<list<string>>
     */
    public const array ROWS = [
        ['teacher', 'classroom_status'],
        ['course_name', 'course_edition'],
        ['course_start_date', 'course_end_date'],
        ['exam_date', 'internship_company'],
        ['internship_start_date', 'internship_end_date'],
        ['interest_expression'],
    ];

    /**
     * The codes assigned to the Formazione ROOT — SELF_EMPLOYMENT_ATTRIBUTES
     * excluded, which lives on one subcategory.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::ATTRIBUTES, 'code');
    }
}
