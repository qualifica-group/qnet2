<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\LayoutSectionVariant;

/**
 * The APL practices — internships, apprenticeships, orientation — in one
 * place: each is a category with its own offer fields, offer form and working
 * states (user directives 2026-10-02, 2026-10-05). Pure data, read by the
 * seeders that consume them (QualificaCatalogSeeder, QualificaQuoteLayoutSeeder,
 * WorkflowStatusCatalogue), so a new practice is one row per map here rather
 * than one line in each of those files.
 *
 * Each category is also cut off the "APL" root (CategoryInheritanceRules) and
 * declared under it in QualificaCatalogSeeder::CATALOG.
 */
final class AplPracticeCatalogue
{
    /**
     * Category name => its OFFERTA-context attributes.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>, config?: array<string, mixed>}>>
     */
    public const array QUOTE_ATTRIBUTES = [
        AplInternshipAttributeCatalogue::CATEGORY => AplInternshipAttributeCatalogue::ATTRIBUTES,
        ApprenticeshipAttributeCatalogue::CATEGORY => ApprenticeshipAttributeCatalogue::ATTRIBUTES,
        AplOrientationAttributeCatalogue::CATEGORY => AplOrientationAttributeCatalogue::ATTRIBUTES,
    ];

    /**
     * Category name => its offer form. Kept per category because the practices
     * share attributes ("Decreto", "ID decreto", "ID rendicontazione", "Data
     * fine"): filtered out of one common list, each would also render the
     * others' sections.
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>>
     */
    public const array FORMS = [
        AplInternshipAttributeCatalogue::CATEGORY => AplInternshipAttributeCatalogue::SECTIONS,
        ApprenticeshipAttributeCatalogue::CATEGORY => ApprenticeshipAttributeCatalogue::SECTIONS,
        AplOrientationAttributeCatalogue::CATEGORY => AplOrientationAttributeCatalogue::SECTIONS,
    ];

    /**
     * Category name => the form a previous revision seeded for it, recognised
     * and recomposed by QualificaQuoteLayoutSeeder (see each catalogue's
     * PREVIOUS_SECTIONS).
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>>
     */
    public const array PREVIOUS_FORMS = [
        AplInternshipAttributeCatalogue::CATEGORY => AplInternshipAttributeCatalogue::PREVIOUS_SECTIONS,
        AplOrientationAttributeCatalogue::CATEGORY => AplOrientationAttributeCatalogue::PREVIOUS_SECTIONS,
    ];

    /**
     * Category name => the OFFERTA-context codes a previous revision assigned
     * there and the catalogue no longer does, withdrawn from that category
     * alone by QualificaQuoteLayoutSeeder: the assignments are additive, so
     * dropping a code from a catalogue is otherwise a no-op on an installation
     * already seeded.
     *
     * @var array<string, list<string>>
     */
    public const array RETIRED_ATTRIBUTES = [
        AplInternshipAttributeCatalogue::CATEGORY => AplInternshipAttributeCatalogue::RETIRED_ATTRIBUTES,
    ];

    /**
     * Section key => its working states, for WorkflowStatusCatalogue::SECTIONS.
     *
     * @var array<string, array<string, array{legend: string, description: string}>>
     */
    public const array STATUS_SECTIONS = [
        AplInternshipWorkflowStatusCatalogue::SECTION => AplInternshipWorkflowStatusCatalogue::STATUSES,
        ApprenticeshipWorkflowStatusCatalogue::SECTION => ApprenticeshipWorkflowStatusCatalogue::STATUSES,
        AplOrientationWorkflowStatusCatalogue::SECTION => AplOrientationWorkflowStatusCatalogue::STATUSES,
    ];

    /**
     * Category name => its workflow, for WorkflowStatusCatalogue::WORKFLOWS:
     * matched on the EXACT category, so each outranks the "APL" branch list.
     *
     * @var array<string, array{section: string}>
     */
    public const array WORKFLOWS = [
        AplInternshipAttributeCatalogue::CATEGORY => ['section' => AplInternshipWorkflowStatusCatalogue::SECTION],
        ApprenticeshipAttributeCatalogue::CATEGORY => ['section' => ApprenticeshipWorkflowStatusCatalogue::SECTION],
        AplOrientationAttributeCatalogue::CATEGORY => ['section' => AplOrientationWorkflowStatusCatalogue::SECTION],
    ];
}
