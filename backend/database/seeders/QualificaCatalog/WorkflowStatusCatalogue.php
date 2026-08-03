<?php

namespace Database\Seeders\QualificaCatalog;

use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use InvalidArgumentException;

/**
 * The client's "stati di lavorazione" catalogue (spec 0047): the working-state
 * pick list each product category drives, transcribed from the client's
 * "Stati di Lavorazione_Commerciale" sheet. Pure data, like
 * TrainingCourseCatalogue and SelfFundedCourseCatalogue — QualificaWorkflowSeeder
 * holds the logic, this file holds the rows.
 *
 * The sheet is organised in four blocks; each becomes one SECTION here, and a
 * section is bound to one or more product categories by WORKFLOWS below:
 *
 *   1. GOL FORMAZIONE  — one column per region, each a DIFFERENT subset of the
 *                        same status vocabulary, in its own order. Hence the
 *                        explicit `statuses` list per `GOL - <Regione>`.
 *   2. AUTOIMPIEGO/YISU — "uguale per tutte le regioni": one list, two
 *                        categories.
 *   3. AUTOFINANZIATO  — one list, one category.
 *   4. CONSULENZA      — one list, bound to the "Consulenza" ROOT (user
 *                        decision 2026-07-28).
 *
 * TRANSCRIPTION NOTES (the sheet is a spreadsheet, not a database):
 *   - The same state is spelled differently across columns. Folded to ONE
 *     canonical form (the spelling used by the majority of the columns, user
 *     decision 2026-07-28): "Attesa Esito SFL/ADI" -> "Attesa esito SFL/ADI",
 *     "Trasferito altra sede QG" -> "Trasferito altra Sede QG", "Non pertinente
 *     altra Regione" -> "Non pertinente - Altra regione", "Numero Inesistente"
 *     -> "Numero Inesistente/Errato". A per-column variant would otherwise
 *     produce distinct statuses for one and the same state.
 *   - "GOL - Abruzzo" and "DIL" have no column in the sheet: no workflow is
 *     seeded for them, so their opportunities fall back to the GLOBAL default
 *     status set (OpportunityWorkflowResolver).
 *   - Descriptions come from the sheet's second page and are scoped PER
 *     SECTION: the same name ("Da Richiamare", "Irreperibile", "Doppione")
 *     carries a different description in each block.
 */
final class WorkflowStatusCatalogue
{
    /**
     * The CriterionFieldRegistry allow-list key every seeded workflow matches
     * on: the product category of the opportunity's product lines.
     */
    public const string CRITERION_FIELD = 'product_category_id';

    /**
     * The sheet's LEGEND, the only thing that classifies a state: a cell's
     * fill colour. Mapped to the WorkflowStatusGroup the row is persisted with
     * plus the badge colour token (BADGE_COLOR_TOKENS) that keeps the sheet's
     * own reading in the grid.
     *
     * `Regional` is the sheet's yellow fill — "stato di lavorazione aperto,
     * usato solo dalla regione di pertinenza". It is an OPEN state like the
     * unfilled one; the distinction survives only as the badge colour, since
     * every workflow here is already scoped to a single region.
     *
     * @var array<string, array{group: string, color: string}>
     */
    public const array LEGEND = [
        self::OPEN => ['group' => WorkflowStatusGroup::Open->value, 'color' => 'slate'],
        self::REGIONAL => ['group' => WorkflowStatusGroup::Open->value, 'color' => 'yellow'],
        self::POSITIVE => ['group' => WorkflowStatusGroup::ClosedWon->value, 'color' => 'green'],
        self::NEGATIVE => ['group' => WorkflowStatusGroup::ClosedLost->value, 'color' => 'red'],
    ];

    private const string OPEN = 'open';

    private const string REGIONAL = 'regional';

    private const string POSITIVE = 'positive';

    private const string NEGATIVE = 'negative';

    private const string GOL = 'gol';

    private const string SELF_EMPLOYMENT = 'self_employment';

    private const string SELF_FUNDED = 'self_funded';

    private const string CONSULTING = 'consulting';

    /**
     * Section key => status name => its legend bucket and its description.
     * Declaration order IS the seeded order for every section whose categories
     * take the whole list (see WORKFLOWS).
     *
     * @var array<string, array<string, array{legend: string, description: string}>>
     */
    public const array SECTIONS = [
        self::GOL => [
            'Da Richiamare' => ['legend' => self::OPEN, 'description' => 'Contatto da ricontattare per completare la lavorazione o fornire ulteriori informazioni.'],
            'Attesa esito SFL/ADI' => ['legend' => self::OPEN, 'description' => 'In attesa dell\'esito relativo alla pratica SFL/ADI del candidato.'],
            'Attesa Attivazione DOTE' => ['legend' => self::REGIONAL, 'description' => 'In attesa dell\'attivazione della DOTE necessaria per procedere.'],
            'Attesa DOC APL' => ['legend' => self::REGIONAL, 'description' => 'In attesa della documentazione da parte del settore APL.'],
            'Inviata MAIL APL' => ['legend' => self::REGIONAL, 'description' => 'Comunicazione inviata all\'APL in attesa di riscontro.'],
            'Attesa _ App. APL' => ['legend' => self::REGIONAL, 'description' => 'In attesa della definizione e fissazione dell\'appuntamento presso l\'APL per procedere con le attività previste.'],
            'OK App. Fissato APL' => ['legend' => self::REGIONAL, 'description' => 'Appuntamento con APL fissato e confermato.'],
            'APL-Orientamento' => ['legend' => self::REGIONAL, 'description' => 'Candidato preso in carico dall\'APL per lo svolgimento dell\'attività di orientamento prevista.'],
            'Attesa _ App. CPI' => ['legend' => self::OPEN, 'description' => 'In attesa della definizione dell\'appuntamento presso il CPI.'],
            'OK App. Fissato CPI' => ['legend' => self::OPEN, 'description' => 'Appuntamento presso CPI fissato e confermato.'],
            'Attesa ok Assegno GOL' => ['legend' => self::REGIONAL, 'description' => 'In attesa della conferma/autorizzazione dell\'Assegno GOL necessaria per procedere con il percorso del candidato.'],
            'Attesa Iscrizione SIUF' => ['legend' => self::REGIONAL, 'description' => 'In attesa del caricamento su piattaforma SIUF.'],
            'In attesa aggancio BES' => ['legend' => self::REGIONAL, 'description' => 'Candidato in attesa dell\'associazione/aggancio al servizio BES per poter procedere con le attività previste del percorso.'],
            'Associato SI _ NOI' => ['legend' => self::POSITIVE, 'description' => 'Candidato associato correttamente al percorso/ente NOI.'],
            'Percorso 101' => ['legend' => self::NEGATIVE, 'description' => 'Candidato inserito nel percorso 101.'],
            'Autofinanziato' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che segue un percorso autofinanziato.'],
            'Associato NO _ Altro Ente' => ['legend' => self::NEGATIVE, 'description' => 'Candidato associato a un altro ente diverso da NOI.'],
            'Frequenta già corso GOL' => ['legend' => self::NEGATIVE, 'description' => 'Candidato già iscritto o frequentante un corso GOL.'],
            'NO _ Non ha Requisiti' => ['legend' => self::NEGATIVE, 'description' => 'Candidato non idoneo per mancanza dei requisiti previsti.'],
            'Non interessato/a' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che ha comunicato di non essere interessato al percorso.'],
            'Stato Rinunciatario' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che ha rinunciato volontariamente al percorso.'],
            'Irreperibile' => ['legend' => self::NEGATIVE, 'description' => 'Impossibile contattare il candidato dopo i tentativi effettuati.'],
            'Trasferito altra Sede QG' => ['legend' => self::NEGATIVE, 'description' => 'Candidato trasferito presso un\'altra sede QG.'],
            'Non pertinente - Altra regione' => ['legend' => self::NEGATIVE, 'description' => 'Candidato non pertinente perché appartenente a un\'altra regione in cui non siamo accreditati.'],
            'Numero Inesistente/Errato' => ['legend' => self::NEGATIVE, 'description' => 'Recapito telefonico non valido o inesistente.'],
            'Doppione' => ['legend' => self::NEGATIVE, 'description' => 'Record duplicato presente nel sistema.'],
            'Doppione già associato' => ['legend' => self::NEGATIVE, 'description' => 'Record duplicato già collegato a un\'associazione esistente.'],
            'In Standby' => ['legend' => self::OPEN, 'description' => 'Pratica temporaneamente sospesa in attesa di ulteriori sviluppi.'],
        ],
        self::SELF_EMPLOYMENT => [
            'Da Richiamare' => ['legend' => self::OPEN, 'description' => 'Candidato da ricontattare per completare la lavorazione, fornire informazioni o aggiornare la pratica.'],
            'Attesa Documenti' => ['legend' => self::OPEN, 'description' => 'In attesa della ricezione della documentazione necessaria per procedere con la gestione della pratica.'],
            'Problema DOC' => ['legend' => self::OPEN, 'description' => 'Documentazione mancante, incompleta, errata o con anomalie che impediscono il proseguimento della pratica.'],
            'Attesa App. CPI' => ['legend' => self::OPEN, 'description' => 'In attesa della definizione e fissazione dell\'appuntamento presso il CPI.'],
            'OK_ App CPI Fissato' => ['legend' => self::OPEN, 'description' => 'Appuntamento presso il CPI fissato e confermato.'],
            'Attesa Termine APL' => ['legend' => self::OPEN, 'description' => 'In attesa del completamento delle attività previste da parte dell\'APL o della conclusione delle verifiche necessarie.'],
            'OK_Da Caricare' => ['legend' => self::POSITIVE, 'description' => 'Pratica verificata e pronta per essere caricata/inserita su piattaforma.'],
            'Associato SI _ NOI' => ['legend' => self::POSITIVE, 'description' => 'Candidato correttamente associato al percorso/ente NOI.'],
            'Non ha i Requisiti' => ['legend' => self::NEGATIVE, 'description' => 'Candidato non idoneo in quanto non possiede i requisiti previsti per l\'accesso al percorso.'],
            'Non Interessato' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che ha comunicato di non essere interessato a proseguire con il percorso proposto.'],
            'Rinunciatario' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che ha deciso di interrompere o non proseguire il percorso dopo l\'adesione iniziale.'],
            'Irreperibile' => ['legend' => self::NEGATIVE, 'description' => 'Candidato non raggiungibile dopo i tentativi di contatto effettuati.'],
            'Doppione' => ['legend' => self::NEGATIVE, 'description' => 'Anagrafica o pratica duplicata già presente nel sistema.'],
            'In Standby' => ['legend' => self::OPEN, 'description' => 'Pratica temporaneamente sospesa in attesa di ulteriori informazioni, aggiornamenti o condizioni necessarie per procedere.'],
        ],
        self::SELF_FUNDED => [
            'Da Richiamare' => ['legend' => self::OPEN, 'description' => 'Candidato da ricontattare per completare la gestione del contatto, fornire informazioni o procedere con le attività successive.'],
            'Pre-Iscrizione' => ['legend' => self::OPEN, 'description' => 'Candidato che ha manifestato interesse ed è stato inserito nella fase iniziale di raccolta dati e avvio della procedura di iscrizione.'],
            'Appuntamento' => ['legend' => self::OPEN, 'description' => 'Appuntamento fissato con il candidato per approfondire la proposta, verificare l\'interesse o procedere con la fase successiva.'],
            'In trattativa' => ['legend' => self::OPEN, 'description' => 'Candidato in fase di valutazione della proposta, con contatti e approfondimenti ancora in corso prima della definizione dell\'esito.'],
            'OK_Iscritto' => ['legend' => self::POSITIVE, 'description' => 'Candidato che ha completato correttamente l\'iscrizione ed è stato confermato nel percorso.'],
            'Non risponde' => ['legend' => self::OPEN, 'description' => 'Tentativi di contatto effettuati senza ricevere risposta dal candidato.'],
            'Irreperibile' => ['legend' => self::NEGATIVE, 'description' => 'Candidato non raggiungibile dopo diversi tentativi di contatto tramite i recapiti disponibili.'],
            'Esito negativo - prezzo' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che non ha aderito per motivazioni legate al costo o al prezzo della proposta.'],
            'Esito negativo - altri motivi' => ['legend' => self::NEGATIVE, 'description' => 'Candidato che non ha aderito per motivazioni diverse dal prezzo.'],
            'Non attinente' => ['legend' => self::NEGATIVE, 'description' => 'Contatto o candidato non coerente con il servizio, il percorso o la proposta prevista.'],
            'Non possiede titolo di studio' => ['legend' => self::NEGATIVE, 'description' => 'Candidato escluso perché non in possesso del titolo di studio richiesto per l\'accesso al percorso.'],
            'Numero inesistente' => ['legend' => self::NEGATIVE, 'description' => 'Recapito telefonico errato, inesistente o non valido.'],
            'Doppione' => ['legend' => self::NEGATIVE, 'description' => 'Anagrafica o contatto duplicato già presente nel sistema.'],
        ],
        self::CONSULTING => [
            'Da Richiamare' => ['legend' => self::OPEN, 'description' => 'Contatto da ricontattare per fornire informazioni, aggiornamenti o proseguire la gestione della trattativa.'],
            'Trattativa' => ['legend' => self::OPEN, 'description' => 'Opportunità in fase di valutazione/negoziazione, con attività ancora in corso prima della definizione dell\'esito finale.'],
            'Appuntamento' => ['legend' => self::OPEN, 'description' => 'Appuntamento fissato con il cliente/candidato per approfondire la proposta o procedere con la fase successiva.'],
            'Rimandata' => ['legend' => self::OPEN, 'description' => 'Trattativa o contatto posticipato a una data successiva in attesa di un nuovo confronto o aggiornamento.'],
            'VINTO' => ['legend' => self::POSITIVE, 'description' => 'Trattativa conclusa positivamente.'],
            'Persa' => ['legend' => self::NEGATIVE, 'description' => 'Trattativa conclusa negativamente senza finalizzazione.'],
            'Annullata' => ['legend' => self::NEGATIVE, 'description' => 'Trattativa o appuntamento annullato e non più proseguito.'],
            'NR' => ['legend' => self::OPEN, 'description' => 'Nessuna risposta ricevuta dopo i tentativi di contatto effettuati (Non Risponde).'],
            'Irreperibile' => ['legend' => self::NEGATIVE, 'description' => 'Contatto non raggiungibile dopo diversi tentativi tramite i recapiti disponibili.'],
            'Non pertinente' => ['legend' => self::NEGATIVE, 'description' => 'Contatto non coerente con il servizio, la proposta o il target previsto.'],
            'Numero inesistente' => ['legend' => self::NEGATIVE, 'description' => 'Recapito telefonico errato, inesistente o non valido.'],
        ],
    ];

    /**
     * The column shared verbatim by Molise, Puglia, Calabria and Basilicata —
     * extracted rather than copied four times, since the sheet itself gives
     * them one and the same list.
     *
     * @var list<string>
     */
    private const array GOL_BASE_STATUSES = [
        'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa _ App. CPI', 'OK App. Fissato CPI', 'Associato SI _ NOI',
        'Percorso 101', 'Autofinanziato', 'Associato NO _ Altro Ente', 'Frequenta già corso GOL',
        'NO _ Non ha Requisiti', 'Non interessato/a', 'Stato Rinunciatario', 'Irreperibile',
        'Trasferito altra Sede QG', 'Non pertinente - Altra regione', 'Numero Inesistente/Errato', 'Doppione',
        'Doppione già associato', 'In Standby',
    ];

    /**
     * Product category name => the section it draws its statuses from, and —
     * for the GOL regions only — the ordered SUBSET that region's column
     * lists. `statuses` omitted means "the whole section, in declaration
     * order", which is what the three single-column blocks of the sheet say.
     *
     * The category names are bound by identity to QualificaCatalogSeeder::
     * CATALOG: a rename there breaks loudly here instead of silently dropping
     * a whole workflow.
     *
     * @var array<string, array{section: string, statuses?: list<string>}>
     */
    public const array WORKFLOWS = [
        'GOL - Lombardia' => ['section' => self::GOL, 'statuses' => [
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa Attivazione DOTE', 'Attesa DOC APL', 'Inviata MAIL APL',
            'OK App. Fissato APL', 'Attesa _ App. CPI', 'OK App. Fissato CPI', 'Attesa Iscrizione SIUF',
            'In attesa aggancio BES', 'Associato SI _ NOI', 'Percorso 101', 'Autofinanziato',
            'Associato NO _ Altro Ente', 'NO _ Non ha Requisiti', 'Non interessato/a', 'Stato Rinunciatario',
            'Irreperibile', 'Non pertinente - Altra regione', 'Frequenta già corso GOL', 'Trasferito altra Sede QG',
            'Numero Inesistente/Errato', 'Doppione', 'Doppione già associato', 'In Standby',
        ]],
        'GOL - Campania' => ['section' => self::GOL, 'statuses' => [
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa _ App. CPI', 'OK App. Fissato CPI', 'APL-Orientamento',
            'Associato SI _ NOI', 'Percorso 101', 'Autofinanziato', 'Associato NO _ Altro Ente',
            'NO _ Non ha Requisiti', 'Frequenta già corso GOL', 'Non interessato/a', 'Stato Rinunciatario',
            'Irreperibile', 'Trasferito altra Sede QG', 'Non pertinente - Altra regione', 'Numero Inesistente/Errato',
            'Doppione', 'Doppione già associato', 'In Standby',
        ]],
        'GOL - Lazio' => ['section' => self::GOL, 'statuses' => [
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa _ App. CPI', 'Attesa _ App. APL', 'OK App. Fissato CPI',
            'OK App. Fissato APL', 'APL-Orientamento', 'Associato SI _ NOI', 'Percorso 101', 'Autofinanziato',
            'Associato NO _ Altro Ente', 'Frequenta già corso GOL', 'NO _ Non ha Requisiti', 'Non interessato/a',
            'Stato Rinunciatario', 'Irreperibile', 'Trasferito altra Sede QG', 'Non pertinente - Altra regione',
            'Numero Inesistente/Errato', 'Doppione', 'Doppione già associato', 'In Standby',
        ]],
        'GOL - Sicilia' => ['section' => self::GOL, 'statuses' => [
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa _ App. CPI', 'Attesa _ App. APL', 'OK App. Fissato CPI',
            'OK App. Fissato APL', 'APL-Orientamento', 'Associato SI _ NOI', 'Percorso 101', 'Autofinanziato',
            'Associato NO _ Altro Ente', 'Frequenta già corso GOL', 'NO _ Non ha Requisiti', 'Non interessato/a',
            'Stato Rinunciatario', 'Irreperibile', 'Trasferito altra Sede QG', 'Non pertinente - Altra regione',
            'Numero Inesistente/Errato', 'Doppione', 'Doppione già associato', 'In Standby',
        ]],
        'GOL - Umbria' => ['section' => self::GOL, 'statuses' => [
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa ok Assegno GOL', 'Attesa _ App. CPI',
            'OK App. Fissato CPI', 'Associato SI _ NOI', 'Percorso 101', 'Autofinanziato',
            'Associato NO _ Altro Ente', 'Frequenta già corso GOL', 'NO _ Non ha Requisiti', 'Non interessato/a',
            'Stato Rinunciatario', 'Irreperibile', 'Trasferito altra Sede QG', 'Non pertinente - Altra regione',
            'Numero Inesistente/Errato', 'Doppione', 'Doppione già associato', 'In Standby',
        ]],
        'GOL - Molise' => ['section' => self::GOL, 'statuses' => self::GOL_BASE_STATUSES],
        'GOL - Puglia' => ['section' => self::GOL, 'statuses' => self::GOL_BASE_STATUSES],
        'GOL - Calabria' => ['section' => self::GOL, 'statuses' => self::GOL_BASE_STATUSES],
        'GOL - Basilicata' => ['section' => self::GOL, 'statuses' => self::GOL_BASE_STATUSES],
        'Autoimpiego' => ['section' => self::SELF_EMPLOYMENT],
        'Yisu' => ['section' => self::SELF_EMPLOYMENT],
        'Autofinanziato' => ['section' => self::SELF_FUNDED],
        'Consulenza' => ['section' => self::CONSULTING],
    ];

    /**
     * The groups whose first sheet row is PROMOTED onto the matching pinned
     * system row (user decision 2026-07-28): every set is created with four
     * pinned rows whose default labels ("Aperta", "Chiusa positiva", "Chiusa
     * negativa") belong to no block of the sheet, so each takes the label of
     * the first state the sheet classifies under its own group instead — the
     * `open` one lands on "Da Richiamare" in every block.
     *
     * `validated` is deliberately absent: it is not promoted BY GROUP (no
     * sheet state is classified as such) but by NAME, see VALIDATED_STATUSES.
     *
     * @var list<string>
     */
    private const array PINNED_GROUPS = [
        WorkflowStatusGroup::Open->value,
        WorkflowStatusGroup::ClosedWon->value,
        WorkflowStatusGroup::ClosedLost->value,
    ];

    /**
     * Section key => the ONE state that carries the optional 'validated'
     * system row. Only "OK_Da Caricare" does — "pratica verificata e pronta
     * per essere caricata" is exactly the working phase's last step, esito
     * accertato ma non ancora chiuso (user directive 2026-08-03). Every other
     * section is seeded WITHOUT a validated row: it is optional and has no
     * default.
     *
     * A section listed here loses that state from its closed_won promotion:
     * pinnedStatusesFor() removes it before picking the first ClosedWon row,
     * so the positive outcome falls to the next one ("Associato SI _ NOI").
     *
     * @var array<string, string>
     */
    private const array VALIDATED_STATUSES = [
        self::SELF_EMPLOYMENT => 'OK_Da Caricare',
    ];

    /**
     * The sheet row promoted onto each system row of $categoryName's set,
     * keyed by system key and shaped to the CreateOpportunityWorkflowData
     * system-row contract (no `group`: a pinned row's group is fixed by its
     * system key). Null for a key the category's list never fills — the three
     * MANDATORY rows then keep the writer's default label, while a null
     * `validated` means the set gets no validated row at all.
     *
     * @return array<string, array{name: string, description: string, color: string, requires_note: bool}|null>
     */
    public static function pinnedStatusesFor(string $categoryName): array
    {
        $validatedName = self::validatedStatusNameFor($categoryName);

        // The validated state is claimed BEFORE the group promotions, so it
        // never doubles as the closed_won row of its own section.
        $statuses = array_values(array_filter(
            self::statusesFor($categoryName),
            static fn (array $status): bool => $status['name'] !== $validatedName,
        ));

        $promoted = [WorkflowStatusSystemKey::Validated->value => self::promotable(
            array_find(self::statusesFor($categoryName), static fn (array $status): bool => $status['name'] === $validatedName),
        )];

        foreach (self::PINNED_GROUPS as $group) {
            $promoted[$group] = self::promotable(
                array_find($statuses, static fn (array $status): bool => $status['group'] === $group),
            );
        }

        return $promoted;
    }

    /**
     * The state $categoryName's section marks as the validated one, or null
     * when it declares none (VALIDATED_STATUSES).
     */
    private static function validatedStatusNameFor(string $categoryName): ?string
    {
        $workflow = self::WORKFLOWS[$categoryName] ?? throw new InvalidArgumentException("Unknown workflow category [{$categoryName}].");

        return self::VALIDATED_STATUSES[$workflow['section']] ?? null;
    }

    /**
     * @param  array{name: string, description: string, color: string, group: string, requires_note: bool}|null  $status
     * @return array{name: string, description: string, color: string, requires_note: bool}|null
     */
    private static function promotable(?array $status): ?array
    {
        return $status === null ? null : [
            'name' => $status['name'],
            'description' => $status['description'],
            'color' => $status['color'],
            'requires_note' => $status['requires_note'],
        ];
    }

    /**
     * $categoryName's rows left as CUSTOM rows: the full list minus the ones
     * promoted onto a pinned system row above. Keeping a promoted row in both
     * places would duplicate its label inside one set — which the
     * (opportunity_workflow_id, name) unique index rejects outright.
     *
     * @return list<array{name: string, description: string, color: string, group: string, requires_note: bool}>
     */
    public static function customStatusesFor(string $categoryName): array
    {
        $promotedNames = array_column(array_filter(self::pinnedStatusesFor($categoryName)), 'name');

        return array_values(array_filter(
            self::statusesFor($categoryName),
            static fn (array $status): bool => ! in_array($status['name'], $promotedNames, true),
        ));
    }

    /**
     * $categoryName's ordered status rows, EVERY one of them — the sheet
     * column transcribed as it stands, before pinnedStatusesFor()/
     * customStatusesFor() split it between the pinned system rows and the
     * custom ones.
     *
     * @return list<array{name: string, description: string, color: string, group: string, requires_note: bool}>
     */
    public static function statusesFor(string $categoryName): array
    {
        $workflow = self::WORKFLOWS[$categoryName] ?? throw new InvalidArgumentException("Unknown workflow category [{$categoryName}].");
        $section = self::SECTIONS[$workflow['section']];
        $names = $workflow['statuses'] ?? array_keys($section);

        return array_map(
            static function (string $name) use ($section, $categoryName): array {
                // A region listing a name the section never defines means the
                // two halves of the sheet drifted apart: fail loudly rather
                // than seed a status with no description nor classification.
                $status = $section[$name] ?? throw new InvalidArgumentException("Undefined status [{$name}] for category [{$categoryName}].");
                $legend = self::LEGEND[$status['legend']];

                return [
                    'name' => $name,
                    'description' => $status['description'],
                    'color' => $legend['color'],
                    'group' => $legend['group'],
                    // Nothing in the sheet marks a state as note-requiring.
                    'requires_note' => false,
                ];
            },
            $names,
        );
    }
}
