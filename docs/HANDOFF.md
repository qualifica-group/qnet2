# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

## PRODOTTI — UTILIZZO IN OFFERTA (VENDIBILE / COSTO) — NON COMMITTATO (2026-09-18)

Spec 0142. Enum `App\Enums\ProductUsage` { Sale='SALE', Cost='COST' } (HasMeta, `config.form_enums.product_usage`,
`ProductUsage::forLineType(QuoteLineType)`).
- DB: `products.usages` JSON (cast `AsEnumCollection::of(ProductUsage)`), migrazione `2026_09_18_130000_add_usages_to_products_table`
  backfill `["SALE"]` (decisione utente: esistenti e nuovi = solo Vendibile). Default nel model (`$attributes`).
  `QuoteWorkflowMigrationTest` rollback step 84 -> 85.
- API prodotto: `usages` (`sometimes|array|min:1`, `distinct`, enum) su store/update; `ProductResource.usages`; field permission
  `usages` (multiselect, non mandatory). `GET products/for-select?usage=SALE|COST` (`ForSelectQuery::$productUsage`,
  `whereJsonContains`); `ids[]` bypassa il filtro.
- Enforcement: `QuoteLineWriter::assertProductsUsable()` (unico punto comune a Offerte / Gestione Richieste / inline-edit / conversione
  Lead): riga nuova o con prodotto cambiato -> 422 `{offer_lines|cost_lines}.{i}.product_id` (`quotes.product_not_usable.{SALE|COST}`);
  riga esistente con lo stesso prodotto esente. `ProductOfferLineResolver` salta i prodotti di interesse non vendibili.
- Factory: default ENTRAMBI gli usi (comportamento pre-0142, non rompe i test su cost_lines); stati `saleOnly()` / `costOnly()`.
- FE: `ProductUsageField` (2 checkbox, sezione Classificazione), `DEFAULT_PRODUCT_USAGES` in `product-form-payload.ts`, badge nel
  dettaglio. `QuoteProductSelect` ha prop obbligatoria `usage` (da `variant` in `QuoteLineRow`); il probe autofill richieste invia
  `usage: 'SALE'`. i18n `enums.product_usage.*`, `products.form.usages[Required]`, `products.columns.usages`.
- Test: `tests/Feature/Products/ProductUsageTest.php` (AC-001..007), FE `product-form-usages.test.tsx`, casi in
  `quote-line-row.test.tsx` / `product-form-payload.test.ts`; aggiornati i test che asserivano la shape esatta dei `params` del picker.
- Fuori scope (D-8): colonna griglia prodotti, filtro nel picker "prodotti di interesse".
- Seed demo: `DemoCostProductSeeder` + `DemoCatalog\DemoCostProductCatalogue` (13 voci solo COST: noleggio auto, carburante,
  biglietti treno AV/regionale, aereo, taxi, pedaggio, parcheggio, hotel, pasto, materiale didattico, affitto aula, docente
  esterno) sotto la radice "Spese e Trasferte" SENZA funzione aziendale (mai prodotto di interesse / riga REVENUE). Chiamato da
  `DemoDataSeeder` dopo `DemoProductSeeder`. `DemoProductSeeder` marca i corsi demo SALE+COST e `DemoQuoteSeeder` pesca le righe
  costo tra i prodotti COST. Test `tests/Feature/Products/DemoCostProductSeederTest.php`.
- Nota verifica: `migrate:fresh --seed` su SQLite fallisce in `locations:add` (SQL world, "near ghair") — preesistente, non 0142.

## SEED DEMO vs SPEC 0142 (usages prodotto) — FIX NON COMMITTATO (2026-09-18)

`DemoQuoteSeeder` falliva ("The selected product cannot be used as a cost."): la migrazione 0142 porta tutti i prodotti a
`["SALE"]` e `QuoteLineWriter::assertProductsUsable()` rifiuta le righe costo. Fix: `DemoProductSeeder` crea i prodotti demo
con `usages: [Sale, Cost]`; `DemoQuoteSeeder` pesca le righe costo solo tra prodotti con `usages` contenente `COST`
(nessuno -> offerta senza riga costo). `DemoQuoteSeederTest` rosso senza fix, verde con fix; 152 test seeder verdi.
Un DB gia' popolato resta con prodotti SALE-only (seeder idempotente, non li aggiorna): serve `migrate:fresh` + seed.

## OFFERTE — DESCRIZIONE AGGIUNTIVA SULLA RIGA PRODOTTO — NON COMMITTATO (2026-09-18)

- DB: `quote_lines.additional_description` TEXT nullable (migrazione `2026_09_18_120000_add_additional_description_to_quote_lines_table`).
  `QuoteWorkflowMigrationTest` rollback step 83 -> 84 (poi 84 -> 85 per la migrazione di spec 0142, gia' applicato).
- API: `offer_lines[*]`/`cost_lines[*].additional_description` (`sometimes|nullable|string|max:5000`, `QuoteLineRules::ADDITIONAL_DESCRIPTION_MAX_LENGTH`).
  Chiave ASSENTE = il writer conserva il valore salvato (`QuoteLineData::$hasAdditionalDescription`): griglia/cell PATCH e
  Gestione Richieste non la inviano e non la cancellano. `QuoteLineResource.additional_description`.
- Preventivo: nuova ColumnKey `additional_description` (validator BE + `COLUMN_KEYS` FE + label i18n). `ProductsTableRenderer`
  salta una riga di cella vuota se la cella ha altro contenuto (niente paragrafo bianco sotto il nome).
- FE: `quote-line-additional-description.tsx` (azione compatta -> textarea a tutta riga), solo tab Offerta del form Offerte
  (`variant=revenue && withCommissions`); `linesToFormValues`/`originalLineInputs` idratano il campo solo con `withCommissions`;
  `toLineInputs` invia trim o null solo se la riga ha la chiave. Read-only list mostra il testo sotto la riga.
- Test: BE `QuoteLineAdditionalDescriptionTest` + nuovo caso `ProductsTableRendererTest` (1330 verdi su Quotes/QuoteWorkflows/
  DocumentLayouts/RequestManagement); FE `quote-line-additional-description.test.tsx` + read-only (935 verdi), tsc -b pulito.
- Da fare lato utente: `php artisan migrate`; nel layout del preventivo aggiungere la chiave "Descrizione aggiuntiva riga"
  come seconda riga della colonna prodotto.

## OFFERTE — POPUP COMMISSIONI MOSTRA IL DESTINATARIO SELEZIONATO — NON COMMITTATO (2026-09-18)

`quote-commissions-dialog.tsx`: per i ruoli senza commissione, sotto il titolo del ruolo compare
"Selezionato sull'offerta: <nome>" (da `POST /quotes/commission-recipients`, gated da field permission
`commission_recipient`). Nuova chiave i18n `quotes.form.commissions.selectedRecipient` (it/en). Test aggiornato in
`quote-commissions-dialog.test.tsx`. Vitest quotes 260/260, tsc -b, eslint puliti.

## GESTIONE RICHIESTE — COLONNE REPORT CONFIGURABILI PER CATEGORIA — NON COMMITTATO (2026-09-18)

Spec 0141 (supera 0131 D-4-bis, nota D-6 in 0131). Catalogo colonne resta `config('request-management-report.indicator_columns')`;
`category_columns` (mappa per nome) ELIMINATA: nessuna logica per nome categoria nel codice.
- DB: `product_categories.report_columns` JSON nullable (null = eredita dall'antenato configurato piu' vicino, walk
  strutturale). Migrazione `2026_09_18_110000_add_report_columns_to_product_categories_table` copia lo snapshot D-7 per
  nome. `QuoteWorkflowMigrationTest` rollback step 82 -> 83.
- `App\Services\ProductCategories\ReportColumnsInheritance` (`effectiveMap`, `resolve`, `resolveFromAncestors`);
  normalizzazione unica `ProductCategoryService::normalizeReportColumns()` ([] -> null, ordine catalogo).
- API: `GET product-categories/report-columns` -> `[{key,label}]` (viewAny). Resource `report_columns`; show/store/update
  `effective_report_columns`, `report_columns_source_category`, `inherited_report_columns`,
  `inherited_report_columns_source_category` (solo antenati). Field permission `report_columns` (multiselect).
- Report: colonna non configurata = null (non calcolata), cella vuota nel file; export header = unione colonne
  configurate delle categorie selezionate (ordine catalogo); dashboard per sezione solo le sue colonne, summary = unione.
- Factory: `reportable()` imposta tutto il catalogo (test pre-0141 invariati); state `reportColumns(?array)`.
- Seed produzione: `QualificaCatalog/ReportColumnsCatalogue` chiamato da `QualificaCatalogSeeder`, solo se null.
- FE: `product-category-report-columns-field.tsx` + `product-category-report-column-tile.tsx` (card, contatore, stato
  Proprie/Ereditate da X/Nessuna, Tutte/Nessuna/Torna alle ereditate), hook `use-report-columns-inheritance.ts`,
  `use-report-columns-catalog.ts`; dashboard con stato vuoto tile.
- Verifica: Pest suite completa verde (7918, pre rev-1) + ProductCategories/Report 414/414 dopo rev-1 e test AC-005/006
  (`RequestManagementReportColumnConfigurationTest`); Vitest 5336/5336; tsc -b --force pulito; Pint/ESLint puliti.
- DA VERIFICARE a mano: /product-categories/2/edit (GOL origine: niente "Torna alle ereditate"); export con piu' categorie.

## CATEGORIE PRODOTTO — "VISIBILE NEI REPORT" EREDITABILE CON FORZATURA — NON COMMITTATO (2026-09-18)

Direttiva utente: se il padre è visibile nei report, i figli lo sono di default (ereditano) ma si possono forzare a
"no". Spec 0131 D-5 (supera D-1 "mai ereditata"), AC-007..009.
- DB: `product_categories.is_reportable` ora nullable = override PROPRIO (null = eredita). Migrazione
  `2026_09_18_100000_make_is_reportable_inheritable_on_product_categories_table` (false -> null, report invariato al
  deploy). `QuoteWorkflowMigrationTest` rollback step 81 -> 82.
- `App\Services\ProductCategories\ReportableInheritance`: `effectiveMap()`, `effectiveMapForAll()`, `resolve()`
  (valore + `source_category`). Risolto a lettura, NON denormalizzato.
- API: `is_reportable` `boolean|null` (write `sometimes|nullable|boolean`); show/store/update aggiungono
  `effective_is_reportable` e `is_reportable_source_category`. Tree: override proprio. Tabella: valore effettivo,
  filtro derivato (`FilterApplier::booleanFilterValues` reso public), colonna non ordinabile.
- Report (`ReportBranchResolver`): usa il valore effettivo; figlio forzato a no escluso col sottoalbero anche dai
  totali degli antenati; radice eredita le colonne dall'antenato mappato più vicino (`inheritedColumns`).
- Seed `QualificaCatalogSeeder::REPORTABLE_CATEGORIES` = GOL, Autoimpiego, Yisu, Autofinanziato, DIL, Orientamento
  Specialistico (figli null => ereditano). Consulenza/APL non più reportable nel seed (solo alla creazione: le
  installazioni esistenti non cambiano).
- FE: `reportable-inheritance.ts` (`resolveInheritedReportable`, `reportableOverrideFor`: switch = valore effettivo,
  tornare al valore ereditato salva null), hook `use-reportable-inheritance.ts` usato da tile, header, summary;
  detail usa `effective_is_reportable` + chip "Ereditato da".
- Verifica: Pest suite completa seriale verde (1 flaky noto `CampaignCrudTest` budget casuale, 3/3 da solo);
  Vitest 5307/5307; `tsc -b --force` pulito; ESLint/Pint puliti.
- Tile: "Forzato" solo se un antenato imposta il flag; senza antenato che lo imposta, un valore proprio è il punto
  di partenza del ramo (`isReportableOriginHint`), e l'ereditato "no" senza sorgente mostra `isReportableNoSourceHint`.
- DA VERIFICARE a mano: /product-categories/160/edit (figlio di categoria reportable -> switch attivo + "Ereditata
  da"; spegnendolo compare "Forzato").

## GESTIONE RICHIESTE — REPORT: CATEGORIE AD ALBERO (SOTTOCATEGORIE) — NON COMMITTATO (2026-09-18)

Direttiva utente (dopo due ripensamenti: niente select separato per le sottocategorie): un solo select Categorie ad
albero come il picker "categoria prodotto" di Gestione Richieste.
- Backend: `ReportBranchResolver` emette, sotto ogni categoria reportable (radice = reportable senza antenati
  reportable), ogni sottocategoria a ogni livello in ordine ad albero; `ReportBranch` ha `depth` e `parentKey`; una
  sottocategoria eredita le colonne attive dell'antenato mappato in `category_columns`.
  `GET report/categories` -> `[{key,label,depth,parent_key}]`, solo rami con richieste in perimetro, calcolati con UNA
  query (`usedCategoryIds`) invece di un EXISTS per ramo. Allow-list `category_keys` include le sottocategorie.
- Frontend: `SearchableMultiSelect` accetta `parentValue` sulle opzioni (indentazione + contatore figlie selezionate
  `k/n`, aria-hidden). Click su una madre = ciclo: solo madre -> madre + tutto il sottoalbero -> niente. Figlia = toggle.
  Casella madre: "–" (indeterminate) = madre selezionata SENZA tutto il sottoalbero (tooltip `includeChildrenHint`:
  "clicca di nuovo"), spunta = madre + sottoalbero, vuota = madre non selezionata (anche con figlie scelte).
  Default/reconcile: tutto selezionato (radici + figlie).
- Ogni chiave selezionata e' una riga/sezione propria (la madre aggrega il suo sottoalbero, spec 0131).

## GESTIONE RICHIESTE — REPORT: COLONNE ATTIVE PER CATEGORIA + DEFINIZIONI INDICATORI — NON COMMITTATO (2026-09-18)

Direttiva utente: per ogni categoria solo le sue colonne, le altre tassativamente 0 (dashboard + Excel/CSV).
Spec 0131 D-4-bis (supera D-4).
- `config/request-management-report.php` -> `category_columns` (chiave nome categoria lowercase). Categoria reportable
  non mappata = tutto 0.
- `ReportBranch`: nuovo `columnCategoryIds` + `withColumns()`/`categoryIdsFor()`; `ReportBranchResolver::activeColumns()`;
  `ReportBranchRowsBuilder` calcola solo le colonne attive; dashboard `columnUnionOf()` per il riepilogo complessivo.
- Indicatori: Telefonate esclude primo stato (supera rev-3 AC-058); Richiami = callback <= oggi, qualunque periodo,
  stato non chiuso (supera 0106 D-4/AC-012); `invio_presa_in_carico` = closed_won (stesso `WorkflowTransitionIndicator`).
- Test adeguati (requisito cambiato): Counters AC-058/AC-012 riscritti; fixture filtri Sede/Operatore spostate su uno
  stato "lavorato" (`SiteFilterFixture::workedStatus()`); DynamicCategories riscritto + 2 nuovi casi; nuovo
  `RequestManagementReportTransitionsEndToEndTest` (Potenziali via PATCH reale di Gestione Richieste: OK).
- Decisioni utente 2026-09-18: (1) "Presa appuntamenti" resta 0 per ora (gli stati indicati esistono solo nel
  workflow APL). (2) CORRETTO: `App\Services\Quotes\QuoteStatusChangeLogger` scrive sul trail dell'Opportunity
  (`activity('opportunities')`, stessa shape attributes/old di Gestione Richieste) ogni cambio stato fatto dal modulo
  Offerte (`QuoteService::update`, e `create` con stato esplicito); prima Potenziali/Associati/Trattative/Invio presa
  in carico lo ignoravano. Non registra i riallineamenti amministrativi (QuoteWorkflowResolver delete-reassign,
  `ResyncQuoteWorkflowStatuses`). Test: `Quotes/QuoteStatusChangeActivityTest`, caso Offerte in
  `RequestManagementReportTransitionsEndToEndTest`. (3) Richiami: confermato "fino a oggi, a prescindere dal periodo".

## GESTIONE RICHIESTE — FILTRI DASHBOARD: SELECT RICERCABILI, SEDI -> OPERATORI, CHIP APPLICATI — NON COMMITTATO (2026-09-18)

Direttiva utente: select ricercabili con "seleziona tutto"; Sedi sopra Operatori e operatori limitati alle Sedi
scelte; filtro applicato visibile sulla pagina; grafica da CRM moderno.
- Backend (additivo, spec 0109 emendata): `ReportOperatorAvailabilityResolver::available()` aggiunge `site_keys`
  a ogni operatore (`siteKeysByOperator`, una query sulla pivot). Test: nuovo caso in
  `RequestManagementReportSitesEndpointTest`; shape aggiornato in `RequestManagementReportOperatorsEndpointTest`.
- Nuovo atomo `components/ui/searchable-multi-select.tsx` (`SearchableMultiSelect`): trigger a pill (max 2 + "+N",
  "Tutte..." se completo), popover con ricerca, "Seleziona tutto" tri-state SUI RISULTATI filtrati, footer
  contatore + "Svuota". Label passate dal chiamante (domain-agnostic).
- `request-report-key-group.tsx` ELIMINATO (sostituito da `request-report-key-select-field.tsx`, `KeySelectField`).
- `request-report-site-operators.ts`: `operatorsForSites` (tutte le Sedi -> tutti gli operatori, incluso
  "Non assegnato"; altrimenti solo membri) e `followSiteSelection` ("tutti" resta "tutti", parziale -> intersezione).
  Applicate in `RequestReportFilters.changeSites` via `useFormContext().setValue`.
- `request-dashboard-applied-filters.tsx`: chip per Periodo/Categorie/Sedi/Operatori/Righe, tinta primary sulle
  dimensioni che restringono. `RequestDashboardFilterBar` ora riceve `categories/sites/operators` (non piu' i conteggi).
- i18n: nuovi `requestManagement.report.picker.*`, `report.fields.operatorsBySite`, `dashboard.applied.*`;
  rimossi `dashboard.filtersSummary`/`operatorsSummary`.
- Test aggiornati (requisito cambiato: i gruppi non sono piu' checkbox sempre aperte, i test aprono il picker).
- Verificato: Pest Report 137/137, Vitest 5299/5299, tsc -b pulito, ESLint e Pint puliti. NON verificato a schermo
  (nessun browser automatizzato): controllare 375/768/1024 dentro lo sheet.

## RUOLI QUALIFICA — RAGGIO GESTIONE ISCRITTI PER RUOLO COMMERCIALE — NON COMMITTATO (2026-09-18)

Direttiva utente (corregge la prima versione, gia' nel commit 7bd99898, che toglieva le Sedi al commerciale-iscritti):
- `commerciale`: Gestione Iscritti in sola lettura, SOLO i propri (tier 2, `quotes.operator_id`) — prima non aveva il modulo.
- `commerciale-iscritti`: Gestione Iscritti in sola lettura sulle Sedi (`enrollee-management.viewSite`) — come prima.
- `supervisore-didattica` (proprie + Sedi su entrambi) e supervisore/coordinatore (tutto) invariati.
- `OperatorRoleCatalogue`: `ENROLLEES_READ` (viewAny/view, raggio = proprie) + nuovo blocco `SITE_ENROLLEES`
  (`enrollee-management.viewSite`). `SITE_REQUESTS` torna a concedere SOLO `request-management.viewSite`: i due raggi
  sono indipendenti (il commerciale-iscritti ha Sedi in Iscritti ma solo proprie in Richieste).
- Test: `Seeding/QualificaOperatorSeederTest` (permessi per ruolo + righe HTTP `enrollee-management/rows` per
  commerciale/commerciale-iscritti/supervisore-didattica/supervisore); `Users/QualificaRoleMatrixTest` menu commerciale
  ora include `/enrollee-management` (requisito cambiato).
- Da applicare in produzione: rieseguire `QualificaProductionDataSeeder` (o `QualificaOperatorSeeder`): sync completo dei ruoli.

## SUPERVISORE COMMERCIALE — UTENTI E RUOLI TRANNE CREAZIONE — NON COMMITTATO (2026-09-18)

Direttiva utente: Fabrizio Aliberti e Rosa Falzarano (ruolo `supervisore-commerciale`, unici con quel ruolo nel
roster) vedono le sezioni Utenti e Ruoli con tutti i permessi tranne `create`.
- `OperatorRoleCatalogue`: nuovo blocco `USERS_AND_ROLES` (+ `USERS_AND_ROLES_MODULES` = users, roles;
  `USERS_AND_ROLES_DENIED_ABILITIES` = create), aggiunto ai blocchi di `SUPERVISOR_ROLE`.
- `QualificaRoleSeeder::blockGrants()`: nuovo ramo del match. Concesso quindi anche `users.impersonate`,
  `users.import`, `roles.import`, `export`, `viewActivity`, `update`, `delete`.
- `QualificaRoleMatrixTest`: requisito cambiato → tolti `roles`/`users` dalle asserzioni "chiuso"; nuovo test
  `opens users and roles to the supervisor, creation excluded` + controllo server-side (tabelle 200, `POST /api/roles` 403).
- Verifica: Pest Users/Seeding/Roles/Navigation 371 verdi; Pint pulito.
- In produzione: rieseguire `php artisan db:seed --class=QualificaRoleSeeder` (o QualificaProductionDataSeeder).

## GESTIONE RICHIESTE — LAYOUT COLONNE ATTRIBUTI/TAB NON SALVATO — NON COMMITTATO (2026-09-18)

Segnalazione utente: modificando/spostando le colonne degli attributi flessibili, o gestendo le colonne dal pannello
Colonne di AG Grid, il layout non si salvava correttamente. Il layout e' UNA riga per dominio, ma ogni tab categoria
mostra (e salva) solo le proprie colonne `attr.*`. Tre cause:
- `RequestManagementScopedTableDefinition::defaultColumnLayout()` dava alle `attr.*` `visible: false`, mentre il tab
  le mostra visibili: nascondere una colonna attributo = uguale al default = nessun delta → ricompariva al reload.
  Ora `visible: true` (stesso baseline di `AttributeColumnBuilder::resolved()`).
- `TablePreferenceService::save()` SOSTITUIVA l'intero delta con le sole colonne inviate: salvare dal tab B (o da
  "Tutte") cancellava le personalizzazioni `attr.*` del tab A. Ora una colonna inviata sostituisce il proprio override,
  una non inviata lo conserva; gli override di colonne non piu' definite vengono scartati. Per gli altri domini il
  client invia sempre tutte le colonne: comportamento invariato.
- Frontend: dopo un salvataggio solo la cache config del tab corrente veniva aggiornata; gli altri tab (staleTime
  10 min) mostravano il layout vecchio e la modifica successiva lo risalvava sopra. `useSaveTablePreferences` e
  `useResetTablePreferences` invalidano ora tutte le config del dominio (`tableKeys.configs(domain)`,
  `refetchType: 'none'`); il salvataggio riscrive poi il proprio tab.
- Test: `RequestManagementAttributeColumnPreferencesTest.php` (4, 3 falliscono sul codice precedente),
  `use-table-preferences-cache.test.tsx` (2, falliscono sul codice precedente). Backend Table/RequestManagement/Unit
  2040 + Quotes/Tasks/CustomFields 412 verdi, Pint pulito; Vitest table/data-table/request-management 778 verdi,
  ESLint e `tsc -b --force` puliti.
- Fuori scope, stesso difetto: `use-table-filters.ts` (filtri salvati per dominio) aggiorna solo la cache del tab
  corrente. `QuoteOpportunityScopeTest` isolato fallisce (helper `quoteTableUserWith` definito in un altro file).
- Da verificare a mano: in un tab categoria nascondi/ridimensiona una colonna attributo, passa a un altro tab e
  modifica una colonna, torna al primo tab e ricarica: le modifiche restano.

## GESTIONE RICHIESTE — PATCH CELLA INLINE LENTA (N+1 SULLE CATEGORIE) — COMMITTATO IN 4af506b6 (2026-09-18)

Segnalazione utente: in produzione `PATCH /api/tables/request-management/rows/{id}` (modifica inline da griglia)
lenta, mentre il salvataggio dalla scheda è immediato. Non era AG Grid: misurato nel browser, apertura menu ~45 ms,
valore ottimistico in cella ~35 ms.

- Causa: `TableCellUpdateService` chiama `columns()`, che per `request-management` calcola
  `AttributeScopeResolver::union()` — un giro su OGNI categoria prodotto con ~5 query ciascuna (find + attributi e
  opzioni per ogni livello della catena). Costo lineare nel catalogo: 206 categorie = 1102 query / 486 ms in locale.
- Fix: nuovo `CategoryHierarchy::effectiveAttributesByCategory(AttributeContext)` (3 query fisse, eager load di
  tutte le assegnazioni); composizione estratta in `EffectiveAttributeComposer::compose()` (statico: i test fanno
  `new CategoryHierarchy` senza argomenti), condivisa da `effectiveAttributes()`. `resolveUnion()` usa il batch e
  riempie anche la memo per-categoria. Dopo: 82 query costanti, 206 categorie = 82 ms.
- Test: `tests/Unit/Tables/AttributeScopeResolverUnionTest.php` (union identica al merge per-categoria; query
  costanti al crescere delle categorie). Suite backend seriale 7890/7890, Pint pulito.
- Residuo non toccato: la PATCH rilegge la riga con `baseQuery()->find()` due volte (~14 query ciascuna).

### Seconda passata — costo CPU del batch — NON COMMITTATO (2026-09-18)

Dopo il deploy: prod da ~4 s a ~1 s. Col catalogo di produzione in locale (200 categorie, 253 attributi, 2969
assegnazioni `quote`) `columns()` costava ancora 612 ms con sole 4 query: idratare 2969 `Attribute`+pivot e
ricostruire le stesse voci/opzioni per 4275 entry.
- `effectiveAttributesByCategory()` legge i pivot come righe semplici (`DB::table('attribute_category')`) e
  descrive ogni attributo UNA volta (`EffectiveAttributeComposer::describe()`), riusato da tutte le categorie.
- `compose()` ora riceve "assignment" (`descriptor` + `is_required` + `sort_order`); il percorso per-livello li
  costruisce con `assignmentFromPivot()`. Shape e ordine delle chiavi delle entry invariati.
- Misure locali: `columns()` 612 → 49 ms, PATCH completa 519 → 78 ms. `CategoryHierarchy.php` a 493 righe.
- Test: suite correlate 1200/1200; suite seriale 7889/7890 con 1 flaky preesistente
  (`CampaignCrudTest` budget random del faker, 5/5 verde in isolamento). Pint pulito.

## STATI DI LAVORAZIONE — "NUOVO CONTATTO" PRIMA DI "DA RICHIAMARE" — NON COMMITTATO (2026-09-18)

Direttiva utente 2026-09-18: nel seed di produzione (`QualificaProductionDataSeeder` → `QualificaWorkflowSeeder`)
ogni set di stati apre con lo stato aperto "Nuovo Contatto", subito prima di "Da Richiamare".

- `WorkflowStatusCatalogue`: aggiunto in tutte e 5 le SECTIONS (GOL, Autoimpiego/Yisu, Autofinanziato, Consulenza,
  APL; legend `open`, descrizione unica) e in tutte le liste esplicite (`GOL_BASE_STATUSES`, Lombardia, Campania,
  Lazio, Sicilia, Umbria, DIL). Essendo il primo `open`, ora è lui la riga pinned `system_key='open'`;
  "Da Richiamare" diventa riga custom aperta.
- Test: `QualificaWorkflowSeederTest` aggiornato al nuovo requisito. Seeding/QuoteWorkflows/RequestManagement/
  Opportunities/RoleMatrix verdi, Pint pulito.
- **Attenzione:** il seeder salta i workflow già esistenti (per nome): sugli ambienti già seedati lo stato NON
  compare da solo. Va aggiunto dal configuratore, o serve una migrazione/comando dedicato se richiesto.
- Nota: `tests/Feature/Opportunities` in `--parallel` dà 9 errori preesistenti (`opportunityFromLeadActor()`
  definita in `OpportunityFromLeadTest.php`); in sequenza 238/238.

## GESTIONE RICHIESTE — PANNELLO "STATISTICHE" PERSISTITO VS PERMESSO — NON COMMITTATO (2026-09-17)

Bugfix: lo stato aperto del pannello vive in `localStorage` (`stats-panel:{module.key}`), per browser e non per
utente; solo il bottone era gated da `.report` (spec 0107 D-6), il pannello no. Un utente impersonificato senza
`.report` ereditava il pannello aperto dall'impersonificatore: 403 e nessun bottone per chiuderlo.

- Fix (solo frontend): `request-management-table.tsx` passa a `RequestDashboardPanel` `isDashboardOpen =
  dashboard.isOpen && can(module.permission('report'))`. Derivato al render, la preferenza salvata NON viene
  riscritta: a fine impersonificazione l'impersonificatore ritrova il pannello. Vale anche per Gestione Iscritti.
- Test: nuovo `request-management-table-dashboard-permission.test.tsx` (fallback senza permesso + ripristino con
  permesso). Suite `request-management` + `stats` 470/470, ESLint e `tsc -b --force` puliti.
- Nota: `request-management-table.tsx` è a 476 righe (soglia hard 500): al prossimo intervento va splittato.

## LEAD — CAMPO "REGIONE" RIMOSSO (ANCHE A DB) — NON COMMITTATO (2026-09-17)

Direttiva utente 2026-09-17: il campo Regione del Lead non serviva più a niente (restava dal criterio workflow
della spec 0047, già tolto dall'Opportunità il 2026-09-01). Spec 0047 aggiornata (AMENDMENT 2026-09-17).

- DB: migrazione `2026_09_17_140000_drop_state_id_from_leads_table` (drop FK + colonna `leads.state_id`, pulizia
  `role_field_permissions` leads/state_id; `down()` ripristina solo la struttura). `QuoteWorkflowMigrationTest`:
  rollback portato a 81 passi. **Da eseguire `php artisan migrate`** sugli ambienti.
- Backend: via `Lead::state()`/fillable, regole `state_id` in Store/UpdateLeadRequest, `state`/`state_id` di
  `LeadResource`, `stateId`/`stateIdSubmitted` nei DTO, derivazione dalla Sede in `LeadService`. Via anche `meta`
  {state_id, state_label} di `OperationalSiteForSelectResource` (unico consumatore: il form Lead) e l'eager load
  `state` in `OperationalSiteService::forSelectBaseQuery`. Contratto: `POST/PATCH /leads` non accetta più `state_id`
  (chiave ignorata), `GET /leads/{id}` non la espone, `operational-sites/for-select` non ha più `meta`.
- Frontend: tolta la select Regione dal form e il campo dal dettaglio; tipi, schema Zod, payload, chiavi i18n
  `leads.form.state`/`stateSearch` rimossi. Sezione "Dettagli" del form ridisegnata: Sede | Operatore affiancati
  (coppia collegata dal filtro), Fonte a tutta riga sotto; descrizione della sezione aggiornata.
- Test: eliminati `LeadStateTest.php` e `lead-form-body-region.test.tsx` (requisito ritirato); aggiornati
  `LeadConversionTest`, `OperationalSiteForSelectTest`, e i test Vitest del form/dettaglio/schema/payload lead.
- Verifica: Pest Leads/OperationalSites/Imports/Opportunities/Campaigns + migration test 764/765 (1 flaky casuale
  in `DemoOpportunitySeederTest`, verde 4/4 da solo); Vitest leads/operational-sites/i18n 374/374; `tsc -b --force`
  pulito; Pint ed ESLint puliti.

## LEAD — "CONVERTI IN OPPORTUNITÀ" DIRETTO + OFFERTA COLLEGATA — NON COMMITTATO (2026-09-17)

Spec `docs/specs/0140-lead-convert-creates-offer.xml`. Richiesta utente: la conversione singola deve funzionare come
l'import (opportunità + offerta) e senza aprire il form opportunità precompilato.

- Backend: nuovo `Services/Opportunities/LeadConversionOfferCreator` (estratto da `ConvertLeadToOpportunity`: controllo
  `single` + 2+ prodotti → 422 `offer_lines`, poi `QuoteService::create` con una riga REVENUE per prodotto di interesse,
  zero righe se non ce ne sono). Lo chiama `OpportunityService::create()` quando `lead_id` è valorizzato, dentro la
  transazione, poi `refresh()` (il nome viene ricalcolato dai prodotti, spec 0077). Quindi TUTTI i percorsi con lead
  (import, checkbox, massiva, form opportunità con lead scelto a mano / deep-link `?lead_id=`) creano l'offerta;
  `ConvertLeadToOpportunity` non la crea più da sé. Nessuna offerta senza `lead_id`.
- Frontend: `use-lead-conversion.ts` (sostituisce `.tsx`) chiama `POST /leads/convert-to-opportunities` con un solo id,
  nessun form né popup; toast successo/motivo blocco/messaggio 422. `lead-conversion-error.ts` (`conversionBlockers`)
  condiviso con `ConvertLeadsDialog`. Tabella lead: riga busy durante la conversione, refresh griglia+stats. Scheda
  lead: bottone disabilitato durante la conversione, poi invalida il dettaglio → "Vai all'opportunità". i18n `leads.convert.*`.
- Test: `OpportunityFromLeadOfferTest` (nuovo, 4); `OpportunityFromLeadTest` AC-065 invia `products_of_interest: []`
  (cambio requisito dichiarato); `leads-table.test.tsx` e `lead-conversion-action.test.tsx` riscritti sui casi di
  conversione diretta (superano spec 0045 AC-020..025).
- Verifica: Pest completo 7888 passati / 1 saltato, Leads+Opportunities+Imports 629 verdi a fine lavoro, Pint pulito;
  Vitest 700 file / 5284 test, ESLint e `tsc -b --force` puliti.
- Nota: durante il lavoro un'altra sessione modificava in parallelo i lead (rimozione `state_id`, form lead): quelle
  modifiche non fanno parte di questa voce.

## OPPORTUNITÀ — "PRODOTTI DI INTERESSE" NON PIÙ OBBLIGATORIO — NON COMMITTATO (2026-09-17)

Direttiva utente 2026-09-17: annulla l'obbligatorietà introdotta il 2026-07-23. Contratto: `products_of_interest`
è `sometimes|array` sia in `StoreOpportunityRequest` sia in `UpdateOpportunityRequest` (omesso = creata senza
prodotti / lasciata invariata; `[]` = svuota). `OpportunitiesAuthorization`: niente più `mandatory`, soglia massima
`visibleEditable()` senza `required` → meta `required: false`, e la modifica inline in griglia accetta `[]`.
Frontend: `opportunity-schema.ts` senza `.min(1)`, rimossa la chiave i18n orfana `products.ofInterest.required`.
Invariato: la regola di coerenza con le categorie prodotto (`ProductCategoryCoherence`) e l'obbligatorietà di `product_lines`.

- Test aggiornati (cambio di requisito, dichiarato nei commenti): `OpportunityProductsOfInterestTest` (create
  omesso/`[]` → 201, update `[]` svuota), `ProductsOfInterestInlineEditTest` (`[]` → 200),
  `OpportunityMetaTest` (`required` false), `InlineCellEditingMandatoryFieldTest` (il controllo sulla collezione
  vuota ora è coperto marcando il campo `required` tramite la matrice dei permessi nel DB), `opportunity-schema.test.ts`.
- Verifica: suite Pest completa in seriale verde (7895 passati, 1 saltato), Vitest 1234 verdi, Pint/ESLint e `tsc -b --force` puliti.
- Nota: `pest --parallel` dà ~75 errori `Call to undefined function` (helper condivisi tra file) e 1 failure in
  `TaskConfigPermissionsTest`; tutti spariscono in seriale e sono estranei a questa modifica.

## CAMPAGNE — DATA INIZIO PRECOMPILATA DAL PROGETTO — NON COMMITTATO (2026-09-17)

Richiesta: la campagna prende la data inizio dal progetto collegato, ma solo come precompilazione (resta modificabile).

- Backend: `ProjectForSelectResource` espone `meta.start_date` (`Y-m-d` o `null`); `ProjectService::forSelectBaseQuery`
  seleziona anche `start_date`.
- Frontend: `ProjectForSelectMeta.start_date: string | null`; `CampaignProjectField` alla selezione del progetto
  imposta `start_date` SOLO se il progetto ne ha una (una data gia' digitata non viene svuotata). Scollegare il
  progetto non tocca la data (come Partner/Sede). Nessun lock, `end_date` non ereditata.
- Test: `ProjectForSelectTest` (meta.start_date valorizzata/null), `campaign-project-link.test.tsx` (+2: prefill
  modificabile e inviato come editato; data digitata preservata se il progetto non ne ha). Pest Projects+Campaigns
  165 verdi, Vitest campaigns 94 verdi, Pint/ESLint puliti, `tsc -b --force` pulito.

## TABELLE AG GRID — PAGINA DA 100 CARICATA A BLOCCHI DA 25 — NON COMMITTATO (2026-09-17)

Bug (Gestione Richieste, vale per ogni tabella SSRM): scelta la dimensione di pagina 100, la griglia continuava a
chiamare `POST /tables/{domain}/rows` mentre si scorreva. Causa: `cacheBlockSize` resta a
`defaultPagination.limit` (25) anche quando il selettore cambia `paginationPageSize`, quindi l'SSRM caricava la
pagina in 4 blocchi (0-25, 25-50, ...), una chiamata per blocco.

- Nuovo `components/data-table/pagination-block-size.ts` → `syncCacheBlockToPageSize` su `onPaginationChanged` in
  `DataTable`: con `newPageSize` imposta `cacheBlockSize` = page size (AG Grid resetta lo store → 1 chiamata
  `0-100`). Guardia di uguaglianza contro il loop sul `paginationChanged` emesso dal reset.
- Verificato in browser (Playwright, 3000 righe simulate): prima 25-50/50-75/75-100 allo scroll; dopo `0-100`,
  pagina 2 `100-200`, nessuna chiamata a riposo. Test: `pagination-block-size.test.ts` (4), data-table 159 e
  features/table 198 verdi, ESLint e `tsc -b --force` puliti.
- Vincolo: il selettore offre `blockSize × {1,2,4}` e il backend ha `MAX_LIMIT = 100`; oggi tutte le
  `TableDefinition` hanno limit 25. Un limit > 25 farebbe andare in 422 la pagina più grande.

## TABELLE AG GRID — LAYOUT COLONNE PERSO AL RELOAD IMMEDIATO — NON COMMITTATO (2026-09-17)

Bug: cambiando una colonna (sposta/ridimensiona/nascondi) e ricaricando subito la pagina, la modifica si perdeva.
Root cause in `use-table-layout-persistence.ts`: il salvataggio aspettava un debounce di 500 ms; al reload React
non smonta e il timer muore con la pagina, e anche lo smontaggio (navigazione interna) CANCELLAVA il timer invece di
inviare il salvataggio (il commento diceva "flush", il codice scartava). Stesso difetto per il filterModel della griglia.

- Payload catturati al momento della modifica (`pendingLayoutRef`/`pendingFilterRef`), non allo scadere del timer
  (allo smontaggio AG Grid e' gia' distrutto).
- Smontaggio: `flushLayout`/`flushFilters` inviano subito via mutation (aggiorna la cache config).
- `pagehide` (reload/chiusura tab): chiamata diretta a `saveTablePreferences`/`saveTableFilters` con
  `{ keepalive: true }` → `api.ts` usa `adapter: 'fetch'` + `fetchOptions.keepalive` (passa dagli interceptor axios,
  quindi Bearer + Accept-Language; `sendBeacon` non puo' mandare l'header Authorization). Esito ignorato.
- Reset layout/filtri: svuotano anche il payload pendente, cosi' non viene reinviato.
- Test: `use-table-layout-persistence.test.tsx` (nuovo, 5 casi; 3 falliscono sul codice precedente) + caso keepalive
  in `api.test.ts`. Suite FE 700 file / 5290 test verdi, `tsc -b --force` e ESLint puliti.
- Da verificare a mano nel browser: sposta una colonna e premi subito F5 → in DevTools/Network la POST
  `/tables/{domain}/preferences` parte con keepalive e al reload il layout resta. Fuori scope: i filtri avanzati
  (`use-advanced-filters.ts`) hanno un salvataggio proprio, non toccato.

## GESTIONE RICHIESTE — COLONNE DI DEFAULT + COLONNA EMAIL — NON COMMITTATO (2026-09-17)

Richiesta utente (screenshot Excel): la griglia mostra di default SOLO, in quest'ordine: Categoria prodotto,
Operatore, Stato di lavorazione, Prossimo richiamo, Linee di prodotto, Nome, Cognome, Telefono, Email, Codice
fiscale, Note generali, Fonte, Tutor (GA1); poi, nei tab categoria, le colonne `attr.*` come prima. Decisioni
utente: Nome/Cognome restano DUE colonne affiancate; "Email" e' una colonna nuova gemella di "Telefono"
(editabile, filtro/sort/ricerca); vale anche per Gestione Iscritti (stesso catalogo); preferenze colonne salvate
NON azzerate (chi ha un layout personale usa "Ripristina predefinito").

- `RequestColumnCatalog::columns()` riordinato; `pending_change_requests`/`operational_site`/`is_transferred`/
  `vat_number` ora `visible: false` (restano nel selettore colonne). `RequestManagerColumns::column($id)` per
  piazzare Operatore e GA1 separati. `operational_site` resta sulla riga: lo scope del picker Operatore funziona.
- Larghezze di default delle 13 colonne visibili in `RequestColumnCatalog::DEFAULT_WIDTHS` (valori forniti
  dall'utente); le nascoste restano `width: null`. Il catalogo e' a 452 righe: al prossimo ampliamento va diviso.
- Email: `RequestClientColumns::CONTACT_COLUMNS` (phone/email) sostituisce la logica solo-telefono;
  `RequestRowMapper::primaryContact()`; `RequestClientProfileWriter::CONTACT_KEY_TYPES` (`client_phone`,
  `client_email`, update in place / create / clear); chiave permesso `client_email` in
  `RequestManagementAuthorization` (nessuna riga matrice = non ristretta); `CellValueValidator` format `email`
  (lowercase + `email:rfc`). i18n `requestManagement.columns.email` = "Email".
- Test: nuovo `RequestManagementDefaultColumnsTest.php` (ordine + email); aggiornati per requisito cambiato
  `RequestManagementSourceAndNotesColumnsTest` (posizioni Fonte/Note) e `RequestManagementTableSearchTest`
  (ordine `searchable`).

## TELEFONO UNICO: TIPO CONTATTO `mobile` RIMOSSO + IMPORT LEAD CONTATTI PRINCIPALI (spec 0139, 2026-09-17) — VERDE, NON COMMITTATO

Segnalazione utente: in Gestione richieste il telefono non compariva. Causa: `LeadProfileBuilder::buildContacts` marcava
principale solo il PRIMO contatto della riga (l'email); la colonna "Telefono" legge solo i principali. Decisioni utente:
"Cellulare" sparisce ovunque, esiste solo `phone`; ex cellulare resta principale; import con un solo campo Telefono.

- `ContactTypeEnum::Mobile` rimosso (enum = phone, fax, email, pec, website). Tolto da import lead (campo, dedup
  `MATCH_ORDER`), `IdentityDuplicateFinder`, `ValidatesPhoneUniqueness` (pool = solo phone), `CheckIdentityDuplicatesRequest`
  (`in:email,phone`), `InputFormat`, `RewardedReferentRowMapper`, Gestione richieste (RowMapper/ClientColumns/
  ProfileWriter: `client_phone` => [Phone]), factory (`mobile()` state eliminato), seeder demo, `lang/it.json`.
- `config/imports.php`: alias `cellulare`/`cell` ora su `phone` (file con Telefono+Cellulare = conflitto di mappatura).
- Import lead: `buildContacts` salva ogni contatto come principale del suo tipo; `mergeContacts` sovrascrive il principale
  di quel tipo (altrimenti il primo) e lo rende principale (`mergeTargetIds`).
- Sorgenti migrazione Referenti/Sedi: campo esterno `mobile` salvato come `phone` dopo il fisso (resta principale).
- Migrazioni (down no-op dichiarato, D-9): `2026_09_17_130000_merge_mobile_contacts_into_phone` (demote fisso se c'e'
  un mobile principale, label Mobile/Cellulare/Cell azzerate, retype, promuove id minore di email/phone senza principale);
  `2026_09_17_130100_remap_mobile_import_field_to_phone` (template/run leads + `mapped_values` staged).
  `QuoteWorkflowMigrationTest` rollback a 80 step. GIA' APPLICATE al DB dev (insieme alla pending 100000 del segnatempo):
  0 mobile, 20/20 richieste con telefono principale.
- Frontend: tolti `mobile` da tipi/schema/chip/icone (`smartphone` tolto da enum-icon-map e tint), duplicati, i18n
  enum/import/duplicati. `icon-catalog` dei campi custom invariato (non e' il tipo contatto).
- Test: nuovi `Feature/Database/MergeMobileContactsMigrationTest` (4), `Feature/Imports/LeadImportPrimaryContactsTest` (4);
  test `mobile` aggiornati a `phone` o rimossi dove il requisito non esiste piu' (pool phone/mobile, "solo cellulare = 201";
  `RegistryCrudTest` ora verifica 422 su `type: mobile`).
- Verifica: Pest completo 7893 passati / 1 skipped; Pint pulito; Vitest (13 feature toccate) 1652/1652; ESLint e
  `tsc -b --force` puliti.
- Da segnalare (fuori scope): in RequestClientColumns/RowMapper/ProfileWriter le mappe colonna -> ARRAY di tipi ora hanno
  un solo tipo ciascuna; si potrebbero semplificare a tipo singolo.

## NOTA SEGNATEMPO -> COMMENTO SULLA COMMESSA — VERDE, NON COMMITTATO (2026-09-17)

Richiesta utente: la nota del segnatempo collegato a commessa non va piu' in `work_orders.internal_notes` ma nelle
note collaborative (commenti, spec 0134) della commessa. Supera la voce "NOTA SEGNATEMPO -> NOTE INTERNE COMMESSA"
(2026-09-14). Decisioni utente: commento SINCRONIZZATO (testo cambiato = stesso commento riscritto; nota svuotata /
commessa tolta / segnatempo eliminato = commento soft-deleted; commessa cambiata = spostato); autore = TITOLARE del
segnatempo (non chi salva); i blocchi gia' copiati in `internal_notes` restano dove sono e non sono piu' aggiornati.

- `Services/TimeEntries/WorkOrderNoteSynchronizer` riscritto: crea `Note` (morph `work_order`, `quote_id` null,
  nessuna menzione/notifica) direttamente sul model, senza `NoteService`. Corpo = `RichTextConverter::plainTextToHtml`
  del testo trim (escape HTML). Agisce solo se `notes` o `work_order_id` cambiano: una modifica fatta nel thread
  sopravvive ai salvataggi che non toccano la nota; un commento cancellato dal thread si ricrea solo al prossimo
  cambio di testo. Chiamate in `TimeEntryService` invariate.
- Migrazione `2026_09_17_100000_replace_work_order_note_with_note_id_on_time_entries_table`: drop
  `time_entries.work_order_note`, nuova FK `work_order_note_id` -> `notes` (nullOnDelete, non fillable, non esposta).
  `QuoteWorkflowMigrationTest` rollback a 78 step.
- Test: `TimeEntryWorkOrderNoteSyncTest.php` riscritto (12). Helper rinominati `timeEntryCommentActor`/
  `timeEntryCommentPayload`: il vecchio `workOrderNoteActor` collideva (via `function_exists`) con quello di
  `WorkOrderNotesTest`, causa probabile dei 9 rossi in suite completa.
- Verifica: Pest TimeEntries + Tasks + WorkOrders + Notes + QuoteWorkflowMigrationTest = 799/799 in un solo run;
  Pint pulito. Nessuna modifica frontend (il thread si aggiorna alla riapertura della commessa, come prima).

## CATALOGO CORSI DIL - LOMBARDIA (seed produzione, 2026-09-17) — VERDE, NON COMMITTATO

- Fonte: i 3 PDF "Catalogo DIL - Lombardia" (Bergamo, Grumello del Monte, Milano) hanno gli STESSI corsi, cambiano
  solo i recapiti sede. Unione = 29 righe / 28 nomi: "Make-up Artist Professionale" 30 e 40 ore = due corsi distinti
  (`CatalogProducts::disambiguate` -> suffisso "(N ore)").
- Dati: `database/seeders/QualificaCatalog/DilCourseCatalogue.php` (stessa shape di `TrainingCourseCatalogue`, chiave
  `DIL - Lombardia`); `CatalogProducts::seedTrainingCourses` itera GOL + DIL.
- Albero: `QualificaCatalogSeeder::CATALOG` `DIL => ['DIL - Lombardia']`. Decisione utente: "DIL" diventa CONTENITORE
  come GOL (non selezionabile, uscito da `SINGLE_OFFER_CATEGORIES`, il prodotto "DIL" non si semina più; su DB già
  seminati resta, non viene cancellato). "DIL - Lombardia" eredita da DIL campi Offerta/Commessa e layout (barriera
  su DIL invariata) e competenze operatori (`OperatorRoster` su "DIL" copre i discendenti).
- Workflow: "DIL" ora su `product_category_branch_id`. `QualificaWorkflowSeeder::realignCriterionField` riallinea un
  workflow esistente SOLO se la sua signature è lo stesso categoria-id con l'altro field (installazioni seminate prima);
  criteri modificati dal configuratore restano intatti.
- Totale prodotti seed catalogo: 294 (252 GOL + 29 DIL + 10 autofinanziati + 3 single-offer).
- Test: nuovo `tests/Feature/Products/QualificaDilCatalogueTest.php`; aggiornati `QualificaCatalogSeederTest`
  (selezionabili/contenitori, totale), `QualificaWorkflowSeederTest` (DIL su branch), `QualificaProductionDataSeederTest`
  (294). Suite DIL/catalogo/workflow/seeding verdi. Fallimenti preesistenti non correlati: `TaskConfigPermissionsTest`
  (task-statuses 422) e helper "undefined function" in run parziali/paralleli.

## IMPORT LEAD — PULIZIA CARATTERI SPECIALI NEI NOMI (spec 0138, 2026-09-17) — VERDE, NON COMMITTATO

- Nuovo `App\Imports\Recognition\PersonNameRecognizer`, in `LeadsImportDefinition::recognizers()` tra
  `CampaignRecognizer` e `NameSplitRecognizer` (ordine vincolante: la divisione lavora sul `full_name` pulito).
  Pulisce `full_name`/`first_name`/`last_name`: NFKC (`Normalizer`, polyfill senza ext-intl) -> lettere non latine
  via `Str::ascii` -> punteggiatura e `|` diventano spazio, cifre/emoji/simboli via -> spazi compattati.
  Accenti latini MANTENUTI (`Macrì`). Poi `InputFormat::personName` (stesso title case dei form, D-5): un cambio di
  sole maiuscole/spazi NON segnala la riga (`ALESSIA` -> `Alessia` resta valid).
- Cambio oltre gli spazi = riga `warning` con messaggio `{field} contained special characters and was cleaned to
  "{value}"; review it.`; nome vuoto dopo la pulizia -> placeholder esistente. Il valore pulito finisce in
  `mapped_values`, quindi un edit in revisione non ri-segnala la riga.
- Limiti noti: maiuscoletto (`Sᴀʀᴀ` -> `S`) e alfabeti decorativi senza traslitterazione (`Vᥲᥣᥱᥒtιᥒᥲ` -> `Vti`)
  perdono lettere ma restano segnalati; bio nel nome ("Jessica / Mental Coach...") restano parole.
- Test dichiarato modificato: `LeadsImportDefinitionTest` (lista recognizer del contratto). Nuovi:
  `PersonNameRecognizerTest`, `LeadImportNameCleaningTest`. Suite Unit/Imports, Feature/Imports,
  Unit/Support, Unit/Jobs, Feature/PersonalData: 568 verdi; Pint pulito. Verificato sul file reale (46 nomi anomali su 400).

## IMPORT WIZARD — AVANZAMENTO STAGING/PROCESSING (spec 0137, 2026-09-17) — VERDE, NON COMMITTATO

- `GET /api/imports/{domain}/{importRun}` espone `import_run.progress: {processed, total} | null`
  (solo `ImportRunPayloadBuilder`, NON `ImportRunResource`): `staging` = righe staged / `total_rows`;
  `processing` = righe persistibili con `persisted_at` / righe persistibili
  (`ProcessStagedImportJob::PERSISTABLE_STATUSES`, ora pubblica). Null in ogni altro stato o con total 0.
  Calcolato in lettura, nessuna colonna nuova. Una riga che fallisce il commit non avanza il contatore.
- FE: tipo `ImportPhaseProgress` (`progress?` opzionale su `ImportRunDetail`), componente
  `ImportPhaseProgressBar` usato da `ImportStepReview` (staging) e `ImportRunProgress` (processing, fallback
  barra indeterminata). i18n `importWizard.background.progress`.
- Stallo: chiave `useStallTimeout` = `${id}:${status}:${progress.processed}` -> la finestra di 120 s riparte a
  ogni avanzamento (bloccato = 120 s senza progresso). `analyzing` resta senza percentuale (D-1).
- Verifica: Pest `ImportShowProgressTest` + suite Imports/Unit Jobs/Unit Imports (351 verdi), Vitest
  `features/imports` (246 verdi), Pint, ESLint (solo warning preesistenti), `tsc -b --force` puliti.

## IMPORT LEAD — DEDUP SCALABILE + JOB HARDENING (spec 0136, 2026-09-16) — VERDE, NON COMMITTATO

- Sintomo prod: import lead (400 righe) fermo in `staging` e poi `failed`. Causa: `LeadDuplicateMatcher` idratava
  TUTTI i contatti per riga (lineare nei contatti: ~101k = 750 ms e 134 MB per riga).
- `contacts.normalized_value` (indice `type,normalized_value`, backfill in migrazione `2026_09_16_110000`): valorizzato
  SOLO da `Contact::booted()` saving con `ContactValueNormalizer::contact`; non fillable, in `$hidden` (PII).
  Chi scrive contatti fuori da Eloquent (DB::table) deve valorizzarlo a mano, altrimenti il dedup non li vede.
- Lookup indicizzati su `normalized_value`: `LeadDuplicateMatcher::matchByContact` (una query, ordine id globale),
  `IdentityDuplicateFinder::matchContactType` (email/phone/mobile), `ValidatesPhoneUniqueness::phoneValueTaken`.
  Fiscale del matcher lead: `where(col, target)` + ricontrollo PHP (rischio residuo: legacy con spazi iniziali).
  Rami fiscali di `IdentityDuplicateFinder`/`UniquePersonalDataIdentifier` invariati (whereRaw UPPER(TRIM)).
- Job: `StageImportJob`/`ProcessStagedImportJob` `tries=1`, `timeout=config('imports.job_timeout')` (1800, env
  `IMPORT_JOB_TIMEOUT`); staging cancella le righe del run prima di ricostruirle; commit legge `lazyById`
  (`imports.batch_size`) solo righe con `persisted_at` null e lo imposta nella transazione della riga;
  `imported_rows` ricalcolato via query, `error_count` = failures dell'esecuzione corrente.
- **Nota operativa prod:** default `DB_QUEUE_RETRY_AFTER` portato a 1900 in `config/queue.php` (deve superare
  `IMPORT_JOB_TIMEOUT`). Se il `.env` di prod lo definisce esplicitamente, va allineato (>= 1900). Effetto: un job
  orfano per crash del worker viene ripreso dopo ~32 min invece di 90 s. Al deploy gira il backfill su `contacts`.
- AC-006 [revised]: il re-match al commit deduplica righe interne al file solo con `update_existing`; con
  `create_new` due righe uguali creano due anagrafiche, con `manual` la seconda viene saltata (preesistente).
- Verifica: suite mirate 1469/1469, Pint pulito, benchmark MySQL locale 100k contatti: staging 400 righe 12.85 s,
  memoria dedup 0.012 MB/riga costante. Migrazioni applicate al MySQL locale. `QuoteWorkflowMigrationTest` step 75->77.
- Segnalati, non trattati: `GeoRecognizer` ~30 ms/riga; `StagingErrorReporter` carica tutte le righe error;
  `TaskConfigPermissionsTest` (campo `group`) rosso anche su main; activity log lancia `ValueError` su update di un
  contatto con `type` fuori enum; formato `+39...` e numero senza prefisso restano diversi nel dedup.

## CONVERSIONE LEAD -> OPPORTUNITA' PER MARKETING/COORDINATORI/SUPERVISORI (2026-09-16) — NON COMMITTATO

- Richiesta utente: Fabozzi, Falzarano, Aliberti, Santamaria, Chiacchio, Figurelli (e Del Giudice) convertono i lead
  in opportunita', nel seeder di produzione. I sei sono TUTTI i membri di `supervisore-commerciale`,
  `coordinatore-commerciale`, `marketing` → grant a livello ruolo.
- `OperatorRoleCatalogue`: nuovo blocco `LEAD_CONVERSION` + `LEAD_CONVERSION_PERMISSIONS`
  (`opportunities.viewAny` per `GET /meta/opportunities` del form, `opportunities.create` per entrambi i percorsi).
  `view` escluso: la voce di menu Opportunita' resta nascosta. `QualificaRoleSeeder::blockGrants` gestisce il blocco.
- Miriam Del Giudice: NON e' nel roster (account inesistente, decisione 2026-09-15) → nessun grant, da chiarire.
- Test: nuovo `tests/Feature/Users/QualificaLeadConversionPermissionTest.php`; `QualificaRoleMatrixTest` aggiornato
  (requisito cambiato: `opportunities` tolto dalle liste "chiuse" di supervisore e marketing).

## DIL — ID CORSO + SEDE CORSO, VIA CORSO SCELTO (2026-09-16) — VERDE, NON COMMITTATO

- Decisione utente: la categoria `DIL` ha gli stessi campi corso di GOL (`id_corso`, `course_site`) e non ha piu' `chosen_course`.
- `ContactProcessingAttributeCatalogue`: specs estratte in `COURSE_ID_SPEC`/`COURSE_SITE_SPEC` (condivise training/DIL);
  `chosen_course` in `RETIRED_ATTRIBUTES` (riga attributo mantenuta, assegnazioni e item di layout rimossi) e tolto da `ROWS`.
- Convergenza installazioni gia' seminate: nuovo `PREVIOUS_OWN_ATTRIBUTES` (storia congelata dei codici propri di DIL) +
  `SeedsAttributeLayouts::effectiveCodeRevisions()` e `composesAs()` (confronto blob ignorando gli id riga, che la
  retirement non rinumera). Usati da `QualificaContactProcessingSeeder::isOwnComposition` e `QualificaQuoteLayoutSeeder::isPreviousComposition`.
- Test: nuovo `tests/Feature/Products/QualificaDilCourseFieldsTest.php`; aggiornati (requisito cambiato) i test DIL e
  l'assegnazione di `course_site` (ora anche su DIL). Suite Products/Seeding/ProductCategories/Unit RequestManagement 567/567 + 20/20 seeder correlati, Pint pulito.

## RECORD FORM — COLONNA LATERALE SOTTO IL FORM SU MOBILE (2026-09-16) — VERDE, NON COMMITTATO

- Richiesta utente: in tutti i record form, su mobile (container sotto `@4xl`) il form viene prima e la sidebar va sotto.
- Fix nella primitiva condivisa `components/record-form/layout.ts`: `SIDE_COLUMN_CLASS` ha `order-2` e `MAIN_COLUMN_CLASS`
  `order-1` incondizionati (prima solo `@4xl:`). Vale per anagrafiche, referenti, opportunita', utenti, prodotti,
  categorie, aziende, sedi azienda, sedi operative, task, Gestione Richieste (work panel + create) e skeleton.
- Nuovo `SPLIT_SIDE_COLUMN_CLASS` (aside `@max-4xl:contents`): usato da referenti e anagrafiche, l'avviso duplicati
  resta SOPRA il form su mobile, il riepilogo (wrapper `order-last`) va sotto. Rimosso `REFERENT_SIDE_COLUMN_CLASS`.
- Nota: su mobile anche le "Note generali" (Gestione Richieste, Opportunita') ora stanno sotto il form.
- Verifica: Vitest su tutte le feature coinvolte 194 file / 1439 test, `tsc -b --force` pulito, ESLint pulito sui file
  toccati (1 errore preesistente in `registry-form-metadata.test.tsx`, non toccato).

## ACTIVITY LOG — LABEL AL POSTO DEGLI ID (2026-09-16) — VERDE, NON COMMITTATO

- Bug cliente: nello Storico di Gestione Richieste "Stato: 209 -> 211" (id grezzi). Causa: le entry ESPLICITE
  su Opportunity (D-9) riportano campi dell'Offerta (`quote_workflow_status_id`, `operator_id`, `manager_slots`,
  `product_lines`, `rewards`) per cui Opportunity non ha relation BelongsTo -> `ForeignKeyLabelResolver` non li
  riconosceva. In piu' Lead/OperationalSite/Quote/WorkOrder/Task non hanno colonna `name` -> label null.
- Fix (solo backend, contratto `old_display`/`new_display` invariato, FE intatto):
  - `config/activity-log.php` nuova chiave `foreign_keys` [subject alias][field] => model (Opportunity + 
    `quote_line_commission.commission_configuration_id`). Vince sulla detection via relation.
  - Nuovo `App\Services\ActivityLog\ActivityLogLabelFetcher` (estratto dal resolver): label column
    Quote/WorkOrder=`code`, Task=`title`; label composte OperationalSite (`OperationalSiteLabel` su primaryAddress,
    fallback `alias`) e Lead (nome anagrafica). Label vuote scartate -> FE mostra l'id grezzo.
  - Liste di id: `ActivityLogEntryResource::labelFor` unisce le label con ", ", slot null/lista vuota = "—".
- Non coperto (segnalato): `offer_lines` (snapshot oggetti product_id/vat_rate_id) e `attribute_values` (mappa
  codici) restano JSON grezzo nello Storico.
- Verifica: `tests/Feature/ActivityLog` 86/86 (3 test nuovi in `ActivityLogForeignKeyLabelTest`), altri 62 test che
  leggono l'endpoint activity-log verdi, Pint pulito.

## IMPORT CONDIVISI + COLONNA OPERATORE (2026-09-16) — VERDE, NON COMMITTATO

- Decisione utente: gli import (`/imports`) NON sono piu' per-utente. Chi ha `leads.import` vede,
  apre (dettaglio + wizard), modifica, conferma ed elimina gli import di TUTTI. Nessuna spec dedicata.
- Backend: rimosso ogni filtro `user_id` da `LeadImportsTableDefinition::baseQuery`, `ImportController::index`,
  `LeadImportsStatsDefinition`, `ImportRunPolicy::view/delete`. `assertOwnedRun` -> `assertRunMatchesDomain`
  (resta solo il 404 su dominio diverso); `ImportMappingTemplateController::resolveOwnedRun` -> `resolveRun`;
  `SelectionScopeController` non ha piu' guard sul run.
- Nuova colonna derivata `user` (label `leadImports.columns.operator`, set filter per nome, sort via subquery)
  risolta da `App\Tables\LeadImports\ImportRunUserColumn`; valore `{id,name,avatar_url}`, FE `UserCell`.
- Fix collaterale: `ExportValueFormatter::formatScalar` esporta un summary `{id,name}` come `name`
  (prima "Array to string conversion" su OGNI colonna persona di tipo text esportata, es. operatore lead).
- Test aggiornati (requisito cambiato): i test "404 per run di un altro utente" sono ora positivi.
- Verifica: suite backend seriale 7790/7800 — i 9 rossi sono `WorkOrderNotesTest` e
  `DemoOpportunitySeederTest`, estranei a questo lavoro. Vitest imports+i18n 399/399, `tsc -b --force` e Pint puliti.

## SEED OPERATORI — MARLENA JARUGA SENZA COMPETENZE (2026-09-16) — VERDE, NON COMMITTATO

- `OperatorRoster`: la riga di Marlena Jaruga (`supervisore-didattica`) ha categorie `[]` (nessuna
  competenza, non assegnabile); le citta' abilitate restano, perche' `request-management.viewSite`
  vede le richieste per Sede di appartenenza. Ruolo invariato.
- Verifica: Pest `tests/Feature/Seeding/QualificaOperatorSeederTest.php` 11/11, Pint pulito.
  Sul DB esistente: `php artisan db:seed --class=QualificaOperatorSeeder` (convergente).

## SPEC 0135 — SEDI OPERATIVE ATTIVA/DISATTIVA (2026-09-16) — VERDE, NON COMMITTATO

- Spec: `docs/specs/0135-operational-site-active-flag.xml`. Migrazione
  `2026_09_16_100000_add_is_active_to_operational_sites_table` (default true, indice): va eseguita
  `php artisan migrate` sul DB di sviluppo.
- `OperationalSiteService::forSelect` filtra `is_active = true`; `appendHydratedIds` NON filtra
  (record gia' collegati a una sede disattivata la mostrano ancora). Tutti i select sede FE passano
  da li': nessuna modifica ai consumer.
- Nomi: colonna/campo `is_active`; DTO `CreateOperationalSiteData::$isActive` (default true),
  `UpdateOperationalSiteData::$isActive` (`?bool`, null = non inviato); factory `->inactive()`;
  FE `OperationalSiteDetail.is_active`, i18n `operationalSites.columns.is_active`,
  `.detail.is_active`, `.form.isActive`, `.form.sections.status`.
- Fuori scope (D-4): le FormRequest dei moduli che referenziano una sede non rifiutano una sede
  inattiva; opzioni filtro report richieste e filtri team segnatempo invariati.
- Verifica: Pest `tests/Feature/OperationalSites` 67/67 + suite toccate in seriale verdi;
  `QuoteWorkflowMigrationTest` rollback step 74 -> 75. Vitest completo 5282/5282, `tsc -b --force`
  EXIT 0, ESLint pulito. Fallimento NON correlato: `TaskConfigPermissionsTest` AC-051
  dataset task-statuses (422 `group` richiesto invece di 403).

## SPEC 0132 — CLASSIFICAZIONE PER CATEGORIA GENITORE (2026-09-16) — VERDE, NON COMMITTATO

- Spec: `docs/specs/0132-root-category-classification.xml`. Nei campi `product_lines` delle CARD
  (opportunita', richieste create/work panel/cella griglia, progetti, campagne) il primo select non e'
  piu' la funzione aziendale ma la **categoria genitore** (root, `parent_id` null); il secondo offre i
  discendenti `is_selectable` di quella root, sempre obbligatorio. Competenze utente ESCLUSE (D-4):
  restano funzione + categoria + "tutte le categorie".
- Contratto: il payload card invia SOLO `{product_category_id}`. Il backend deriva la funzione EFFETTIVA
  (`BusinessFunctionResolver`, batch su `CategoryHierarchy::effectiveBusinessFunctionSummaries()`) e la
  persiste nella colonna esistente: nessuna migrazione. `business_function_id` inviato = ignorato.
  Duplicato = stessa categoria (`DUPLICATE_CATEGORY_MESSAGE`); nuovo 422
  `CATEGORY_WITHOUT_BUSINESS_FUNCTION_MESSAGE`. Lettura: ogni riga card espone
  `root_category {id,name}` (concern `SummarizesProductLines`, `CategoryRootResolver`); riga di griglia
  richieste con `root_category_id/root_category_name` piatti. `ProjectForSelectResource` NON la espone
  (la radice si ricava dall'albero in cache).
- Nomi da rispettare. FE: `ProductLineRow = {root_category_id, product_category_id}` (root = solo stato
  UI, mai sul filo); `CompetenceLineRow` separato; `ProductLinesField` (card) vs `CompetenceLinesField`;
  `ProductCategoryRootSelect`; `ProductCategoryTreeSelect` con `scope: CategoryPickScope`
  (`kind: 'root' | 'business_function'`); `selectableIdsUnderRoot`, `rootCategoryIdFor`. La root in
  modifica si risolve a render time in `useProductLinesField.rootCategoryFor`, non nell'hook del form.
  Dettaglio: `ProductLinesReadOnlyList` rende "Root > Categoria — funzione: X" se la riga ha
  `root_category`, altrimenti il formato competenze invariato. Payload cella: allow-list
  `PRODUCT_LINE_PAIR_WIRE_KEY` in `use-table-cell-edit.tsx`.
- Da sapere: `ProductLineWriter::sync()` fa passare invariata una riga CON `business_function_id`
  (competenze, seeder demo) e deriva per quelle senza; ogni percorso card normalizza a monte
  (verificato dal verifier). `CategoryHierarchy` e' a ridosso del limite 500 righe: nuova logica di
  gerarchia va in classi dedicate.
- Verifica (verifier indipendente): frontend Vitest 699 file / 5272 test verdi, `tsc -b --force` 0
  errori, ESLint pulito; backend Pint pulito, perimetro 0132 verde (+ test AC-006 opportunita' e
  AC-010: 240/240). Test adeguati per requisito cambiato, nessuna asserzione competenze toccata.
- Aperti: `DemoOpportunitySeederTest` "one business function and one branch root" e' flaky
  (preesistente, verde da solo; verifica INV-1/INV-2 gia' revocate da spec 0077 rev.2: candidato a
  rimozione). `product-lines-cell-editor.tsx` a 341 righe (soft limit). Dato sporco noto: categorie con
  `parent_id` ciclico (#76/#122/#179 nel dataset legacy) restano non classificabili.
- Stato working tree: convive con lavoro NON committato di altre sessioni (spec 0133/0134 work-orders,
  dashboard richieste, sheet utente, seeder contratti) che fa fallire `WorkOrderNotesTest.php`. Il
  commit della 0132 va fatto per percorsi espliciti, non con un add globale.

## FIX — Dashboard Gestione Richieste: errori parlanti e stato "nessun dato" (2026-09-16) — VERDE, NON COMMITTATO

- Bug: senza richieste visibili la dashboard mostrava "Impossibile caricare la dashboard.". Causa
  (riprodotta in locale): `/report/categories` risponde `[]`, la riconciliazione dei filtri salta
  sulla lista vuota, i `category_keys` ripristinati da localStorage partono verso
  `/report/dashboard` e il backend li rifiuta con 422 (allow-list) -> messaggio generico.
- Fix (solo FE, `request-dashboard-panel.tsx`): la query parte solo se ogni `category_key` e' nella
  lista caricata; lista vuota -> avviso informativo `dashboard.noCategories` (role=status, nessuna
  fetch); errore categorie -> `report.errors.categoriesLoadFailed` + Riprova; errore dashboard mappato
  da `dashboardErrorKey`: nessuna risposta `errors.network`, 403 `errors.forbidden`, 422
  `errors.invalidFilters`, altro `errors.generic`. Chiave `dashboard.loadError` rimossa (it/en).
- Test cambiato per requisito: il testo atteso di "retryable error (AC-048)"; +7 casi nuovi.
- Verifica: Vitest request-management 58 file / 417 test verdi (i nuovi falliscono sul codice
  precedente); ESLint pulito; `tsc -b --force` 0 errori. Backend non toccato.

## FIX — useNavigate fuori dal Router nello sheet utente (2026-09-16) — VERDE, NON COMMITTATO

- Bug: `useNavigate() may be used only in the context of a <Router>` da `RecordLink` ->
  `useRecordModalLink` -> `useModuleOpener`. Causa: `UserDetailSheetProvider` era montato in
  `App.tsx` SOPRA `RouterProvider`, e `UserDetailView` renderizza `RecordLink` (sedi, societa').
- Fix: provider spostato dentro `AppLayout` (quindi dentro il router); `openUserDetailPage` usa
  `useNavigate` invece di `router.navigate` (niente piu' import di `@/routes/router`, che avrebbe
  creato un ciclo router -> AppLayout -> sheet -> router). Commento di `SheetDetailPageLink` aggiornato.
- Test cambiato per requisito: `user-detail-sheet.test.tsx` mocka `useNavigate` invece di `router`.
- Verifica: Vitest users/detail/layouts/modules 146/146; ESLint pulito; `tsc -b --force` 0 errori.
- Regola: nessun provider che renderizza `Link`/router hook va montato sopra `RouterProvider`.

## COMMESSA — DETTAGLIO ALLINEATO A OPPORTUNITA'/OFFERTA (2026-09-16) — VERDE, NON COMMITTATO

- Richiesta: dettaglio Commessa con lo stesso template stilistico di Opportunita'/Offerte.
  Decisione utente: task in pannello a tutta larghezza sotto il record (come Offerte
  nell'Opportunita'), non piu' strip "Dettagli | Task" -> spec 0133 D-2/AC-009/AC-010 revisionati.
- Split come Opportunita'/Offerta: `work-order-detail-header.tsx` (`WorkOrderDetailHeader`:
  monogramma `size-10`, pill stato/tipo, Modifica; `WorkOrderDetailStats`: KPI Data inizio, Data
  richiamo, Contratto n., Righe prodotto) + `work-order-detail-sections.tsx`
  (`WorkOrderDetailSections`: callout `GeneralNotesCallout` per le note commessa, Dettagli
  = descrizione/modello task/motivo chiusura, Team, Offerta + righe come lista semplice,
  Informazioni aggiuntive). `work-order-detail.tsx` ora solo layout (~80 righe).
- Tipo/Stato non piu' ripetuti come campi (sono le pill); date e contratto nella strip KPI.
- `WorkOrderTasksSection` ora e' una `RecordCard` con `RecordCardHeader` (titolo + badge
  contatore + "Nuovo task"). i18n: rimossi `detail.type`, `detail.status`, `detail.linesEmpty`,
  `detail.sections.notes`, `detail.tabs.*`; aggiunti `detail.lines`, `detail.tasks.title`.
- Test cambiati per requisito: `work-order-detail-tabs.test.tsx` -> `work-order-detail-tasks-panel.test.tsx`,
  una asserzione in `work-order-collaboration-section.test.tsx`.
- Verifica: Vitest work-orders 98/98 verdi, i18n 157/157; ESLint pulito; `tsc -b --force` 0 errori.
- Poi (richiesta utente): la sezione nomina il **Contratto**, non l'Offerta. Backend:
  `WorkOrderResource.contract` = `{ id, code, title }` (id del `Contract`, code/title della quote,
  come l'header del dettaglio Contratto), `null` finche' la quote non ha contratto;
  `WorkOrderService::DETAIL_RELATIONS` ora `quote.contract`. Nuovo
  `tests/Feature/WorkOrders/WorkOrderContractSummaryTest.php` (2). Frontend: `WorkOrderContractRef`
  in `types.ts`, campo "Contratto" = `RecordLink domain="contracts"` (modale), sezione
  "Contratto e righe prodotto"; i18n `detail.quote`/`sections.offer` sostituite da
  `detail.contract`/`sections.contract`. `quote` resta nel payload (form lo usa).
  Verifica: Pest WorkOrders/Contracts/Tasks 732 verdi, Pint pulito; Vitest
  work-orders/contracts/tasks/i18n 581 verdi; ESLint pulito; `tsc -b --force` 0 errori.
- Da verificare a mano: resa nel Sheet stretto e nella pagina `/work-orders/:id`.

## COMMESSA — NOTE, DOCUMENTI, CARD LATERALE E TEAM (spec 0134, 2026-09-16) — VERDE, NON COMMITTATO

- Richiesta: note e documenti sulla Commessa "come opportunita'", card laterale destra nel
  dettaglio con attivita'/note; poi Responsabili/Partecipanti con il componente utente (click =
  modale) e collegamenti in modale come Opportunita'/Offerte.
- Backend note: `App\Services\WorkOrders\WorkOrderNotable` (ricalca `TaskNotable`), slug
  `work-orders` in `config/notes.php`, `HasNotes` su `WorkOrder`. Lettura = `work-orders.view` AND
  `WorkOrderVisibilityScope`; menzionabili = membri + `viewAll` + super-admin; niente quote scope;
  deep link `/work-orders/{id}`. `NoteAgnosticismTest` ha i needle `WorkOrder`.
- Backend documenti: `HasAttachments` su `WorkOrder`, alias `work_order` in
  `config/attachments.php`, nuova ability `work-orders.viewDocuments` (`WorkOrderPolicy`) esposta
  come azione `view_documents` (`WorkOrdersAuthorization`). Allegati autorizzati da
  `attachments.*` (nessun gate per record, come Opportunita'/Task). SERVE `php artisan
  permissions:sync` e assegnare `work-orders.viewDocuments` ai ruoli.
- Frontend: `work-order-collaboration-section.tsx` (Note | Documenti | Log attivita') +
  `use-work-order-collaboration-gates.ts`; `work-order-detail.tsx` a due colonne
  (`RECORD_BODY_GRID_CLASS`/`RECORD_BODY_WITH_SIDE_CLASS`), log attivita' tolto dal fondo card.
  `WORK_ORDER_ATTACHABLE_ALIAS` in `work-orders/api.ts`. Team estratto in
  `work-order-detail-team.tsx` con `RecordPerson` (una riga per slot "Partecipante n").
  Offerta/Modello task/Prodotti erano gia' `RecordLink` (modale).
- Tasto "Modifica" sulla card (come Opportunita'): `WorkOrderDetailView` accetta `onEdit`, bottone
  nelle `actions` di `RecordCardHeader` gated `permissions.resource.update`; registry
  `detailOwnsEditAction: true` (niente piu' Edit nell'header pagina; nel Sheet ora c'e').
- Test cambiati per requisito: `WorkOrderSecurityTest` AC-051 conta 10 permessi (era 9);
  `work-order-detail.test.tsx`/`work-order-detail-tabs.test.tsx` cercano il log nel tab laterale.
- Verifica: Pest WorkOrders/Notes/Attachments/Authorization/ActivityLog/Tasks/Seeding = 1029 test,
  1 fallimento (conteggio permessi) corretto e rieseguito verde; nuovi `WorkOrderNotesTest` (11),
  `WorkOrderDocumentsTest` (5). Pint pulito. Vitest work-orders/notes/attachments verdi (nuovi
  `work-order-collaboration-section.test.tsx`, `work-order-detail-team.test.tsx`). ESLint pulito.
  `tsc -b --force` pulito (0 errori) all'ultimo giro.
- Da verificare a mano: layout a due colonne nel Sheet stretto e nella pagina, upload documento.

## COMMESSA — TAB "TASK" NEL DETTAGLIO (spec 0133, 2026-09-16) — VERDE, NON COMMITTATO

- Richiesta: nel dettaglio Commessa un tab con i task di quella commessa. Decisioni utente: strip
  "Dettagli | Task" nella card; log attivita' e metadati restano fuori dai tab; "Nuovo task" con
  la commessa precompilata (modificabile).
- Backend: nuovo decorator `App\Tables\Tasks\WorkOrderScopedTableDefinition` (ricalca
  `QuoteScopedTableDefinition`), avvolge SOLO `tasks` in `TableRegistry`. Parametro `workOrderId`
  su rows/values/export, `work_order_id` su columns (`exists:work_orders,id`). `where
  tasks.work_order_id` in AND con `TaskVisibilityScope` (che raggruppa le sue OR). Nessuna
  migrazione, nessun endpoint nuovo.
- Frontend: `TableRowScope.workOrderId` propagato come `quoteId` (ssrm-datasource, table-view,
  data-table/column-def-builder/column-filters, export-dialog). `work-orders/work-order-tasks-section.tsx`
  (TableView `tasks` + `useTaskRowActions` in modale + contatore + "Nuovo task") montata da
  `work-order-detail.tsx` solo con `tasks.viewAny`. `useTaskRowActions` espone ora `openCreateWith`.
- Prefill: `TaskFormMode` create ha `workOrderId`; `TaskFormScreen` legge `params.work_order_id`;
  `use-task-work-order-prefill.ts` idrata l'etichetta "code — title" dalla cache del dettaglio
  commessa. Query key condivisa esportata: `workOrderDetailQueryKey` (`work-orders/api.ts`, usata
  anche da `work-order-screens.tsx`).
- Verifica: Pest `tests/Feature/Tasks/TaskWorkOrderScopeTest.php` (9 test) + cartelle
  Tasks/WorkOrders/Exports/Contracts/Quotes/Table = 1289 verdi. Vitest work-orders/tasks/contracts/
  table/data-table/exports = 796 verdi (nuovi: `work-order-tasks-section.test.tsx`,
  `work-order-detail-tabs.test.tsx`, 2 casi in `use-task-form.test.tsx`). ESLint pulito sui file
  toccati. `tsc -b --force` ROSSO ma solo su file del refactor ProductLineRow in corso in un'altra
  sessione (campaigns/projects/request-management/products): zero errori nei file di questa spec.
- Da verificare a mano: tab e "Nuovo task" nel browser (Sheet e pagina dedicata).
- Nota dimensioni: `table-view.tsx` 499 righe (limite 500, accorciato un JSDoc);
  `use-task-form.ts` 304 (sopra il soft limit 300).

## DEMO SEED — CONTRATTI (2026-09-16) — VERDE, NON COMMITTATO

- Richiesta: seed demo per contratti, offerte, commesse, task, segnatempo. Esistevano gia'
  `DemoQuoteSeeder`, `DemoWorkOrderSeeder`, `DemoTaskSeeder`, `DemoTimeEntrySeeder`: mancava solo
  il seeder dei contratti (le offerte demo nascevano tutte `open`, quindi zero contratti).
- Nuovo `DemoContractSeeder` (in `DemoDataSeeder` dopo `DemoRewardSeeder`): nessun insert diretto
  (spec 0072 D-6). Chiude un'offerta ogni 2 come vinta via `QuoteService::update()` sulla riga
  `closed_won` del SUO set di workflow, cosi' `ContractLifecycleManager` apre il contratto (con la
  regola `generates_contract`, spec 0091). Poi ruota 5 forme via `ContractService`/
  `ContractActionService`: da validare, validato, stato custom di lavorazione con scadenza entro 20
  giorni (alert), disdetto, sospeso (offerta riportata a `open`).
- Idempotente: lo stride gira su tutte le offerte prima del filtro "ha gia' un contratto".
- Verifica: `tests/Feature/Seeding/DemoContractSeederTest.php` (4 test) + Seeding/Quotes/Contracts
  242 test verdi; `DemoDataSeeder` completo eseguito due volte su uno schema MySQL temporaneo
  (poi eliminato): 20 offerte, 10 contratti, 10 commesse, 60 task, 97 segnatempo.

## GEO IMPORT — COMUNI NON RISOLTI SUL DATASET DI RIFERIMENTO (2026-09-16) — VERDE, NON COMMITTATO

- Sintomo: `/operational-sites/25` non mostrava il comune. Causa: `addresses.city_id` NULL — il
  `comune` legacy "Fonte Nuova" non esiste in `cities`. Il dataset `dev/DatabaseWorld/world.sql`
  (caricato da `locations:add`) e' un estratto GeoNames di *populated places*, non il registro ISTAT:
  di Fonte Nuova (comune dal 1997) porta solo le ex frazioni Tor Lupara e Santa Lucia.
- Perimetro deciso scansionando TUTTI i record geo legacy (operational-sites 68, companies 21,
  company-sites 45, users 393, referents 14384) rigiocando `MigrationGeoResolver`: i casi con
  provincia/regione risolte ma comune no sono esattamente 7. Il catalogo del seeder contiene solo
  quelli, niente liste inventate.
- Fix 1 — `ItalianMunicipalitySeeder` (nuovo, reference data pulito): comuni assenti dal dataset,
  chiamato da `DatabaseSeeder::run()` subito dopo `locations:add`, stesso criterio di
  `UnitOfMeasureSeeder`. Oggi una sola riga: `['Fonte Nuova', 'Rome']`. Idempotente
  (`firstOrCreate` su nome+provincia), no-op se il dataset geo non e' caricato. `world.sql` NON si
  tocca: i comuni mancanti si aggiungono qui.
- Fix 2 — `ItalianGeoLocalizer::CITIES` (era gia' il punto di estensione, prima solo per gli
  anglicizzati): tre grafie che il dataset scrive diversamente dal legacy —
  `san nicandro garganico` => `Sannicandro Garganico`, `godega di sant'urbano` => `Godega`,
  `cancello ed arnone` => `Cancello-Arnone`. Piu' `setsu` => `Sestu`: refuso del legacy, non una
  grafia del dataset — provincia (`CA`) e CAP (`09028`) del record sono di Sestu, non di Setzu
  (South Sardinia, 09029). Stesso precedente del typo `Sicillia` gia' mappato in `REGIONS`.
- Righe gia' importate riallineate sul DB di sviluppo ri-risolvendo dai record legacy, solo dove
  `city_id` era NULL (mai sovrascritto un valore esistente): siti 25, 29, 57, 58 e company 10.
  L'`alias` del sito resta il valore legacy verbatim (il 58 si chiama ancora "Setsu"): si corregge
  solo sistemando il `comune` nel gestionale legacy, non da qui.
- NON risolvibili, restano vuoti (dato legacy errato, non un buco del dataset): siti 12 "Cancello
  ed Arnone" e 17 "Caserta 2" mandano provincia `NA` ma sono in `CE` — il comune esiste nel dataset
  e l'alias c'e', ma la ricerca e' scopata sulla provincia sbagliata, quindi servirebbe correggere
  il legacy o introdurre il fallback a livello regione; 67/68 sono placeholder senza provincia.
- Segnalato, non toccato: `company-sites`/`users`/`referents` mandano `country: null` e la provincia
  per NOME esteso ("Potenza") invece del codice targa, che e' l'unico formato accettato da
  `ItalianGeoLocalizer::province()` — regione e provincia falliscono a monte e il comune non viene
  nemmeno tentato (~1250 casi nella scansione). Inoltre le 45 `company_sites` non hanno alcuna riga
  in `addresses` pur avendo `street` nel legacy: import mai eseguito qui, o `buildAddress` scarta.
- Test eseguiti: `tests/Feature/Seeding/ItalianMunicipalitySeederTest.php` (3, nuovo) e
  `ItalianGeoLocalizerTest` (7, +2) verdi; 216 verdi su Unit/Support/Geo + Unit/Imports +
  Unit/Migrations + Feature/Seeding, 219 su Feature/Migration, 61 su Feature/OperationalSites.
  Pint pulito.

## CATEGORIE PRODOTTO — ALERT PRIMA DELL'AZZERAMENTO FUNZIONI AZIENDALI DEI FIGLI (2026-09-16) — VERDE, NON COMMITTATO

- Direttiva utente: assegnando una funzione aziendale a una categoria che ha discendenti con una
  funzione PROPRIA, deve comparire un popup che elenca le categorie che verranno azzerate.
- Il backend gia' azzerava quelle righe in silenzio (`ProductCategoryService::
  cascadeBusinessFunctionToDescendants`, spec 0023 — al massimo una funzione per catena
  radice->foglia). Nessuna modifica backend: cambia solo l'avviso lato UI.
- Frontend (nessun endpoint nuovo, si riusa la cache di `useProductCategoryTree`, che porta gia'
  `business_function_id` per nodo):
  - `business-function-inheritance.ts` → `collectDescendantsWithOwnBusinessFunction(nodes, categoryId)`
    + tipo `BusinessFunctionResetCandidate` (discendenti ricorsivi con funzione propria, depth-first).
  - `use-business-function-reset-confirmation.ts` (nuovo): intercetta il submit; scatta SOLO in edit
    quando `business_function_id` e' non-null e diverso da quello salvato. Trattiene i values
    validati, li salva al confirm.
  - `business-function-reset-dialog.tsx` (nuovo): `AlertDialog` con la lista dei nomi; l'azione fa
    `preventDefault()` per restare aperta durante il salvataggio (Radix altrimenti chiude subito).
  - `product-category-form-body.tsx`: `form.handleSubmit(businessFunctionReset.submit)` + dialog.
  - i18n `it/en-products.ts`: `businessFunctionResetTitle`, `businessFunctionResetDescription_one/_other`,
    `businessFunctionResetConfirm`.
- Confine deliberato: NON copre il caso reparent (spostare la categoria sotto un ramo che gia' porta
  una funzione azzera anch'esso i discendenti) — fuori dalla richiesta, da valutare se serve.
- Test: `business-function-reset-dialog.test.tsx` (7 casi: unit sulla raccolta + gate/confirm/cancel/
  no-op). Verde: 184 test su `src/features/product-categories` (24 file), `tsc -b --force` EXIT=0,
  ESLint pulito sui file toccati.

## IMPORT CATEGORIE — FUNZIONE "FORMAZIONE OLD" (2026-09-16) — VERDE, NON COMMITTATO

- Direttiva utente: le categorie prodotto IMPORTATE dal legacy che puntano alla funzione aziendale
  "Formazione" devono finire su una funzione nuova, "FORMAZIONE OLD", per non confondersi con il
  ramo "Formazione" del catalogo statico (che `QualificaBusinessFunctionLinkSeeder` continua a
  legare alla funzione "Formazione" vera).
- Implementato in `CategoryBusinessFunctionLinker::REDIRECTED_FUNCTIONS` (`Formazione` =>
  `FORMAZIONE OLD`), applicato in `redirect()` dentro `ownFunctionFor()` PRIMA del confronto con la
  funzione ereditata dal ramo (altrimenti un figlio con la stessa funzione genererebbe un warning di
  mismatch). Match sul nome case-insensitive; la funzione sostitutiva e' creata on-demand con
  `firstOrCreate(['name' => ...])`, senza `old_id`, quindi nessun import successivo la rivendica.
  Memoizzazione per-run in `$redirected`.
- Confine deliberato: il redirect NON tocca il percorso di ADOZIONE (`fillFreeSlot`) — una categoria
  adottata e' un nodo del catalogo statico qnet e resta sulla funzione "Formazione" reale.
- Vale per ogni lancio dell'import `product-categories` (seed `QualificaLegacyImportSeeder` e
  sezione Migrazioni da UI), non solo per il seed.
- Test: `tests/Feature/Migration/ProductCategoriesSourceImportTest.php` (+2 casi: redirect su
  categoria creata, nessun redirect su categoria adottata) — 15 verdi sul file, 490 verdi su
  `Feature/Migration` + `Feature/ProductCategories` + `Unit/Migrations`, Pint pulito.

## FILTRI TABELLA — VOCE "(VUOTI)" IN TUTTI I SET FILTER (2026-09-16) — VERDE, NON COMMITTATO

- Problema: il set filter mostrava solo i valori esistenti, mai le celle vuote (segnalato su
  `/product-categories`, "Funzione aziendale"; richiesto poi per OGNI colonna con checkbox).
- Marker: `null` dentro `values`. E' la voce blank nativa di AG Grid, gia' localizzata "(Vuoti)" da
  `AG_GRID_LOCALE_IT`, e torna al backend dentro il filter model: nessun codice frontend dedicato
  (unica modifica FE: il tipo `TableColumnValuesResponse.values` -> `(string | null)[]`).
- Colonne REALI (motore generico, vale per ogni dominio): `TableService::distinctFromColumn()` antepone
  `null` se lo scope ha celle vuote (NULL sempre; `''` solo sui tipi testuali —
  `FilterApplier::treatsEmptyStringAsBlank()`, MySQL castava `= ''` a 0 sulle numeriche) e la stringa
  vuota non e' piu' un valore a se'. `TableService::capValues()` tiene il blank fuori dal cap e da
  `hasMore`. `FilterApplier::applySet()` traduce il `null` in `whereNull` (+ `= ''` sui testuali) in OR
  col `whereIn`, dopo l'allow-list `options`.
- Colonne DERIVATE: regola unica — "cella vuota" = nessuna riga in fondo alla relazione, quindi
  `whereDoesntHave(<relazione>)` sia per rilevare il blank sia per filtrarlo. Trait condiviso
  `App\Tables\Concerns\HandlesBlankSetFilter` (`matchesBlankEntry()` / `withBlankEntry()`), incluso in
  `AbstractTableDefinition` (quindi in TUTTE le definition) e nelle classi-colonna. Coperti: relation
  columns di opportunities/quotes/request-management/contracts/work-orders/products/projects/tasks/
  campaigns/leads/registries/sectors/commission-configurations/rewarded-referents/business-functions,
  colonne condivise (operational_site, primary_contact, products_of_interest, offer_lines,
  business_function), geo di users/companies/company-sites/operational-sites/projects, employment e
  roles/permissions/user_type di users, client columns di request-management.
- Cataloghi NON scoped (geo, enum employment, ruoli, permessi, user_type): il blank e' offerto sempre,
  coerente con la lista che gia' mostra l'intero catalogo e non i soli valori presenti.
- Custom field / attributi (JSON): `ResolvesDistinctJsonValues` emette `null` quando la chiave manca o
  vale null/''/`[]`; `AppliesSetFilter` lo traduce in `whereNull` + `= ''` + `= '[]'`. Le colonne text
  passano dal FilterApplier nativo sul json path, quindi sono coperte dal motore generico.
- Mai offerto durante una ricerca nella checklist (il blank non matcha nessun termine).
- Colonne senza blank per costruzione: aggregati `*_count`, `status` di opportunities/work-orders,
  boolean (catalogo fisso Si/No), colonne `hasFilterValues: false` (nessun tab Set).
- Test: nuovi `TableBlankSetFilterTest` (4), `ProductCategoryTableTest` (+7), caso JSON in
  `FieldTypeFilteringTest`; aggiornate ~12 asserzioni preesistenti che ora includono `null`.
  Suite backend completa VERDE (7746 pass, 1 skip). Typecheck + vitest frontend puliti.

## REPORT RICHIESTE/ISCRITTI — CATEGORIE DINAMICHE `is_reportable` (spec 0131, 2026-09-15) — VERDE, NON COMMITTATO

- `product_categories.is_reportable` (default false, per-nodo, mai ereditato, come `is_selectable`): migrazione
  `2026_09_15_140000` con backfill per nome dei 6 rami storici + DIL; `QualificaCatalogSeeder::REPORTABLE_CATEGORIES`
  (solo alla creazione). Esposto in Resource, store/update, field authz, tree payload, colonna tabella categorie.
- `ReportBranchResolver::resolve()`: un ramo per categoria reportable, `key = (string) id`, ordine per nome,
  `categoryIds` = nodo + discendenti (una query). `keys()` = allow-list di `category_keys.*` nei FormRequest.
- Rimossi `config('request-management-report.branches')` e `ReportBranch::$columns`: tutti gli indicatori reali
  per ogni ramo (stub restano 0). ExportRun vecchi con chiavi `gol` non matchano piu' nessun ramo.
- `QuoteWorkflowMigrationTest` rollback `--step` portato a 74. Frontend: toggle "Visibile nei report" nelle
  Regole di gestione. Da fare in locale: `php artisan migrate`.

## DASHBOARD GESTIONE RICHIESTE — "N. TELEFONATE" NON SI AGGIORNAVA (2026-09-15) — VERDE, NON COMMITTATO

- Causa: frontend. Dashboard montata (modale / dialog note di riga) + `refetchOnWindowFocus: false`; la creazione nota
  invalidava solo `notesKeys.lists`, mai la query dashboard → valore fermo finche' non si cambia filtro o si riapre.
- Fix: `requestManagementKeys.dashboardAll(moduleKey)` (prefisso, `dashboard(...)` ora lo estende) +
  `useInvalidateRequestDashboard()` in `use-request-dashboard.ts`, passato come `onThreadChanged` a `NotesSection`
  (`request-work-collaboration.tsx`) e `NotesDialog` (`request-management-table.tsx`). Test in
  `request-management-table-notes.test.tsx`.
- Formula backend invariata: nessun filtro sul primo stato (rev-3 D-16, confermato dall'utente 2026-09-15), solo note
  dell'operatore GA2 della richiesta (D-17).

## MIGRAZIONE `product-categories` → FUNZIONE AZIENDALE (2026-09-15) — VERDE, NON COMMITTATO

- Legacy (`/Users/Repository/qnet`, repo separato): `Api/V2/ProductCategoryMigrationController` espone
  `business_function_id` = `service_categories.gruppo_lavoro_id` (0 → null), id di `/migration/business-functions`.
- qnet-2: `Migrations/Support/CategoryBusinessFunctionLinker` rimappa via `BusinessFunction.old_id` rispettando
  l'invariante spec 0023 (una sola funzione propria per ramo): figlio sotto un ramo che la fornisce eredita (own null,
  warning se diversa); riferimento non migrato → warning; adozione → solo slot libero (no own/ereditata/discendenti);
  figlio detached ricollegato → own + discendenti azzerati se il ramo la fornisce. Nuova colonna nativa preview.
- Ordine: `business-functions` (fase 1) prima di `product-categories` (fase 4) — gia' garantito da `MigrationOrder`.
- Test: `Migration/ProductCategoriesSourceImportTest` (+5). Legacy: 2 figli con funzione diversa dal padre
  (`Ente_Iso`, `FOR_Classi`) → warning, non applicata.

## SEED PRODUCTION — OPERATORI REALI DAL MANSIONARIO (2026-09-15) — NON COMMITTATO

Fonte: `Mansionario Operatori_Abilitazioni.csv` (utente). Catena `QualificaProductionDataSeeder`: Template → Catalog →
TaskTaxonomy → `TestUsersSeeder` (ora SOLO Ciro Cacciapuoti super-admin, attore dell'import) → LegacyImport →
BusinessFunctionLink → **`QualificaOperatorSeeder`** (sostituisce `QualificaOperatorSiteLinkSeeder`, rimosso).
- `QualificaCatalog/OperatorRoster.php`: 67 righe dall'xlsx `Mansionario_Operatori_Abilitazioni_aggiornato (2)`; righe in
  GIALLO escluse (Miriam Del Giudice, Maddalena Vitale, Elisa Finizio, Imma Pascale: account inesistenti). Shape riga:
  `[first, last, email, job, role, physicalCity, cities, categories]` (nome/cognome separati a mano). Email = chiave
  naturale: account gia' seedati con email vecchie NON vengono rinominati. Nota: `biagio.fusco@qualificagroup.it` (.it
  nel file), `yailin.calderon@` per "Yadin De Pina Calderon".
- Anagrafica: `Concerns/SyncsPersonName` upserta la `PersonalData` (individual, first/last) di ogni operatore e di Ciro
  Cacciapuoti (`TestUsersSeeder::TEST_USERS` ora `first_name`/`last_name`); `users.name` = "Nome Cognome".
  Sedi = CITTA', espanse a runtime su TUTTI i siti con alias `== citta'` o `citta' + ' ...'` (primo per alias = fisico,
  resto = remoti). **"no operatore" (7 profili: coordinatori/supervisori/marketing) = `[]` sedi abilitate e `[]` categorie**:
  solo sede fisica, nessuna competenza, `covers_all_product_categories` sempre false (converge) → mai assegnabili.
  Costante jolly `OperatorRoster::ALL` rimossa. Categorie per nome catalogo; "APL <regione>" = ramo `APL`; **Consulenza mai assegnata
  e NON collegata a COMMERCIALE** (decisione utente). Pomezia/Gaeta: nessuna sede legacy → warning.
- `QualificaRoleSeeder` + `QualificaCatalog/OperatorRoleCatalogue.php`: ruoli a blocchi, NOMI RUOLO IN ITALIANO (richiesta
  utente; costanti PHP in inglese). `supervisore-commerciale` ALLINEATO AL CSV (CSV aggiornato 15:34: prodotti + categorie
  prodotti + anagrafiche + referenti completi, Marketing e Lead, Richieste complete + report, `quote-workflows`
  "configuratore di stati", gruppo Premi e Incentivi, Richieste di modifica, Gestione Iscritti completa (CSV 15:46); niente Opportunita'/Task),
  `coordinatore-commerciale` (prodotti/categorie/anagrafiche/referenti + Marketing e Lead + Richieste + report; niente
  configuratore, premi, richieste modifica; + Gestione Iscritti completa), `marketing`, `commerciale` (anche senza `report`; + iscritti viewAny/view, solo propri, dal 2026-09-18), `commerciale-iscritti` (+ iscritti
  viewAny/view/viewSite), `supervisore-didattica` (+ viewSite richieste e iscritti). I vecchi ruoli inglesi
  `supervisor`/`commercial` (`RETIRED_ROLES`) vengono CANCELLATI a ogni run (chi li aveva resta senza quel ruolo).
- Password: `seeding.password` SOLO alla creazione; ruolo/sedi/competenza riconvergono a ogni run.
- Test: `Seeding/QualificaOperatorSeederTest`, `Users/QualificaRoleMatrixTest` (ex TestUsersSeederTest),
  `FieldChangeRequests/QualificaRolePermissionsTest`; gli altri test ruolo usano gli account reali .com.

## SPEC 0130 GESTIONE ISCRITTI (`enrollee-management`) — VERDE, COMMITTATO c86b49e5 (2026-09-15)

Spec `docs/specs/0130-enrollee-management.xml` (approved). Clone operativo di Gestione Richieste senza file copiati:
un solo codice, due moduli. Filtro = gruppo stato `validated`/`closed_won`; permessi `enrollee-management.*` separati.
- Decisioni: D-7 export dei due domini su `{domain}.export` (non piu' `quotes.export`, cambia anche Richieste);
  D-8 niente creazione in Iscritti (niente rotte store/form-context, niente permesso `create`: 16 abilita' + `updateSource`);
  D-9 `POST /assignment/selection-scope` accetta `domain: 'enrollees'` (`AssignmentDomain::requestModule()`).
- BE: `App\RequestManagement\RequestModule` (enum, UNICA fonte: `permission()`, `abilities()`, `allowsCreate()`,
  `statusGroups()`, `recordPath()`, `fromRequest()` via route default `requestModule`). `RequestManagementScope` prende il
  modulo come ultimo parametro (default Requests). Rotte in loop su `RequestModule::cases()`. Sottoclassi minime che
  cambiano solo `module()`/`resource()`: `EnrolleeManagementPolicy`, `EnrolleeManagementTableDefinition`,
  `EnrolleeManagementAuthorization`, `EnrolleeManagementNotable`, `EnrolleeManagementActivityAuthorizer`.
  Report asincrono: modulo in `ExportRun.state['module']` (assente = Requests). Nessuna migrazione.
- FE: `features/request-management/request-module.tsx` (`RequestModuleConfig` con `assignmentDomain`, `REQUEST_MODULE`,
  `ENROLLEE_MODULE`, `RequestModuleProvider`, `useRequestModule()`); API con `basePath` come primo parametro, query key
  e storage key con radice `module.key`; `enrollee-management-screens.tsx` (`generateRoutes: false`); pagine
  `pages/enrollee-management-{page,detail-page}.tsx`; namespace i18n `enrolleeManagement`.
- Regola: nuove differenze tra moduli SOLO come proprieta' di `RequestModule`/`RequestModuleConfig`, mai `if` sul modulo.
- Test esistenti cambiati per requisito (dichiarati): `ProtectedFieldRegistryTest:54`, `RequestContactTransferGridTest` (D-7),
  `RequestContactTransferPerspectiveTest` (AC-028), `FieldCatalogueEndpointTest:91`, `permissions-i18n-parity`, ~23 test FE
  (indice posizionale `basePath`).
- Verifica (verifier indipendente): Pest completo 7712/7717, unici rossi esterni `QualificaLegacyImportSeederTest`
  (lavoro categorie prodotto spec 0131) + `FieldCatalogueEndpointTest` poi corretto (Authorization+EnrolleeManagement+
  RequestManagement 885/885); Pint ok; Vitest 5232/5232; `tsc -b --force` ed ESLint puliti. AC-001..AC-018 verdi.
- L'avviso nella voce "telefono obbligatorio" qui sotto su tsc/test rotti dalla 0130 era uno stato intermedio: risolto.
- Aperti: grant dei permessi `enrollee-management.*` ai ruoli a cura dell'amministratore; deep link notifiche di
  assegnazione restano su `/request-management/:id` (scope/out).

## GESTIONE RICHIESTE: TELEFONO OBBLIGATORIO SUL NUOVO CLIENTE — VERDE (slice), NON COMMITTATO (2026-09-15)

Direttiva utente: in creazione richiesta, ramo `client_identity` (nuovo cliente), serve almeno un contatto `phone`/`mobile`.
- BE: `StoreRequestRequest` usa `ValidatesRequiredPhoneContact` (solo se `client_identity` presente; ramo `registry_id` invariato),
  `authorizationResource()` = `request-management`. Il trait ora legge/segnala su `phoneUniquenessContactsKey()`
  (default `personal_data.contacts`, `client_contacts` qui). Errore 422 su `client_contacts`. Solo create, non il PATCH del pannello.
- FE: `RequestCreateClientSection` passa `requiredCreateTypes=['phone']`; `useRequestCreateForm` blocca con `hasPhoneContact` → banner `personalData.section.phoneRequired`.
- Verifica: Pest RequestManagement+Registries+Referents 898/898, Pint ok; Vitest create-form/registry-phone 28/28, ESLint ok.
- ATTENZIONE: lavoro parallelo spec 0130 non committato in `features/request-management` rompe tsc (`use-request-form-context.ts:53`) e ~30 test del work panel/tabella/report: non causati da questa slice.

## SPEC 0129 COMPETENZA UTENTE: TUTTE LE CATEGORIE / RIGA "TUTTE" / CATEGORIA MADRE — VERDE, NON COMMITTATO (2026-09-15)

Spec `docs/specs/0129-user-competence-scope.xml` (approved). Estende la 0111.
- Flag `employment_profiles.covers_all_product_categories` (API `employment.covers_all_product_categories`,
  campo permesso proprio: chiavi `employment.*` ora 14). True = competente per ogni record; al salvataggio le righe
  vengono CANCELLATE (anche con `product_lines` assente); flag true + righe non vuote = 422.
- `employment_product_lines.product_category_id` nullable: riga (F, null) = tutte le categorie con funzione EFFETTIVA F.
- Righe utente accettano categorie madre `is_selectable=false` (D-6); madre senza funzione ammessa se un discendente ha F (D-7).
  Ridondanza (F,null)+(F,C) o (F,madre)+(F,discendente) = 422 sulla riga specifica.
- Regola unica in `OperatorCompetence`/`CompetenceProfile`; validazione in nuovo `App\Services\Assignment\CompetenceLineSetValidator`
  (`ProductLineSetValidator`/`SelectableProductCategory` INVARIATI per offerte/progetti/campagne/richieste).
  Rimossa `ValidatesEmployment::persistedCompetenceCategoryIds()`.
- Migration `2026_09_15_130000_add_competence_scope_to_employment_profiles`; `QuoteWorkflowMigrationTest` `--step` 73.
- FE: `ProductLinesField` prop `enforceManagementModeCap` SOSTITUITA da `variant?: 'card' | 'competence'` (checkbox "Tutte",
  madri pickable via `pickableCategoryIdsFor(..., { includeContainers })`); `ProductLineRow.all_categories?`;
  `useAssignmentFieldsVisibility` include `coversAllProductCategories` in `any`.
- Verifica: Pest 1502/1503 + 1506/1506 (unico rosso: flaky preesistente order-dependent `MetaEndpointTest` con
  `PermissionDoesNotExist users.export`, passa isolato, non toccato); Vitest 686 file/5209 test; ESLint e `tsc -b --force` puliti.
  Pest va lanciato con `XDEBUG_MODE=off` (con Xdebug segfault).
- Aperti: `CompetenceLineSetValidator.php` 332 righe (sopra soft-limit); flaky MetaEndpointTest da sistemare a parte.

## SPEC 0128 RICH TEXT (TIPTAP) NOTE + DESCRIZIONE TASK/TEMPLATE — VERDE (2026-09-15)

Spec `docs/specs/0128-rich-text-notes-task-description.xml` (approved, D-13 esteso ai template in build).
Codice interamente committato dall'utente (8e7a6dc6, eabb8802, dbe93b53).

- Formato: HTML sanificato server-side (`symfony/html-sanitizer`, allow-list D-1). Nuove dipendenze autorizzate:
  `symfony/html-sanitizer`, `@tiptap/*` 3.31.3. Nessun `dangerouslySetInnerHTML`.
- Backend `app/RichText/`: `RichTextSanitizer`, `RichTextImageProcessor` (data: URI → allegato `rich_text` del
  proprietario, dentro la transazione; `deleteUnreferenced` dopo commit), `RichTextAttachmentCopier` (ricorrenze e
  generazione da commessa), `RichTextPlainText`, `RichTextConverter`, `RichTextOwnerAccess`; `config/rich_text.php`.
  Allegati `rich_text`: visibili a chi legge il proprietario, 403 su POST/DELETE/index esplicito, esclusi da
  `GET /api/attachments` e da `TaskTemplateItemResource`. Alias attachable `note`, `task_template`.
- Note: menzione = `span[data-type="mention"][data-id][data-label]` (`MentionParser` via DOM); `NoteBodyProcessor`;
  body vuoto dopo sanificazione → 422; limite su testo visibile 5000. Task/template: `TaskDescriptionWriter`,
  `TaskTemplateDescriptionWriter`; vuoto → null. Field type `description` = `richtext`.
- Migrazione `2026_09_15_120000_convert_rich_text_columns_to_html` (reversibile). OGNI nuova migrazione deve
  incrementare `migrate:rollback --step` in `QuoteWorkflowMigrationTest` (ora 72).
- Frontend `components/rich-text/`: `RichTextEditor`, `RichTextContent`, compressione immagini client
  (1920px, webp 0.85, gif intatte), `isPayloadTooLargeError` (413 → errore su body/description/banner template).
  Blob URL immagini via `useSyncExternalStore` (StrictMode-safe); `setEditable(x, false)` per non sporcare il form.
- Verifier finale VERDE: backend 7600/7602 (unico rosso `StateForSelectTest`, flaky Geo non legato, passa isolato),
  frontend 5179/5179, eslint (solo 2 errori preesistenti quotes/registries), tsc -b, build ok.
- Da fare / segnalato: limiti Herd locale 2M (`herd.conf` client_max_body_size, `post_max_size`) da alzare;
  in produzione verificare limite body; verifica manuale UI 375/768/1024; `useAttachmentThumbnail`
  (`features/attachments/use-attachment-binary.ts`) ha lo stesso bug StrictMode dei blob URL (preesistente, non
  corretto); `AttachmentPolicy` senza controllo record per collection non `rich_text` (fuori scope); nessun header
  `X-Content-Type-Options: nosniff` sugli allegati.

## SEGNATEMPO — INTESTAZIONE ALLINEATA AGLI ALTRI MODULI — VERDE (2026-09-15, committato 08434be7)

- `/time-entries`: rimossi titolo/sottotitolo; `TimeEntriesDashboard` ora monta `PageHeader` (solo breadcrumb) con
  "Nuovo segnatempo" in `actions` (stesso schema di `TasksTable`), ancora gated su `meta.can_write` + `time-entries.create`.
  `TimeEntriesPage` non monta piu' `PageHeader`, wrapper `gap-6` come le altre pagine.
- Chiavi i18n `timeEntries.page.title/subtitle` rimosse (it/en, orfane).
- Test dashboard: aggiunto mock di `@/components/page-header` (breadcrumb richiede router; stesso mock di
  `work-orders-table.test.tsx`). Vitest time-entries 144/144, ESLint e `tsc -b --force` puliti.

## LINK AI RECORD COLLEGATI NELLE SCHEDE DETTAGLIO — VERDE, NON COMMITTATO (2026-09-14)

Richiesta utente (senza spec): nei dettagli, le relazioni verso record operativi diventano `RecordLink`.
Decisioni utente: SOLO record operativi (anagrafica, referente, opportunita', offerta, commessa, lead, prodotto,
azienda, sede aziendale, sede operativa, template task); le voci di configurazione (fonte, settori, stati, IVA,
UdM, metodo pagamento, layout, categoria/tipologia prodotto) restano testo. Le persone User restano
`UserProfileHoverCard`/testo (anche `reports_to`, destinatario provvigione `user`).

- `components/detail/record-link.tsx`: ora gated su `can('<domain>.view')` -> senza permesso testo semplice
  (vale anche per i link gia' esistenti su task/lead/campagna/progetto/offerta/richieste modifica).
  I test che rendono un dettaglio con link devono mockare `use-abilities` (+ `MemoryRouter` e stub
  `use-module-open-mode`, stesso schema di `campaign-detail.test.tsx`).
- Nuovi link: Opportunita' (anagrafica, referente, commerciale, segnalatore -> referents, lead d'origine, prodotti
  di interesse); Offerta (anagrafica, referente, commerciale, segnalatore, azienda, sede aziendale, sede operativa);
  Contratto (anagrafica, azienda, sede aziendale via `RelationField domain`, sede operativa); Commessa (offerta,
  template task, prodotto delle righe); Anagrafica (commerciale/segnalatore e card referenti); Lead (prodotti di
  interesse); Prodotto (fornitore -> registries); Sede aziendale (azienda nella KPI strip); Utente (sede primaria,
  sedi remote, azienda); Provvigione (prodotto, destinatario referent/registry); Funzione aziendale (sedi operative).
- Fuori scope, segnalato: `reward-card.tsx` (griglia buoni) mostra ancora l'anagrafica come testo; i link
  Opportunita'/Offerta del contratto usano ancora `RelatedRecordLink` (bottone modale con proprio gate).
- Verifica: vitest completo 672 file / 5141 test verdi; `tsc -b --force` pulito; ESLint pulito sui file toccati.

## DETTAGLIO PRODOTTO: ATTRIBUTI IN SOLA LETTURA NELLA CARD — VERDE, NON COMMITTATO (2026-09-14)

Richiesta utente: nel dettaglio prodotto la sezione attributi con layout configurato (es. "Dati incarico")
era montata con `AttributeLayoutRenderer` (input readOnly, sembravano editabili). Decisione utente: solo
testo in sola lettura, DENTRO la card prodotto.

- `features/products/product-attribute-values-section.tsx`: niente più RHF/renderer del form. Restituisce
  `RecordSection` (label/valore) nel `RecordSectionsGrid` della card: una per sezione del layout (per
  `sort_order`, attributi in ordine di riga), gli attributi valorizzati non piazzati sotto
  `attributes.layout.otherInformation`; senza layout una sola sezione `products.form.dynamicFields.title`.
  Solo attributi valorizzati; sezioni vuote omesse. Supera spec 0062 AC-014 per il dettaglio prodotto.
- Test `product-attribute-values-section.test.tsx` aggiornato (requisito cambiato): 4 verdi.
- Aperto (altra sessione): `product-detail.test.tsx` "VAT rate + Supplier" fallisce con
  "useAuth must be used within an AuthProvider" per il nuovo `RecordLink` sul fornitore, non per questa modifica.

## NOTA SEGNATEMPO -> NOTE INTERNE COMMESSA — SUPERATA IL 2026-09-17 (vedi voce commento sulla commessa)

Richiesta utente (senza spec): se un segnatempo ha una commessa, la sua nota va anche sulla commessa.
Decisioni utente: destinazione `work_orders.internal_notes` (non il componente note collaborative); solo il
testo (trim), nessuna intestazione; SINCRONIZZATA (modifica testo = sostituita al suo posto, nota svuotata /
commessa cambiata o tolta / segnatempo eliminato = rimossa, spostata sulla nuova commessa).

- `Services/TimeEntries/WorkOrderNoteSynchronizer` (`sync` prima del save, `detach` prima del delete), chiamato da
  `TimeEntryService` create/update/delete in transazione: copre anche `POST /tasks/{task}/time-entries` e il
  segnatempo del completamento Task (commessa derivata dal Task, D-5).
- Migrazione `2026_09_15_110000_add_work_order_note_to_time_entries_table`: `time_entries.work_order_note` = testo
  ultimo copiato (NON fillable, NON esposto nella Resource). Il blocco si trova solo come blocco intero separato da
  riga vuota (`\n\n`); se l'utente lo ha modificato a mano non combacia: il nuovo testo viene accodato, quello
  manuale resta. `DemoTimeEntrySeeder` scrive diretto sul model: i dati demo non sono sincronizzati.
- Test: `tests/Feature/TimeEntries/TimeEntryWorkOrderNoteSyncTest.php` (10). `QuoteWorkflowMigrationTest` rollback
  portato a 71 step (il test richiede il bump a ogni migrazione).
- Verifica: TimeEntries + Tasks 529 verdi; Pint pulito. Suite completa: fallisce
  `TaskConfigPermissionsTest` AC-051 task-statuses (422 "group field is required"), dovuto alle modifiche non
  committate di `StoreTaskStatusRequest` di un'altra sessione, non a questa modifica.

## SOTTO-TASK IN MODALE — VERDE, NON COMMITTATO (2026-09-14)

"Crea sotto-task" nel dettaglio task apre sempre il form in Sheet sopra il padre, qualunque sia la preferenza
pagina/modale. `TaskDetailScreen` (`frontend/src/features/tasks/task-screens.tsx`) usa un secondo
`useModuleOpener(TASKS_DOMAIN, { forceMode: OPEN_MODE_MODAL, viewAfterCreate: true, onSaved })`: `onSaved` invalida
`taskDetailQueryKey(id)` del padre, cosi' la lista sotto-task si aggiorna. L'apertura di un sotto-task esistente segue
ancora la preferenza. Fix loop di ricarica: il form sotto-task legge il padre con la STESSA query key
(`useTaskParentPrefill`) e al mount la rifetcha; `useEntityDetail` metteva il dettaglio in skeleton e smontava lo Sheet
col form, che rimontando rifetchava all'infinito. Ora gli sheet si renderizzano fuori dal ramo loading/error: non
reintrodurre early return prima di `{sheet}{subtaskSheet}`. Inoltre `useTaskParentPrefill` ha
`refetchOnMount: false`: riusa il padre in cache senza rifetcharlo, cosi' il dettaglio dietro lo Sheet non va in skeleton
all'apertura (test "reuses a cached parent without refetching it" in `use-task-form.test.tsx`). Test: `task-screens.test.tsx` (3, incluso regressione loop).
Verificati: vitest (screens/subtasks/detail/use-module-opener 63 ok), eslint, `tsc -b --force`.

## SPEC 0127 DATA DI COMPLETAMENTO SOLO SERVER — VERDE, NON COMMITTATO (2026-09-14)

Spec `docs/specs/0127-task-completion-date-server-owned.xml` (approvata). Decisione utente: un task chiuso negativo non ha data.
- `completion_date` e' `prohibited` in `StoreTaskRequest`/`UpdateTaskRequest` (null o assente ammessi); mapping rimosso da
  `CreateTaskData`/`UpdateTaskData`; ceiling in `TasksAuthorization` sempre readonly. Unica scrittura: `TaskCompletionService`
  (complete/approve = oggi, uncomplete/reject = null). PATCH verso closed_negative lascia la data null.
- `DemoTaskSeeder`: `isFinished()` (closing o in_validation) per le finestre di date; `applyActionOnlyStatus()` stampa
  `completion_date = min(end_date, oggi)` solo per in_validation/closed_positive.
- Frontend: `completion_date` fuori da `task-schema.ts`, `use-task-form.ts`, `task-form-payload.ts`, `task-form-server-error-fields.ts`,
  `CreateTaskPayload`; resta in lettura (`TaskDetail`, dettaglio, tabella).
- Verifica (verifier indipendente): Pest completo SERIALE 7509: 7508 passed, 1 skipped; Pint pulito; Vitest 669 file / 5123 test;
  ESLint e `tsc -b --force` puliti. Test: `TaskCompletionDateTest` (AC-001..005), `TaskRecordRoleMatrixTest` AC-006 e
  `DemoTaskSeederTest` aggiornati come REQUIREMENT CHANGED, `task-form-payload.test.ts` AC-007.
- Attenzione: `QuoteWorkflowMigrationTest` "rolls back all 7 new migrations" e' fallito una volta nella suite completa e passato
  isolato e al secondo giro completo (possibile interferenza con modifiche concorrenti di un'altra sessione): tenerlo d'occhio.
- Non incluso: righe storiche closed_negative con data gia' valorizzata (nessuna migrazione dati).

## SPEC 0126 ALLINEAMENTO TASK AL DOCUMENTO — VERDE, NON COMMITTATO (2026-09-14)

Spec `docs/specs/0126-task-document-alignment.xml` (approvata). Build a subagent (M1..M8) con ownership disgiunta,
in contemporanea con i lavori frontend "RESTYLE" e "BARRA MODALE" di un'altra sessione (file in parte condivisi:
`task-classification-section.tsx`, `task-screens.tsx`, `use-module-opener.tsx`, it/en-tasks).

**Contratto e naming da rispettare.**
- D-1: `TaskRecordRoles::isManager` = super-admin (`RoleAssignmentGuard::PRIVILEGED_ROLE`) OR (`tasks.manageAll` AND non assegnatario).
  Il super-admin assegnatario e' admin pieno (annulla B1 della 0125 per il super-admin); il manageAll non super-admin decade.
- D-2: permesso globale `notes.deleteAny` (`NotePolicy::abilities` = create, deleteAny); `NoteService::delete(User, Note)`
  ricontrolla l'host (`reauthorizeHost`) se l'attore non e' l'autore. Modifica note solo autore. DELETE nota = 200.
- D-3: `TaskAbilityResolver::canManageTimeEntry(User, Task, TimeEntry)`, usata da `TimeEntryPolicy` update/delete per i
  segnatempo con `task_id`: mandato su tutti, proprietario se `canComplete`, osservatore mai; `time-entries.manageAll` non basta.
- D-4: `Tasks\TaskManualStatusGuard::assertAllowed(Task, ?int)` prima di `fill()` in `TaskService::update`: il PATCH di stato
  richiede fase attuale open/pending (422 "The status of this task can only be changed through its actions.") e task non
  bloccato (422 "A blocked task cannot change status."). Flag `permissions.actions.change_status` (16a chiave);
  `close_via_status` false su bloccato. Frontend: select stato `forceDisabled={!changeStatus}`.
- D-5: migrazione `2026_09_14_140000_set_in_validation_task_statuses_completion_percentage` (down per nome 30/80, stati custom
  restano 100); `Store/UpdateTaskStatusRequest`: group in_validation => percentuale 100. Il test `QuoteWorkflowMigrationTest`
  conta le migrazioni: ogni nuova migrazione va aggiunta li'.
- D-6: `request-update` consentito su task bloccato; complete/uncomplete/approve/reject restano 409.
- D-7: dialog Richiedi aggiornamento con dedup, tutti selezionati di default, "Seleziona tutti" tri-stato (`tasks.actions.requestUpdate.selectAll`).
- D-8: `useModuleOpener(domain, { viewAfterCreate })`, attivo su Tasks: dopo create in pannello apre la vista del nuovo id.

**Verifica (verifier indipendente).** Pest completo SERIALE 7493: 7492 passed, 1 skipped (preesistente); Pint pulito;
Vitest 668 file / 5117 test; ESLint 0 errori; `tsc -b --force` pulito. AC-001..016 mappati su test. I test di M5
(`TaskManualStatusChangeTest`) sono stati riprovati rossi a posteriori disattivando il fix (4 rossi attesi).

**Aperti / prossimi passi.**
- Spec 0127 (data di completamento solo server) completata e verde, voce sopra.
- `TaskService.php` 446 righe (soft 300): split da pianificare.
- Commit in attesa di via libera utente (§3.6). Nota: la 0125 e' gia' committata in `17b9da2b`.

## LINK DEI DETTAGLI IN MODALE — VERDE, NON COMMITTATO (2026-09-14)

Solo frontend. Direttiva utente: ogni link a un altro record su una pagina dettaglio apre una MODALE; dalla modale
l'icona `SheetDetailPageLink` porta alla pagina intera.
- Nuovo `features/modules/use-record-modal-link.ts` (`useRecordModalLink(domain, id)` → `{ onClick, sheet }`): `useModuleOpener`
  con `forceMode: 'modal'`; click semplice = `preventDefault` + modale, click modificato (cmd/ctrl/shift/alt, tasto non primario)
  lasciato al browser. Il link resta un `<Link>` con href reale (nuova scheda/copia).
- `RecordLink` (`components/detail/record-link.tsx`) lo usa: coperti task, lead, progetti, campagne. Migrati anche:
  opportunità nel dettaglio offerta (`quote-detail-sections.tsx` → `RecordLink`), "Vai all'opportunità" del lead
  (`GoToOpportunityAction` in `lead-conversion-action.tsx`), record della richiesta modifica campo
  (`findModuleRecordByPath` nuovo in `module-registry.ts`; path non registrato = `Link` come prima).
- Già modali prima: contratti (`RelatedRecordLink`), persone (`UserProfileHoverCard`).
- Test: harness con stub di `use-module-open-mode` (useModuleOpener legge `useAuth`) in task-detail, task-collaboration-section,
  campaign-detail, project-detail, quote-detail, field-change-request-detail; nuovi `record-link.test.tsx`, `module-registry.test.ts`,
  caso modale in `lead-conversion-action.test.tsx`. Verifica: tsc -b pulito, ESLint pulito, Vitest 672 file / 5134 test verdi.
- Fuori scope (non dettagli, lasciati com'erano): link task nella griglia segnatempo (`time-entry-entries-columns.tsx`),
  alert opportunità esistente nei form (`existing-opportunity-alert.tsx`, `opportunity-screens.tsx`), card progetti,
  premi nella riga espansa dei referenti premiati (usano la modalità utente).

## BARRA MODALE (apri pagina dettaglio + chiudi) — VERDE, NON COMMITTATO (2026-09-14)

Solo frontend. La X di `SheetContent` (absolute top-4 right-4) si sovrapponeva agli header dei `DetailScreen`/`FormScreen`.
- Nuovo `SheetToolbar` in `components/ui/sheet.tsx`: barra in flusso `bg-surface border-b`, azioni a destra + X (`closeLabel` obbligatorio, i18n). Si usa con `showCloseButton={false}`.
- `features/modules/sheet-detail-page-link.tsx` (`SheetDetailPageLink`): bottone icona `SquareArrowOutUpRight`, path `${basePath}/${id}` dal registry, `onOpen(path)` fornito dall'host (chiude + naviga). NON usare `<Link>`/hook del router: `UserDetailSheetProvider` è montato in `App.tsx` SOPRA `RouterProvider` (usa `router.navigate`); inoltre la Sheet non sempre si smonta navigando (`ModuleDetailPage` riusata per un altro id).
- `useModuleOpener` (tutte le modali dei moduli): toolbar sempre montata; link su `view`/`edit`, assente su `create`/`duplicate` (nessun id).
- `UserDetailSheetProvider` (clic su una persona, es. da `/tasks/:id`): toolbar con link a `/users/:id` (rotta generata dal registry `users`).
- i18n `common.close`, `common.openDetailPage` (en/it). Test in `use-module-opener.test.tsx` e `users/user-detail-sheet.test.tsx`.
- Da valutare: le altre ~50 Sheet/Dialog ad-hoc (dialog di azione con `SheetHeader` proprio) usano ancora la X assoluta.

## RESTYLE FORM + DETTAGLIO TASK (solo grafica) — VERDE, NON COMMITTATO (2026-09-14)

Solo frontend, nessun cambio di contratto/payload/backend. Riferimento visivo: form task di `q-net` + form/dettaglio Opportunità.
- **Select con badge:** prop generica opzionale `renderItem(item)` su `AsyncPaginatedSelect` (trigger + opzioni) e
  `RelationSelectField` (+ `RelationFieldRef.meta?` passato all'opzione idratata). Nuovo `tasks/task-lookup-select-field.tsx`
  (`TaskLookupSelectField`): Stato/Tipologia/Priorità/Importanza/Categoria rendono `TaskLookupBadge` da `meta.color/icon`.
- **Form:** layout record-form come Opportunità: `task-form-header.tsx` (barra sticky, badge stato/scadenza, Salva/Annulla via `form=task-form`),
  colonna laterale `task-form-summary.tsx` + `TaskClosureSection`; `RecordFormActions` in fondo; skeleton = `RecordFormSkeleton`.
  Registry: `formOwnsHeader: true`. Titolo come card principale con input grande. Pianificazione: data+ora affiancate,
  **`completion_date` non più nel form** (si valorizza con Completa; resta nello schema/payload invariato).
  Flag chiusura: label brevi ("Feedback obbligatorio"/"Validazione"), spiegazione nel hint (i).
- **Dettaglio:** `task-detail-header.tsx` (`TaskDetailHeader` con bottone Modifica sulla card, gated `onEdit && permissions.resource.update`;
  `TaskDetailStats` completamento/date/stima). Registry `detailOwnsEditAction: true`.
  Anagrafica, referente, opportunità, commessa = link alla pagina via `RecordLink`. Persone (creatore, richiedente,
  assegnatari, osservatori) = `TaskPerson` in `task-people-list.tsx` con `UserProfileHoverCard` (come il dettaglio Lead). I test che montano `TaskDetailView` ora usano `MemoryRouter`.
- Nuove chiavi i18n `tasks.form.{titlePlaceholder,descriptionPlaceholder,header.*,summary.*}` (it/en).
- Verifica: `tsc -b --force` pulito, ESLint pulito, Vitest completo 668 file / 5115 test verdi (+ test renderItem, badge nei lookup, Modifica sulla card).
- Da verificare a occhio: 375/768/1024 in Sheet e pagina dedicata. Prossimo passo: chiedere se committare.

## SPEC 0125 CORREZIONI AUTORIZZAZIONE TASK (B1-B3) — COMMITTATO (2026-09-14)

Spec `docs/specs/0125-task-authorization-hardening.xml` (approvata). Nasce dall'analisi di
`DOC Tasks.docx` rispetto al codice. Solo backend: nessuna migrazione, nessun permesso nuovo, nessun file frontend.

**Contratto e naming da rispettare.**
- B1: `TaskService::delete(Task $task, User $actor)` (firma nuova) ricontrolla `TaskAbilityResolver::canDelete()`
  oltre il `Gate::before` e risponde 403 PRIMA del 409 sui sotto-task. Chiamanti: `TaskController::destroy`,
  `TasksTableDefinition::deleteModel()` (`Auth::user()`). Un super-admin assegnatario non elimina, a meno che non sia anche creatore o richiedente.
- B2: flag `permissions.actions.delete` = `tasks.delete` AND `canDelete`. `TasksTableDefinition::authorizeDelete()`
  (override) = Gate AND `canDelete`, usato anche dalla row-action `delete`. Nel bulk-delete la riga risulta `forbidden`.
- B3: `TaskAbilityResolver::canCreateSubtask()` (= `canUpdate`). Nuovo `Tasks\TaskParentAccessGuard::assertMayAttach(?int, User)`:
  padre visibile E `canCreateSubtask`, altrimenti 422 su `parent_task_id` con il messaggio unico
  'The selected parent task is not available.'. Chiamato in `create()` (Step 2a) e in `update()` solo se
  `isDirty('parent_task_id')`. Il flag `create_subtask` include `canCreateSubtask`.
- `DemoTaskSeeder`: il sotto-task e' creato dal creatore del padre. `statusesFor()` esclude gli stati di chiusura sotto un
  padre che inizia nel futuro. La finestra di start dei sotto-task chiusi arriva al massimo a oggi. Corregge un difetto preesistente
  (completion < start) emerso quando e' cambiata la sequenza casuale.

**Verifica (verifier indipendente, seconda passata).** Pest completo SERIALE 7455 test: 7454 passed, 1 skipped;
Seeding 93/93; Pint pulito; `tsc -b --force` pulito. Test nuovi: `TaskDeleteAuthorizationTest` (AC-001..007),
`TaskParentAccessTest` (AC-008..014). La prima passata era rossa (seeder) ed e' stata corretta.

**Aperti / prossimi passi.**
- `TaskService.php` 439 righe e `TasksTableDefinition.php` 314 (soft limit 300): valutare lo split.
- Differenze dal documento cliente ancora da decidere (sezione 2 dell'analisi): permessi su note, segnatempo e documenti
  (documenti senza controllo per task, 0117 D-8), avanzamento 30/80% in validazione anziche' 100%, Completa e
  Richiedi aggiornamento vietati su task bloccato, destinatari di Richiedi aggiornamento, "Da assegnare" con piu'
  assegnatari, admin assegnatario in modifica, redirect al dettaglio dal pannello laterale.
- Commit in attesa di via libera utente (§3.6).

## SPEC 0124 MODELLI DI TASK — COMMITTATO (2026-09-14)

Spec `docs/specs/0124-task-templates-module.xml` (approvata). Build a subagent con ownership disgiunta,
in parallelo alla build 0123 non committata (file disgiunti: nessun file 0123 toccato).

**Backend.**
- Schema: `task_templates` (name unique, description, is_active), `task_template_items`
  (task_template_id cascade, title, description, estimated_minutes, task_status_id restrict, due_offset_days,
  sort_order), `work_orders.task_template_id` (nullable, restrict, solo in create). Morph alias
  `task_template`/`task_template_item`; `task_template_item` in `config/attachments.php`.
- CRUD `task-templates`: `TaskTemplateService` + `TaskTemplates\TaskTemplateItemWriter` (sync righe: id=update,
  senza id=create, mancante=delete via Eloquent per ripulire gli allegati), `Rules\TaskTemplateItemStatus`
  (attivo + gruppo open/pending), `TaskTemplatesAuthorization`, `TaskTemplatesTableDefinition`
  (+ `TaskTemplateItemsCountColumn`), `routes/api/task-templates.php`, nav `config/navigation/tasks.php`.
  Delete: 409 se referenziato da una Commessa; righe cancellate una a una (la cascade DB NON pulisce i file).
  `TaskStatusService::delete` 409 se lo stato e' usato da una riga modello.
- Generazione: `WorkOrders\WorkOrderTaskGenerator::generate(WorkOrder, TaskTemplate, User)` (pattern
  `TaskOccurrenceFactory`, NON usa `TaskService`), Step 4 in `WorkOrderService::create`; actor via
  `Auth::user()` (raggiungibile solo da rotte auth:sanctum). Assegnatari = tutti i supervisori; creator =
  requester = actor; end_date = start_date + due_offset_days; allegati copiati con
  `AttachmentService::copyTo` (collection `documents`), file copiati rimossi su errore. Notifiche differite da
  `TaskNotifier` via `DB::afterCommit`.
- `DemoTaskTemplateSeeder` (3 modelli) in `DemoDataSeeder`, prima di `DemoTaskSeeder`.

**Frontend.** `features/task-templates/*` (tabella TableView, form con `SortableList` delle righe, select stato
filtrata su meta.group, allegati per riga con staging e upload posizionale dopo la create), pagina, router,
breadcrumb, i18n it/en. Campo `task_template_id` nel form Commessa (solo create; in edit readonly tramite
tetto dei permessi sui campi) e nel dialog Programma del contratto; `TASK_TEMPLATES_FOR_SELECT_RESOURCE` si
importa da `task-templates/for-select-api.ts`.

**Verifica (verifier indipendente).** Pest completo SERIALE 7437 test: 7436 passed, 1 skipped; Pint pulito;
Vitest 5099/5099; `tsc -b --force` pulito; ESLint pulito sui file 0124 (2 errori pre-esistenti estranei:
`quotes/column-renderers.tsx:11`, `registries/registry-form-metadata.test.tsx:271`). AC-001..028 mappati su test.

**Aperti / prossimi passi.**
- `WorkOrderService.php` 318 righe (soft limit 300): valutare l'estrazione del read path (`forSelect`/`loadDetail`).
- Estrazione suggerita: `task-template-item-attachment-staging.tsx` duplica `tasks/task-attachment-staging.tsx`
  -> primitive condivisa dopo il commit della 0123.
- `DemoWorkOrderSeeder` gira prima delle tassonomie task: per usare i modelli nel demo va riordinato.
- Commit in attesa di via libera utente (§3.6), da separare dai file della 0123.

## MODULO TASK — SPLIT SERVIZI + SPEC 0123 (SEGNATEMPO NEL COMPLETAMENTO, COERENZA SOTTO-TASK) — COMMITTATO (2026-09-14)

**Refactor senza cambio di comportamento** (prerequisito della 0123):
- `TaskService` 480 -> 358: estratti `Tasks\TaskForSelectService` (read path for-select, unico
  chiamante `TaskForSelectController`) e `Tasks\TaskReferentRegistryGuard::assertBelongs()` (AC-014 0101,
  chiamato dentro la transazione come prima).
- `Tasks\TaskActionService` 432 -> 155: restano `block/unblock/requestUpdate`. Nuovo
  `Tasks\TaskCompletionService` (271) con `complete/uncomplete/approve/reject`; iniettato da
  `TaskComplete/Uncomplete/Approve/RejectController`. `assertNotBlocked()` spostato in
  `TaskWriteLock::assertNotBlocked()` (statico, stesso messaggio 409). Docblock aggiornati.
- Verifica eseguita: Pest Tasks+Unit Tasks+DemoTaskSeeder 418/418 prima e dopo; suite completa SERIALE
  7330 test, 7329 passed, 1 skipped; Pint pulito. ATTENZIONE: `pest --parallel` da' 73 falsi errori
  "Call to undefined function" (helper di test duplicati fra file) + 1 falso rosso su
  `TaskConfigPermissionsTest`: usare solo la suite seriale.

**Archivio.** Le voci dal 2026-09-11 indietro sono in `docs/handoff-archive/2026-09.md`; intestazioni
delle voci rimaste corrette in "COMMITTATO" (albero pulito a inizio sessione).

**Spec 0123 IMPLEMENTATA E VERIFICATA** `docs/specs/0123-task-completion-time-entry-and-subtask-coherence.xml`.

Contratto e naming da rispettare:
- `POST /tasks/{task}/complete` richiede `time_entry{date,task_type_id,minutes,start_time?,end_time?,notes?}` su
  entrambi i percorsi; creato in `TaskCompletionService::complete()` via `TimeEntryService::create()` dentro la
  transazione, SENZA `time-entries.create` (D-2). Regole da fonte unica
  `Http\Requests\TimeEntries\TimeEntryValidationRules::rules(prefix)` (usata anche da `StoreTaskTimeEntryRequest`).
- `TaskActionOnlyStatusGuard::assertReachableByPatch()`: PATCH verso fasi `in_validation`/`closed_positive` = 422
  per chiunque, super-admin incluso. Il seed demo scrive quegli stati direttamente sul model.
- `TaskActionAvailability::hasOpenSubtasks()/openSubtasksCount()` (figli diretti, ignora visibilita'): 422 su
  complete/approve; `TaskResource.open_subtasks_count` (una query, Resource usata solo per singolo record).
- `TaskParentDateRangeGuard` (D-7 figlio, D-8 padre che restringe -> 422 col conteggio).
- `TaskWriteLock::isLockedByAncestor()` / `assertParentChainUnlocked()`: cascata solo strutturale.
- `permissions.actions` 15 chiavi: + `close_via_status`, `create_subtask` in coda.
- UI kit: `AsyncPaginatedSelect.isItemDisabled` (riga in `async-paginated-select-option.tsx`), passthrough in
  `RelationSelectField`. FE: `task-status-option-availability.ts`, `task-complete-time-entry-section.tsx` +
  `use-task-complete-time-entry-form.ts` (useForm separato, errori 422 `time_entry.*` mappati a mano),
  `use-task-parent-prefill.ts`, `task-parent-date-range.ts`, `task-form-attachments-upload.ts`.

Verifica (verifier indipendente, VERDE): Pest SERIALE 7437 test, 7436 passed, 1 skipped (+1 test super-admin D-4
aggiunto dopo, 6/6); Vitest 668 file / 5099; `tsc -b --force` EXIT 0; Pint e ESLint puliti sui file 0123 (2
errori ESLint preesistenti in `quotes/column-renderers.tsx` e `registries/registry-form-metadata.test.tsx`).
AC-001..040 e 042 PASS. Test preesistenti modificati per cambio di requisito: TaskActionNotificationsTest,
TaskActionsTest, TaskClosureFeedbackTest, TaskCompletionPercentageTest, TaskValidationRequirementTest,
TaskRecurrenceContractTest, TaskDocumentsTest, TaskRecordRoleMatrixTest; FE api.test.ts, task-detail.test.tsx,
task-subtasks-section.test.tsx, use-task-form.test.tsx.

APERTI: AC-041 responsive manuale (pop-up Completa, select Stato, form sotto-task) a carico utente. Commit: la
0123 e la 0124 condividono l'albero; committare SEPARATAMENTE (file 0124: task-templates, work-orders,
migrazioni 2026_09_15_*, QuoteWorkflowMigrationTest, FieldCatalogueEndpointTest, WorkOrderMetaTest, ecc.).

## MODULO TASK — FASE 7: PIANIFICAZIONE RICORRENTE (spec 0120) — VERDE, COMMITTATO (2026-09-14)

**Cosa e'.** Regola di ripetizione sul Task; le occorrenze sono Task AUTONOMI (non sotto-task) legati
alla serie. Spec approvata dall'utente 2026-09-14, fatti riverificati e piano aggiunto nella spec.

**Backend (nomi da rispettare).**
- Tabella `task_recurrences` (`frequency`, `interval`, `weekdays` JSON ISO 1..7, `month_day`, `ends`,
  `ends_on`, `occurrence_count`, `generated_until`); `tasks.task_recurrence_id` nullOnDelete, NON
  fillable; UNIQUE(`task_recurrence_id`, `end_date`) = idempotenza. Migrazioni
  `2026_09_14_130000_create_task_recurrences_table`, `2026_09_14_130100_add_task_recurrence_id_to_tasks_table`.
- `TaskRecurrence` (+factory), enum `TaskRecurrenceFrequency`/`TaskRecurrenceEnd`, DTO
  `Tasks\TaskRecurrenceData`; servizi `Tasks\TaskRecurrenceCalculator` (puro,
  `nextDates(rule, from, limit, alreadyGenerated=1, horizon=null)`), `TaskOccurrenceFactory::materialize()`,
  `TaskRecurrenceService::set()/replace()/cancel()` (rigenera solo le occorrenze future VERGINI, D-11).
- Comando `tasks:generate-recurrences {--recurrence=} {--dry-run}`, PRIMO `Schedule::command` del repo
  (`routes/console.php`, dailyAt 01:00, withoutOverlapping).
- Contratto: `recurrence` in POST/PATCH (assente = invariata, null = cancella, oggetto = crea/sostituisce)
  e `data.recurrence` nel `TaskResource`. `FIELD_TYPES` +`'recurrence' => 'recurrence'` in fondo (25);
  `PROTECTED_FIELDS` 19; `actions()` 13; catalogo `tasks.*` 15.
- DEVIAZIONE DI CONVENZIONE (voluta, AC-028): non-mandatario che invia `recurrence` -> 403 via
  `UpdateTaskRequest::authorize()`; gli altri campi protetti restano 422. Task congelato -> 422 (D-13).
- `EnforcesFieldPermissions::readTopLevel()` generalizzato a relazioni to-one (prima solo to-many).
- Contatore `QuoteWorkflowMigrationTest` = 66. `TaskService.php` a **480/500**: il prossimo intervento
  DEVE splittare per write path prima di aggiungere righe.

**Frontend.** `task-recurrence-section.tsx` (dopo `TaskPlanningSection`), `task-recurrence-weekdays-field.tsx`,
`task-recurrence-format.ts` (badge nel dettaglio), `task-recurrence-defaults.ts`, `task-recurrence-types.ts`
(ri-esportato da `types.ts`), `task-form-server-error-fields.ts`. PATCH invia `recurrence` solo se la regola
normalizzata differisce dalla persistita e mai se `permissions.fields.recurrence` non e' editabile. i18n
`tasks.form.sections.recurrence.*`, `tasks.form.recurrence.*`, `tasks.detail.recurrence.*`.

**Verifica (verifier indipendente).** AC-001..029, 031..035, 037..039 PASS. Pest Tasks/Unit/QuoteWorkflows/
Authorization/Seeding 1614/1614; consumer di EnforcesFieldPermissions (Opportunities/WorkOrders/Users/
Companies) 596/596; Vitest tasks+i18n 318/318; Pint, ESLint, `tsc -b --force` puliti; `schedule:list` ok.

**Manuali, a carico utente.** AC-030: cron `php artisan schedule:run` ogni minuto sul server (senza, nessuna
occorrenza viene generata). AC-036: responsive 375/768/1024 della sezione Ricorrenza.

**Prossimi passi.** Commit su richiesta. Poi: segnatempo nel pop-up di completamento Task; decidere PATCH
di un assegnatario verso `in_validation` (0121); split di `TaskService`.

## MODULO SEGNATEMPO (spec 0122) — VERDE, COMMITTATO (2026-09-14)

**Cosa e'.** Nuovo modulo `time-entries`: replica flusso e grafica del segnatempo di q-net
(`/Users/Repository/q-net/src/app/features/workActivities`, backend `/Users/Repository/qnet`)
con i requisiti di `Doc Segnatempo.docx`. Spec `docs/specs/0122-time-entries-module.xml`
(D-1..D-15, 42 AC). Tutti i microtask MT-B1..B5, B2b, U1, U2, F1..F7 chiusi e verificati.

**ATTENZIONE git.** Il commit `fc677f65` "feat(time-entries): initial implementation" contiene
SOLO rifiniture 0121 + la bozza della spec 0122: nessun codice segnatempo. Il modulo e' tutto
nel working tree, NON committato.

**Decisioni utente 2026-09-14 (non riaprire).** Struttura grafica 1:1 con q-net ma componenti
qnet-2, lucide, niente rich text, nessuna nuova dipendenza. Documento vince sul flusso, q-net
sulla grafica. Vista team inclusa. Segnatempo nel pop-up di completamento Task: FUORI, prossima spec.

**Backend (nomi da rispettare).**
- Tabelle `time_entries`, `time_entry_day_notes`; model `TimeEntry`, `TimeEntryDayNote` (morph
  `time_entry`, `time_entry_day_note`); `config/time_entries.php` (480/366/15/100).
- `TimeEntryPolicy`, 9 permessi `time-entries.{viewAny,view,create,update,delete,export,
  exportMonthly,manageAll,viewAll}`. Proprietario scrive i propri; `manageAll` tutti; responsabile
  (discendenti via `employment_profiles.reports_to_id`, `TimeEntrySubordinateResolver`) legge
  soltanto (`TimeEntryReadAuthorizer`, regola R). Day-notes gated da `update`.
- Rotte `routes/api/time-entries.php`: GET lista (fuori envelope), stats/overview|pulse|team,
  exports/filtered|monthly, PUT day-notes, POST/GET/PUT/DELETE `{timeEntry}`; in
  `routes/api/tasks.php` GET|POST `tasks/{task}/time-entries`.
- Servizi in `app/Services/TimeEntries/`: `TimeEntryLinkResolver` (D-5: con task_id titolo e
  collegamenti imposti dal Task), `WorkCalendar` (festivita' IT, Pasqua via `easter_days`),
  `DailyTargetResolver` (standard - break, fallback 480), `TimeEntryDaySetBuilder`/`DayBuilder`,
  `TimeEntryStatsService` + `TimeEntryTeamPulseService` (formula condivisa
  `TimeEntryClusterCalculator`), `TimeEntryExportService` + `app/Exports/TimeEntries/*` (xlsx
  PhpSpreadsheet da codice). Enum `TimeEntryDailyStatus`.
- Delta for-select: `registry_id` su opportunities/work-orders; work-orders item `meta.registry`.
- Overview: `tracked_days` = giorni filtrati CON segnatempo (bug corretto in build).

**Frontend.** `features/time-entries/{base .ts, form, dashboard, days, team, task, page}`,
`time-entries-dashboard.tsx`, pagine `/time-entries`, `/time-entries/new`, `/time-entries/:id`
(rotte esplicite in `router.tsx`, non module-registry), breadcrumb e icona `clock`. Tab
"Segnatempo" con badge totale in `features/tasks/task-collaboration-section.tsx`. Nuovi
`components/ui/popover.tsx` e `slider.tsx` multi-thumb (`thumbLabels`). i18n solo namespace
`timeEntries` (`it/en-time-entries.ts`, testi allineati a q-net, nessuna chiave orfana).

**Verifica (eseguita dai verifier + lead).** Pest `tests/Unit` 1005 verdi, `tests/Feature` 6324
verdi / 1 skip preesistente / 0 fail (la suite intera in un solo processo va in signal 11: eseguirla
a blocchi Unit/Feature). Filtro TimeEntr 105 verdi. Vitest intera 5019/5019, `tsc -b --force`
pulito, ESLint pulito sul modulo (7 problemi preesistenti in altri moduli), `vite build` ok, Pint
pulito, nessuna nuova dipendenza.

**Prossimi passi.** (1) Commit su richiesta utente. (2) Spec successiva: segnatempo nel pop-up di
completamento Task (D-9 fuori scope). Contatore `QuoteWorkflowMigrationTest`: la 0122 ha aggiunto
+2 migrazioni, la 0120 (sessione parallela) altre +2.

## MODULO TASK — FIX SEED DEMO + TRADUZIONE "RICHIEDI AGGIORNAMENTO" — VERDE, COMMITTATO (2026-09-14)

- `DemoTaskSeeder` riallineato alla 0118: niente `taskStatusId` (lo stato estratto si applica DOPO
  la create via `TaskService::update()` agendo come creatore), `requesterId` sempre valorizzato,
  osservatori estratti fuori da assegnatari+creatore+richiedente (`pickWatchers(array $excludedIds)`
  in `Concerns/PicksTaskRecordLinks`). Il seed gira dentro `withoutNotifications()`
  (`Notification::fake()` + `swap` del canale precedente nel `finally`): nessuna mail accodata.
  File a 346 righe (soft limit superato, sotto hard).
- `lang/it.json`: tradotte "Update requested" e ":requester asked for an update on :title"
  (`TaskUpdateRequested`), pinnate da un test nuovo in `TaskUpdateRequestedNotificationTest`.
- Test nuovi: seeding senza notifiche (`DemoTaskSeederTest`), traduzione it/en.
- Verifica eseguita: Pest `tests/Feature/Tasks tests/Unit tests/Feature/Seeding` 1391/1391; Pint
  pulito sui file toccati.

## MODULO TASK — FASE 8: FLAG "RICHIEDE VALIDAZIONE" (spec 0121) — VERDE (2026-09-14)

**Cosa e'.** Chiusura e validazione diventano due regole indipendenti sul Task:
`requires_closure_feedback` (esistente) obbliga il feedback su `/complete`; il nuovo
`requires_validation` manda in validazione il completamento di chi NON detiene il mandato.
RETTIFICA la 0116 (D-4, AC-019, AC-043): il client non sceglie piu' SE andare in validazione.

**Stato git.** Gran parte della feature e' nel commit `41c6ee6f` (09:13, non creato da questa
sessione ne' dai suoi agenti); sopra restano modifiche non committate (rifiniture + test). Nessun
commit fatto da questa sessione.

**Regole congelate (D-1..D-7), da NON riaprire.**
- Percorso derivato in UN solo metodo: `TaskAbilityResolver::completionRequiresValidation(User, Task)`
  = `requires_validation` && !ownsTheMandate. Creatore/richiedente/gestore chiudono sempre;
  l'admin-assegnatario (non gestore per 0116 D-2) va in validazione.
- `/complete`: il server decide SE; il client sceglie IN QUALE stato `in_validation`.
  `validation_status_id` obbligatorio sul percorso validazione, VIETATO (422) su quello di chiusura.
- Feedback: `TaskClosureFeedbackGuard::assertProvided()` su entrambi i percorsi (su `/complete`);
  `assertSatisfied()` resta per il PATCH.
- PATCH verso stato di chiusura da chi non ha mandato su Task con flag -> 422 `task_status_id`
  (`TaskValidationRequirementGuard::assertClosableBy`, innestato in `TaskService::update()`).
- `permissions.actions.complete_to_validation` (13 azioni): unico segnale per il frontend.
- UI: i due flag nel form (sezione Chiusura); `closure_feedback` FUORI dal form, solo nel pop-up;
  pop-up senza switch, titolo "Invia in validazione" + select obbligatoria sse
  `complete_to_validation`. 422 del PATCH su `closure_feedback`/`task_status_id` -> toast
  (`TOAST_ONLY_SERVER_ERROR_FIELDS` in `use-task-form.ts`).

**Naming.** colonna `tasks.requires_validation`; migrazione
`2026_09_14_100000_add_requires_validation_to_tasks_table`; factory state `requiringValidation()`;
i18n `form.requiresValidation(+Hint)`, `detail.requiresClosureFeedback`, `detail.requiresValidation`,
`completeDialog.validationTitle`, `completeDialog.validationHint` (rimossa `completeDialog.requestValidation`).
Contatore `QuoteWorkflowMigrationTest` = 62. `buildTaskSchema(t, isCreate)` ha perso il param
`statusGroup`; `useTaskForm` non restituisce piu' `statusGroup`.

**Verifica (eseguita).** Verifier indipendente: AC-001..AC-024 PASS. Pest Tasks+Unit Tasks+
QuoteWorkflows+Authorization 545 passed; Pint pulito; Vitest tasks+i18n 272 passed (dopo il fix
dead code: tasks 133 passed); ESLint pulito; `tsc -b --force` pulito. Test preesistenti modificati
solo per cambio di requisito: 0116 AC-019 (`TaskActionsTest`), 0119 AC-019/AC-020
(`TaskActionNotificationsTest`), conteggi catalogo/azioni, describe D-7 feedback-nel-form lato FE.

**Aperti / prossimi passi.**
- BUG PREESISTENTE (0118): `DemoTaskSeeder.php:172` passa `taskStatusId:` a `CreateTaskData`,
  parametro rimosso -> `DemoTaskSeederTest` rosso (6). Fuori scope, da correggere a parte.
- Fuori scope da decidere: PATCH di un assegnatario verso stato `in_validation` resta consentito
  e non invia `TaskValidationRequested`.
- `use-task-form.ts` a ~330 righe (sopra soft limit 300): valutare split.

## MODULO TASK — FASE 6: MAPPA NOTIFICHE (spec 0119) — VERDE, COMMITTATO (2026-09-11)

**Cosa e'.** Sesta fase del modulo Task, sopra 0101/0116/0117/0118. Il blocco "Mappa Notifiche
Task" di `DOC Tasks.docx`: 11 classi di notifica `database`+`mail`, un orchestratore, gli innesti
negli 8 write path. Backend-only: il centro notifiche del frontend e' generico e consumava gia' le
quattro chiavi di `NotificationData`. 6 microtask in 5 onde, verifier indipendente su 35 criteri.

**LA CONTRADDIZIONE DEL DOCUMENTO, risolta dall'utente e da NON riaprire.** Sul completamento il
documento dice due cose diverse: la sezione "Azioni" scrive "creatore, assegnatari e osservatori",
la Mappa Notifiche scrive "richiedente + osservatori". **Vince l'UNIONE** (decisione utente
2026-09-11, D-4). Conseguenza: `TaskClosed` e `TaskFeedbackInserted` condividono l'insieme completo
con `TaskUnCompleted`/`TaskLocked`/`TaskUnLocked`, e in tutta la mappa restano quattro soli insiemi
distinti. Chi trovasse "strano" che la voce 2 raggiunga il creatore sta rileggendo la riga sbagliata.

**LE ALTRE DECISIONI, congelate come D-1..D-13 nella spec.**
- **L'attore e' SEMPRE escluso** (D-3). Il documento non lo dice mai; `AssignmentNotifier` lo fa
  gia' ed e' l'unico precedente in repo. Conseguenze accettate: il creatore che blocca il proprio
  task non riceve `TaskLocked`; un task creato da chi ne e' anche unico assegnatario non produce
  alcun `TaskAssigned` (pinnato da AC-013).
- **Ripiego D-5**, in UN SOLO posto: `TaskValidationRequested` e' l'unica voce in cui il richiedente
  e' il solo destinatario, quindi e' l'unica in cui un `requester_id` null fa subentrare il
  creatore. Altrove un richiedente nullo contribuisce semplicemente nessuno.
- **Flusso di modifica** (D-9): su PATCH si notificano SOLO gli utenti AGGIUNTI, riusando le voci 7
  e 8. Chi c'era gia' e chi viene rimosso non riceve nulla. Nessuna dodicesima classe.
- **`approve()` accende DUE notifiche**, ed e' l'unico evento a farlo: voce 4 agli assegnatari PIU'
  la chiusura (voce 2 o 3) a tutti, perche' approve() chiude il Task definitivamente. La tabella
  della spec e' stata corretta in corsa per dirlo esplicitamente: chi leggeva la sola voce 4 non se
  lo aspettava.

**NAMING CONGELATO, da riusare e non reinventare.**
- `App\Notifications\TaskNotification` — base ASTRATTA condivisa dalle 11. Canali, payload, deep
  link e mail stanno li'; una sottoclasse dichiara solo `title()`, `body()` e `level()`.
  La gerarchia deve restare **profonda un solo livello**: il nome della classe finisce nella colonna
  `type` di `notifications`, quindi e' un dato persistito.
- `TaskUnCompleted` e `TaskUnLocked` con la maiuscola in mezzo, **come le scrive il documento**.
  Non "uniformarle": vedi sopra, e' un dato. Attenzione, `class_exists()` NON e' un buon modo di
  verificarlo — il filesystem di macOS e' case-insensitive e risolve anche `TaskUncompleted`. Il
  test legge i nomi dei FILE con `glob`.
- `App\Services\Notifications\TaskNotifier` — 11 metodi pubblici e nessun altro (AC-006, pinnato
  per Reflection): `validationRequested`, `closed`, `feedbackInserted`, `validationApproved`,
  `validationRejected`, `validationReopened`, `assigned`, `watching`, `uncompleted`, `locked`,
  `unlocked`. `assigned`/`watching` prendono un terzo argomento `?array $onlyUserIds` che serve a D-9.
- `App\Services\Notifications\TaskNotificationAudience` — PURA, costruita su 4 insiemi di id.
  Tutte le risposte passano dall'unico `resolve()`: esclusione attore e dedup sono impossibili da
  aggirare per costruzione, non "testate su alcuni casi".
- `App\Support\Notifications\TaskDetails` — scheda dettagli mail. **NON chiama `__()`**:
  restituisce le chiavi `notifications.fields.*` e le fa tradurre a valle da `DetailsTable`, nel
  locale del destinatario. Questo dettaglio ha gia' prodotto un test vacuo una volta (vedi sotto).
- `TaskService::update()` ora richiede l'attore: `update(Task $task, UpdateTaskData $data, User
  $actor)`. Il controller e' l'unico chiamante nel repo (verificato).

**LE TRADUZIONI STANNO IN DUE POSTI, non uno.** Titoli e corpi delle notifiche -> `lang/it.json`
(chiave inglese). Label della scheda dettagli -> `lang/{it,en}/notifications.php` (chiavi puntate).
La spec diceva solo `it.json`: e' il repo ad avere questa separazione, ed e' stata seguita.

**TRE TEST CHE NON PROVAVANO NIENTE, trovati dal verifier e corretti. Non rifare questi errori.**
1. `DB::listen()` **accumula** i listener, non li sostituisce. Due misure con due listener lasciano
   il primo attivo durante la seconda: la baseline si gonfia e il confronto diventa vero per
   costruzione. Il verifier lo ha dimostrato replicando la meccanica con 20 query deliberate: il
   test passava. Ora: un solo contatore azzerato fra le fasi, warm-up della cache permessi, e
   `toBe(0)` — dopo il warm-up comporre il payload costa ZERO query.
2. `Notification::fake()` **sopprime la persistenza**, quindi non puo' provare "nessuna riga in
   `notifications`". I due casi di AC-010 girano ora SENZA fake: in test la coda e' `sync` e il
   mailer e' `array` (phpunit.xml), quindi la notifica fa la sua strada vera senza spedire nulla.
3. Una scansione delle chiavi i18n basata sul solo `__()` e' **cieca su `TaskDetails`**, che non lo
   chiama mai: quella meta' del test estraeva zero chiavi e dichiarava verde il nulla. Ora la
   scansione riconosce anche i letterali `'notifications.*'`, verifica it **ed** en, e fallisce se
   un file non produce chiavi — cosi' non puo' tornare vacua in silenzio.
Ogni correzione e' stata validata con una MUTAZIONE che l'ha fatta cadere, poi ripristinata.

**BUCO PREESISTENTE SEGNALATO E NON SANATO (non e' nostro).** `TaskUpdateRequested` della spec 0118
usa `__('Update requested')` e `__(':requester asked for an update on :title')`, **prive di
traduzione italiana**: quella notifica arriva oggi in inglese a un utente italiano. Fuori perimetro
(§1.6: si segnala, non si implementa). `__('Open')` e' stata invece tradotta, perche' la base
condivisa la usa. Le altre due restano aperte.

**DIMENSIONI MESSE A VERBALE.** `TaskService` **444** righe (era 397) e `Tasks\TaskActionService`
**387** (era 329): sopra il soft limit, sotto l'hard limit, deliberatamente non splittati — i guard
devono vivere nella stessa transazione della scrittura che proteggono. Il margine si e' pero'
assottigliato: chi affronta la 0120 splitti per WRITE PATH, mai estraendo i guard dalla transazione.
Tutti i file nuovi sotto 300 (`TaskNotifier` 178, `TaskNotification` 158, `TaskNotificationAudience`
149, `TaskDetails` 90). `TaskActionNotificationsTest` 379 righe: non splittato di proposito, perche'
spezzarlo spingerebbe i suoi helper in un file condiviso, cioe' nella trappola `function_exists` che
il modulo si porta dietro da tre fasi.

**VERIFICA ESEGUITA (verifier indipendente + rirun su albero fermo dopo le correzioni).**
`XDEBUG_MODE=off ./vendor/bin/pest` -> **7158 test, 7151 passed, 1 skipped, 6 errors**, 30022
asserzioni (baseline 0118: 7091/7098; **+60 test, +60 verdi, errori invariati**). I 6 errori sono
`DemoTaskSeederTest` a `DemoTaskSeeder.php:172` ("Unknown named parameter $taskStatusId"), rotto dal
D-3 della 0118, filone di un'altra sessione: AC-034 li ammette esplicitamente. `pint --test` EXIT 0
sull'intero backend. `npx tsc -b --force --pretty false` EXIT 0. `git status --porcelain frontend/`
VUOTO (AC-035). **35 criteri su 35 PASS**, nessuno NON VERIFICATO.

**RISCHIO DICHIARATO, da chiudere da chi possiede quel file.** `DemoTaskSeeder` chiama
`TaskService::create()` 60 volte: dopo questa spec un `db:seed --class=DemoDataSeeder` accodera' le
mail delle voci 7 e 8. Va anteposto un `Notification::fake()`, come `DemoTaskNoteSeeder` gia' evita
di innescare le @mention. Non e' stato fatto qui perche' quel file e' di un altro filone ed e' gia'
rotto.

**PROSSIMO PASSO.** Lavoro VERDE e NON COMMITTATO (§3.6: il commit lo chiede l'utente). La spec
**0120 (pianificazione ricorrente)** e' gia' scritta e approvata, con 15 decisioni e 39 criteri:
e' il prossimo lotto. Il suo AC-030 e' una verifica OPERATIVA a carico dell'utente — senza il cron
di sistema `php artisan schedule:run` la ricorrenza non genera nulla e nessun test lo rivela.

## MODULO TASK — FASE 5: REGOLE DI CREAZIONE + RICHIEDI AGGIORNAMENTO (spec 0118) — VERDE, COMMITTATO (2026-09-11)

**Cosa e'.** Quinta fase del modulo Task, sopra 0101/0116/0117. Due blocchi del documento di
prodotto `DOC Tasks.docx` che erano scoperti: le REGOLE DEL FLUSSO DI CREAZIONE e l'azione
RICHIEDI AGGIORNAMENTO. 10 microtask in 5 onde, verifier indipendente VERDE su 68 criteri.

**IL SEGNATEMPO NON C'E', ED E' DELIBERATO.** L'utente ha detto "segnatempo non pensarlo ora".
Conseguenza da NON riscoprire come difetto: il pop-up di completamento NON chiede il segnatempo,
che il documento vorrebbe obbligatorio in tutti e tre i casi. Resta scoperto fino alla sua spec.

**LE DECISIONI DELL'UTENTE, congelate nella spec come D-1..D-14.**
- Stato iniziale DERIVATO dal server: `task_status_id` e' `prohibited` su POST. La regola e' letta
  al SINGOLARE come il documento la scrive: **esattamente un** assegnatario che sia il creatore o
  il richiedente -> `open`; in ogni altro caso -> `assigned`. Due assegnatari danno `assigned`
  anche se sono creatore e richiedente. Derivazione SOLO alla creazione, mai ri-derivata su PATCH.
- Campi obbligatori alla creazione: `title`, `requester_id`, `assignee_ids` (min 1), `end_date`.
  Su PATCH `sometimes|required`. Nessuna sanatoria sulle righe storiche che ne sono prive.
- Divieto osservatore IMPLEMENTATO con 422: **AC-083 della 0101 E' RITIRATO**. I ruoli continuano
  a sommarsi per tutto il resto (creatore+richiedente+assegnatario restano cumulabili).
- Destinatari di "Richiedi aggiornamento": scelti fra assegnatari e osservatori, e ricevono SOLO
  loro. Nessun invio automatico. Messaggio opzionale. Disponibile solo su task aperti.

**NAMING CONGELATO, da riusare e non reinventare.**
- `TaskStatusSystemKey::Assigned = 'assigned'` — QUINTA riga protetta. "Assegnato" era ordinaria,
  promossa in-place dalla migrazione `2026_09_11_110000`. CONSEGUENZA ACCETTATA: l'admin non puo'
  piu' cancellarla, riordinarla, disattivarla ne' cambiarne fase (`SystemStatusGuard`); resta
  rinominabile, ricolorabile, e puo' cambiare icona e percentuale.
- `App\Services\Tasks\TaskInitialStatusResolver::resolve(array $assigneeIds, int $creatorId, ?int $requesterId): int`
- `App\Services\Tasks\TaskWatcherOverlapGuard::assertNoOverlap(...)` — riceve insiemi RISOLTI,
  non la DTO: su PATCH combina submitted e persistito, ed e' quello che fa passare AC-033.
- `tasks.requestUpdate` — catalogo `tasks.*` da 14 a **15**. `request_update` e' la dodicesima
  chiave di `TasksAuthorization::actions()`. E' l'UNICA riga di matrice con l'osservatore
  abilitato e l'assegnatario escluso, e il primo consumatore di `TaskRecordRoles::isWatcher()`.
- `App\Notifications\TaskUpdateRequested(Task $task, User $requester, ?string $message)`.
- FE: `RequestTaskUpdatePayload`, `task-request-update-dialog.tsx`, `task-action-error-message.ts`
  (estratto da `task-actions-bar.tsx`: un `.tsx` non puo' esportare una non-componente,
  `react-refresh/only-export-components`), `task-attachment-staging.tsx`.

**COSE SCOPERTE QUI, che costano tempo se riscoperte.**
1. Nella tupla di `TaskTaxonomyCatalogue::STATUSES` il quinto elemento e' la
   **completion_percentage**, NON il `sort_order` (quello lo assegna `StatusOrderManager::placeNew()`).
2. `tests/Feature/QuoteWorkflows/QuoteWorkflowMigrationTest.php` contiene uno `--step` di
   `migrate:rollback` che va bumpato da CHIUNQUE aggiunga una migrazione, in qualsiasi dominio.
   Il test lo dice ("Adding a migration means bumping this number") e diventa rosso lontano da
   dove hai scritto. Ora e' 61.
3. Le copie di `taskActorWith()` sono **13**, non 11, e crescono a ogni file di test nuovo. Non
   contarle a memoria: `grep -rln "function taskActorWith" backend/tests/`. Esistono anche due
   gemelli con nome diverso, `taskDocumentActor` e `taskNoteActor`, che enumerano le stesse
   ability e vanno aggiornati insieme. Una copia rimasta indietro non rompe niente in modo
   visibile: rende false un flag, in silenzio, secondo l'ordine di caricamento.
4. Il typecheck e' uno **Stop hook**: qualsiasi finestra in cui l'albero non compila lo fa firare.
   Toccare un tipo condiviso PRIMA dei suoi consumatori compra parallelismo e paga con quella
   finestra. Il cambio al file condiviso va DENTRO il primo microtask che lo consuma.

**DEVIAZIONE SU UN COMPONENTE CONDIVISO, da sapere.** `components/ui/async-paginated-multi-select.tsx`
(12+ caller) ha una prop nuova `excludeIds` — additiva, default hoistato — ma anche UN cambio di
comportamento: lo stato vuoto e' ora `options.length === 0 && !hasNextPage`. Senza, una pagina
interamente filtrata direbbe "nessun risultato" mentre restano pagine da caricare. Va nella
direzione sicura (meno falsi "nessun risultato") e le 22 suite esistenti sono verdi SENZA
modifiche, che e' la prova che serviva.

**DIMENSIONI MESSE A VERBALE.** `TaskService` 397 righe e `Tasks\TaskActionService` 329: sopra il
soft limit di 300, sotto l'hard limit, deliberatamente non splittati — i guard devono vivere nella
stessa transazione della scrittura che proteggono. Se si avvicinano a 500, lo split e' per WRITE
PATH, mai estraendo i guard dalla transazione. La nota e' nel docblock di entrambe.

**VERIFICA ESEGUITA dal verifier su albero fermo.** Pest **7091/7098** (i 6 errori sono
`DemoTaskSeederTest`, filone estraneo, vedi sotto); Vitest **4827/4827** su 632 file;
`npx tsc -b --force --pretty false` **EXIT 0**; Pint e ESLint puliti. 66 criteri su 68 PASS,
nessun criterio scoperto. **AC-066 (responsive 375/768/1024) NON verificato**: e' verifica
manuale sull'app reale e resta da fare, come l'AC-028 della 0117 che e' ancora aperto.

**COLLISIONE APERTA CON UN ALTRO FILONE, non nostra da chiudere.** Un'altra sessione sta
costruendo il seed demo del Task (`DemoTaskSeeder`, `DemoTaskNoteSeeder`, `DemoDataSeeder`,
`DemoCatalog/DemoTaskCatalogue`, `Concerns/PicksTaskRecordLinks`, `rich-cells.tsx`). Il nostro D-3
le rompe il codice: `DemoTaskSeeder.php:219` passa `taskStatusId:` a `CreateTaskData`, che non lo
accetta piu' -> 6 errori. Va corretto da chi possiede quel file: togliere il parametro e accettare
lo stato derivato, oppure creare e poi spostare lo stato con un update (D-6 garantisce che nessuno
lo ri-deriva). **Quei file non fanno parte di questo lotto e non vanno committati con esso.**

## SEED DEMO DEL MODULO TASK (richiesta utente 2026-09-11) — VERDE, COMMITTATO

**Cosa e'.** Il dataset demo che al modulo Task mancava: `tasks` + le sue due tabelle di
appoggio (`task_assignee`, `task_watcher`) + il thread di note sul Task (spec 0117). Le cinque
lookup di classificazione NON sono state duplicate: sono reference data del cliente, gia'
possedute da `QualificaTaskTaxonomySeeder`, che ora `DemoDataSeeder` richiama come step proprio
(su un db demo puro quelle tabelle erano vuote, a parte i 4 stati protetti creati dalle
migrazioni).

**Decisioni dell'utente (2026-09-11).** Riusare la tassonomia Qualifica invece di inventarne una
demo; note SI, documenti NO in questa fase; volume ~60 task con gerarchia.

**File nuovi/toccati (4 + 1).**
- `database/seeders/DemoTaskSeeder.php` — 40 task radice + 20 sottotask, ognuno creato con
  `TaskService::create()` (write path reale: `creator_id` dall'attore, coerenza
  referente/anagrafica AC-014, closure feedback D-7); i 6 task bloccati passano da
  `TaskActionService::block()` con il creatore come attore. Idempotente: cancella e ricrea.
- `database/seeders/Concerns/PicksTaskRecordLinks.php` — trait: carica una volta sola stati,
  lookup, utenti, anagrafiche/referenti, opportunita', commesse, ed espone i pick. Estratto per
  tenere il seeder a 300 righe.
- `database/seeders/DemoCatalog/DemoTaskCatalogue.php` — solo frasi (titoli, note), nessuna
  classificazione: il vocabolario resta quello del cliente.
- `database/seeders/DemoTaskNoteSeeder.php` — thread su 1 task su 2, via `NoteService::create()`.
  Autore SOLO un membro del task che ha anche `tasks.view` (le due condizioni di
  `TaskNotable::authorizeRead`), verificate prima di scrivere invece che scoperte come 403.
- `database/seeders/DemoDataSeeder.php` — aggiunti in coda a `DemoRewardSeeder`:
  `QualificaTaskTaxonomySeeder` -> `DemoTaskSeeder` -> `DemoTaskNoteSeeder`.

**Vincoli messi a verbale (non "correggerli" dopo).**
- NESSUNA @mention seminata: `NoteMentionNotification` e' `ShouldQueue` con canale `mail` e su
  `QUEUE_CONNECTION=sync` il seed dipenderebbe da un SMTP raggiungibile. `note_mentions` resta
  vuota di proposito.
- Le note del Task non hanno cleanup a FK ne' hook: `DemoTaskSeeder` le `forceDelete()` prima di
  cancellare il task, altrimenti un re-run lascia note orfane su `notable_id` inesistenti.
- Un task in fase closing ha `completion_date` sempre nel passato: per questo la finestra delle
  date dei task chiusi si ferma a `-1 week` (un bug trovato e chiuso in verifica: prima la
  completion poteva cadere nel futuro).
- `DemoRolesSeeder` non da' permessi `tasks.*` a manager/operator/user; solo `admin` (tutti) e
  `viewer` (`.view`/`.viewAny`). Conseguenza accettata: i thread nascono solo dove un membro e'
  admin o viewer. Se si vuole piu' copertura, la modifica e' su `DemoRolesSeeder`, non qui.

**Verifica eseguita (non "dovrebbe").**
- `tests/Feature/Seeding/DemoTaskSeederTest.php` (nuovo, 6 test) + suite `Seeding`, `SeederFlowTest`,
  `Tasks`, `Notes`: 346 test, 2199 asserzioni, verdi.
- Catena reale `db:seed --class=DemoDataSeeder` su un db MySQL usa-e-getta (creato e droppato):
  60 task / 20 sottotask / 124 assegnatari / 60 osservatori / 6 bloccati / 118 note su 28 thread,
  zero completion incoerenti, zero referenti fuori pivot, zero note orfane dopo un secondo run.
- Pint pulito. Nessun file di produzione toccato.

**Prossimo passo.** Committare (in attesa del via libera) oppure, se si vuole il seed anche sul db
di sviluppo `qnet2`, eseguirlo li' — cancella e ricrea TUTTI i task esistenti, quindi va chiesto.

## MODULO TASK — FASE 4: NOTE E DOCUMENTI (spec 0117) — VERDE, COMMITTATO (2026-09-11)

**Cosa e'.** Quarta fase del modulo Task, sopra la 0116. Porta sul Task le due capacita'
collaborative che `DOC Tasks.docx` gli assegna e che il codebase gia' aveva per altri moduli:
note collaborative (spec 0052) e documenti allegati. NESSUN ENDPOINT NUOVO: l'intera feature e'
la registrazione di due alias sugli endpoint generici esistenti, piu' il descrittore che li
accompagna. 19 file toccati, 92 righe aggiunte in produzione.

**Le quattro decisioni dell'utente (2026-09-11), congelate nella spec.**
- Documenti: si comportano ESATTAMENTE come quelli di Opportunita' -> NESSUN gate per-record
  (D-8). Conseguenza ACCETTATA e messa a verbale: chi ha `attachments.viewAny` legge e scarica
  i documenti di un Task che `TaskVisibilityScope` gli nasconde. E' pinnata da un test
  ("D-8: attachments carry NO per-record gate...") perche' nessuno la riscopra come difetto.
  Chiuderla significa dare al sottosistema Attachment un gate per tutti e sei gli alias: spec a se'.
- Note: stesso meccanismo delle note dell'Offerta -> `TaskNotable` rispecchia
  `RequestManagementNotable`, riscrivendone il predicato su `TaskVisibilityScope`.
- Dettaglio: card collaborazione a tab (Note | Documenti | Attivita'), il log attivita' SI SPOSTA
  li' dentro e non e' piu' un blocco a se'.
- Solo il dettaglio: niente dialog/badge di riga nella tabella Task in questa fase.

**NAMING CONGELATO, da riusare e non reinventare.**
- `App\Services\Tasks\TaskNotable` — il descrittore `NotableEntity` del Task. Vive nel
  namespace del modulo OSPITE, mai in `app/Notes/` (AC-021 della 0052, verificato da
  `NoteAgnosticismTest`, che ora cerca anche i needle `Task`/`TaskNotable`/`App\Services\Tasks`).
  A differenza delle altre classi Tasks e' risolta dal container, quindi NON deve restare statica.
- Slug note = **`tasks`** (plurale, vocabolario di AUTORIZZAZIONE, allineato a `TASKS_DOMAIN`).
- Alias documenti = **`task`** (singolare, vocabolario di IDENTITA', gia' nella morph map dalla
  0101). I due sono diversi per costruzione: non "uniformarli".
- `tasks.viewDocuments` — nuova ability su `TaskPolicy`, esposta come `view_documents` in
  `TasksAuthorization::actions()`. Il catalogo permessi `tasks.*` passa da 13 a **14**.
- `TASK_ATTACHABLE_ALIAS` in `features/tasks/api.ts`; `TaskCollaborationSection` in
  `features/tasks/task-collaboration-section.tsx`.

**TRE COSE SCOPERTE QUI, che costano tempo se riscoperte.**
1. `GET /api/notes/mentionable-users` risponde con l'envelope for-select (`items`/`pagination`):
   **non ha affatto una chiave `data`**. Un test che legge `json('data')` ottiene `null` e passa
   in silenzio se l'asserzione e' un `not->toContain`.
2. `GET /api/tasks/{id}` mette `permissions` accanto a `data`, non dentro: il path e'
   `permissions.actions.*`, non `data.permissions.actions.*`.
3. Radix monta SOLO il pannello del tab attivo e sotto jsdom l'attivazione via pointer non e'
   affidabile. L'idioma gia' in uso nel repo (`contract-detail.test.tsx`) e' stubbare
   `TabsContent` perche' renderizzi sempre, lasciando reali `Tabs`/`TabsList`/`TabsTrigger`.
   Non c'e' `@testing-library/user-event` in questo repo.

**LA TRAPPOLA DELLA 0116 SI E' RIPRESENTATA, come previsto.** `taskActorWith()` e' duplicato in
11 file di `tests/Feature/Tasks/` e PHP tiene solo la prima copia in ordine alfabetico: aggiunta
`viewDocuments`, sono state aggiornate TUTTE E 11. Aggiungendo un'altra ability, rifarlo.

**DUE TEST MODIFICATI, e perche' (non e' test tampering).** `TaskPermissionsTest` asseriva 13
permessi `tasks.*`: il requisito e' cambiato, `tasks.viewDocuments` e' un permesso nuovo e voluto,
quindi 13 -> 14 in due punti. `NoteAgnosticismTest` ha guadagnato i needle del secondo host.

**VERIFICA ESEGUITA (su albero fermo).** `XDEBUG_MODE=off ./vendor/bin/pest` -> **7030 test,
7029 passed, 1 skipped, 0 failed**, 29355 asserzioni (baseline 0116: 6999; +31 = 21 note + 10
documenti). `npx vitest run` -> 629 file, **4773/4773**. `npx tsc -b --force --pretty false`
EXIT 0. `pint --test` pulito sull'elenco esplicito dei file. **AC-028 (responsive 375/768/1024)
NON verificato**: e' verifica manuale sull'app reale, resta da fare.

**COSA RESTA FUORI (non e' incompleto: e' deciso).** Segnatempo; le 11 classi di notifica `Task*`
e ogni mail; "Richiedi aggiornamento"; ricorrenza; campi obbligatori alla creazione e stato
iniziale automatico; allegati in fase di CREAZIONE del Task; note/documenti come azioni di riga
in tabella; Fase 2 della 0101 (Task collegati dentro Opportunita'/Commessa/Anagrafica); qualunque
gate per-record sugli allegati.

**DEBITO NOTO, segnalato e non risolto.** Questo file ha superato **960 KB** contro la regola di
manutenzione di ~50 KB scritta nel suo stesso header: le voci vecchie andrebbero spostate in
`docs/handoff-archive/`. Non fatto qui perche' fuori dallo scope della 0117.

## MODULO TASK — FASE 3: RUOLI SUL RECORD + AZIONI (spec 0116) — VERDE, COMMITTATO (2026-09-11)

**Cosa e'.** Terza fase del modulo Task, dopo la Fase 1 (spec 0101). Nasce dal documento di
prodotto dell'utente `DOC Tasks.docx`: di quel documento erano implementati solo i CAMPI, tutto
il comportamento mancava. L'utente ha scelto due voci — matrice di autorizzazione per RUOLO SUL
RECORD e azioni di dominio — lasciando fuori il resto (vedi "Cosa resta fuori").

**Le sei decisioni dell'utente, congelate nella spec (D-2..D-7, D-9, D-10).**
- Il "gestore" e' un permesso NUOVO `tasks.manageAll`, non un ruolo. Separa "vedere tutto"
  (`tasks.viewAll`, preesistente) da "comandare tutto".
- Ruoli cumulati: VINCE IL PIU' PERMISSIVO. AC-083 della spec 0101 NON e' ritirato (si puo'
  essere assegnatario e osservatore insieme) e il divieto del documento NON e' implementato.
- Stato di ripresa designato per CHIAVE: `TaskStatusSystemKey::InProgress`, sulla riga "In corso".
- PATCH su Task congelato: rifiuta le SOLE chiavi strutturali (422 per campo), non l'intero body.
- Segnatempo e notifiche restano FUORI; l'azione "Richiedi aggiornamento" pure.

**NAMING CONGELATO, da riusare e non reinventare.**
- `App\Services\Tasks\TaskRecordRoles` (STATICA) — CHI e' l'attore sul record.
- `App\Services\Tasks\TaskAbilityResolver` (STATICA) — LA MATRICE, unico posto dove vive.
  Costante `PROTECTED_FIELDS` = i 17 campi del mandato.
- `App\Services\Tasks\TaskActionAvailability` (INIETTABILE) — QUANDO un'azione ha senso.
- `App\Services\Tasks\TaskWriteLock` (STATICA) — `OPERATIVE_KEYS`, `isLocked`,
  `assertStructuralWriteAllowed`, `assertDeletable`.
- `App\Services\Tasks\TaskActionService` — le sei azioni.
Le prime due e la quarta DEVONO restare statiche: le consuma `TaskPolicy`, e `permissions:sync`
istanzia ogni Policy con `new $class` senza container. Renderle iniettabili = fatal error al sync.

**CONTRATTO API (congelato, il frontend lo consuma gia').** Sei POST su `/api/tasks/{task}/`:
`complete` (body `{closure_feedback?, validation_status_id?}`), `uncomplete`, `approve`,
`reject`, `block`, `unblock`. Tutte rispondono `okWithPermissions(TaskResource)`.
Transizioni: complete senza `validation_status_id` e approve -> riga `closed_positive`;
uncomplete e reject -> riga `in_progress`. `uncomplete` CANCELLA `closure_feedback`, `reject` NO
(e' la motivazione del validatore). Errori: 403 authz, 409 Task bloccato, 422 fase/validazione.
`permissions.actions` espone i sei flag = ability AND matrice AND disponibilita'.

**TRE RETTIFICHE ALLA SPEC fatte durante il build — leggerle prima di toccare questa roba.**
1. `completion_percentage` di `in_progress` e' **50, non 25**. Il 25 era il bootstrap ritirato
   dalla `2026_09_04_120000`; il valore vivo nel catalogo e' 50. NB: il quinto elemento della
   tupla di `TaskTaxonomyCatalogue::STATUSES` e' la PERCENTUALE, non `sort_order`.
2. `TaskAbilityResolver` NON ha `canRequestUpdate`: sarebbe stato un metodo senza chiamanti.
   La riga della matrice nascera' col suo endpoint.
3. **Il write lock si valuta PRIMA di `fill()`**, sullo stato PERSISTITO — a differenza di
   `TaskClosureFeedbackGuard`, che si valuta DOPO, sullo stato RISULTANTE. L'asimmetria e'
   INTENZIONALE: il lock chiede "da dove parti", il feedback "dove arrivi". Valutandolo dopo,
   un PATCH con `task_status_id` (chiave operativa) verso uno stato non congelato avrebbe
   scongelato il Task e fatto passare i campi strutturali nello stesso body — il lucchetto si
   apriva con la chiave che esso stesso autorizza. Chi "uniforma" i due guard riapre il buco.
   Coperto da AC-050.

**DUE TRAPPOLE TROVATE QUI, che costano ore se riscoperte.**
- `QuoteWorkflowMigrationTest.php` ha un contatore HARD-CODED di rollback (`--step`), oggi 60,
  con l'istruzione nel docblock "Adding a migration means bumping this number". OGNI migration
  nuova, di QUALUNQUE modulo, rompe quel test finche' non si alza il numero. Si vede solo
  eseguendo la suite INTERA: il modulo su cui lavori resta verde.
- `taskActorWith()` e' duplicato in 11 file di `tests/Feature/Tasks/` con guard `function_exists`:
  PHP tiene solo la PRIMA in ordine alfabetico (`TaskClosureFeedbackTest.php`). Creava solo le 9
  ability vecchie e ignorava le 4 nuove -> `PermissionDoesNotExist` solo a suite intera, verde in
  isolamento. Ora tutte e 11 le copie creano le stesse 13 ability: se ne aggiungi una, aggiornale
  TUTTE, non solo quella del file su cui stai lavorando.
- Xdebug 3.4.0alpha2-dev su PHP 8.4.23 SEGFAULTA (exit 139) sulla suite intera. Usare
  `XDEBUG_MODE=off ./vendor/bin/pest`. Non e' un difetto del codice.

**VERIFICA ESEGUITA (verifier indipendente, su albero fermo, dopo le correzioni).**
`XDEBUG_MODE=off ./vendor/bin/pest` -> 6999 test, 6998 passed, 1 skipped, **0 failed**, 29254
asserzioni. `migrate:fresh --seed` EXIT 0. `pint --dirty --test` passed.
`npx vitest run` -> 628 file, 4766/4766. `npx tsc -b --force --pretty false` EXIT 0.
Tutti gli AC-001..AC-050 PASS, mappati 1:1 su test nominati.

**COSA RESTA FUORI (non e' incompleto: e' deciso).** Le 11 classi di notifica `Task*` e ogni
mail; il segnatempo (il pop-up di chiusura chiede il solo feedback); l'azione "Richiedi
aggiornamento"; note e documenti sul Task; campi obbligatori alla creazione e stato iniziale
automatico; ricorrenza; Fase 2 della spec 0101 (Task collegati dentro Opportunita'/Commessa/
Anagrafica). Punto d'innesto per le notifiche gia' dichiarato: la chiusura di ogni metodo
pubblico di `TaskActionService`. NON sono stati creati eventi o listener vuoti in anticipo.

**DEBITO NOTO, segnalato e non risolto.** `backend/app/Services/TaskService.php` e' a 319 righe
(era 281): ha superato il soft limit di 300 a causa di questa spec, va valutato lo split al
prossimo intervento. Sopra soglia anche `TaskActionsTest.php` (350, file nuovo) e
`frontend/src/features/tasks/task-detail.test.tsx` (336). Nessuno supera l'hard limit di 500.

**PROSSIMO PASSO.** Lavoro VERDE e NON COMMITTATO (§3.6: il commit lo chiede l'utente).
Se si prosegue col modulo Task, l'ordine naturale e': segnatempo -> notifiche -> note/documenti.

