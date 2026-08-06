<?php

namespace Database\Seeders;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Models\Source;
use App\Services\QuoteWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the opportunity workflow configurator (spec 0047): a
 * few realistic workflows plus an enriched GLOBAL default status set, so the
 * demo shows the working-state dimension in action.
 *
 * Every workflow is created through QuoteWorkflowService::create() — the
 * same path POST /api/quote-workflows uses — so this exercises the real
 * write path (signature uniqueness, the 3 pinned system rows, criteria sync),
 * not a raw insert. Because DemoDataSeeder runs this BEFORE DemoOpportunitySeeder,
 * the demo opportunities whose source (a criterion below) matches a workflow
 * pick up that workflow's own statuses at creation time (the resolver runs in
 * OpportunityService::create) — the "reference opportunities" that make the
 * feature concrete in the demo grid.
 *
 * Criteria key on `source_id` (a direct Opportunity column DemoOpportunitySeeder
 * assigns round-robin, so matches are deterministic) plus one two-criteria
 * workflow (source + business_function) to exercise the specificity tie-break
 * (AC-011). Idempotent: existing workflows are cleared first (cascading their
 * criteria/statuses), and the default set is re-synced in place.
 *
 * Depends on DemoSourceSeeder (mandatory — the criterion values) and
 * DemoBusinessFunctionSeeder (optional — the two-criteria workflow is skipped
 * when its function is absent). A no-op when no sources are seeded.
 */
class DemoQuoteWorkflowSeeder extends Seeder
{
    /**
     * Custom intermediate rows added to the GLOBAL default set (open/
     * closed_won/closed_lost system rows are seeded by the migration and left
     * untouched). `color` is one of the badge tokens (BADGE_COLOR_TOKENS).
     *
     * @var array<int, array{name: string, description: string, color: string, group: string, requires_note: bool}>
     */
    private const array DEFAULT_CUSTOM_STATUSES = [
        [
            'name' => 'Da lavorare',
            'description' => 'Richiesta presa in carico ma non ancora avviata: nessuna attivita\' svolta.',
            'color' => 'slate',
            'group' => 'open',
            'requires_note' => false,
        ],
        [
            'name' => 'In lavorazione',
            'description' => 'Lavorazione avviata dall\'operatore assegnato, in corso di svolgimento.',
            'color' => 'blue',
            'group' => 'pending',
            'requires_note' => false,
        ],
        [
            'name' => 'In attesa cliente',
            'description' => 'Lavorazione sospesa in attesa di documenti o risposte dal cliente.',
            'color' => 'amber',
            'group' => 'pending',
            'requires_note' => true,
        ],
    ];

    /**
     * Descriptive seed of the three PINNED system rows, keyed by `system_key`
     * — so the demo shows the description marker on the system statuses too,
     * not only on the custom ones. `name` mirrors WorkflowStatusWriter's own
     * default label (the override replaces it wholesale).
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const array SYSTEM_STATUSES = [
        'open' => [
            'name' => 'Aperta',
            'description' => 'Stato iniziale: la richiesta e\' aperta e attende la presa in carico.',
        ],
        'validated' => [
            'name' => 'Validato',
            'description' => 'Lavorazione conclusa e verificata: esito accertato, in attesa della chiusura.',
        ],
        'closed_won' => [
            'name' => 'Chiusa positiva',
            'description' => 'Lavorazione conclusa con esito positivo: nessuna ulteriore azione.',
        ],
        'closed_lost' => [
            'name' => 'Chiusa negativa',
            'description' => 'Lavorazione conclusa senza esito: la richiesta non prosegue.',
        ],
    ];

    public function __construct(private readonly QuoteWorkflowService $workflows) {}

    public function run(): void
    {
        $sources = Source::query()->pluck('id', 'name');

        if ($sources->isEmpty()) {
            // The criterion values live on sources — nothing sensible to seed
            // without them (mirrors DemoOpportunitySeeder's registry guard).
            return;
        }

        // Step 1: clear existing workflows (cascade drops their criteria/
        // statuses) so the signature-uniqueness check never rejects a re-run.
        QuoteWorkflow::query()->delete();

        // Step 2: enrich the always-present global default set.
        $this->syncDefaultSet();

        // Step 3: the demo workflows, most-specific last so a matching
        // opportunity resolves to the two-criteria workflow over the plain one.
        $this->seedSourceWorkflow($sources, 'Website', 'Vendite Web', [
            [
                'name' => 'Primo contatto',
                'description' => 'Primo contatto effettuato con il richiedente, esigenza non ancora qualificata.',
                'color' => 'indigo',
                'group' => 'open',
                'requires_note' => false,
            ],
            [
                'name' => 'Qualificazione',
                'description' => 'Verifica di budget, tempistiche e reale interesse prima di procedere.',
                'color' => 'blue',
                'group' => 'pending',
                'requires_note' => true,
            ],
            [
                'name' => 'Demo prodotto',
                'description' => 'Demo concordata o gia\' erogata: si attende il riscontro del cliente.',
                'color' => 'violet',
                'group' => 'pending',
                'requires_note' => false,
            ],
        ]);

        $this->seedSourceWorkflow($sources, 'Referral', 'Segnalazioni', [
            [
                'name' => 'Verifica segnalazione',
                'description' => 'Controllo della segnalazione ricevuta e del referente che l\'ha inoltrata.',
                'color' => 'teal',
                'group' => 'open',
                'requires_note' => false,
            ],
            [
                'name' => 'Trattativa',
                'description' => 'Negoziazione economica in corso sulla base dell\'offerta inviata.',
                'color' => 'amber',
                'group' => 'pending',
                'requires_note' => true,
            ],
        ]);

        $this->seedWebCommercialWorkflow($sources);
    }

    /**
     * The GLOBAL default set (quote_workflow_id null): its system rows
     * already exist (migration) and are re-submitted unchanged but for their
     * description; the custom intermediate rows are full-replaced. Idempotent
     * on re-run.
     */
    private function syncDefaultSet(): void
    {
        $customRows = array_map(
            static fn (array $status): array => [
                'id' => null,
                'name' => $status['name'],
                'description' => $status['description'],
                'color' => $status['color'],
                'group' => $status['group'],
                'requires_note' => $status['requires_note'],
            ],
            self::DEFAULT_CUSTOM_STATUSES,
        );

        $this->workflows->syncDefaultStatuses([...$this->describedDefaultSystemRows(), ...$customRows]);
    }

    /**
     * The GLOBAL set's pinned rows re-submitted as they are persisted — only
     * `description` is added: the writer accepts every descriptive change on a
     * system row but rejects a `group` one, and the migration-seeded names
     * must not be overwritten here.
     *
     * @return array<int, array{id: int, name: string, description: string, color: ?string, group: string, requires_note: bool}>
     */
    private function describedDefaultSystemRows(): array
    {
        return $this->workflows->defaultStatuses()
            ->filter(static fn (QuoteWorkflowStatus $status): bool => $status->isSystem())
            ->map(static fn (QuoteWorkflowStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'description' => self::SYSTEM_STATUSES[$status->system_key]['description'],
                'color' => $status->color,
                'group' => $status->group->value,
                'requires_note' => $status->requires_note,
            ])
            ->values()
            ->all();
    }

    /**
     * A single-criterion workflow matched on the source named $sourceName.
     *
     * @param  Collection<string, int>  $sources
     * @param  array<int, array{name: string, description: string, color: string, group: string, requires_note: bool}>  $customStatuses
     */
    private function seedSourceWorkflow($sources, string $sourceName, string $name, array $customStatuses): void
    {
        $sourceId = $sources->get($sourceName);

        if ($sourceId === null) {
            return;
        }

        $this->workflows->create(new CreateQuoteWorkflowData(
            name: $name,
            isActive: true,
            criteria: [['field' => 'source_id', 'value_id' => $sourceId]],
            statuses: $this->normalizeCustomStatuses($customStatuses),
            openStatus: self::systemStatusSeed('open'),
            validatedStatus: self::systemStatusSeed('validated'),
            closedWonStatus: self::systemStatusSeed('closed_won'),
            closedLostStatus: self::systemStatusSeed('closed_lost'),
        ));
    }

    /**
     * A two-criteria workflow (source Website AND business function
     * "Commerciale e Vendite"): more specific than the "Vendite Web" workflow,
     * so an opportunity carrying both resolves to THIS one (AC-011). Skipped
     * when either lookup is absent.
     *
     * @param  Collection<string, int>  $sources
     */
    private function seedWebCommercialWorkflow($sources): void
    {
        $sourceId = $sources->get('Website');
        $businessFunctionId = BusinessFunction::query()->where('name', 'Commerciale e Vendite')->value('id');

        if ($sourceId === null || $businessFunctionId === null) {
            return;
        }

        $this->workflows->create(new CreateQuoteWorkflowData(
            name: 'Vendite Web Commerciale',
            isActive: true,
            criteria: [
                ['field' => 'source_id', 'value_id' => $sourceId],
                ['field' => 'business_function_id', 'value_id' => $businessFunctionId],
            ],
            statuses: $this->normalizeCustomStatuses([
                [
                    'name' => 'Analisi esigenze',
                    'description' => 'Raccolta dei requisiti tecnici e commerciali insieme alla funzione aziendale.',
                    'color' => 'blue',
                    'group' => 'open',
                    'requires_note' => false,
                ],
                [
                    'name' => 'Offerta dedicata',
                    'description' => 'Offerta personalizzata inviata: si attende accettazione o revisione.',
                    'color' => 'emerald',
                    'group' => 'pending',
                    'requires_note' => true,
                ],
            ]),
            openStatus: self::systemStatusSeed('open'),
            validatedStatus: self::systemStatusSeed('validated'),
            closedWonStatus: self::systemStatusSeed('closed_won'),
            closedLostStatus: self::systemStatusSeed('closed_lost'),
        ));
    }

    /**
     * Shape a demo status list to the CreateQuoteWorkflowData custom-row
     * contract ({name, description, color, group, requires_note}) — the pinned
     * open/closed_won/closed_lost rows are added by WorkflowStatusWriter, seeded
     * with systemStatusSeed(), not listed here.
     *
     * @param  array<int, array{name: string, description: string, color: string, group: string, requires_note: bool}>  $customStatuses
     * @return array<int, array{name: string, description: ?string, color: ?string, group: string, requires_note: bool}>
     */
    private function normalizeCustomStatuses(array $customStatuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'name' => $status['name'],
                'description' => $status['description'],
                'color' => $status['color'],
                'group' => WorkflowStatusGroup::from($status['group'])->value,
                'requires_note' => $status['requires_note'],
            ],
            $customStatuses,
        );
    }

    /**
     * The descriptive seed WorkflowStatusWriter applies to a pinned row.
     *
     * @return array{name: string, description: string, color: ?string, requires_note: bool}
     */
    private static function systemStatusSeed(string $systemKey): array
    {
        return [
            'name' => self::SYSTEM_STATUSES[$systemKey]['name'],
            'description' => self::SYSTEM_STATUSES[$systemKey]['description'],
            'color' => null,
            'requires_note' => false,
        ];
    }
}
