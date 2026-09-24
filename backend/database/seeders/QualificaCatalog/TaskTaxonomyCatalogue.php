<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;

/**
 * The client's Task classification vocabulary (spec 0101): the four PURE
 * lookups a Task is classified on, plus the status pick-list. Pure data,
 * consumed by Database\Seeders\QualificaTaskTaxonomySeeder — the logic lives
 * there, the rows live here (file-size split, engineering.md §6).
 *
 * Names are user-facing domain values, kept in their original language, and
 * are the natural key of every idempotent insert. `color` is a token of
 * App\Support\BadgeTokens::colors(), `icon` a name of ::icons(): the same
 * allow-lists the FormRequests validate against, so every seeded badge
 * resolves to a swatch and a glyph the grid can actually render.
 */
final class TaskTaxonomyCatalogue
{
    /**
     * Tipologia: WHAT the activity is.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const array TYPES = [
        ['Attività', 'blue', 'activity'],
        ['Riunione', 'indigo', 'users'],
        ['Chiamata', 'green', 'phone'],
        ['Ticket', 'amber', 'ticket'],
        ['Email', 'teal', 'mail'],
        ['Visita', 'orange', 'map-pin'],
        ['Altro', 'slate', 'box'],
    ];

    /**
     * Categoria: WHICH area of the business the activity belongs to.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const array CATEGORIES = [
        ['Altro', 'slate', 'layers'],
        ['Amministrativo', 'gray', 'file-text'],
        ['Commerciale', 'green', 'briefcase'],
        ['Contabile', 'emerald', 'calculator'],
        ['CRM', 'blue', 'users'],
        ['Direzionale', 'indigo', 'compass'],
        ['Legale', 'red', 'landmark'],
        ['Marketing', 'pink', 'target'],
        ['Non classificato', 'slate', 'inbox'],
        ['Operativo', 'orange', 'cog'],
        ['Organizzativo', 'amber', 'calendar-days'],
        ['Segreteria', 'teal', 'clipboard-list'],
        ['Sviluppo software', 'violet', 'code'],
        ['Tecnico (generico)', 'yellow', 'wrench'],
        ['Tecnico (informatico)', 'purple', 'laptop'],
    ];

    /**
     * Priorita': HOW SOON the activity has to be dealt with. Ordered by
     * severity, and the palette ramps slate to red with it, so the badge
     * reads as a scale and not as a set of unrelated labels.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const array PRIORITIES = [
        ['Bassa', 'slate', 'flag'],
        ['Media', 'blue', 'gauge'],
        ['Alta', 'amber', 'flame'],
        ['Urgente', 'orange', 'alarm-clock'],
        ['Critica', 'red', 'zap'],
    ];

    /**
     * Importanza: HOW MUCH the activity matters. Same severity ramp as the
     * priorities, distinct icon family (bookmark to gem) so the two badges
     * stay tellable apart on the same Task row.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    public const array IMPORTANCES = [
        ['Bassa', 'slate', 'bookmark'],
        ['Media', 'blue', 'star'],
        ['Alta', 'orange', 'medal'],
        ['Massima', 'red', 'gem'],
    ];

    /**
     * The client's chosen defaults (spec 0154, D-8): the row a new Task
     * falls back to when the field is omitted. Names, not ids — matched the
     * same way as every other row here, by the natural `name` key.
     */
    public const string DEFAULT_TYPE = 'Attività';

    public const string DEFAULT_PRIORITY = 'Media';

    public const string DEFAULT_IMPORTANCE = 'Media';

    /**
     * The client's ORDINARY statuses, in the order they are worked through:
     * name, phase, color, icon, completion percentage. None of them carries
     * a `system_key` — they are renameable, reorderable and deletable from
     * the module like any other row. The phase is what decides behaviour
     * (D-7), never the label.
     *
     * "Assegnato" is NOT here: spec 0118 D-5 promotes it to a PROTECTED row
     * (below), the landing status for a Task whose creation-time derivation
     * does not qualify for `open` (spec 0118 D-4).
     *
     * "Interrotto" sits in `Pending`, NOT in a closing phase (user directive
     * 2026-09-04): it suspends the Task rather than closing it, so it does
     * not demand a closure feedback. Its 0% says the work produced nothing,
     * which is independent of the phase.
     *
     * The two `InValidation` rows carry 100, not their former 30/80 (spec
     * 0126, D-5): validation is a gate, not a stage of progress, so a task
     * waiting on it already reads as done. An already-seeded install is
     * moved onto 100 by migration 2026_09_14_140000, which this bootstrap
     * value must stay in sync with.
     *
     * @var array<int, array{0: string, 1: TaskStatusGroup, 2: string, 3: string, 4: int}>
     */
    public const array STATUSES = [
        ['In preanalisi', TaskStatusGroup::Open, 'indigo', 'eye', 20],
        ['Preanalisi da validare', TaskStatusGroup::InValidation, 'amber', 'shield', 100],
        ['Preanalisi validata', TaskStatusGroup::Open, 'teal', 'shield-check', 40],
        ['In attesa controparte', TaskStatusGroup::Pending, 'orange', 'clock', 60],
        ['Interrotto', TaskStatusGroup::Pending, 'red', 'flag', 0],
        ['Esecuzione da validare', TaskStatusGroup::InValidation, 'amber', 'clipboard-list', 100],
    ];

    /**
     * The five PROTECTED rows, keyed by `system_key`: the client's wording
     * for the status a Task opens in, the one it resumes on when reopened
     * (spec 0116 D-4), the one a creation-time derivation lands on when it
     * does not qualify for `open` (spec 0118 D-4/D-5), and the two it closes
     * in.
     *
     * `in_progress` and `assigned` differ from the other three: neither is
     * reshaped from a migration bootstrap name, because their own migrations
     * (2026_09_11_100000, 2026_09_11_110000) create/promote the row ALREADY
     * carrying the client's own wording — there is no generic placeholder to
     * rewrite away from. `bootstrap_name` and `name` are therefore identical
     * on both, on purpose, which also makes `reshapeProtectedStatuses()`
     * below a no-op for either key on every seed run (the row already
     * carries `$target['name']`), not a source of duplication.
     *
     * The other three are RESHAPED here rather than created: `system_key` is
     * not mass-assignable and the rows already exist, inserted by
     * 2026_09_04_100400 with generic bootstrap names. The seeder matches
     * them by KEY, never by label (D-5), and only rewrites one while it
     * still carries the bootstrap name below — an admin rename is left
     * alone (AC-005).
     *
     * "Chiuso negativo" keeps its bootstrap name: the client's own list has
     * no negative-closing wording, and dropping the row would leave the
     * `ClosedNegative` phase with nowhere to land, so D-7's negative-closure
     * feedback could never fire.
     *
     * @var array<string, array{bootstrap_name: string, name: string, group: TaskStatusGroup, color: string, icon: string, completion_percentage: int}>
     */
    public const array PROTECTED_STATUSES = [
        TaskStatusSystemKey::Open->value => [
            'bootstrap_name' => 'Aperto',
            'name' => 'Da assegnare',
            'group' => TaskStatusGroup::Open,
            'color' => 'slate',
            'icon' => 'inbox',
            'completion_percentage' => 0,
        ],
        TaskStatusSystemKey::InProgress->value => [
            'bootstrap_name' => 'In corso',
            'name' => 'In corso',
            'group' => TaskStatusGroup::Open,
            'color' => 'violet',
            'icon' => 'activity',
            'completion_percentage' => 50,
        ],
        TaskStatusSystemKey::Assigned->value => [
            'bootstrap_name' => 'Assegnato',
            'name' => 'Assegnato',
            'group' => TaskStatusGroup::Open,
            'color' => 'blue',
            'icon' => 'user',
            'completion_percentage' => 10,
        ],
        TaskStatusSystemKey::ClosedPositive->value => [
            'bootstrap_name' => 'Chiuso positivo',
            'name' => 'Esecuzione validata',
            'group' => TaskStatusGroup::ClosedPositive,
            'color' => 'green',
            'icon' => 'badge-check',
            'completion_percentage' => 100,
        ],
        TaskStatusSystemKey::ClosedNegative->value => [
            'bootstrap_name' => 'Chiuso negativo',
            'name' => 'Chiuso negativo',
            'group' => TaskStatusGroup::ClosedNegative,
            'color' => 'red',
            'icon' => 'lock',
            'completion_percentage' => 0,
        ],
    ];
}
