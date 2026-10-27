# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

## App: form a pannello stile CRM + tre app nel seed di produzione — VERDE, NON COMMITTATO (2026-10-27)

- Form create/edit di `/dev/workspaces`: `WorkspaceFormDialog` sostituito da `components/workspace-form-sheet.tsx`
  (Sheet 720px, `storageKey sheet-width:workspaces-form`): header con icona/colore live, tre `FormSection`
  (`workspace-identity-section.tsx`, `workspace-appearance-section.tsx` con anteprima del launcher, Voci di menu con
  badge conteggio), footer sticky (riepilogo voci, Annulla, Salva + Ctrl/Cmd+Invio), conferma su chiusura con modifiche.
  Slug segue il nome in creazione finche' non e' scritto a mano (`workspace-slug.ts` `slugify`, `useWorkspaceForm`
  `followName`/`markSlugEdited`). Picker: ricerca, checkbox tri-state per sezione, Seleziona tutte/Deseleziona,
  icone voce (`resolveIcon`); logica pura in `workspace-entries.ts` (`groupEntries`, `toggleKeys`). Contratto API invariato.
- Seed: `QualificaWorkspaceSeeder` + `QualificaCatalog/WorkspaceCatalogue` (slug `contabilita`/`hr`/`sales`,
  "Qualifica Contabilità/HR/Sales"), CREATE-ONLY per slug (non sovrascrive le modifiche del super-admin), chiamato da
  `QualificaProductionDataSeeder` dopo `QualificaContractTypeSeeder`. Ogni app solo con le pagine del suo settore
  (direttiva utente 2026-10-27: niente Assenze in Contabilita'); in tutte e tre solo `dashboard` e `tasks`
  (`SHARED_KEYS`); `time-entries`/`absences` solo in HR. Il DB dev ha gia' le 3 app con la mappatura VECCHIA
  (create-only): vanno riallineate a mano.
- Launcher header (`workspace-launcher.tsx`): trigger con griglia + icona/nome dell'app corrente + chevron; pannello con
  intestazione e conteggio, tessere icona tinte `bg-current/10` (test AC-025 aggiornato, REQUIREMENT CHANGED),
  piede "Vista completa" + "Gestisci app" (`/dev/workspaces`) solo con `full_view_available`. ATTENZIONE D-5: dal seed in poi i non super-admin vedono solo le voci delle app; una pagina
  fuori da tutte le app (es. config Task, prodotti/attributi, Develop) resta raggiungibile dal menu solo in Vista completa.
- Menu: regola utente 2026-10-27 in `features/navigation/nav-blocks.ts` (`isSingleChildGroup`, `buildNavBlocks`) usata da
  `nav-main.tsx`: gruppo senza route con UN solo figlio non e' collapsible; al primo livello diventa una sezione (label
  come "Configurazione") sopra la voce, dentro una sezione la voce sale nella sezione. Voci navigabili con figli
  (Lead -> Import) invariate. NavMain ora rende i blocchi in ordine di menu (run di voci + sezioni).
- Header sidebar: `ActiveWorkspaceName` sostituito da `features/workspaces/components/workspace-brand.tsx`
  (`WorkspaceBrand`): tessera logo `size-8` con badge icona app nell'angolo, nome app (colore app) sopra il nome prodotto.
- Test: `QualificaWorkspaceSeederTest` (3), `QualificaProductionDataSeederTest` (3, +asserzioni), `tests/Feature/Workspaces`
  verdi; Vitest `features/workspaces` 39 + `features/help` 151 verdi; eslint + `tsc -b --force` puliti.
- Manuale: guide in-app IT/EN `workspaces` aggiornate; manuale Claude Docs aggiornato (sezione App, rev 414).
- Da verificare a vista: pannello a 375/768/1024, tema dark. Non eseguito il seeder sul DB dev.

## App del menu, stile Microsoft 365 (spec 0317, rev 3) — VERDE, NON COMMITTATO (2026-10-27)

- Spec `docs/specs/0317-workspace-apps.xml` (approvata, rev 3: icona/colore D-11..D-14, descrizione D-15).
  Il super-admin configura N "app" (entita' `Workspace`, in UI "App") da `Develop > App` (`/dev/workspaces`);
  ogni app = sottoinsieme di voci di menu (per `key`). Visibilita' dai permessi (D-1), filtro SOLO sul menu (D-2),
  vista completa solo super-admin (D-3) MA a tutti finche' non esiste nessuna app (D-5, retrocompatibilita').
- Backend: tabelle `workspaces` (slug, name, description, icon, color, sort_order) e `workspace_navigation_items`;
  `color`/`description` in migrazioni additive `2026_11_04_100200/100300` perche' la create era gia' migrata sul DB
  dev (tutte e 4 eseguite sul dev). `App\Services\Workspaces\{NavigationCatalogue,WorkspaceService,
  WorkspaceAccessService}`, `NavigationService::prune()` dopo il filtro esistente. Rotte `routes/api/workspaces.php`:
  `GET workspaces/available` (tutti), `GET workspaces/navigation-catalogue` + apiResource dietro `super-admin`;
  `GET /navigation?workspace=<slug>` (404 se non visibile; senza param `[]` per chi non ha la vista completa).
  `icon`/`color` validati con `BadgeTokens::icons()/colors()`. Alias morph `workspace` in AppServiceProvider.
- Frontend: `features/workspaces/`; rotte figlie del layout estratte in `routes/app-child-routes.tsx` (407 righe,
  sopra soft limit) e montate sotto `/` e `/app/:workspaceSlug`; URL senza prefisso -> redirect nell'app ricordata
  (localStorage `qnet.activeWorkspace.<userId>`). Launcher a sinistra nell'header (dopo il seam toggle), nome app
  sopra "QNet" nel colore dell'app (sfumature `-300`, mappa `workspace-color.ts`, AA verificato a calcolo).
- Test REQUIREMENT CHANGED: `DevelopSectionTest` (5 voci develop), `HELP_GUIDE_KEYS` 98; i 3 test di rollback
  (SupplierCommissionDirection, PurchaseOrderMigration, QuoteWorkflowMigration) ora calcolano `--step` con
  `migrationStepsFrom()` (`tests/Helpers/MigrationStepHelpers.php`) invece del numero fisso.
- Verifica: verifier su rev 3 -> tutti gli AC verdi; Pest suite completa 12557 pass, rossi non 0317: PDF senza
  LibreOffice, TableBulkDelete super-admin, 7 test `tests/Feature/Migration/*` causati da
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` nel `backend/.env` locale (verdi con `false`). Vitest completo 9261/9261.
  Dopo i fix: Workspaces+Navigation+rollback 60/60, vitest mirato 138 file/1054, ESLint e `tsc -b --force` puliti.
- Manuale: guide in-app IT/EN `workspaces` (nuova) e `general`; Claude Docs rev 413 (riga App nell'intestazione,
  paragrafo App in "Com'e' fatta", sezione "App" dopo "Stato del sistema"). PDF derivato da rigenerare.
- Da verificare a occhio: 375px (launcher vs seam toggle, footer del dialog). Commit: stage per file, nel tree
  ci sono anche i file della 0316 (altra sessione).

## Visibilita' record Opportunita'/Offerte/Commesse/Contratti (spec 0316) — VERDE, NON COMMITTATO (2026-10-27)

- Spec `docs/specs/0316-record-visibility-opportunities-quotes-work-orders.xml` (approvata; AC-031 [revised]).
- Livelli: Proprie = `{module}.view` esistente, RISTRETTO; nuove ability `opportunities.viewAll`,
  `quotes.viewSite`/`viewAll`, `work-orders.viewSite`, `contracts.viewSite`/`viewAll` (etichette i18n gia' esistenti).
  Restringono, mai concedono: senza `view` niente. D-1: NESSUN backfill, chi ha solo `view` ora vede solo i propri.
- Scope statici fail-closed (forma query + forma record, in memoria sulle griglie):
  `App\Services\Opportunities\OpportunityVisibilityScope` (supervisor_id o Gestore account),
  `App\Services\Quotes\QuoteVisibilityScope` (parametrizzata sul prefisso: riusata dai contratti; supervisor_id o
  managers, + sede `operational_site_id` in `RequestManagementScope::actorSiteIds()` con viewSite),
  `App\Services\Contracts\ContractVisibilityScope` (whereHas quote, permessi `contracts.*`),
  `WorkOrderVisibilityScope` + livello sede (offerta `quote_id` o offerte delle righe pivot, D-2;
  `WorkOrder::scopeWithSourceQuoteInSites`). Policy: view/update/delete (+ azioni contratto) = permesso E visibilita'.
- Applicato a: baseQuery delle 4 griglie (righe, filtri, export, inline, bulk), for-select opportunita'
  (estratto in `App\Services\Opportunities\OpportunityForSelectService`) e offerte, stats opportunita'/offerte
  (cache `user:{id}`), `quotes_count` dell'opportunita', anteprima layout, menzioni note commessa.
- Allegati (D-9): `config('attachments.owner_scoped_types')` = opportunity/work_order/contract -> serve `view` sul
  proprietario. Per `opportunity` vale `OpportunityDocumentAccess::allows`: view opportunita' OPPURE raggiunta da
  Gestione Richieste/Iscritti (`RequestManagementScope::reachesOpportunity`, ora usata anche da
  RequestManagementNotable) OPPURE contratto visibile di quell'opportunita' (regressione operatori RM evitata).
- Fuori scope (follow-up segnalati): nomi/codici in Task/Ore/Ordini/Fatture/Premi/Provvigioni, `Rule::exists` sulle
  scritture di altri moduli, `quote-offer-lines/for-select`, link/notifiche verso record non visibili (403 accettato),
  Controllo di gestione e Gestione Richieste invariati. Cataloghi ruoli Qualifica che danno "tutte le ability" di un
  modulo ricevono automaticamente viewAll/viewSite. Creatore fuori dal team perde il record (nessuna colonna creatore).
- Test: 6 nuovi file (`*VisibilityTest`, `OwnerScopedAttachmentVisibilityTest`, `OpportunityQuotesCountVisibilityTest`,
  `RecordVisibilityPermissionsTest`); ~60 test esistenti adeguati SOLO nel setup (viewAll/team), dichiarato
  REQUIREMENT CHANGE; cambi attesi dichiarati: PermissionCatalogueEndpointTest AC-007, WorkOrderSecurityTest 17->18,
  WorkOrderNotesTest AC-005. Verifier: tutti gli AC PASS; `composer test` nessun rosso 0316 (rossi presenti = 0317
  workspaces migrations modificate durante il run + pre-esistenti noti). Pint, ESLint, vitest help/roles,
  `tsc -b --force` puliti. Da fare: rilanciare `composer test` completo quando 0317 e' stabile.
- Manuale: guide in-app IT/EN sezione `visibility` (opportunities, quotes, work-orders, contracts, roles) +
  Claude Docs rev 412 (5 sottosezioni "Quali ... vedi" + "Livelli di visibilita' dei record"); PDF da rigenerare.
- Nota: nello stesso albero c'e' il lavoro parallelo spec 0317 (Workspaces, altra sessione): committare per file.
- Nota: questo HANDOFF e' ~840 KB, molto oltre i 50 KB: serve un'archiviazione in `docs/handoff-archive/`.

## Apri contratto dall'Offerta chiusa positiva (spec 0315) — VERDE, NON COMMITTATO (2026-10-27)

- Richiesta utente: bottone che apre in modale il contratto nato da un'offerta chiusa positiva, su tabella e dettaglio.
- Backend: azione `contract` (`actions.openContract`, icona `file-signature`, permesso `contracts.view`) in
  `QuoteColumnCatalog` DOPO `notes` -> sta nel menu "tre puntini" (le 3 inline restano view/generate_document/notes).
  Righe: `contract_id` + azione solo se il contratto esiste e Gate `view` sul Contract. `actionsFor`/`allowsNotes`
  spostati in nuova `App\Tables\Quotes\QuoteRowActions` (QuotesTableDefinition era a 498 righe, ora 448).
  Dettaglio: `QuoteResource.contract_id` (+ `contract` in `QuoteService::DETAIL_RELATIONS`). Contratto sospeso
  incluso (resta il contratto di riferimento, D-1).
- Frontend: `useQuoteRowActions` caso `contract` (opener `contracts` forzato in modale, `sheet` restituito come
  Fragment dei due Sheet: vale anche per il pannello Offerte dentro l'Opportunita'); `QuoteDetailHeader` pulsante
  "Apri contratto" dietro `useViewableDomain('contracts')`; `QuoteDetail.contract_id?`; i18n `actions.openContract`.
- Test: `QuoteContractShortcutTest` (AC-001..003) + suite Quotes/Contracts/RequestManagement/Opportunities/Table
  verdi salvo i rossi gia' noti (SupplierCommissionDirection, PDF senza LibreOffice, TableBulkDelete super-admin);
  Pint pulito. FE `quote-contract-shortcut.test.tsx`; vitest quotes/opportunities/contracts/help/i18n 139 file /
  1226 test, ESLint, `tsc -b --force` puliti.
- Manuale: guide in-app IT/EN Offerte (sezione stato) + Claude Docs rev 411; PDF derivato da rigenerare.
- Fuori scope: griglia Gestione Richieste (catalogo azioni proprio). Se l'utente vuole l'azione inline, va spostata
  prima di `notes` nel catalogo (cambia l'ordine delle icone inline, direttiva 2026-08-06).

## Previsione commerciale in Gestione Richieste (spec 0313) — VERDE, NON COMMITTATO (2026-10-27)

- Richiesta utente: data chiusura prevista, valore stimato, probabilita' di successo anche in Gestione Richieste.
  Scelte utente: pannello (modifica) + form di creazione + colonne griglia; valori sull'OPPORTUNITA' (condivisi con
  le richieste sorelle e col modulo Opportunita'); report CSV aggregato NON toccato.
- Backend: `UpdateRequestRequest`/`StoreRequestRequest` (regole di UpdateOpportunityRequest, sparse, null svuota;
  `OPPORTUNITY_FIELDS` + i tre per il gate per campo), `RequestManagementAuthorization` (3 FieldDefinition, ereditate
  da Gestione Iscritti), `RequestManagementService::OPPORTUNITY_SCALAR_FIELDS` (fill sull'Opportunita'),
  `CreateRequestData` -> `CreateOpportunityData`. `RequestManagementResource`: i tre top-level, blocco `context`
  RIMOSSO. Griglia: nuova `RequestForecastColumns` (hidden, sortable/filterable via OPPORTUNITY_SCALAR_COLUMNS,
  display-only), `RequestRowMapper`.
- Frontend: `request-forecast.ts` (schema shape, `toForecastValues`, `buildForecastPayload`, `NO_FORECAST`) +
  `RequestForecastSection` (riusa i campi di `opportunity-fields.tsx`, resi generici con prop `name`), nel pannello
  e nel form di creazione subito dopo il Team (indicazione utente 2026-10-27); tolti i 3 valori da `RequestWorkSummary`; renderer griglia
  (`ProbabilityCell` ora esportato da opportunities). Probabilita': slider, 0 = non indicata, viaggia solo se cambia.
- Test: `RequestManagementForecastFieldsTest` (AC-001..005), `request-work-panel-forecast.test.tsx`,
  `request-forecast.test.ts`; REQUIREMENT CHANGED 0313 su ShowTest (`context` -> top-level),
  DefaultColumnsTest (3 colonne nascoste), ordine sezioni del form di creazione, fixture del pannello.
- Suite backend completa: 14 failure + 2 error FUORI scope (Migration utenti, rollback migrazioni, TableBulkDelete
  super-admin, SupplierCommissionDirection, DemoOpportunitySeeder, PDF senza LibreOffice), non legati alla modifica.
- Manuale: guide IT/EN `request-management` + Claude Docs aggiornati; PDF derivato da rigenerare.

## Campo Lead dell'Opportunita' nascosto ai ruoli tranne super-admin (spec 0314) — VERDE, NON COMMITTATO (2026-10-27)

- Richiesta utente: "tutti tranne superadmin, oscurare il campo lead in opportunita' tramite permessi in productionSeeder".
- Backend: `lead_id` ora e' un FieldDefinition di `OpportunitiesAuthorization` (in coda al catalogo, `select`);
  ceiling editabile solo in creazione con `$mayWrite`, sempre readonly in modifica (BR-2). `QualificaRoleSeeder::
  EVERY_ROLE_HIDDEN_FIELDS` (`opportunities.lead_id`) unito alle righe nascoste di OGNI ruolo dentro
  `syncFieldPermissions` (mansioni, admin, HR, back office, magazziniere); super-admin bypassa. Costante nel seeder,
  non in `OperatorRoleCatalogue` (494 righe, limite 500). Con il campo nascosto POST con `lead_id` -> 422;
  la conversione `leads/convert-to-opportunities` non passa dalla matrice ed e' invariata. La risposta API porta
  ancora `lead`/`lead_id` (stesso schema degli altri campi nascosti: la UI nasconde).
- Frontend: sezione Lead del form di creazione e riga "Lead di origine" del dettaglio dietro
  `useResourcePermissions().field('lead_id').visible`. Deep-link `?lead_id=N` non gestito (non linkato dall'app).
- Test: nuovo `OpportunityLeadFieldPermissionTest` (AC-001..003); REQUIREMENT CHANGED 0314 su `OpportunityMetaTest`
  (17 campi), `QualificaCommercialBackOfficeSeederTest` e `StockAuthorizationTest` (ora solo la riga lead). Suite
  Opportunities/Leads/Seeding/RequestManagement/Roles/Authorization/ActivityLog/Users/Stock/Spec0177/ContractTypes/
  CompanyClosures verdi; Pint pulito. FE: opportunities+roles+leads 64 file / 513 test, help 150, ESLint, `tsc -b --force` puliti.
- Manuale: guide in-app IT/EN Opportunita' (tabella campi + paragrafo campi senza matita), Claude Docs rev 410
  (paragrafo "Se l'opportunita' nasce da un lead"); PDF derivato da rigenerare.
- Da fare in ambiente: rilanciare `QualificaRoleSeeder` (o il production seed) perche' le righe arrivino sul DB.

## Fix: ruoli ereditati dall'anagrafica sull'opportunita' — VERDE, COMMITTATO c33b1439 (2026-10-27)

- Segnalazione utente: in `/opportunities/new` e in "Nuova opportunita'" dal dettaglio anagrafica, scegliendo
  l'anagrafica non arrivavano Commerciale, Segnalatore, Supervisore e G.A. (spec 0040 A-5, 0199: gia' previsti).
- Root cause: `fetchOpportunityRegistryMeta` (`opportunity-relation-meta.ts`) leggeva `page.items[0]`, ma
  `registries/for-select?ids[]=N` NON filtra: restituisce la prima pagina per nome + l'id in coda
  (`appendHydratedForSelectIds`). Si ereditavano i ruoli della prima anagrafica alfabetica ("A. GABELLI", vuota).
  Ora match per id, come `use-campaign-project-meta.ts`. I test erano verdi perche' il mock restituiva solo l'id
  richiesto: fixture di `opportunity-registry-role-inheritance.test.tsx` allineata al contratto reale (prima riga
  = altra anagrafica con ruoli propri), 5/6 rossi prima del fix. Requisito invariato.
- Verifica: vitest `features/opportunities` 33 file / 253 test, ESLint, `tsc -b --force` puliti; endpoint reale
  con id 6058 -> la riga giusta porta commerciale/segnalatore/G.A. Manuale: nessun impatto (la guida descrive gia'
  l'ereditarieta'). Le altre letture per id (`use-competence-lines-field`, `use-task-work-order-registry`) fanno
  gia' `find` per id.

## Note su Anagrafiche, Referenti e RDA (spec 0312) — VERDE, NON COMMITTATO (2026-10-26)

- Sotto-task B della 0311. Richiesta utente: "voglio le note su rda, anagrafiche, referenti" + "prendi spunto da
  opportunita'": scheda **Note** (la prima) nel dettaglio e azione di riga `notes` con `notes_count` + `NotesDialog`.
- Backend: base agnostica `App\Notes\PermissionGatedNotable` (permesso di vista + `isVisibleTo`/`narrowReaders`
  opzionali) estesa da `RegistryNotable` (`registries.view`), `ReferentNotable` (`referents.view`),
  `PurchaseRequestNotable` (`purchase-requests.view` + `PurchaseRequestVisibilityScope`); slug in
  `config/notes.php`. `HasNotes` solo su Registry: Referent e PurchaseRequest hanno gia' la colonna testo `notes`,
  la relazione si chiama `noteThread()` (usata da `withCount('noteThread as notes_count')`). Azione `notes` ammessa
  quando lo e' `view`. `NotesSource::NOTABLE_TYPES` + registry/referent/purchase_request, dipendenze opzionali
  registries/referents/purchase-requests: rilanciando la sorgente arrivano le 560 note prima fallite.
- Frontend: tab Note in `registry-detail`/`referent-detail` (gate `permissions.resource.view` del record) e
  `purchase-request-detail` (passato a `RecordBody`, gate `can('purchase-requests.view')`); azione + dialog nelle
  tre griglie. Nota UX: referente e RDA hanno anche un campo testo "Note" accanto alla scheda "Note" (fuori scope
  rinominarlo; i test che cercavano il campo per etichetta ora usano il ruolo textbox).
- Test: nuovo `tests/Feature/Notes/RegistryReferentPurchaseRequestNotesTest.php` (AC-001..006, mutation-check
  sulla visibilita' RDA), `NotesSourceImportTest` e 3 test griglia aggiornati (REQUIREMENT CHANGED 0312). Backend
  Notes/Registries/Referents/PurchaseRequests/Migration/Tables 1213/1213 con `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`.
  FE: 3 nuovi `*-table-notes.test.tsx`, casi tab Note nei detail test; 157 file / 1227 test verdi; ESLint e
  `tsc -b --force` puliti; Pint pulito.
- Manuale: guide in-app IT/EN (sezioni note di Anagrafiche/Referenti/RDA, generale, Migrazioni), requisiti
  sorgente, manuale Claude Docs (rev 408: paragrafo note generale, "Note dell'anagrafica", referenti+RDA, sorgente Note).
  Il manuale Claude Docs non ha un capitolo sul modulo RDA: le note RDA stanno nel paragrafo dei referenti.

## Migrazione note legacy, sorgente unica `notes` (spec 0311) — VERDE, qnet-2 in 8223a855, legacy NON COMMITTATO (2026-10-26)

- Sotto-task B chiuso dalla spec 0312 (voce sopra): il punto qui sotto su `NOTABLE_TYPES` e' superato.

- Richiesta utente: "import di tutte le note per ogni modulo [...] un'unica /migration". Legacy: le note sono righe
  `activity_log` con `log_name` Note/Call/Appointment (111.826); `Email` e log di sistema esclusi (decisione utente).
- **Legacy** (`../qnet`, non committato, git soggetto a §3.6): `NoteMigrationController` + `LegacyNoteBody`, route
  `GET /api/v2/migration/notes` spostata con tutto il blocco "Migration domain" in `routes/apiV2-migration.php`
  (richiesto da `apiV2.php`, che aveva gia' superato le 500 righe). Risposte appiattite sulla radice (catene
  Activity->Activity e `parent_id`), risposta a log di sistema -> nota radice con `blockquote` del log; tre formati
  di menzione (Quill, badge v1 `data-mention-id`, badge v2) -> nodo qnet-2 con id utente legacy; autore cancellato
  -> `LegacyPlaceholderUser::ID`; soggetto cancellato, risposta orfana, lead -> orphan (spec 0303).
  Scansione completa sui dati reali (1.119 pagine, 1-1,7 s a pagina): 110.518 note (commesse 102.282, opportunita'
  5.721, task 1.955, anagrafiche 525, RDA 33, referenti 2), di cui 11.298 risposte e 186 risposte a log di sistema;
  1.308 orphan (1.253 su record cancellati, 26 risposte orfane, 28 lead); warning: 430 menzioni di utenti
  inesistenti, 234 autori cancellati; nessun markup di menzione legacy residuo.
- **qnet-2**: `old_id` su `notes` (migrazione `2026_11_03_100000`, NON ancora eseguita su qnet2 dev), `NotesSource`
  (fase 18, dopo `collections`; required users/work-orders, optional opportunities/tasks), allow-list
  `NOTABLE_TYPES` = work_order/opportunity/task. `registry`/`purchase_request`/`referent` falliscono "not
  supported yet" -> **sotto-task B** (decisione utente): thread note su Anagrafiche e RDA con spec dedicata, poi
  aggiungerli a `NOTABLE_TYPES` e rilanciare la sorgente (le righe fallite non hanno old_id).
- Test: `NotesSourceImportTest` (13), registri sorgenti aggiornati (REQUIREMENT CHANGED 0311), OldIdSchemaTest.
  Migration+Notes+RichText 731/731 con `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`: col `.env` locale (`true`) 7 test
  utenti/disponibilita' falliscono gia' prima di questa modifica. FE: vitest help/migrations/i18n 415/415, ESLint,
  `tsc -b --force` puliti. Pint pulito.
- Manuale: guida in-app Migrazioni IT/EN (nuova sezione `notes`, frasi task/commesse), i18n sorgente + requisiti,
  manuale Claude Docs (paragrafo sorgente Note + frase Task).

## Export tabelle AG Grid: colonne id/old_id e enum (Prodotti) — VERDE, NON COMMITTATO (2026-10-26)

- Bug 1 (Offerte, Opportunita', ogni tabella): la griglia riceve le colonne di default `id`/`old_id`
  (`InjectsDefaultIdColumn`), ma `CreateExportRequest` validava contro `columns()` grezzo -> 422 su `columns.N.colId`
  appena l'utente le mostra. Ora l'allow-list e' `array_keys(defaultColumnLayout())`, lo stesso di
  `TablePreferencesRequest` (include anche i custom field). `old_id` resta solo a chi ha `legacy-ids.view`.
- Il file valorizza `old_id`: nuovo `LegacyIdVisibility::attachToRow()`, condiviso da `TableService` (righe griglia)
  e `ExportService` (prima solo la griglia lo aggiungeva).
- Bug 2 (Prodotti): `mapRow` restituisce l'enum `ProductType` -> 500 nel formatter. `ExportValueFormatter` esporta
  un enum con `HasMeta` come `label()` (come il badge), altrimenti come value/name.
- Verifica su TUTTE le 84 tabelle del registry (script tinker in scratchpad, super-admin, tutte le colonne di
  `/tables/{d}/columns` + sort, max 30 righe, run cancellati a fine prova): 84/84 completed.
- Test: ExportStoreTest (old_id 201 con permesso, 422 senza), GenerateExportJobTest (old_id scritto solo con
  permesso), ExportValueFormatterTest (enum con/senza label). Exports 61/61, Pint pulito.
- Suite completa: 12428 passati, 15 rossi tutti fuori area export e gia' rossi o di ambiente: TableBulkDeleteTest e
  SupplierCommissionDirectionTest rossi anche col backend a HEAD; QuoteDocumentPdfTest = LibreOffice assente;
  Migration*/UsersSource*/rollback = area con modifiche locali non committate (migrazione note, spec 0311);
  GeoLookupTest verde da solo (flaky in parallelo).
- Manuale: nessun impatto.

## Export tabelle AG Grid: fix 422 e stato griglia congelato — VERDE, COMMITTATO (2026-10-26)

- Bug: nessun export partiva (0 `ExportRun` nel DB). `buildExportGridState` escludeva solo la colonna azioni: sulle
  tabelle con selezione righe (bulk delete/azioni) mandava `ag-Grid-SelectionColumn`, sulle raggruppate/tree
  `ag-Grid-AutoColumn` -> `Rule::in` di `CreateExportRequest` -> 422 "errore di validazione". Backend sano
  (verificato: export reali su tasks/registries/quotes/users completati).
- Fix FE: colonne e sortModel filtrati sul catalogo del dominio (`labelById`), come `toColumnPreferences`; prop
  `actionsColumnId` rimossa (`ExportDialog`, `table-view-slots`). `ExportDialog` non memoizza piu' lo stato griglia:
  lo legge a ogni render mentre e' aperto (prima restava quello del grid-ready, senza filtri/sort/colonne successivi).
- Test: builder (colonne sintetiche scartate; REQUIREMENT CHANGED: niente piu' fallback al colId grezzo) + dialog
  (stato letto all'apertura). Vitest exports+table 354/354, ESLint e `tsc -b --force` puliti.
- Manuale: nessun impatto.

## Back Office Commerciale: vedere e creare Proforma (spec 0299 D-8) — VERDE, NON COMMITTATO (2026-10-27)

- Nuova `CommercialBackOfficeRoleCatalogue::EXTRA_PERMISSIONS` (`proforma-requests.viewAny`/`view`/`create`), letta da
  `QualificaRoleSeeder::backOfficePermissions()`. Niente update/delete/export/viewActivity, niente Fatture.
- Spec 0299: D-8 (REQUIREMENT CHANGED rispetto a D-3), AC-001/AC-003 aggiornati. Test: nuovo `0299 D-8`, AC-003 con
  `/proforma-requests` nel menu. Seeding 191/191, Pint pulito.
- Solo seed: su qnet2 rieseguire `php artisan db:seed --class=QualificaCommercialBackOfficeSeeder` (risemina i ruoli).
- Manuale: nessun impatto.

## Back Office Commerciale: anche i Contratti (spec 0299 D-7) — VERDE, NON COMMITTATO (2026-10-26)

- `CommercialBackOfficeRoleCatalogue::MODULES` + `contracts`: il ruolo `back-office-commerciale` riceve ogni ability
  `contracts.*` (view/viewAny, update, export, viewActivity, changeStatus, validate, program, terminate, reactivate) e
  la voce di menu Contratti. Stati contratto, Configuratore Stati Offerta e Gestione Richieste restano fuori.
- Spec 0299: D-7 (REQUIREMENT CHANGED rispetto a D-3), AC-001/AC-003 aggiornati. Test: nuovo `0299 D-7`, AC-003 con
  `/contracts` nel menu e `GET /api/meta/contracts` 200; il negativo `contracts.view` sostituito da
  `contract-statuses.view`. Seeding 190/190, Pint pulito.
- Solo seed: su qnet2 rieseguire `php artisan db:seed --class=QualificaCommercialBackOfficeSeeder` (risemina i ruoli).
- Manuale: nessun impatto.

## Martina Mosca nel ruolo Back Office Commerciale (spec 0299 D-6) — VERDE, COMMITTATO (2026-10-26)

- `CommercialBackOfficeRoleCatalogue::USERS` + `martina.mosca@qualificagroup.com`: nel seed di produzione
  (`QualificaCommercialBackOfficeSeeder`, step 7 di `QualificaProductionDataSeeder`) riceve `back-office-commerciale`
  in aggiunta a `coordinatore-commerciale`, che resta. Spec 0299: D-6 (REQUIREMENT CHANGED rispetto a D-1), AC-002 a
  sei persone. Test `0299 D-6` in `QualificaCommercialBackOfficeSeederTest`; Seeding 189/189, Pint pulito.
- Solo seed: su qnet2 il ruolo NON e' stato assegnato (lo assegna il seeder o la pagina Utenti).
- Manuale: nessun impatto.

## Settori: "codice — nome" in tutti i select — VERDE, NON COMMITTATO (2026-10-26)

- Richiesta utente: "sui select dei settori voglio anche il codice oltre al nome, in tutti i select". Supera la AC-002
  della spec 0213 ("la label resta il nome", REQUIREMENT CHANGED, spec aggiornata con delta).
- **Fonte unica BE:** `Sector::selectLabel()` = `"{code} — {name}"`, solo nome se il codice e' null/vuoto. Usata da
  `SectorForSelectResource` (anche per gli `ids[]` idratati e per `RelationValueScopeChecker`) e dai ref `sectors:
  [{id, name}]` di `RegistryResource`, `PurchaseOrderResource` e righe `PurchaseOrdersTableDefinition` (`name` porta la
  label, cosi' i chip salvati coincidono con le opzioni). Eager load `sectors:id,code,name`. Rimosso
  `RegistryResource::toRefList` (orfano).
- `GET /sectors/tree`: ogni nodo ha `code`. FE `features/sectors/sector-label.ts` (`sectorLabel`) rispecchia il formato
  per le opzioni costruite lato client: picker settore padre (`flattenSectorTree`, `findSectorLabel` ex
  `findSectorName`) e quick-create settore.
- Non toccati: ordinamento del for-select (resta per nome) e filtro testo della colonna Settori della griglia ODA
  (cerca solo sul nome).
- Verifica: Pest Sectors 61, PurchaseOrders 261, Registries 140, InlineCellEditing 18 verdi; Pint ok; Vitest
  sectors/quick-create/purchase-orders/registries 286 + help 150 verdi; `tsc -b --force` exit 0; ESLint pulito.
- Manuale: guide `sectors` IT/EN aggiornate (campo Codice); manuale Claude Docs aggiornato (tabella Settori).

## Tab uniformi + "+" quick-create su tutti i select di modulo — VERDE, COMMITTATO (2026-10-26)

- Richiesta utente: "i tab devono essere tutti uguali; ogni select che ha un modulo, con permesso, deve avere il +".
- **Tab:** l'unico look e' in `components/ui/tabs.tsx` (`TABS_LIST_CLASS`, `TABS_TRIGGER_CLASS`, ex `FORM_TAB_*` di
  `form-tab-strip.tsx`, rimossi). `TabsList`/`TabsTrigger` li applicano di default; `FilterTabs`, il trough e il bottone
  "altro" di Gestione Richieste importano le costanti. Vietati override di sizing/colore sui trigger (solo layout:
  `w-fit`, `flex-1`...). Rimossi `RECORD_TAB_TRIGGER_CLASS` e i `TRIGGER_CLASS`/`TAB_CLASS` locali. Test `components/ui/tabs.test.tsx`.
  `SegmentedControl` resta distinto: e' un campo radio di form/filtri, non un tab.
- **Quick-create (spec 0028 D3):** nuove mappe `quick-create-entries/{lookup,record,catalog}-entries.tsx` (sedi aziendali,
  tipi contratto, layout documento, tag fattura, metodi pagamento, tipologie prodotto, priorita' RDA, lookup task, template
  task, UdM, IVA, conti finanziari, lead, opportunita', task, commesse, tipi costo, mansioni, attivita', competenze, corsi).
  Label = quella del `*ForSelectResource` (lead = nome anagrafica, commessa = `code — title`). Nuova chiave
  `financialAccounts.form.createSubtitle` IT/EN.
- Select "raw" cablati con `useQuickCreateAction` (wrapper fatture/fatture passive, conto finanziario, layout/tag fattura,
  generate-orders, lead opportunita', programma contratto, costi commessa, chiusure, competenze, formazioni, user-skills,
  magazzino, richiesta assenza). `ForSelectFormField`/`ForSelectMultiFormField` hanno prop opt-in `quickCreate` (spento:
  i filtri della run di liquidazione non devono avere il "+"). Test `quick-create/wired-selects-quick-create.test.tsx`.
- 20 test esistenti: aggiunto solo `vi.mock('@/features/auth/use-abilities')` con `can: () => false` (ora i select
  renderizzano `<Can>`, che senza AuthProvider lancia). Nessuna asserzione cambiata.
- **Senza "+", deliberato:** aliquota IVA e prodotto nelle righe fattura/offerta/costo (la riga ricava prezzo/aliquota dal
  `meta`, il form di creazione restituisce solo `{id,name}`); prodotti d'interesse (vincolati alle categorie) e prodotto
  del movimento di magazzino (solo INTERNAL); offerte (creazione = pagina intera, nessun form); nodi categoria (albero);
  filtri, azioni bulk, editor dell'import, campo intercettato read-only; absence-types (nessun select async).
- Guida in-app `general` IT/EN, sezione `opening-records-and-quick-create`, aggiornata. Manuale Claude Docs: da aggiornare
  la stessa sezione (creazione rapida) e l'aspetto dei tab.
- Nota: `docs/HANDOFF.md` e' ~820 KB, molto oltre il limite di 50 KB: va archiviato in `docs/handoff-archive/`.

## Attivazione/disattivazione massiva utenti (spec 0310) — VERDE, COMMITTATO (2026-10-26)

- `POST /api/users/bulk-activation` `{ is_active, ids (1..200) }` -> `data: { updated: int[], failed: [{id, name, message}] }`.
  FormRequest `UserBulkActivationRequest` (users.update, altrimenti 403), controller invokable
  `UserBulkActivationController`, service `App\Services\Users\UserBulkActivation`: per id policy `update` +
  permesso di campo `is_active` editabile, mai disattivare se stessi (D-2), poi `UserService::update` con
  `UpdateUserData(is_active:)` (stesse regole del singolo: cessazione 0272 D-8, funzione aziendale 0275 D-4, log).
  Esito per utente, best-effort (D-1). Messaggi in `lang/{it,en}/users.php` `bulk_activation.*`.
- FE: `use-user-bulk-activation.ts` (getBulkActions: Attiva/Disattiva selezionati, conferma solo su disattiva,
  un toast con l'esito misto), cablato in `users-table.tsx` solo con `can('users.update')`; `bulkSetUsersActive`
  in `api.ts`, tipi in `types.ts`. Chiavi i18n sotto `users.detail.actions.bulk.*` (nei file
  `*-users-detail-actions.ts`: `en.ts` e' gia' a 500 righe, hard limit).
- Test: `tests/Feature/Users/UserBulkActivationTest.php` (10), `use-user-bulk-activation.test.tsx` (3),
  `users-table.test.tsx` (+1 gate permesso; harness con `ConfirmContext` e mock `useAbilities`, nuovo requisito
  del componente). Guida in-app Utenti IT/EN aggiornata (sezione `edit-deactivate-delete`).
- Direttiva utente (spec 0310 D-5): un super-admin si modifica/disattiva/elimina SOLO da un altro super-admin.
  `UserPolicy::update/delete` rifiutano un target super-admin (helper `isPrivilegedTarget`, riusato da
  `impersonate`; i super-admin passano da Gate::before). `UsersAuthorization` legge update/delete dalla policy
  (`resourcePermissions`, `actorMayWrite`, azioni delete/avatar): per un non super-admin la scheda di un
  super-admin e' tutta in sola lettura, la griglia non offre Elimina. PATCH di un campo cambiato = 422 (gate dei
  campi, prima della policy). Test `UserSuperAdminProtectionTest.php`; `UserCrudTest` "422 when deleting the
  last super-admin" riscritto (REQUIREMENT CHANGED): ora e' l'unico super-admin che elimina se stesso.
- Fuori scope, segnalato: disattivare non revoca token/sessioni gia' aperti (vale anche per il singolo).

## Segni +/- nel Riepilogo Commissioni dell'offerta — VERDE, NON COMMITTATO (2026-10-23)

- Richiesta utente, solo FE, nessun cambio di contratto. Card estratta in `features/quotes/quote-commission-summary.tsx`
  (`QuoteCommissionSummary`): Commerciale/Segnalatore/Supervisore sempre "-"; Fornitore "+" se RECEIVED, "-" se PAID;
  con entrambi i versi due righe `supplierReceived`/`supplierPaid`; zero senza segno; hint `commissionsHint`.
- Parte ricevuta = `receivedSupplierCommission(productMargins.rows)` (in `quote-product-margins-calc.ts`), pagata =
  `commissionTotals.supplier - ricevuta`: stessa fonte di "Margine per prodotto", live e dettaglio salvato.
- Margine atteso: la commissione Fornitore RECEIVED entra gia' come ricavo della riga (`QuoteTotalsCalculator::
  receivedAdjustment`, FE `calculateLineMarginBeforeCosts`, spec 0202 D-8): nessuna modifica.
- Seguito: segni anche nel "Dettaglio provvigioni per prodotto" (`quote-commission-details.tsx`): ogni importo
  firmato con `commissionFlowSign(role, direction)` (`commission-calculator.ts`, +1 solo Fornitore RECEIVED);
  totali di riga, di ruolo e generale = SALDO entrate - uscite (non piu' somma lorda). Formatter condiviso
  `formatSignedQuoteAmount` (`quote-summary.tsx`, usato anche dalla card); `sumFormula` dei calculation hints
  sottrae i termini negativi. Test aggiornati per requisito cambiato (REQUIREMENT CHANGED):
  `quote-commission-details.test.tsx`, `quote-summary.test.tsx` (importo commerciale ora "−60.00").
- Fuori scope, segnalato: il KPI "Commissioni" della testata (`sumCommissionTotals`) somma ancora tutti i ruoli,
  ricevute incluse. Aperta con l'utente: base % del Commerciale sulle righe RECEIVED (imponibile vs commissione
  ricevuta), es. offerta 28020 margine 3.600 perche' Commerciale 40% = Fornitore 40%.
- Verifica: Vitest 9162/9162, `tsc -b --force` e ESLint puliti; guida in-app IT/EN `quotes` + manuale Claude Docs (rev 400).

## Visibilita' team dall'organigramma, spec 0309 — VERDE, NON COMMITTATO (2026-10-23)

- Direttiva utente: chi ha sottoposti vede il proprio team senza permesso dedicato. Eliminati `tasks.viewTeam`,
  `absence-requests.calendarTeam`, `staff-trainings.viewTeam`, `user-skills.viewTeam` (migrazione
  `2026_10_23_100000_drop_team_visibility_permissions`, `down()` irreversibile; GIA' applicata al DB dev).
- Regole (D-1..D-7 della spec): Task = tier team per chi ha `tasks.view` (assegnatari, disattivati inclusi, mai privati);
  calendario assenze = `calendarOwn` + sottoposti attivi -> team (senza calendarOwn niente calendario); Formazione/
  Competenze = chi ha sottoposti attivi entra anche senza `viewAny`; Competenze scrittura solo `user-skills.manage`;
  Segnatempo `time-entries.viewAll` legge chiunque in sola lettura (`TimeEntryReadAuthorizer`).
- Contratto: `GET /api/auth/me/abilities` + `has_subordinates` (discendenti ATTIVI), `useAbilities().hasSubordinates`;
  chiave di navigazione `open_to_managers` (solo voci staff-trainings/user-skills, rimossa dal payload).
- Nota: l'opzione filtro Task `visible` usa `allDescendantIds` (anche disattivati), `has_subordinates` solo attivi:
  divergenza voluta (coerente col tier Task 0214).
- Verifica (verifier indipendente): AC-001..015 PASS; Vitest 9155/9155, `tsc -b --force` e ESLint puliti, Pint pulito;
  `composer test` 12387 passati, rossi fuori scope: 7 in `tests/Feature/Migration` (UsersSource*, MigrationSource
  Availability, MigrationPlanEndpoints), `QuoteDocumentPdfTest` (manca soffice), `DemoOpportunitySeederTest` flaky
  in parallelo (isolato verde). Alzati di 1 i `--step` di rollback in PurchaseOrderMigrationTest,
  SupplierCommissionDirectionTest, QuoteWorkflowMigrationTest (nuova migrazione nel conteggio).
- Manuale: guide in-app IT/EN (tasks, absences, staff-trainings, user-skills, time-entries) e manuale Claude Docs
  aggiornati; PDF derivato da rigenerare.

## Righe offerta in sola lettura come le card dell'editor — VERDE, NON COMMITTATO (2026-10-23)

- Direttiva utente: le righe salvate del dettaglio offerta (e contratto, componente condiviso BR-7) hanno lo stesso
  aspetto della card dell'editor aperto. `QuoteLinesReadOnlyList` ora usa `QuoteLineEditorFrame` (catalog `null`,
  titolo "Righe dell'offerta"/"Righe di costo" + conteggio, totali Imponibile/IVA/Totale offerta|costi sommati dalle
  righe persistite) e una card per riga. Parti comuni estratte in `line-editor/quote-line-card-parts.tsx`
  (`LINE_CARD_CLASS`, `LINE_CARD_STRIP_CLASS`, `LINE_CARD_FOOTER_CLASS`, `QuoteLineCardHeading`, `QuoteLineAmounts`),
  usate anche da `QuoteLineCard`. Nel read-only la striscia mostra Quantita (+ simbolo UM), Prezzo unitario, IVA,
  Prodotto associato (costi) come etichetta/valore; categoria = `product.category.name` (non il percorso dal tree,
  per non aggiungere una fetch). `QuoteLineProductRef.product_typology` ora tipizzato con `color` (il backend lo
  mandava gia'). Test `quote-lines-read-only.test.tsx` aggiornato (REQUIREMENT CHANGED: niente piu' tabella).
- Verifica: Vitest quotes+contracts+help 71 file/724 test verdi, `tsc -b --force` pulito, ESLint pulito. Guide in-app
  IT/EN (`quotes.ts`, sezione righe) e manuale Claude Docs aggiornati. Verifica visiva su /quotes/28020 da fare a mano.
  Committato in `f27f63f8` (solo questi file; la spec 0306 resta nel working tree).
- Seguito (NON COMMITTATO): la matita delle righe Offerta/Costi sta nell'intestazione del riquadro, accanto a
  "Righe dell'offerta (n)", non piu' fuori. `RecordInlineField` accetta `children` come funzione
  `(editAction) => ReactNode` (il chiamante colloca la matita; `null` se non modificabile, altri usi invariati);
  `QuoteLineEditorFrame` e `QuoteLinesReadOnlyList` hanno lo slot `headerAction`.

## Ricalcolo commissioni delle offerte non chiuse (spec 0308) — VERDE, COMMITTATO (2026-10-23)

- Agent team (backend-0308, frontend-0308, ui-design-0308, verifier-0308). Verifier: AC-001..AC-014 PASS; AC-015
  (guide IT/EN commission-configurations + quotes, manuale Claude Docs rev 383) fatto dal lead, parity 150/150.
- Backend: enum `CommissionRecalculationMode` (KEEP_MANUAL, REGENERATE_ALL); `QuoteCommissionRecalculator` (D-5:
  snapshot verso dalla tipologia, KEEP_MANUAL = `QuoteLineCommissionWriter::sync()` con le commissioni salvate,
  REGENERATE_ALL = sync regenerate, poi margini + `QuoteService::persistAggregates()` ora pubblico; rilegge la quote con
  `lockForUpdate` e salta le chiuse); `CommissionRecalculationImpact`; `RecalculateCommissionConfigurationQuotesJob`
  (tries 1, `config/commission_recalculation.php` job_timeout 1800, GenericNotification N aggiornate / K no);
  azione `recalculate_commissions` in `QuotesAuthorization`. Endpoint: GET/POST `quotes/{quote}/commission-recalculation`
  | `recalculate-commissions`, GET/POST `commission-configurations/{id}/quote-impact` | `recalculate-quotes` (202).
  Test: `tests/Feature/Quotes/QuoteCommissionRecalculationTest.php`,
  `tests/Feature/CommissionConfigurations/CommissionConfigurationQuoteRecalculationTest.php`,
  helper `tests/Helpers/CommissionRecalculationHelpers.php`.
- Frontend: `components/ui/radio-group.tsx`, `components/controlled-choice-dialog.tsx` (props `pending`,
  `pendingLabel`, `onOpenChange`, `dismissible`, nessuna preselezione); offerta `quote-recalculate-commissions.tsx` +
  `use-quote-commission-recalculation.ts` (bottone in `quote-detail-header`, `onChanged` passato da `quote-detail.tsx`);
  regola `commission-configuration-propagation-dialog.tsx` + `use-commission-configuration-propagation.ts` (dopo il
  salvataggio di un campo diverso da `name`).
- Suite complete: Pest 12375/12386 (10 rossi preesistenti: 7 users-migration per `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true`,
  3 QuoteDocumentPdfTest senza LibreOffice), Vitest 9152/9152, tsc -b pulito, Pint pulito.
- Note: nei POST `mode` invalido -> 422 anche senza permesso (validazione prima dell'authz, come nel resto). Sullo
  stesso repo lavora in parallelo un'altra sessione (es. spec 0307): al commit includere solo i file di 0306/0308.

## Verso commissione Fornitore sulle regole (spec 0306) + dettaglio offerta — VERDE, COMMITTATO (2026-10-23; legacy committato a parte)

- Spec 0306 (backend + frontend + legacy `LegacySupplierDirection`): implementata; suite complete verdi insieme alla 0308
  (Pest 12375/12386 con i 10 rossi preesistenti, Vitest 9152/9152); mappatura AC 1:1 della 0306 non rifatta. Decisioni aperte: base di calcolo per regola (sol. 2), regole legacy mai
  applicate (5 Fornitore senza destinatario + categoria padre 69) importate come Sospese o attive (sol. 3).
- Controllo QUO-104043 (27343) e QUO-104725 (28020) contro il legacy: dati fedeli. Su 104725 il commerciale del
  2 anno Ente ha `agent_perc` 40 e `agent_cost` 0 nel legacy: l'export manda la % (domanda aperta all'utente).
- Dettaglio offerta (frontend): "Informazioni aggiuntive" spostate DOPO le righe Offerta/Costi in dettaglio e creazione
  (`QuoteAttributesBand`, `QuoteDetailAttributes`, `QuoteCreateAttributes`); "Margine per prodotto": tooltip col nome
  intero (`quote-truncated-label.tsx`) e, sulle righe RECEIVED, ricavo = commissione ricevuta e commissioni = solo
  pagate (margine invariato, ora le colonne si sommano); nuovo blocco "Dettaglio provvigioni per prodotto"
  (`quote-commission-details.tsx`, come il legacy "Dettaglio Provvigioni per Servizio" + Fornitore e verso) nel
  riepilogo del dettaglio, chiuso dietro "Mostra dettaglio provvigioni" come i dati avanzati (`QuoteSummaryDisclosure`,
  prop `commissionLines` di `QuoteSummary`, solo offerta salvata e permesso `commissions`). Cella per ruolo: nome
  (`TruncatedLabel`, tooltip col nome intero), % o "Importo fisso" a sinistra e importo a destra, per il Fornitore badge
  Ricevuta/Pagata + (?) (`FieldHint` ha ora la prop opzionale `icon`, default Info). Vitest quotes+help+components
  1124/1124, tsc -b pulito, eslint pulito. Guide IT/EN + manuale Claude Docs (rev 374) aggiornati.
- Calcolo su ogni importo (come le commesse): `CalculationHint` spostato da `features/work-order-contract-data/` a
  `components/calculation-hint.tsx` (import delle commesse aggiornati). Formule in `quote-calculation-hints.ts`:
  provvigione % = base (imponibile − costi imputati, min 0) × %, con "Importo salvato" se differisce; importo fisso;
  totali riga/ruolo/generale come somme; Margine per prodotto: ricavo (imponibile o commissione ricevuta), costi
  imputati, commissioni, margine = ricavo − costi − commissioni. `ProductMarginRow` ha ora `lineNet` e
  `supplierCommissionNet`; `QuoteSummary.commissionDetails = { offerLines, costLines }`. Vitest quotes+commesse+
  components+help 1426/1426, tsc pulito. Lint: unico errore preesistente in `features/quotes/column-renderers.tsx`
  (react-refresh, file non modificato). Manuale Claude Docs rev 376.
- Destinatari nel Dettaglio provvigioni (`quote-commission-recipient.tsx`): `user` -> `UserProfileHoverCard`;
  `referent`/`registry` -> `RecordLink` (domini `referents`/`registries`) + HoverCard con tipo e link; senza permesso
  `<domain>.view` nome semplice. I test di dettaglio/riepilogo mockano `useAbilities` (can false). Vitest quotes 417/417,
  components+help+commesse 763/763 (help 150/150), tsc pulito. Manuale rev 380.
- Legacy: `MigrationFieldFormatter::plainText()` (tag tolti, entita' decodificate, una riga per blocco, `- ` per le
  voci di elenco) usato per `lines[].description` in `QuoteMigrationController`. Dati gia' importati su qnet2 dev:
  4065 `quote_lines.additional_description` ripuliti con la stessa regola (update mirato chiesto dall'utente).
  `task_template_items.description` ha ancora 382 valori HTML: da valutare (forse rich text voluto).
- NB: questo file e' ~800 KB, ben oltre i 50 KB: va archiviato in `docs/handoff-archive/`.

## Dotazioni (spec 0307, fase 1) — VERDE, COMMITTATO (2026-10-23)

- Giacenze di beni fisici a uso interno per sede operativa e utente + registro movimenti append-only. Spec
  `docs/specs/0307-hr-stock-equipment.xml` (D-1..D-21, contratto congelato + `<i18n_keys>`).
- Naming: tutto `stock` nel codice (`stock_balances`, `stock_movements`, `StockBalance`, `StockMovement`,
  `StockMovementType` LOAD|UNLOAD|TRANSFER|ASSIGN|RETURN|ADJUSTMENT, `StockHolder` FREE|ASSIGNED, permessi
  `stock.viewAny|manage`, rotta `/stock`, help key `stock`); in UI "Dotazioni"/"Giacenze". Nuove case
  `ProductType::Physical` ('PHYSICAL'), `ProductUsage::Internal` ('INTERNAL'); tracciato = PHYSICAL + INTERNAL.
- Backend: `Services/Stock/{StockMovementService, StockQueryService}` (transazione + `lockForUpdate` sul prodotto,
  riga a zero cancellata), `StoreStockMovementRequest`, `StockPolicy`, `StockAuthorization`,
  `Tables/Stock/{StockBalances,StockMovements}TableDefinition` (azioni riga assign|return|transfer|unload|adjust|history),
  `Tables/Products/ProductStockColumn`, `holder_key` = colonna generata COALESCE(user_id,0) nell'unique.
  Guard 409 su delete di prodotto, sede e utente con movimenti (anche delete di massa via `deleteModel`).
- Accesso (D-21, decisione utente): ruolo `gestione-dotazioni` solo a flavio.scognamiglio@qualificagroup.com
  (`StockKeeperRoleCatalogue` + `QualificaStockKeeperSeeder`, Step 4d di `QualificaRoleSeeder`) + `admin` (tutto il
  catalogo). NON a `risorse-umane`. Deploy: `php artisan permissions:sync` + `db:seed --class=QualificaStockKeeperSeeder`.
- Frontend: `features/stock/*` (pagina a due schede, sheet movimento, storico riga, sezioni riusabili), sezione
  Giacenze nel prodotto, sezione Dotazioni nella scheda utente, guide in-app `stock` (+ products/users/general).
- Verifica (verifier indipendente): Pest completo 12367 pass con `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`, unici
  rossi `QuoteDocumentPdfTest` (manca LibreOffice); `tests/Feature/Stock` 38/38; vitest completo verde (timeout solo
  sotto carico, rieseguiti isolati verdi); `tsc -b --force` 0; eslint/pint puliti. AC-001..020 PASS, AC-021 a codice.
- REQUIREMENT CHANGED: `ProductTableTest` 13→14 colonne; `FieldCatalogueEndpointTest` + risorsa `stock`;
  `help-guide-keys` 96→97; rollback step +2 in `PurchaseOrderMigrationTest` (90), `QuoteWorkflowMigrationTest` (241),
  `SupplierCommissionDirectionTest` (105) — file condivisi con la 0306 non committata: riallineare se cambia ancora.
- Non verificato: lock e colonna generata su MySQL reale; responsive nel browser.
- Segnalati, fuori scope: etichetta "Utilizzo in offerta" ora contiene anche "Uso interno"; `products.columns.code`
  senza traduzione (preesistente); questo file pesa ~800 KB (oltre il limite di 50 KB, va archiviato).
- Menu spostato (decisione utente 2026-10-23, COMMITTATO): Dotazioni e' figlia di `products-group` in
  `config/navigation/products.php` dopo `products`, icona `boxes`; `config/navigation/stock.php` rimosso. Chi ha solo
  `gestione-dotazioni` vede Prodotti con la sola voce Dotazioni (test in `StockAuthorizationTest`).
- Azioni di riga Giacenze allo standard (richiesta utente 2026-10-23, COMMITTATO): tolto `inlineAllActions` da
  `stock-balances-grid.tsx`, quindi oltre 3 azioni le altre vanno nei tre puntini (`INLINE_ACTION_LIMIT`); ordine dal
  catalogo backend assign|return|transfer|unload|adjust|history. Test `stock-balances-grid.test.tsx`.
- Prossimo: fase 2 (matricole, verbale di consegna, avviso a fine contratto), fase 3 (carico da ricevimenti OdA).

## Migrazioni bloccate "in corso" in produzione — VERDE, NON COMMITTATO (2026-10-23)

- Causa: `RunMigrationJob`/`RunMassMigrationJob` senza `$timeout`, `$tries`, `failed()`. In produzione vale il
  `queue:work --timeout` (default 60 s): il worker uccide il job, il catch di `MigrationService::runSource()` e gli
  update di `RunMassMigrationJob::handle()` non girano, la run resta `processing` per sempre (i contatori, scritti
  riga per riga, sembrano completi; il kill arriva spesso in `afterImport()`, che per users/sectors rilegge tutta l'API).
  In locale non si vede: `composer dev` usa `queue:listen --timeout=0` e il `.env` `QUEUE_CONNECTION=sync`.
- Fix (stesso schema di `StageImportJob`, spec 0136): `$tries = 1`, `$timeout = config('migrations.job_timeout')`
  (env `MIGRATION_JOB_TIMEOUT`, default 1800, deve restare sotto `queue.connections.database.retry_after` 1900),
  `failed()` che porta a `failed` la run se `pending`/`processing`; per la massiva anche la figlia in volo.
  Laravel su timeout fa gia' rollback della transazione aperta (`Job::fail`), quindi l'update di `failed()` persiste.
- Test: `RunMigrationJobTest` (+3), `MassMigrationTest` (+3). `tests/Feature/Migration` 596/596 verdi con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false` (il `.env` locale lo mette a `true` e fa fallire 7 test users: preesistente,
  phpunit.xml non lo fissa).
- Da verificare in produzione: `failed_jobs`, comando del worker (`--timeout`), `DB_QUEUE_RETRY_AFTER`. Se l'import
  massivo supera 1800 s ora finisce `failed` (non piu' appeso): per completarlo alzare insieme `MIGRATION_JOB_TIMEOUT` e
  `DB_QUEUE_RETRY_AFTER`, oppure (decisione aperta, cambia spec 0046) una sorgente per job in `Bus::chain`.

## Seed di produzione: import legacy anche di utenti e conti finanziari — VERDE, NON COMMITTATO (2026-10-23)

- Richiesta utente: `QualificaLegacyImportSeeder` importa anche `users` e `financial-accounts`.
- `financial-accounts` in `SOURCES` (fase 2, dopo `company-sites`: risolve la societa' via `old_id`).
- `users` in `USER_SOURCES`, separato: deve girare DOPO i seeder degli account (operatori, staff), cosi'
  `UsersSource` abbina gli account esistenti invece di crearli lui (in link-only: disattivati e "[OLD]").
  `ALL_SOURCES` = `SOURCES` + `USER_SOURCES`; `run(array $sources = ALL_SOURCES)`.
- `QualificaProductionDataSeeder`: passo 5 con `SOURCES`, nuovo passo 10 (dopo `QualificaHrCatalogSeeder`) con
  `USER_SOURCES`. `QualificaCatalogSeeder::offerLegacyImport()` passa solo `SOURCES`. Esecuzione da sola = tutto.
- `roles` NON importato: gli utenti creati dal legacy (non abbinati) restano senza ruolo legacy (avviso non fatale).
- Test: `QualificaProductionDataSeederTest` (REQUIREMENT CHANGED: 2 mass run, SOURCES poi USER_SOURCES),
  `QualificaLegacyImportSeederTest` (REQUIREMENT CHANGED: mass run = `ALL_SOURCES`; + conto e utente abbinato;
  + liste separate).

## Righe d'offerta legacy senza servizio -> prodotto [OLD] (spec 0305) — REV. 1 COMMITTATA (4ba5395f, legacy 1df9e60b), REV. 2 VERDE NON COMMITTATA (2026-10-22)

- Spec `docs/specs/0305-legacy-quote-lines-without-service.xml` (approvata, D-1..D-9). Caso guida commessa 270 ->
  offerta 238 (righe 74624/74625/74626 "... UNI ISO 11381:2017 _ 1°/2°/3° Anno", `service_id` 0).
- Legacy (`../qnet`, non committato): nuovo `App\Support\Migration\LegacyFreeLineProducts` (titolo normalizzato:
  trim, spazi ridotti, minuscole; `key` = id minimo su TUTTA `quotationservices`, `name` = "[OLD] " + titolo; 339
  titoli). `QuoteMigrationController::mapLine()`: riga senza servizio -> `product_id` null + `free_product {key,name}`
  + `description` null; righe con servizio invariate. Modifiche utente a `AppServiceProvider.php`/`config/database.php`
  non toccate.
- qnet-2: `Migrations/Support/LegacyFreeLineProduct` (`OLD_SOURCE` 'quotationservices'; trova/crea il prodotto in
  "No categoria", prezzo e IVA della prima riga, nome troncato a 191, avviso "Line N: product '...' created from the
  line title."; key o name mancanti -> offerta fallita), `Migrations/Support/UncategorizedProductCategory` (estratta da
  `ProductsSource`, che la usa; messaggio se manca il seed: "The 'No categoria' category does not exist; run
  QualificaCatalogSeeder first."). `MapsLegacyQuoteLines::legacyOfferLine()` usa `free_product`; `QuotesSource`
  inietta `LegacyFreeLineProduct`.
- REV. 2 (D-5 corretta, opzione B dell'utente): anche le righe di un servizio cancellato (311 righe, 56 servizi) hanno
  `free_product` e `product_id` null. Con titolo -> prodotto del titolo; senza titolo (174 righe, 53 servizi) -> un
  prodotto per servizio "[OLD] Servizio cancellato (id N)", key = id minimo delle sue righe senza titolo. Le chiavi
  per titolo si calcolano PRIMA sulle righe con `service_id` 0 (verificato: 0 chiavi cambiate su 653), poi sui servizi
  cancellati. Solo legacy (`LegacyFreeLineProducts` riscritto: mappa id riga -> free_product) + docblock/guide in
  qnet-2: contratto invariato. Fuori scope: opportunita' non migrate.
- REV. 3 (richiesta utente: niente `<p>`/`<h2>` nei nomi): `LegacyFreeLineProducts::title()` riduce la descrizione
  (testo ricco) a testo semplice (fine blocco/`br` -> spazio, `strip_tags`, entita' decodificate, nbsp -> spazio).
  Chiavi: 523 su 964 righe; si unisce solo "OG1/IV_950634975C" (righe 7995 e 11494). DB locale corretto una tantum
  via tinker (query builder, niente activity log) dalla mappa calcolata dal legacy: 119 prodotti rinominati, prodotto
  2866 (key 11494) unito in 2864 (1 riga d'offerta spostata, nessun altro riferimento) e cancellato; 514 prodotti,
  0 tag/entita'/nbsp, 0 nomi doppi. "GROSSETO.In" (riga 7992) e' cosi' nel testo legacy.
- Test: nuovo `QuotesSourceFreeLineProductTest` (7). Pest `tests/Feature/Migration` 584/584 con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false` (le solite 7 note col `.env` locale). Pint pulito. Help vitest 149/149,
  tsc -b --force 0, eslint pulito.
- Rilancio locale (run 39/40): offerte fallite 801 -> 379 (422 create: 124 opportunita' non migrata, 255 servizio
  cancellato, 0 "has no product"), commesse fallite 394 -> 354 (40 create, tutte "Unresolved quote_id"). 323 prodotti
  [OLD] creati. QUO-0238 e COM-0270 presenti. Rev. 2 (run 42/44): offerte fallite 379 -> 124 (tutte opportunita'
  non migrata), commesse fallite 354 -> 137 (tutte "Unresolved quote_id"); prodotti [OLD] da righe 515, di cui 51
  "Servizio cancellato". Nel frattempo qualcuno ha lanciato anche opportunities (run 41) e tasks (run 43).
- Manuale: guida `migrations` IT/EN (nota "No categoria") e manuale Claude Docs (stesso paragrafo) aggiornati.
- Prossimo: rilanciare tasks, proforma/fatture attive se si vogliono i record collegati alle nuove offerte/commesse;
  le 124 offerte restano ferme sulle opportunita' non migrate (run 41 opportunities: 31 fallite per anagrafica non
  migrata, 2 senza cliente; il legame con le 124 non e' verificato).

## Assenze spec 0304 — CONSEGNA 2 VERDE, COMMITTATA (2026-10-22)

- Consegna 2 (B6, B7, B8, F2, F6, F7, F8, F9) + guide in-app e manuale: con questa la spec 0304 e' completa (la
  "consegna 3" prevista, solo guide, e' stata assorbita qui per §4-bis).
- Saldi (B6): spettanza effettiva = allowance.annual_minutes ?? default_annual_days x giornaliere ?? 0, `source`
  allowance|default|none; `AbsenceBalanceBuilder::compose()` senza query, riusato da `AbsenceBalanceOverviewBuilder`
  (query costanti); `AbsenceAllowanceBulkWriter` (per persona, activity log); `Exports/AbsenceBalances/
  AbsenceBalanceOverviewExporter`; rotte `absence-balances/overview`, `overview/export?format=csv|xlsx`,
  `absence-allowances/bulk` (tutte manageAll). Riporto: anche chi ha solo il default (riga con annual_minutes null).
- Chiusure (B7): `CompanyClosureService` (409 se iniziata e non ricorrente), `CollectiveLeaveGenerator`
  (`regenerate()`/`purge()`, insert in blocco: niente activity log per richiesta generata, solo sulla chiusura),
  `CompanyClosure::appliesToSiteIds()`/`isStarted()` usati anche da `PersonWorkCalendar` (`siteIdsByUser()` pubblico);
  Policy con abilities standard, `CompanyClosuresTableDefinition`, menu Configurazione `/company-closures`
  (`calendar-x`), permessi al ruolo `risorse-umane` (`HrOfficeRoleCatalogue::MODULES`), activity-log config.
  Limite 31 giorni INCLUSIVI (FE allineato). REQUIREMENT CHANGED: `FieldCatalogueEndpointTest` (+company-closures).
- Calendario/segnatempo (B8): `closed_days`, `color`, `day_part` nel calendario; `TimeEntryAbsenceResolver::resolve()`
  riceve `PersonNonWorkingDays`; DaySummary `closure: {name}|null`, chiusura = is_non_working_day true, target 0.
- Frontend: tessere saldi (`balances/balance-tile.tsx`, tolto `balance-row.tsx`), vista "Saldi" per manageAll
  (`balance-overview/**`), catalogo tipi con modalita'/preavviso/colore/default, nuovo `features/company-closures/**`
  (rotta in `routes/hr-routes.tsx`, i18n `*-company-closures.ts`, guida + HELP_GUIDE_KEYS 96), calendario (colori,
  mezze giornate, chiusure, riga Assenti, clic su giorno libero -> `AbsenceRequestSheet prefill`), blocco Assenze
  in dashboard, nome della chiusura sulla card del Segnatempo.
- Verifica: composer test 12303/12314 (solo i noti ambientali: 7 Migration con LINK_ONLY=true -> 584/584 con false,
  3 QuoteDocumentPdf senza LibreOffice); vitest 9044/9045 (flaky noto purchase-invoice-editor-withholdings, da solo
  verde); tsc 0; Pint pulito. 375 px verificato dal codice (contenitori con overflow-x-auto), non visivamente.
- Manuale: guide in-app absences, absence-types, company-closures (nuova), time-entries, dashboard IT/EN; Claude
  Docs (Assenze, Tipi di assenza, Chiusure aziendali, Segnatempo, ruolo Risorse Umane, Dashboard). PDF da rigenerare.
- Dopo il deploy: `php artisan migrate`, `permissions:sync`, `db:seed --class=QualificaRoleSeeder` (chiusure a
  risorse-umane), `db:seed --class=QualificaAbsenceTypeSeeder` (modalita' e colori dei tipi).

## Assenze al livello dei migliori HR (spec 0304) — CONSEGNA 1/3 VERDE, COMMITTATA (2026-10-22)

- Spec `docs/specs/0304-hr-absences-overhaul.xml` (approvata; D-1..D-18). Consegna 1 = chi chiede e chi approva
  (B1-B5, F1, F3-F5). Restano: consegna 2 (B6 saldi default/massa/panoramica/riporto, B7 chiusure aziendali CRUD +
  ferie collettive, B8 calendario + segnatempo con chiusure, F2 catalogo tipi, F6 tessere saldi + panoramica HR,
  F7 chiusure, F8 calendario, F9 dashboard), consegna 3 (guide absence-types/company-closures/time-entries/general).
- Schema completo gia' migrato (B1): `2026_10_31_100600` company_closures + pivot `company_closure_operational_site`,
  `100700` tipi (allows_full_day/half_day/hourly, min_notice_days, color, default_annual_days), `100800` richieste
  (day_part, is_short_notice, company_closure_id restrict), `100900` `absence_allowances.annual_minutes` nullable.
  Enum `AbsenceDayPart`, `CompanyClosureKind`; model `CompanyClosure` (morph `company_closure`; activity-log config e
  permessi `company-closures.*` ancora da fare in B7). Contatori rollback +4 in SupplierCommissionDirection,
  QuoteWorkflowMigration, PurchaseOrderMigration (REQUIREMENT CHANGED).
- Backend: `Services/Absences/Calendar/PersonWorkCalendar::load()` -> `PersonNonWorkingDays` (unico punto per i giorni
  non lavorativi per persona, chiusure holiday per sede; `WorkCalendar` invariato, B8 dovra' usarlo in segnatempo e
  calendario); `Requests/AbsenceNoticeRule::isShort()`; mezza giornata = requested_minutes intdiv(giornaliere, 2);
  `Insight/{AbsenceInsightBuilder, AbsenceColleaguesResolver, AbsenceSummaryBuilder}`,
  `Requests/AbsenceRequestBulkDecision`, `Calendar/AbsenceCalendarPeople::visibleUserIds()`;
  `AbstractAbsenceRequestsTableDefinition::visibleQuery()` condivisa con il summary. Rotte nuove in
  `routes/api/absence-requests.php`: preview, bulk-decision, `absences/summary`, `{id}/insight`.
  residual_after: approved = residuo, altrimenti residuo - minutes (spec revised in build).
- Frontend: `absence-duration-format.ts` (`formatAbsenceMinutes`), `absence-type-color.ts` (`absenceTypeDotClass`,
  unica mappa colori dei tipi), `requests/absence-insight-panel.tsx` (variant preview|context), form con modalita'
  (`mode` full_day|half_day|hours) e Persona (manageAll), `summary/*`, azioni riga + bulk sulla scheda Da approvare,
  `FilterTabOption.count`, badge menu via `nav-main.tsx` (`badges`). Rimosso `absence-request-balance-hint.tsx`.
- Verifica (verifier indipendente): composer test 12259/12270 (10 falliti ambientali: 7 Migration con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` nel .env, 577/577 con false; 3 QuoteDocumentPdf senza LibreOffice);
  vitest 9000/9000; tsc -b --force 0; Pint pulito; eslint pulito sui file della consegna. AC-001..009, 018, 019,
  021, 022 e AC-020 (parte riepilogo/schede/badge) PASS.
- Manuale: guida in-app `absences` IT/EN (nuove sezioni `summary`, `quick-approval`; tabs, new-request, detail),
  manuale Claude Docs (Assenze). PDF derivato da rigenerare.

## Intestazioni dei moduli allineate — VERDE, COMMITTATO (2026-10-22)

- Standard unico: `PageHeader` = solo breadcrumb + azioni (come `/opportunities`); il titolo della pagina sta gia'
  nella barra in alto (`AppPageTitle`). Rimosse le prop `title`/`subtitle` da `components/page-header.tsx`, cosi'
  nessuna pagina puo' piu' reintrodurle.
- Allineate: System health, API e integrazioni, le 7 pagine del Controllo di gestione, Partitario, Impostazioni
  (prima `h1` text-2xl proprio; ora `PageHeader`, contenuto resta centrato `max-w-5xl`). Rimosse le chiavi i18n
  orfane IT/EN (`systemHealth.title/subtitle`, `apiIntegrations.title/subtitle`, `ledger.title`,
  `settings.title/subtitle`, `managementControl.title` + `title` di accounts/agents/billing/cashFlow/trend/exposure).
- Verifica: vitest mirato 113 file / 883 test verdi, eslint pulito; `tsc -b` con errori SOLO nei test delle assenze
  (lavoro spec 0304 in corso in un'altra sessione, non toccato). Manuale: nessun impatto (guida `general` gia'
  descrive breadcrumb + pulsante su ogni pagina tranne la home).
- Fuori scope segnalato: `components/page-placeholder.tsx` non e' usato da nessuno (codice morto).

## Migrazione: conti delle banche societarie (spec 0297 D-5, 0301 D-10) + incassi (spec 0302) — VERDE, COMMITTATO (2026-10-22)

- Legacy: nuovo `Support/Migration/LegacyCompanyBankAccounts` (banca -> conto per IBAN, altrimenti id riservato
  1.000.000 + id banca). `/financial-accounts` manda anche le banche 6, 8, 10, 11 di `company_banks` senza conto (13
  conti in totale); `/invoices` (`LegacyInvoiceLookups`) le collega: restano senza conto solo i 1.286 documenti con
  `bank_id` 0. qnet-2: nessuna modifica di codice; testi requisiti, guida IT/EN, manuale aggiornati.
- Incassi (spec 0302, decisione utente "quello che non si importa genera errore, non filtrare nulla"): nuova sorgente
  `collections` (fase 17) + legacy `CollectionMigrationController` (`/migration/collections`, 2.760 item = 2.750
  registrazioni + 10 testate vuote, tutti i 2.906 movimenti con il totale incassato della proforma). qnet-2
  `CollectionsSource`: registra sulle rate aperte solo la parte che manca (min(movimento, totale legacy - incassato,
  residuo)) via `InvoiceCollectionService::record()` (parziale -> residuo su nuova rata, matura le provvigioni),
  salta il gia' riflesso, fa fallire col motivo le 22 senza documento e i movimenti su proforma cancellate (13) o
  non importate. Test `CollectionsSourceImportTest` 10/10, suite Migration+Invoices+InvoiceInstallments+Commission*
  1000/1000. Registry 37. Le 12 registrazioni senza documento ora compaiono come righe fallite.
- ATTENZIONE provvigioni: le Fatture attive scrivono gli incassi delle scadenze direttamente (senza
  CommissionAccrualSynchronizer), quindi quegli incassi non maturano provvigioni; quelli della sorgente Incassi si'.
  Da decidere al punto provvigioni.
- Orfani come righe fallite (spec 0303, decisione utente): legacy `Support/Migration/LegacyOrphanRows` accoda dopo i
  record di `/purchase-requests` (28), `/purchase-orders` (73), `/purchase-invoices` (493 = 4 orfane + 489 scadenze di
  ODA non fatturati), `/invoices` (83) item `{id "tabella:id", orphan_reason}`; qnet-2 `AbstractMigrationSource::
  assertNotOrphan()` li fa fallire col motivo sotto il loro id (test `LegacyOrphanRowsTest`). COMMITTATO.
- Provvigioni (punto 3): nessun codice. `php artisan commissions:sync-accruals` ricostruisce il maturato delle fatture
  incassate ed e' schedulato ogni notte SENZA --settled-before: dopo l'import maturerebbe ~2.900 incassi storici come da
  liquidare. Lanciare una volta `--settled-before=AAAA-MM-GG` subito dopo Incassi. DATA DA CHIEDERE ALL'UTENTE (ultima
  liquidazione legacy o cutover). Legacy `commissions`: 179.784,55 euro su 917 righe, non importato (qnet-2 ricalcola).
- Ricordare prima di importare: i conti vanno importati PRIMA delle fatture attive (il re-import salta i documenti).

## Migrazione richieste proforma e fatture attive dal legacy (spec 0300, 0301) — VERDE, COMMITTATO (2026-10-22)

- Decisioni utente: incassi dalle scadenze legacy (stato 3 incassata, 2 parziale `cashed`); totale <= 0 -> riga
  fallita (214 proforma + 26 ente); proforma ente nella stessa sorgente, intestate all'ente, numerate da qnet-2;
  richieste proforma migrate prima, sorgente propria, documento collegato alla richiesta.
- Legacy (committato su develop): `ProformaRequestMigrationController` (`/migration/proforma-requests`, 10.432: 91 su
  commesse inesistenti) e `InvoiceMigrationController` (`/migration/invoices`, 10.560 = 9.319 `proformas` + 1.241
  `proforma_ente`, prima tutte le proformas poi le ente) con `Support/Migration/LegacyCustomerProformas`,
  `LegacyInstitutionProformas`, `LegacyInvoiceLookups` (banca -> conto per IBAN, societa' dell'offerta, massimo
  dell'anno, scadenza -> rata con incasso), `LegacyProformaRequestMatcher` (abbinamento cronologico per commessa,
  ente: commessa + fornitore; 8.905 abbinati, nessun doppione). 1.419 documenti con banca 6/8 senza conto legacy.
- qnet-2: migrazioni `2026_10_31_100400_add_old_id_to_proforma_requests_table`, `2026_10_31_100500_add_old_id_to_invoices_table`
  (`old_source` + `old_id`, unico sulla coppia: id sovrapposti tra le due tabelle). `ProformaRequestsSource` (fase 12
  con tasks), `InvoicesSource` (fase 16), `Support/LegacyInvoiceWriter`, `Support/LegacyDocumentNumberer` (numerazione
  condivisa, ora usata anche da `PurchaseOrdersSource`), `Support/Concerns/BuildsLegacyDocumentLines` (descrizione,
  old_id facoltativo, scadenza che assorbe la differenza: usato dai writer di ODA, fatture passive e attive).
- Test: `ProformaRequestsSourceImportTest` 9/9, `InvoicesSourceImportTest` 12/12; suite estesa 1261/1261 (flag
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`). REQUIREMENT CHANGED: registry 36, ultima fase, rollback +2 (82/97/233).
  `invoices` non e' nel dataset di OldIdSchemaTest (unico sulla coppia, come i prodotti). Vitest 180/180, ESLint, tsc ok.
  Guida IT/EN (sezione `active-invoices`), i18n, manuale.
- Fuori scope: incassi non legati a scadenze (sorgente Incassi), conti delle banche 6/8/10/11, provvigioni, allegati.

## Migrazione fatture passive dal legacy (spec 0298) — VERDE, COMMITTATO (2026-10-22)

- Decisioni utente: D-1 una fattura per (fornitore, numero, anno, societa'): 315 FTP -> 262 documenti (13 multipli,
  es. fattura 189 su 35 ODA); D-2 la fattura riceve cio' che fattura anche su ODA in bozza (ODA evaso); D-3 "Pagata"
  -> scadenze pagate per intero alla data dell'ultimo aggiornamento legacy, senza conto; D-4 totale <= 0 -> riga
  fallita (30 documenti attesi).
- Legacy (committato su develop): `PurchaseInvoiceMigrationController`, `Support/Migration/LegacyPurchaseInvoiceGroups`
  (raggruppamento in PHP: TRIM MySQL non toglie i tab dei numeri), route `GET /api/v2/migration/purchase-invoices`.
  Correzione ODA (spec 0296 D-8): `LegacyPurchaseOrderLines` manda il `budget_importo` sulle righe delle FTP, la
  fattura il `consuntivo_importo`.
- qnet-2: migrazione `2026_10_31_100300_add_old_id_to_purchase_invoices_table`, `PurchaseInvoicesSource`,
  `Support/LegacyPurchaseInvoiceWriter` (righe via PurchaseInvoiceAmountCalculator, scadenze legacy con ultima che
  assorbe la differenza, planner se assenti, pagato), `Support/LegacyCostTypeLookup` (condiviso con il writer ODA),
  `PurchaseOrderInvoiceReceiptService::receive()` + parametro `requireReceivable` (default true; false solo dalla
  migrazione). Fase 15.
- Test: `PurchaseInvoicesSourceImportTest` 17/17; suite Migration+PurchaseInvoices+PurchaseOrders+PurchaseRequests+
  installments+rollback 1136/1136 (flag `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`). REQUIREMENT CHANGED: registry 34,
  ultima fase, rollback +1 (80/95/231). Vitest 180/180, ESLint, tsc ok. Guida IT/EN (sezione `purchase-invoices`),
  i18n, manuale.
- Fuori scope: scadenze degli ODA non fatturati (489), stato "Validata da pagare", allegati (spec 0295).

## Ruolo Back Office Commerciale nel production seed (spec 0299) — VERDE, COMMITTATO (2026-10-22)

- Richiesta utente: Lidia Russo, Deborah Farina, Caterina Maduni, Alessia Luongo, Lia Palladino abilitate a
  Anagrafiche, Opportunita', Offerte, Commesse "tutto". D-2: Anagrafiche = solo `registries` + `referents`.
- Solo seed: `QualificaCatalog/CommercialBackOfficeRoleCatalogue` (ruolo `back-office-commerciale`, `MODULES`
  completi `registries|referents|opportunities|quotes|work-orders`, `SELECT_ONLY_RESOURCES` viewAny
  `product-categories` (albero) + `operational-sites` (tetto Sede dell'Opportunita'), `USERS` per email);
  step 4c di `QualificaRoleSeeder` (nessuna riga di matrice campi); nuovo `QualificaCommercialBackOfficeSeeder`
  (ruolo AGGIUNTO sopra quello base dello StaffRoster), step dopo `QualificaHrOfficeSeeder` nel production seeder.
  Martina Mosca (Supervisor Back Office Commerciale) NON e' nella lista: fuori per scelta dell'elenco utente.
- Su DB installato: `php artisan db:seed --class=QualificaCommercialBackOfficeSeeder`.
- Test: nuovo `tests/Feature/Seeding/QualificaCommercialBackOfficeSeederTest.php` (4); Seeding 187, Users 268,
  Authorization 109 + gli altri file che usano QualificaRoleSeeder verdi; Pint pulito. Guida `users` IT/EN
  (elenco ruoli senza dati contrattuali), Vitest help 148/148, ESLint, `tsc -b --force` 0; manuale Claude Docs
  (stessa frase). PDF derivato da rigenerare.

## Migrazione conti finanziari dal legacy (spec 0297) — VERDE, COMMITTATO (2026-10-22)

- Richiesta utente: migrare `/financial-accounts` in /migrations, anche nel legacy.
- Legacy (committato su develop): `FinancialAccountMigrationController`, route `GET /api/v2/migration/financial-accounts`.
  Sorgente `amministrazione_contis` (9: 8 "conto" -> bank_account, 1 "cassa" -> cash; nessuna carta). Societa' =
  business unit di `company_banks` con lo stesso IBAN, altrimenti denominazione contenuta nel nome (7 su 9 trovate).
  IBAN segnaposto ("xyz", Transitoria Iniziale) -> null con warning. Numero carta/CVV/password mai inviati.
- qnet-2: migrazione `2026_10_31_100200_add_old_id_to_financial_accounts_table`, `FinancialAccountsSource` (via
  `FinancialAccountService::create`, `ValidIban`, adozione per IBAN di un conto creato a mano). Fase 2, optional
  `companies`. `old_id` servira' a fatture attive (`invoices.financial_account_id`) e rate passive.
- Test: `FinancialAccountsSourceImportTest` 8/8; suite Migration+FinancialAccounts+rollback 614/614 (flag
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`). REQUIREMENT CHANGED: registry 33, rollback +1 (79/94/230).
  Vitest 180/180, ESLint, tsc ok. Guida `migrations` IT/EN (nota nella sezione contabilita'), i18n, manuale.

## Migrazione ODA dal legacy (spec 0296) — VERDE, COMMITTATO (2026-10-22)

- Decisioni utente: D-1 tutti gli 805 `proformfora_heads` (490 ODA "Proforma" + 315 FTP "Fattura") arrivano come ODA;
  ordine RDA -> ODA -> fatture passive (la fattura si collega all'ODA). D-2 numero legacy; doppio per (societa', anno)
  -> max(year_max_number legacy, max qnet-2, contatore) + 1, warning e "Numero legacy N (protocollo P)" nelle note;
  il contatore `purchase_order_number_sequences` sale al numero piu' alto importato.
- Legacy (committato su develop): `PurchaseOrderMigrationController`, `Support/Migration/LegacyPurchaseOrderLines`, route
  `GET /api/v2/migration/purchase-orders`. Mapping: legacy `customer_id` = FORNITORE, `supplier_id` = CLIENTE,
  `company_id` = company_profile (sede aziendale), societa' dal profilo; tipologia di costo = `tipo_oda` ?? Altro;
  creatore = causer activity_log "created" delle righe `Proformfora` (fallback Utente legacy). Tinker: 805 ODA,
  1.384 righe, 1.022 link RDA, 91 warning (80 righe RDA gia' collegate a una riga ODA precedente, 11 orfane),
  1 ODA senza fornitore (fallira').
- qnet-2: migrazione `2026_10_31_100100_add_old_id_to_purchase_orders_tables` (old_id testata e righe, per le future
  fatture passive), `PurchaseOrdersSource`, `LegacyPurchaseOrderLineWriter` (importi via PurchaseOrderAmountCalculator,
  `oda_reference` RDA via PurchaseRequestLineLinker, ricevimento completo degli evasi come il backfill spec 0254),
  concern `RequiresLegacyAnchors` estratto e usato anche da `PurchaseRequestsSource` (ora 270 righe). Fase 14.
- Test: `PurchaseOrdersSourceImportTest` 16/16; suite Migration+PurchaseOrders+PurchaseRequests+rollback 940/940 (con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`, vedi sotto). REQUIREMENT CHANGED: registry 32, ultima fase, rollback +1
  (78/93/229). Vitest 180/180, ESLint, tsc ok. Guida `migrations` IT/EN (sezione `purchase-orders`), i18n, manuale.
- Prossimi passi: fatture passive (dati FTP: numero/data fattura, budget/consuntivo, `schedule_suppliers`) collegate
  agli ODA per `old_id`; allegati in spec 0295.

## Migrazione RDA dal legacy (spec 0294) — VERDE, COMMITTATO (2026-10-22)

- Richiesta utente: iniziare la contabilita' in /migrations con le RDA. Decisioni D-1..D-4: societa'/sede mancanti
  ricavate dalla business unit dell'offerta della commessa (vince la sede); RDA aperta con tutte le righe terminali
  chiusa all'import (autore/data dell'ultimo log, motivo null); storico stati importato; allegati e note di tutti i
  moduli in spec separata `0295-legacy-attachments-and-notes-migration` (draft).
- Legacy (committato su develop; modifiche utente su AppServiceProvider/config/database
  non toccate): `PurchaseRequestMigrationController`, `Support/Migration/LegacyPurchaseRequestLines`, route
  `GET /api/v2/migration/purchase-requests`. Tinker: 623 RDA, 1.370 righe, 1.434 log, nessun ancoraggio null,
  6 warning (5 societa' sostituite dalla sede D-1, 1 fornitore orfano).
- qnet-2: migrazione `2026_10_31_100000_add_old_id_to_purchase_requests_tables` (old_id su testata e righe, D-7 per la
  futura sorgente ODA), `PurchaseRequestsSource` (330 righe, sopra il soft limit), `LegacyPurchaseRequestLineWriter`,
  config + `MigrationOrder` fase 13. Unita' per `code`: `package`/`cubic_metre`/`set` non esistono nel seed (161 righe
  "conf" restano senza unita' se non si crea prima il codice `package`).
- Test: `PurchaseRequestsSourceImportTest` 19/19; REQUIREMENT CHANGED: MigrationRegistryTest (31), MigrationDependenciesTest
  (ultima fase), rollback +1 (PurchaseOrderMigration 77, SupplierCommissionDirection 92, QuoteWorkflowMigration 228).
  Le 7 rosse di Migration (Plan/SourceAvailability/UsersSource*) dipendono dal `.env` locale
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true`: verdi con la variabile a false. Vitest help+migrations 180/180, ESLint, tsc ok.
- Guida `migrations` IT/EN (sezione `purchase-requests`), i18n sorgente + requisiti, manuale Claude Docs (paragrafo dopo Task).
- Prossimi passi: `php artisan migrate` sul DB locale (migrazione pending); nel DB locale `qnet2` le commesse non sono
  ancora migrate (run quotes #29 in processing), quindi l'import RDA va lanciato dopo Offerte e Commesse. Poi sorgente ODA.
- Fuori scope segnalato: testo requisiti `tasks` IT/EN dice ancora "un creatore mancante diventa l'utente che lancia la
  migrazione" (superato da spec 0289 D-3 aggiornata).

## Stato del sistema: card "Versioni" (spec 0187 rev. 2026-10-22, AC-017/018) — VERDE, COMMITTATO (2026-10-22)

- Richiesta utente: mostrare in Stato del sistema la versione di PHP e degli altri linguaggi usati.
- Backend: `RuntimeVersionsService` -> `data.versions` = `[{key, value}]` con key `php` (PHP_VERSION), `laravel`
  (`app()->version()`), `database` ("<driver title> <server version>", null se il DB non risponde). Solo informativo,
  non entra nell'`overall`.
- Frontend: `versions-card.tsx` (gruppi Backend / Frontend) dopo le card check; `frontend-versions.ts` aggiunge
  TypeScript (`typescript/package.json`) e React (`version` di `react`), fissati al build: nel bundle finisce solo la
  stringa di versione, nel chunk lazy della pagina. i18n `versions.*` it/en.
- Guida `system-health` IT+EN e manuale Claude Docs (tabella "Cosa controlla" + frase introduttiva) aggiornati.
- Verifiche: Pest SystemHealthTest 15/15, Pint ok; Vitest help + system-health 158/158, ESLint ok, `tsc -b --force` ok,
  `vite build` ok.

## Task senza creatore -> "Utente legacy" (spec 0289 D-3 aggiornata) — VERDE, COMMITTATO (2026-10-22)

- Decisione utente: il run `tasks` #47 aveva intestato 24.111 task (senza creatore, richiedente ne' assegnatari nel
  legacy) all'utente che lanciava la migrazione (Demo User). Ora vanno al segnaposto "Utente legacy" (999999999).
- Legacy: `LegacyTaskPeople::forTask()` chiude la catena del creatore con `LegacyPlaceholderUser::ID`, quindi
  `creator_user_id` non e' mai null (verificato in tinker su 84774, 85287, 87976...; `php -l` ok).
- qnet-2: `TasksSource` toglie il ripiego sull'attore (backend.md section 9): `creator_user_id` null -> riga fallita
  "No creator in the legacy; the task is not imported."; `importRecord` non riceve piu' il context.
  Test AC-005 REQUIREMENT CHANGED. Migration 464/464 con `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`.
- Guida `migrations` IT+EN e manuale Claude Docs aggiornati.
- Da fare in ambiente: il segnaposto non esiste ancora nel DB locale -> rilanciare `users`, poi rifare `tasks`
  (i task gia' importati vengono saltati: i 24.111 restano a Demo User finche' non si cancellano e reimportano).

## Assenze: anche l'ufficio del personale approva (spec 0278 D-15) — VERDE (2026-10-21)

- Decisione utente: chi ha `absence-requests.manageAll` (ruolo `risorse-umane`) approva o rifiuta qualunque
  richiesta submitted, oltre agli approvatori fissati all'invio; mai la propria (D-7). Supera D-2 e la frase
  "la copia non dà diritto di decidere" di D-14.
- Regola unica `AbsenceRequestVisibility::canDecide()`, usata da `AbsenceRequestCapabilities` (`can.approve|reject`),
  `AbsenceRequestTransitions::decide()` (403) e `AbsenceRequestsToApproveTableDefinition` (per manageAll: tutte le
  submitted tranne le proprie).
- Notifiche: approvazione e rifiuto vanno a persona + approvatori (autore escluso), così il responsabile sa l'esito
  quando decide l'HR; chiavi `approved.approver`/`rejected.approver`; tolto `submitted.office_title` (la copia HR
  dell'invio ha il titolo "da approvare").
- Test: `AbsenceRequestWorkflowTest` (AC-006 REQUIREMENT CHANGED + HR decide), `AbsenceRequestTablesTest` (HR "Da
  approvare"), `AbsenceRequestNotificationTest` (copia HR rev., esito al responsabile); Absence* 95 verdi. Guida
  `absences` IT+EN e manuale Claude Docs aggiornati.

## Rimozione del modulo CCNL (spec 0293) — VERDE, NON COMMITTATO (2026-10-21)

- Decisione utente: via il modulo CCNL con dati, campi e collegamenti. Migrazione
  `2026_10_30_100000_drop_collective_agreements`: drop di `employment_contracts.collective_agreement_level_id`
  (FK per colonne su SQLite, per nome `employment_contracts_level_foreign` su MySQL), di
  `collective_agreement_levels` e `collective_agreements`; cancella permessi `collective-agreements.*`,
  `role_field_permissions`, `user_table_preferences`, `user_table_filters`, `table_filter_views` del dominio e
  l'activity log dei due morph. `down` ricrea solo la struttura vuota. Dopo il deploy: `php artisan migrate`.
- Backend eliminato: model, controller, request, resource, DTO, service, policy, authorization, table definition,
  factory, rotte, lang, `CollectiveAgreementCatalogue`. Tolti: morph map, config authorization/tables/activity-log/
  navigation, `HrOfficeRoleCatalogue::MODULES`, livello da request/resource/model/service delle condizioni e da
  `EmploymentResource::currentContract()`. `QualificaHrCatalogSeeder` non crea più il CCNL e non assegna livelli
  (`ensureCondition()` crea solo la prima condizione); `OrgChartRoster` senza `level`.
- Restano di proposito (D-5): "Rinnovo CCNL 2026" nei dati demo del controllo di gestione e la descrizione della
  mansione di compliance. Le migrazioni di creazione restano (già committate).
- Frontend eliminato: `features/collective-agreements`, pagina, rotta, breadcrumb, i18n, permessi, menu, guida
  in-app (`HELP_GUIDE_KEYS` 95). Scheda utente e dialog "Nuova condizione" senza "CCNL e livello".
- Test: `CollectiveAgreementsRemovalTest` (AC-001/002), test CCNL adattati (REQUIREMENT CHANGED). Manuale:
  guida `users` IT+EN e manuale Claude Docs (ruolo Risorse Umane) aggiornati.

## Assenze aperte a ogni mansione (spec 0292) — VERDE, NON COMMITTATO (2026-10-21)

- Richiesta utente: assenze "come segnatempo visibili a tutti"; il responsabile vede proprie + sottoposti, "tutto"
  solo con permesso. Spec `docs/specs/0292-hr-absences-for-every-role.xml` (D-1..D-4, AC-001..005 verdi).
- Solo seed: nuovo blocco `QualificaCatalog/OwnAbsencesCatalogue` (`BLOCK` + allow-list `PERMISSIONS` =
  `absence-requests.viewAny|create|calendarOwn|calendarTeam`) in `OperatorRoleCatalogue::EVERY_ROLE_BLOCKS` (file a
  parte: il catalogo era a 494 righe), ramo in `QualificaRoleSeeder::blockGrants()`. `manageAll`/`calendarAll`
  restano a `risorse-umane`/`admin`. Ruolo base: "Operatore base (Task, Segnatempo e Assenze)". Visibilita'
  (`AbsenceRequestVisibility`, `AbsenceCalendarScope`), endpoint e FE invariati.
- Supera 0283 D-6 (le mansioni non ricevevano il calendario) e chiude 0279 D-1 (senza squadra = solo se stesso).
- Produzione: `QualificaProductionDataSeeder` -> Operator/Staff seeder -> `QualificaRoleSeeder`; su DB gia'
  installato rieseguire `php artisan db:seed --class=QualificaRoleSeeder`.
- Test: nuovo `tests/Feature/Users/QualificaAbsenceGrantsTest.php` (3); REQUIREMENT CHANGED rotte visibili
  (+ `/absences`) in `QualificaTaskGrantsTest`, `QualificaRoleMatrixTest`. Users 268, Seeding 183, Absences 7,
  AbsenceCalendar 15, AbsenceRequests 66, AbsenceBalances 10, AbsenceTypes 7, Authorization 109, TimeEntries 136
  verdi; Pint pulito; Vitest help+absences 203/203, ESLint, `tsc -b --force` 0.
- Manuale: guide in-app `absences` (overview + calendario) e `general` (Assenze nel menu) IT/EN; Claude Docs
  (Assenze: chi vede cosa, calendario). PDF derivato da rigenerare.
- NB: in parallelo un'altra sessione lavora sulla spec 0293 (rimozione CCNL) e tocca `HrOfficeRoleCatalogue`,
  `QualificaRoleSeeder` e i test HR: committare le due modifiche separatamente.

## Assenze: copia delle notifiche all'ufficio del personale — VERDE (2026-10-21)

- Spec 0278 D-14: ogni passaggio di una richiesta (invio, approvazione, rifiuto, annullamento, approvazione
  automatica) va anche, per conoscenza, agli utenti attivi titolari di `absence-requests.manageAll` (ruolo
  `risorse-umane`); il solo super-admin non la riceve. Nessun doppione (chi è già approvatore o persona riceve
  solo la propria frase), mai all'autore né alla persona della richiesta. La copia non dà diritto di decidere.
- `AbsenceRequestNotifier::dispatch()` (Step 1 destinatari + copie, Step 2 consegna afterCommit),
  `hrOfficeIds()`; `AbsenceRequestNotification` nuovo parametro `copy` -> audience `office`, titolo
  `submitted.office_title`. Chiavi `office`/`automatic_office`/`office_title` in `lang/{it,en}/absence_requests.php`.
- Test: `AbsenceRequestNotificationTest` (5 casi nuovi); Absence* 91 verdi. Guida in app `absences` sezione
  `notifications` (IT+EN) aggiornata, help 149 verdi, tsc pulito.

## IVA 22% sul tutoraggio e-Campus — VERDE (2026-10-21)

- Seed di produzione: "ASSISTENZA E TUTORAGGIO" (Corsi E-Campus, `products/397` locale) ora con l'aliquota 22%
  (`ECampusCourseCatalogue::PLUS_VAT_SINGLE_PRODUCTS`, risolta da `CatalogProducts::plusVatRate()`). TESI e i corsi
  restano senza IVA.
- `CatalogProducts::seedProduct()` su un prodotto già presente riempie ora anche un `vat_rate_id` vuoto (come la
  descrizione, `fillBlanks()`): un'aliquota scelta a mano resta. Per applicarla in produzione: rieseguire il seed
  del catalogo (`QualificaCatalogSeeder`, incluso in `QualificaProductionDataSeeder`).
- Test: `QualificaECampusCatalogueTest` (nuovo caso backfill), `QualificaSelfFundedCatalogueTest` (conteggio IVA
  esclude Corsi E-Campus, REQUIREMENT CHANGED). `tests/Feature/Products` 252 verdi.

## Tirocinante, orario e scadenza contrattuale nello storico (spec 0291) — VERDE, NON COMMITTATO (2026-10-21)

- Spec `docs/specs/0291-employment-condition-working-time-expiry.xml` (approved, AC-001..010 verdi).
- `RelationshipTypeEnum::Intern = 'intern'` (Dipendente, Partita IVA, Tirocinante, Altro). Nuovo `WorkingTimeEnum`
  (full_time, part_time). Migrazione `2026_10_29_100000_add_working_time_and_expiry_to_employment_tables`:
  `working_time` e `contract_expires_on` (date) su `employment_contracts` e copia su `employment_profiles`.
  Stesso nome su entrambe: `contract_expires_on` NON e' il `ends_on` derivato della history.
- Regole: `working_time` required_if employee + Rule::enum; `contract_expires_on` date_format:Y-m-d,
  after_or_equal:starts_on, facoltativa e solo informativa (non chiude, non cessa, nessun avviso). Copia solo da
  `syncCurrent()`; `PUT users` la ignora. Nessun backfill.
- `QualificaContractTypeSeeder`: 3 tipologie (Tirocinio tolta: il tirocinio e' il tipo di rapporto). Non cancella:
  dove "Tirocinio" e' gia' caricato va eliminato o disattivato a mano.
- Griglia: colonne `working_time` (set enum, enumKey `working_time`) e `contract_expires_on` (date, `DateCell` in
  `features/users/column-renderers.tsx`) subito dopo `contract_type`, legate a `employment.contracts`.
- FE: `RELATIONSHIP_TYPES` con `intern`, `WORKING_TIMES`; dialog (Orario + Scadenza accanto alla tipologia),
  storico, Rapporto contrattuale; i18n IT+EN. Guide `users`/`contract-types` IT+EN e manuale Claude Docs (rev 331).
- Test REQUIREMENT CHANGED: rollback step fisso +1 (PurchaseOrderMigration 75, QuoteWorkflowMigration 226,
  SupplierCommissionDirection 90), ContractTypeSeederTest (3 valori), payload employee con working_time in
  EmploymentContractTest/EmploymentContractTypeTest, TablePreferencesTest, UserEmploymentColumnsTest (intern),
  fixture FE di user-contract-history/user-payment-and-contract.
- Verifica: composer test 12087 ok (rossi solo Migration da `.env` LINK_ONLY e QuoteDocumentPdfTest), Vitest 8971
  + renderer, tsc -b, ESLint, Pint puliti; migrate/rollback/migrate su MySQL locale ok.
- Da fare in ambiente: `php artisan migrate`; eliminare/disattivare "Tirocinio" se gia' seminato.
- Segnalati, non fatti: `contract-condition-dialog.tsx` 302 righe (soglia soft 300).

## Tipologia contrattuale sugli utenti (spec 0290) — COMMITTATO adcce78f (2026-10-21)

- Spec `docs/specs/0290-user-contract-type.xml` (approved, AC-001..014 verdi; AC-009 rev.2: i valori del filtro
  sono i nomi in uso nelle righe visibili + blank, come Società, non l'intero catalogo).
- Catalogo `contract_types` (name unique 100, is_active, sort_order) con CRUD come i tipi di assenza: model
  `ContractType` (morph `contract_type`), `ContractTypePolicy`, `ContractTypesAuthorization` (`contract-types.*`),
  `ContractTypeService` (delete 409 se in uso), `ContractTypesTableDefinition`, for-select ADR 0011
  (`/api/contract-types/for-select`), rotte in `routes/api/contract-types.php` incluse da `hr.php`, menu
  Configurazione (icona `clipboard-list`). Seeder `QualificaContractTypeSeeder` (Tempo indeterminato/determinato,
  Apprendistato, Tirocinio, ordine 1-4) in `QualificaProductionDataSeeder`; ruolo HR con `contract-types.*`.
- Storico (spec 0272): `employment_contracts.contract_type_id` e copia `employment_profiles.contract_type_id`
  (FK restrictOnDelete). Scritta SOLO da `EmploymentContractService::syncCurrent()`. `StoreEmploymentContractRequest`:
  `required_if:relationship_type,employee` + exists su tipologie attive. Nessun backfill: condizioni vecchie senza
  tipologia. Import/seeder HR non la impostano. `PUT users` la ignora.
- Contratto: `EmploymentContractResource.contract_type {id,name}|null`; `EmploymentResource.current_contract.contract_type`.
  Griglia Utenti: colonna set `contract_type` (nascosta di default) legata a `UsersAuthorization::CONTRACTS_FIELD`.
- FE: `features/contract-types/*` (`contractTypeKeys`, `CONTRACT_TYPES_FOR_SELECT_RESOURCE`), rotta `/contract-types`,
  dialog condizione con campo obbligatorio se Dipendente, storico e scheda mostrano la tipologia;
  label `users.columns.contract_type`. Guide in-app `contract-types` it/en nuove (96 chiavi), `users` it/en aggiornate;
  manuale Claude Docs aggiornato (rev 327).
- Test REQUIREMENT CHANGED: rollback a step fisso +2 (PurchaseOrderMigration 74, QuoteWorkflowMigration 225,
  SupplierCommissionDirection 89), FieldCatalogueEndpoint, TablePreferences, `EmploymentContractTest::contractPayload()`,
  `user-contract-history`/`user-payment-and-contract` test, `help-guide-keys.test.ts`.
- Verifica: `composer test` 12073 ok; rossi solo i 7 Migration noti (`.env` LINK_ONLY=true, con false 464/464) e
  `QuoteDocumentPdfTest` (manca LibreOffice). Vitest 8968/8968, tsc -b, ESLint, Pint puliti; migrate/rollback/migrate
  su MySQL locale ok.
- Da fare in ambiente: `php artisan migrate`, poi `db:seed --class=QualificaContractTypeSeeder` (o il production
  seeder) e il sync dei permessi del ruolo HR.

## Migrazione commesse W3 e task: utenti cancellati -> "Utente legacy" — VERDE, NON COMMITTATO (2026-10-21)

- Problema W3 del run 2026-10-20 (`~/Downloads/righe-fallite-migrazione-2026-10-20.xlsx`): 112 commesse fallite per
  responsabile/partecipante cancellato fisicamente dal legacy (utenti 69 e 47; nel DB anche 96 e 117: 115 commesse).
- Decisione utente: un utente "Utente legacy" a cui vanno quelle commesse. Logica SOLO nel legacy (backend.md §9):
  `../qnet/app/Support/Migration/LegacyPlaceholderUser.php` (ID 999999999, record, `replaceDeleted()`);
  `UserMigrationController` lo accoda all'ultima pagina senza ricerca (total +1); `WorkOrderMigrationController`
  sostituisce gli id assenti da `users` (dedup prima del max 12 partecipanti). Verificato via tinker sui dati reali
  (commesse 61/76/119 -> 999999999; pagina utenti offset 399 = solo il segnaposto, total 400) + `php -l`.
- qnet-2: nessun codice. Test `WorkOrdersSourceImportTest` "imports a work order whose deleted legacy user...":
  il segnaposto nasce inattivo "Utente legacy" (senza [OLD]) e diventa responsabile/partecipante. Spec 0013
  emendamento sexies, spec 0189 G-27 aggiornata. Migration 463/463 con `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`
  (con il `.env` locale restano le 7 note).
- Da fare in ambiente: rilanciare `users` (crea il segnaposto), poi `work-orders` e `tasks` (riprendono le fallite).
- Task (spec 0289 D-8, richiesta utente): `LegacyTaskPeople` sostituisce l'utente cancellato in ogni ruolo (creatore,
  richiedente, assegnatari, osservatori) prima di unire/togliere doppioni; 52 task (scansione completa: 0 id cancellati rimasti). Test qnet-2
  `TasksSourceImportTest` "AC-005 imports a task whose deleted legacy users...". Migration 464/464.
- Manuale: nessun impatto.

## Ramo "ISO": sottocategorie e listino prodotti dal PDF "iso" — VERDE, COMMITTATO (2026-10-21)

- Fonte: `~/Downloads/iso.pdf` (export Excel del listino servizi legacy, 323 righe; il CSV precedente era sbagliato,
  parola dell'utente). Ricostruito dalle coordinate delle parole (`pdftotext -bbox`), 8 righe con testo fuori colonna
  corrette sul legacy (`services`). Esclusa la riga 1338 "ISO_" (radice PROGETTI-). 322 prodotti, 89 sottocategorie.
- SOSTITUISCE `ISO - Certificazione` (tolta dal catalogo; sul DB locale eliminata, era vuota): ogni "Categoria" del
  listino -> `ISO - <Categoria>`, selezionabile, eredita campi e form dalla radice. Pulizia (decisioni 2026-10-21):
  Ecovadis/ECOVADIS, Emas/EMAS -> EMAS, Sa8000/SA8000 -> SA8000 uniti; `90001+11034` -> `9001+11034`; `ENTE_ISO 9001`
  -> `Ente 9001` (idem gli altri ENTE_); `ISO` -> `Servizi generali`; `STANDARD MULTIPLI` -> `Standard multipli`.
- Dati: `QualificaCatalog/IsoSubcategories` (SUBCATEGORIES, TARGET_CATEGORIES, `typologies()`: Ente -> `institution`,
  resto `consultancy`), `IsoProductCatalogue` (norme numeriche + costanti `IQI`, `NO_VAT_RATE`),
  `IsoNamedStandardProductCatalogue`, `IsoMultiStandardProductCatalogue`. Cablati in `BranchCatalogues` e
  `CatalogProducts` step 7.
- `BranchProducts`: chiave opzionale `vat_rate` {name, rate} per riga (match sul NOME, `firstOrCreate`, la riga piu'
  vecchia vince): 9 prodotti "Nessuna" (Marcatura CE, GACP, 11381). Fornitore IQI (18 prodotti Ente) collegato solo
  se esiste l'anagrafica fornitore "IQI" (in locale non c'e').
- Correzioni al listino: terzo anno `IQI_14_3`, `22+22005_3`, `PAS 24000_3`; i 6 servizi 45001 "Ampliamento scopo" e
  "Transizione" (codice "45001" ripetuto) e la riga senza codice -> PRD-; "27017+27018" terzo anno titolato "3°Anno";
  i due "mantenimento della Certificazione Bio" con "_2° Anno"/"_3° Anno". Non portati (nessun campo prodotto):
  Cartella (sempre SI), Voce di costo (ISO_Costo, 3 righe), Voce di ricavo (1 riga).
- Test: `QualificaIsoCatalogueTest` 8 (4 nuovi sui prodotti). REQUIREMENT CHANGED: `QualificaCatalogSeederTest`
  (979 prodotti, somma per listino, selezionabili + `IsoSubcategories::TARGET_CATEGORIES`),
  `QualificaProductionDataSeederTest` (989), `QualificaSelfFundedCatalogueTest` (conta solo le aliquote 22%).
  Pest Products 251/251, Seeding 184/184, ProductCategories 357, CustomFields 138, Report 167, WorkOrders 325 verdi;
  Quotes 373/376: i 3 rossi sono `QuoteDocumentPdfTest` (LibreOffice locale, `DocumentConversionException`), non legati.
  Pint pulito. Manuale: nessun impatto (dati di catalogo). Seed lanciato sul locale (89 sottocategorie, 322 prodotti).

## Ramo catalogo "ISO" (campi Offerta + Commessa dal legacy) — VERDE, COMMITTATO (2026-10-21)

- Decisioni utente 2026-10-21: radice `ISO` (contenitore, funzione aziendale "ISO", regole Consulenza) + unica
  sottocategoria selezionabile `ISO - Certificazione`; nessun prodotto. Codici nuovi `iso_*` (i codici legacy
  `grp*`/`check_*` restano sulle categorie `[OLD]`). Le 19 caselle norma del legacy -> UNA casella per gruppo
  (`iso_group_general/food/energy/anti_bribery/road_safety/gender_equality`), ognuna apre il suo blocco via
  `visible_when` (spec 0288: una sola condizione booleana, niente "almeno uno"). "Altro" e "Allegati" non portati.
- Dati: `QualificaCatalog/IsoCatalogue` (albero, campi e form Offerta, 10 sezioni di cui 6 condizionate),
  `QualificaCatalog/IsoWorkOrderCatalogue` (Commessa: Dettagli lavorazioni, Focus lavorazione ISO, Verifiche ispettive
  + Data verifica effettiva; niente "Stato Lavorazione", come SOA). Wiring: `BranchCatalogues`, `QualificaCatalogSeeder`
  (CATALOG_QUOTE_ATTRIBUTES, 482 righe), `QualificaQuoteLayoutSeeder::OWN_FORMS`, `CatalogRootRules`,
  `QualificaBusinessFunctionLinkSeeder`.
- `QualificaSoaWorkOrderSeeder` RINOMINATO `QualificaBranchWorkOrderSeeder` (mappa `BRANCHES` SOA + ISO, stessa logica).
  `SeedsAttributeLayouts::layoutSection`: lo style accetta `visible_when` (codice) -> scrive la condizione solo se c'e'.
- Test: nuovo `QualificaIsoCatalogueTest` (4). REQUIREMENT CHANGED: `QualificaCatalogSeederTest` (+`ISO - Certificazione`),
  `QualificaQuoteLayoutSeederTest` (+`ISO` in OWN_CATEGORIES), `QualificaContactProcessingSeederTest` (layout Offerta 26,
  Commessa 9), `QualificaBusinessFunctionLinkSeederTest` (+`ISO`). Pest Products 247/247, Seeding 184/184,
  ProductCategories/CustomFields/Report verdi; Pint pulito. Manuale: nessun impatto (dati di catalogo).
- ATTENZIONE test Migration: 7 rossi in locale (`UsersSource*`, `MigrationSourceAvailability`, `MigrationPlanEndpoints`)
  perche' il `.env` locale ha `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` e `phpunit.xml` non lo sovrascrive; i test
  assumono il default `false`. Non legati a questo ramo.
- Seed lanciato sul DB locale (radice ISO id 273, `ISO - Certificazione` id 274). Da fare: staging/prod.

## Spec 0289: migrazione legacy dei task — VERDE, COMMITTATO (qnet-2 e legacy) (2026-10-21)

- Spec `docs/specs/0289-legacy-tasks-migration.xml` (D-1..D-11, AC-001..AC-016). Decisioni utente: tutti i 421.862
  task; satelliti = assegnatari, osservatori, ricorrenze attive (note, documenti, ore timesheet FUORI); creatore con
  catena nel legacy, null -> attore della migrazione con warning; flag privato `open` ignorato (is_private false).
- Legacy (`../qnet`): `Api/V2/TaskMigrationController` + route `GET /api/v2/migration/tasks`
  (`routes/apiV2.php`), helper `app/Support/Migration/LegacyTaskStatus|LegacyTaskPeople|LegacyTaskRecurrence`.
  Riferimenti a record inesistenti nel legacy -> null con warning. Pagina da 100 in ~0,4 s (4.219 pagine).
  `php artisan route:list` nel legacy e' rotto da prima (`Class "App\Http\Controllers\email"`).
- qnet-2: migrazione `2026_10_27_100000_add_old_id_to_tasks_table`; sorgente `tasks` (`TasksSource`) con
  `Support/LegacyTaskLinks` (padre, commessa, fase per nome solo sulle radici, anagrafica/referente via i guard del
  modulo Task), `LegacyTaskClassifier` (stato per system_key/nome, fallback `TaskInitialStatusResolver`; lookup per
  nome con default), `LegacyTaskLookups` (cache per esecuzione), `LegacyTaskRecurrenceWriter` (serie sul capostipite
  con `generated_until` legacy; occorrenze agganciate via `recurrence_source_id`, mai doppione di `end_date`).
  Scrittura diretta del model (NON `TaskService::create`), senza log ne' notifiche. Utente o commessa non migrati ->
  riga fallita. `MigrationOrder` fase 12 `['tasks']`; dipendenze required users + work-orders.
- REQUIREMENT CHANGED: `MigrationRegistryTest` 29 -> 30, `MigrationDependenciesTest` (ultima fase), contatori rollback
  SupplierCommissionDirectionTest 87, QuoteWorkflowMigrationTest 223, PurchaseOrderMigrationTest 72; `TaskSchemaTest`
  colonne + `old_id`. Helper test condiviso `tests/Helpers/TaskMigrationHelpers.php` (require in `tests/Pest.php`).
- Verifica: Pest completo 12046/12054, 7 rossi estranei (4 seeder catalogo Qualifica toccati da un'altra sessione in
  corso, IsoCatalogue; 3 QuoteDocumentPdfTest senza LibreOffice). Pint pulito sui file toccati, ESLint ok,
  `tsc -b --force` 0, Vitest completo 8947/8947. Frontend: etichette/requisiti `tasks` IT/EN, frase task in
  `work-orders`, guida `migrations`.
- Manuale Claude Docs: intro Migrazioni + paragrafo "La sorgente Task" dopo "Modelli di task". DA AGGIORNARE (fuori
  scope): il paragrafo "solo abbinamento" dice ancora che gli utenti non riconosciuti non vengono creati.
- Rischio cutover (fuori scope): finche' il legacy genera occorrenze e lo scheduler qnet-2 gira, la stessa data puo'
  nascere in entrambi.
- Prossimo passo: run reale `/migrations` -> Task dopo Commesse; leggere il report (fallimenti per commesse non migrate).

## Spec 0288: sezioni condizionali nel layout attributi — VERDE, PARZIALMENTE COMMITTATO (2026-10-20)

- Contratto: sezione del blob layout + `visible_when: { attribute_code } | null` (chiave assente nei blob vecchi = null;
  `AttributeLayoutService::normalizeSection` la scrive sempre). Regole D-2 in `AttributeLayoutValidator::assertConditions`
  (booleano, posizionato, fuori dalla sezione, in sezione non condizionale; 422 `attribute_layout`).
  `AttributeLayoutCopier` azzera la condizione se il controllo viene potato.
- Regola unica per lato (D-6): BE `App\RequestManagement\AttributeSectionVisibility` (`hiddenCodes`, `mergeAndClear`),
  FE `features/attributes/attribute-section-visibility.ts` (`hiddenAttributeCodes`, `hiddenSectionIds`, `isSectionVisible`).
  `AttributeValueValidator::validate(..., $hiddenCodes)` rilassa gli obbligatori; i writer svuotano a null i codici nascosti
  prima del confronto `$merged === $current`. Layout per superficie (D-5): store Create; update Prodotto/Offerta/Commessa
  View; update Richiesta Edit. La migrazione legacy passa layout null (nessuna condizione).
- FE: renderer (useWatch) e vista dettaglio nascondono le sezioni; i 5 schemi form saltano gli obbligatori nascosti
  (parametro `layout` in coda); configuratore con Select "Visibile solo se attivo" (`attribute-layout-section-condition.tsx`,
  `layout-configurator-conditions.ts`); anteprima interattiva con "Visibile se: ...".
- Seeder: `SeedsAttributeLayouts::composesAs/inheritedRendersSame` trattano `visible_when` assente come null
  (senza, i seeder Qualifica non riconoscevano piu' i propri layout). REQUIREMENT CHANGED: fixture
  `duplicateLayoutBlob` in `ProductCategoryDuplicateTest` con `visible_when => null`.
- Test: `AttributeLayoutConditionalSectionTest`, `ConditionalSectionValuesTest`, `AttributeSectionVisibilityTest`, Vitest
  dei 5 schemi + configuratore + renderer. Suite BE 12018/12030: rossi preesistenti Migration x7 (falliscono sul baseline
  912d634e), PDF x3 (LibreOffice assente), `DemoOpportunitySeederTest` flaky (verde da solo). Vitest completo verde.
- Commit: `1a513c78` e `1b6906b6` (fatti fuori dal flusso) contengono parte del lavoro; il resto e' nel working tree.
- Guide in-app IT/EN aggiornate (`product-categories` parti layout, `products`, `quotes`, `work-orders`). Manuale Claude
  Docs NON aggiornato (documento non accessibile da questa sessione): sezioni "Categorie Prodotto > Layout attributi",
  "Prodotti > Sezione Attributi", "Offerte > Informazioni aggiuntive", "Commesse > Informazioni aggiuntive".
- Da verificare: nessun test distingue le modalita' Create/View/Edit del layout (D-5); lieve differenza BE/FE per un codice
  posizionato in due sezioni (BE nasconde solo se assente da sezioni visibili). Prossimo: commit del resto, su richiesta.

## Catalogo Autofinanziato - Campania: +2 prodotti — VERDE, NON COMMITTATO (2026-10-20)

- `SelfFundedCourseCatalogue::COURSES['Autofinanziato - Campania']`: "Traduzione Asseverata del Titolo" (100, IVA 22%) e
  "Aggiornamento Eipass 7 moduli da user a standard" (150, senza IVA). Ente/SERVICE/unita' di default da `ProductService`.
- REQUIREMENT CHANGED nei conteggi: `QualificaSelfFundedCatalogueTest` (Campania 12, "+ iva" 4),
  `TOTAL_SEEDED_PRODUCTS` 638, `QualificaProductionDataSeederTest` 648. Manuale: nessun impatto (dati di catalogo).

## Catalogo: ramo "Istruzione" — VERDE, NON COMMITTATO (2026-10-20)

- Lista "ISTRUZIONE(Foglio1).csv" (CP1252, decodificato). Come Sicurezza/GDPR ma SENZA campi flessibili (decisione utente):
  `QualificaCatalog\EducationCatalogue` (radice "Istruzione", funzione "Istruzione" dell'organigramma, regole Consulenza,
  tipologia Consulenza) + `EducationProductCatalogue` (18 prodotti, prezzo 0, IVA 22%, Mesi scadenza 0, descrizioni di
  DM88 e CONTO TERMICO 3.0). Linee: "Istruzione - ISTRUZIONE" (il prodotto che il legacy mette sulla radice, come
  "Finanza Agevolata - FINANZA AGEVOLATA"), "Istruzione - PNRR", "Istruzione - Consulenza Scuola", "Istruzione - Conto Termico 3.0".
- Wiring: `QualificaCatalogSeeder` (CATALOG, SELECTABLE_SUBCATEGORIES; 497 righe, a ridosso del limite 500),
  `CatalogProducts`, `CatalogRootRules`, `QualificaBusinessFunctionLinkSeeder`. Nessun layout Offerta.
- Test: nuovo `QualificaEducationCatalogueTest`; REQUIREMENT CHANGED: `TOTAL_SEEDED_PRODUCTS` 656, produzione 666,
  categorie selezionabili, funzioni collegate, esclusione IVA in `QualificaSelfFundedCatalogueTest`. 796/796 verdi, Pint pulito.
- Manuale: nessun impatto (dati di catalogo; le guide citano Istruzione solo per la migrazione legacy).

## Catalogo: ramo "Accreditamenti" + codice di listino — VERDE, NON COMMITTATO (2026-10-20)

- Lista "ACCREDITAMENTI_PARTNERSHIP SEDI.xlsx": 1 prodotto "partnerships-sedi" (codice GOL), legacy sulla radice
  "PARTNERSHIP/SEDI-", gruppo ACCREDITAMENTI. `AccreditationCatalogue` (radice "Accreditamenti", funzione
  "Accreditamenti" dell'organigramma, regole e tipologia Consulenza, NESSUN campo flessibile) con la linea
  "Accreditamenti - PARTNERSHIP/SEDI"; `AccreditationProductCatalogue` (prezzo 0, IVA 22%, Mesi scadenza 0).
- Split (QualificaCatalogSeeder era a 497/500): nuovo `QualificaCatalog\BranchCatalogues` (TREE + TARGET_CATEGORIES dei
  rami con listino, nell'ordine di creazione delle radici); il seeder ne fa lo spread (ora 480 righe).
- Regola codice (decisione utente): `BranchProducts` usa `code` della riga se presente (solo in creazione), altrimenti
  PRD- generato; codice gia' usato da un altro prodotto -> RuntimeException che nomina la riga. Il legacy NON invia
  `code` all'import (`ProductMigrationController`), quindi nessuna collisione con i prodotti importati; nel legacy i
  codici non sono univoci (ISO x359, SIC x123): le liste precedenti restano senza codice.
- Test: nuovo `QualificaAccreditationCatalogueTest` (anche codice gia' preso); REQUIREMENT CHANGED: totale 657,
  produzione 667, selezionabili, funzioni collegate; `QualificaSelfFundedCatalogueTest` usa `BranchCatalogues::TARGET_CATEGORIES`.
  798/798 verdi, Pint pulito. Manuale: nessun impatto.

## Commessa: Responsabile facoltativo (spec 0096 D-12, 0189 G-27) — VERDE, COMMITTATO qnet-2 (2026-10-20; legacy da committare)

- Richiesta utente: responsabile non obbligatorio sulla commessa, anche in migrazione; la migrazione fallisce se un id
  della commessa (responsabile, partecipante) non e' valido; senza responsabili ok. Scelta utente: togliere la G-27.
- Backend: `supervisor_ids` => `sometimes|array` in Store/UpdateWorkOrderRequest, GenerateContractWorkOrderRequest
  (eredita MultiQuoteWorkOrderRequest), GenerateContractWorkOrdersBatchRequest (`groups.*`); `?? []` in
  ContractProgramBatch e MultiQuoteWorkOrderProgrammer; WorkOrdersAuthorization senza mandatory/required. Cella griglia
  Responsabili ora svuotabile (passa da UpdateWorkOrderRequest).
- Migrazione: legacy `WorkOrderMigrationController::userIds()` manda gli id distinti cosi' come sono (cancellati
  compresi), `LegacyWorkOrderPeople` CANCELLATO (era usato solo li'). qnet-2 `WorkOrdersSource::assertUsersMigrated()`
  invariato salvo il messaggio: "{Role} (legacy user id N) not migrated; the work order is not imported."
  Verificato su DB legacy via tinker: 16.351 commesse, 3.839 senza responsabili, 115 con utente cancellato (falliranno).
- Frontend: schemi Zod commessa/Programma senza `min(1)`, etichetta Responsabili nel Programma senza asterisco, chiave
  i18n `workOrders.form.supervisorsRequired` rimossa (IT/EN).
- REQUIREMENT CHANGED nei test: WorkOrderTeamTest AC-020/AC-025/AC-040, WorkOrderMetaTest, WorkOrderGridInlineEditTest,
  ContractWorkOrderGenerationTest AC-010, ContractWorkOrderBatchTest (+ gruppo senza responsabili),
  WorkOrdersSourceCostsAndPaymentsImportTest (messaggio + commessa senza responsabili importata); Vitest
  work-order-schema, contract-program-schema, contract-program-dialog.
- Verifica: Pest intera suite 11.957/11.961 (i 4 rossi sono QuoteDocumentPdfTest: LibreOffice assente in locale), con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`; Vitest help+work-orders+contracts 546/546; tsc -b 0 errori; ESLint e
  Pint puliti; legacy `php -l` pulito.
- Guide IT/EN: work-orders (modifica in griglia), contracts (Programma x2), migrations (commesse), task-templates.

## Spec 0189 G-30 — richieste corso orfane non esportate dal legacy — VERDE, NON COMMITTATO (2026-10-20)

- Report migrazione 20/10: 19.441 offerte scartate = bozze "Richiesta corso di formazione per..." in stato 0, senza
  cliente, con opportunita' cancellata fuori dall'app. Decisione utente: filtrarle alla fonte (deroga a G-26).
- Solo legacy: `../qnet` `QuoteMigrationController::orphanCourseRequestIds()` (whereNotIn sugli id). qnet-2 invariato.
- Verificato su DB legacy via tinker: endpoint offerte 47.805 -> 28.364 (-19.441); "Richiesta corso" con opportunita'
  valida ancora esportate (10.793); nessuna commessa punta alle escluse. `php -l` pulito. Il legacy non ha test.
- Le righe fallite dei run 25-27 restano nello storico di quei run; il prossimo run offerte non le riceve piu'.

## Spec 0285 D-3 — solo abbinamento CREA i non abbinati — VERDE, NON COMMITTATO (2026-10-20)

- Chiarimento utente: "se esiste abbina solo l'id, se non esiste lo crea da import". REQUIREMENT CHANGED su D-3/AC-003,
  D-4 superata. `UsersSource::linkRow()`: non abbinato -> `create()` (estratto dal percorso dell'import completo:
  password legacy, "[OLD] " se attivo, impiego, ruoli), avviso "No qnet user matches {email}: created disabled from the legacy
  record (link-only mode)." D-6: creati SEMPRE disattivati (regola qnet-2, decisione utente: il legacy manda il suo
  `is_active` invariato; "[OLD] " segue lo stato legacy). `afterImport()` in link-only ricollega SOLO gli utenti creati nell'esecuzione
  (`$createdInLinkOnly`); gli abbinati restano col solo `old_id` (AC-004 invariato).
- Motivo: report migrazione 20/10 (run 25-27), 5.905 commesse fallite per utenti non creati (127 contabilita@,
  444 offerte@, 61 ex dipendenti). Dopo questo cambio: rilanciare users -> opportunities -> quotes -> work-orders.
- Test: `UsersSourceAdoptionTest` (AC-003 riscritto + relink created/matched). Pest Migration 437/437 con
  `EXTERNAL_MIGRATION_USERS_LINK_ONLY=false`; Unit/Migrations 20/20; Pint pulito; help vitest 148/148; tsc pulito.
- ATTENZIONE test: il `.env` locale ha `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` e `phpunit.xml` non lo fissa ->
  5 test che presuppongono il default falliscono in locale (gia' prima di questa modifica). Da decidere: pin in
  `phpunit.xml` (file protetto dall'hook config-protection).
- Guida `migrations` IT/EN aggiornata. Manuale Claude Docs: ancora `access` deny -> paragrafo "solo abbinamento"
  della sezione Migrazioni da aggiornare a mano.
- Analisi scarti migrazione (in chat, non implementata): clienti cancellati = doppioni per P.IVA (10/17), 41 offerte
  orfane ricostruibili con lo stesso id opportunita', righe service_id=0 = voci reali (684), servizio 126 -> 934.

## UI HR ALLINEATA (spec 0287) — VERDE, COMMITTATO (2026-10-20)

- Spec `docs/specs/0287-hr-ui-alignment.xml`. Richiesta utente: allineare Assenze, Formazione, Competenze a Gestione
  Richieste/Contabilita', "anche le intestazioni" -> stessi componenti di Contabilita' (modello: Liquidazioni).
- Dettagli (Sheet): `SheetToolbar` + `SheetHeader` sr-only + `RecordCanvas` > `RecordBody` > `RecordCardHeader`
  (monogramma persona, titolo = tipo assenza / corso / competenza, sottotitolo = persona, badge stato/esito/livello,
  azioni nello slot `actions` con `flex flex-wrap justify-end gap-1.5`) + `RecordSectionsGrid`/`RecordSection`.
  Ogni valore compare una volta (i test cercano testi unici). Errori 404 invariati (stessi messaggi, non `DetailError`).
- Form (Sheet): `RecordCreateLayout` come le rettifiche provvigioni; campi aperti in `RecordSection`.
  `RecordCreateLayout` ha il nuovo slot `extraActions` (prima di Salva, in alto e in fondo): usato da Assenze per
  "Salva bozza"; il submit del form = "Invia".
- Pagine: il selettore di vista (`FilterTabs`) e' nelle azioni di `PageHeader` dentro
  `flex flex-wrap items-center justify-end gap-2`; `gap-4` tra intestazione, tab e contenuto.
- i18n: `*.detail.sections`, `*.form.sections` in it/en-absences, -staff-trainings, -user-skills.
- REQUIREMENT CHANGED nei test: pulsanti Salva/Invia/Salva bozza duplicati (alto + fondo) -> `getAllByRole(...)[0]`;
  titolo del form -> `findByRole('dialog', { name })`. Nuovi test su intestazione e sezioni.
- Guide IT/EN aggiornate (absences: nuova sezione `detail`; staff-trainings, user-skills, general). Manuale Claude Docs:
  NON accessibile da questo account (`access` deny) -> da aggiornare a mano (vedi riepilogo in chat).
- Verifica: Vitest intera suite 8908/8908, `tsc -b --force` 0 errori, ESLint pulito. Non verificato a vista nel browser.

## BREADCRUMB: HOME SENZA, /management-control RAGGIUNGIBILE — VERDE, COMMITTATO (2026-10-20)

- Richiesta utente: breadcrumb di `/management-control/income-statement` gestite bene, in home tolte.
- `/management-control` non era una rotta: la crumb "Controllo di gestione" portava al nulla. Aggiunta la rotta
  `management-control` -> `Navigate` a `/management-control/income-statement` (stesso schema di `/dev`).
- Home: `DashboardScreen` non monta piu' `PageHeader` (era solo la breadcrumb). Guida `general` IT/EN: "tranne la home".
- REQUIREMENT CHANGED: `management-control-routes.test.tsx` elenca anche `management-control`; nuovo test "nessuna
  breadcrumb in home" in `dashboard-screen.test.tsx`.

## ASSENZE: TRADUZIONI GRIGLIA RICHIESTE — VERDE, COMMITTATO (2026-10-20)

- Bug: in `/absences` le intestazioni della griglia richieste mostravano le chiavi grezze (`absenceRequests.columns.*`
  non esisteva nei locale) e la colonna Stato mostrava "—" (colonna `badge` senza `badges`) con valori grezzi nel filtro.
- Backend: `AbstractAbsenceRequestsTableDefinition` label prefix `absences.requests` (-> `absences.requests.columns.<id>`),
  `badgesFor('status')` da `AbsenceRequestStatus::options()`, `enumKeyFor('status')` = `absence_request_status`;
  `AbsenceRequestStatus` ha `#[Color]` (draft slate, submitted amber, approved green, rejected red, cancelled gray).
- Frontend: `absences.requests.columns` in en/it-absences.ts; `enums.absence_request_status` = `absences.status`
  (stessa fonte, import in en/it-enums.ts). Test `features/absences/absences-i18n.test.ts`.
- Verifica: Pest `AbsenceRequests`/`Absences`/`AbsenceCalendar`/`AbsenceBalances` 92/92 (incluso il nuovo test colonne);
  Vitest absences-i18n + i18n 237/237, `src/features/absences` 52/52 (dopo l'adeguamento dei test della spec 0287);
  `tsc -b --force` 0 errori; Pint/ESLint puliti.
- Manuale: nessun impatto (le guide non descrivono le colonne della griglia).

## UI: TAB DI HR/CONTABILITA' ALLINEATI A GESTIONE RICHIESTE — VERDE, COMMITTATO (2026-10-20)

- Richiesta utente: le strisce di tab di HR/Contabilita' avevano un altro stile; il riferimento e' Gestione Richieste
  (vasca `bg-surface` + `border-border/60`, tab attivo chip `bg-card` + `text-primary` + ring), NON il contrario
  (un primo tentativo nel verso opposto e' stato annullato).
- `components/ui/filter-tabs.tsx` ora riusa `FORM_TAB_LIST_CLASS` / `FORM_TAB_TRIGGER_CLASS` di
  `components/form-tab-strip.tsx` (le stesse classi della striscia categorie di Gestione Richieste): i bottoni
  restano `role="tab"` + `aria-selected` + `onClick`, e impostano `data-state` come un Radix `TabsTrigger`.
  Vasca a tutta larghezza, `flex-wrap`. Chi usa `FilterTabs` NON aggiunge un riquadro proprio (surface su surface).
- Copie inline sostituite con `FilterTabs`: rate attive/passive (`*-quick-filter-toolbar.tsx`), maturato e
  liquidazioni provvigioni, stati ordini d'acquisto (`status-tabs.tsx`), stati righe RDA (`line-status-tabs.tsx`),
  tipo fattura (`invoice-list-toolbar.tsx`: tab fuori dal riquadro, che resta solo per la striscia mesi).
  Tolto il riquadro passato da `registries-table.tsx`. Cambiano di conseguenza anche Assenze, Formazione,
  Competenze, Anagrafiche (gia' su `FilterTabs`).
- NON toccati: `SegmentedControl` (Partitario, Controllo di gestione, saldi Assenze: e' un controllo radio, non tab),
  griglia mesi delle fatture, filtro eventi del registro attivita'.
- Verifica: `tsc -b --force` 0 errori; Vitest su components + 12 feature toccate 1686/1686 verdi; ESLint pulito.
  Non verificato a vista nel browser. Manuale: nessun impatto (le guide non descrivono l'aspetto dei tab).

## CATALOGO: MACROCATEGORIA FINANZA AGEVOLATA — VERDE, COMMITTATO (2026-10-20)

- Fonte: "Gestione richieste finanza Agevolata.docx" + "FINANZA AGEVOLATAù.xlsx" (47 prodotti, nessuna riga gialla).
  Stesso schema di GDPR: `QualificaCatalog/SubsidisedFinanceCatalogue` + `SubsidisedFinanceProductCatalogue` (generato).
- Radice "Finanza Agevolata" -> funzione "Finanza Agevolata" (organigramma). 13 sottocategorie "Finanza Agevolata -
  <linea>" (linee del docx, deduplicate, nomi verbatim: AUT C.NORD, Decreto Turismo, FINANZA AGEVOLATA, FNC, FONDI,
  FORMAZIONE CONTINUA, FRC, FUNT, INCENTIVI ASSUNZIONI, Industria 4.0, IPERAMMORTAMENTO, ISI INAIL, RESTO AL SUD).
  Regole Consulenza, `consultancy`, IVA 22% (anche "Supporto alla presentazione bando", senza aliquota nell'xlsx),
  mesi 0, descrizione solo su "Consulenza ISI Inail - IV fase".
- SEGUITO COMMITTATO (richiesta utente 2026-10-20): "Mesi scadenza" FNC Fase II = 12, Fase III = 24 (l'xlsx ha
  0). `BranchProducts` scrive i mesi solo se vuoti: su un DB gia' seminato con 0 il re-run NON li cambia (correggere
  a mano dal prodotto o ri-seminare da zero).
- Campi Offerta sulla radice, form "Finanza Agevolata": `subsidised_finance_requested_measure` (text, "campo libero"),
  `subsidised_finance_checklist` (boolean, "Check-list da allegare"), `subsidised_finance_notes` (textarea).
- Test: nuovo `Products/QualificaSubsidisedFinanceCatalogueTest` (3); REQUIREMENT CHANGED: prodotti 589->636,
  produzione 599->646, layout offerta 24->25, +13 selezionabili, funzione nei link. Suite completa (flag link-only a
  false) 11930 verdi, 1 rosso estraneo (PDF). Pint pulito.
- `QualificaCatalogSeeder.php` ora a 493 righe: lo split e' OBBLIGATORIO prima di un altro ramo.
- Manuale: nessun impatto (dati di seed).

## CATALOGO: MACROCATEGORIA GDPR — VERDE, COMMITTATO (2026-10-20)

- Fonte: "gestione richieste gdpr.docx" + "SERVIZI GDPR.xlsx". Richiesta utente: "fai la stessa cosa" di Gare. Qui il
  giallo e' nell'xlsx: riga prodotto "GDPR_" (categoria PROGETTI-) esclusa; le due note gialle ("CATEGORIA MADRE GDPR",
  "SOTTOCATEGORIA GDPR E DPO") = struttura, non prodotti.
- `QualificaCatalog/GdprCatalogue` + `GdprProductCatalogue` (generato dall'xlsx, 16 prodotti: 7 GDPR, 9 DPO). Radice
  "GDPR" -> funzione "Privacy" dell'organigramma (non esiste una funzione "GDPR"); sottocategorie "GDPR - GDPR" e
  "GDPR - Nomina DPO" (nomi del docx; l'xlsx chiama la seconda "DPO"). Regole Consulenza, `consultancy`, IVA 22%,
  mesi 0/12/24 dall'xlsx, nessun set Commessa.
- Campi Offerta sulla RADICE (ereditati da entrambe, form "GDPR" in `OWN_FORMS`): `gdpr_employee_count` (integer),
  `gdpr_business_activity` (textarea), `gdpr_video_surveillance` (boolean), `gdpr_video_surveillance_sites` (text,
  "campo libero"), `gdpr_checklist` (boolean, "spunta"), `gdpr_notes` (textarea).
- `BranchProducts`: nuova chiave opzionale `description` (DPO 1-3 anno ce l'hanno): scritta alla creazione, riempita
  al re-run solo se vuota.
- Test: nuovo `Products/QualificaGdprCatalogueTest` (4); REQUIREMENT CHANGED: prodotti 573->589, produzione 583->599,
  layout offerta 23->24, +2 selezionabili, "GDPR" nei link funzione. Suite completa (flag link-only a false) 11927
  verdi, 1 rosso estraneo (PDF senza LibreOffice). Pint pulito.
- Attenzione: `QualificaCatalogSeeder.php` a 488 righe (hard limit 500): il prossimo ramo richiede uno split
  (es. estrarre CATALOG/SELECTABLE/QUOTE_ATTRIBUTES in una classe `QualificaCatalog/*`).
- Manuale: nessun impatto (dati di seed).

## CATALOGO: MACROCATEGORIA GARE — VERDE, COMMITTATO (2026-10-20)

- Fonte: "GESTIONE RICHIESTE SETTORE GARE.docx" (linee di prodotto + documenti per linea) + "SERVIZI GARE.xlsx"
  (33 prodotti). Richiesta utente: nuova categoria nel seed di produzione, campi flessibili e prodotti; "quelli
  segnati in giallo non devi crearli" = le voci del docx evidenziate in giallo (sezioni di testo libero: scadenza,
  categorie merceologiche, N° dipendenti, CCNL, fatturato, termine e urgenza, email, telefono...) NON create.
  Nessuna riga dell'xlsx e' gialla: tutti i 33 prodotti seminati.
- Come "Sicurezza": radice "Gare" (funzione "Gare" di `BusinessFunctionCatalogue`), regole Consulenza, stati di
  default, tipologia `consultancy`, IVA 22%, "Mesi scadenza" 0, nessun fornitore, nessun set Commessa. 12
  sottocategorie selezionabili "Gare - <linea>" (prefisso come Sicurezza; nomi del docx: Mepa, WHITE LIST, ...;
  "Gare - Gare" per la linea "Gare"). Il menu a tendina ordina gia' per nome.
- Campi Offerta = i documenti "(DA ALLEGARE)", boolean `tender_*` (12 codici), assegnati SULLE SOTTOCATEGORIE che
  li chiedono: Avvalimenti/Gare/Progetto Tecnico (7 documenti di gara), Mepa/Albo Fornitori/ANAC (6 di iscrizione),
  WHITE LIST (3), Giornale Telematico (visura), Corso preparazione gare (visura/documento d'identita'). RTI/ATI,
  Consorzio Grade, Albo Gestori Ambientali: nessun campo. "Visura" e "Visura camerale aggiornata" = un solo codice.
  Form: una sezione "Documenti da allegare" per categoria (`QualificaQuoteLayoutSeeder::OWN_FORMS`).
- File: nuovi `QualificaCatalog/TenderCatalogue.php`, `TenderProductCatalogue.php` (generato dall'xlsx); agganci in
  `QualificaCatalogSeeder`, `CatalogProducts` step 7, `CatalogRootRules`, `QualificaBusinessFunctionLinkSeeder`,
  `QualificaQuoteLayoutSeeder`.
- Test: nuovo `Products/QualificaTenderCatalogueTest` (3); REQUIREMENT CHANGED: prodotti catalogo 540->573, produzione
  550->583, layout offerta 14->23, +12 selezionabili, "Gare" nei link funzione. Products 232/232, Seeding 180/180,
  Pint pulito. Suite completa: 14 rossi estranei (Migration utenti per `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` nel
  `.env` locale -> verdi 435/435 col flag a false; PDF senza LibreOffice; `CampaignCrudTest` flaky, verde da solo).
- Da fare: rieseguire `QualificaProductionDataSeeder` (o `QualificaCatalogSeeder`) su locale/staging/prod.
  Manuale: nessun impatto (dati di seed).

## Spec 0285 — Migrazione utenti in modalita' "solo abbinamento" — VERDE, NON COMMITTATO (2026-10-19)

- Spec `docs/specs/0285-users-migration-link-only.xml` (D-1..D-4, AC-001..AC-007).
- `config('migrations.users_link_only')` da env `EXTERNAL_MIGRATION_USERS_LINK_ONLY` (default false = import completo
  invariato). Stessa sorgente `users` (dipendenze, MigrationOrder, import massivo invariati).
- `UsersSource`: con il flag, `linkRow()` = old_id gia' presente -> skipped; `ExistingUserMatcher` abbina -> solo
  `old_id` (created, avviso "(link-only mode)"); nessun abbinamento -> skipped con avviso, nessun account; conflitti
  del matcher -> riga fallita. `afterImport()` senza effetto. Estratto `identity()` (email/nome/cognome obbligatori).
  File a 366 righe (soft limit superato gia' prima, 311).
- Test: 5 nuovi in `UsersSourceAdoptionTest`; Pest Migration 428/428 + Unit/Migrations 20/20, Pint pulito.
- Guida in-app `migrations` IT/EN (nota nuova), help vitest 146/146; manuale Claude Docs: paragrafo dopo la sorgente
  Utenti in Migrazioni.
- Da fare sull'installazione Qualifica: `EXTERNAL_MIGRATION_USERS_LINK_ONLY=true` nel `.env` del server.
- Estensione D-5 (AC-008..010): con il flag la sorgente `business-function-members` non e' abilitata.
  `MigrationSource::isAvailable()` (default true in `AbstractMigrationSource`, false in
  `BusinessFunctionMembersSource` col flag); `MigrationService::start` -> 422 senza run; `MigrationPlanService`
  (ora riceve `MigrationRegistry`) legge `enabled` = salvato AND disponibile, il valore salvato si conserva.
  Contratto: campo `available` in `GET /migrations` e in `plan.sources[]`. Frontend: `isImportAvailable` su
  `MigrationTemplatePanel` (pulsante disabilitato + nota), casella del piano disabilitata + "Non disponibile".
  Test: `MigrationSourceAvailabilityTest` (7), Pest Migration 435/435, Unit/Migrations 20/20, Vitest migrations
  32/32, help 146/146, `tsc -b --force` pulito, ESLint/Pint puliti. Guida `migrations` IT/EN e manuale aggiornati.

## Organigramma — intestazione allineata alle altre pagine — VERDE, COMMITTATO 84ace3ed (2026-10-19)

- `pages/org-chart-page.tsx`: `<PageHeader />` col solo breadcrumb come Assenze/Funzioni/Mansioni (prima titolo +
  sottotitolo sotto il breadcrumb). Tolte le chiavi orfane `orgChart.title`/`orgChart.subtitle` (IT+EN).
  Scheda `orgChart.tabs.functions` rinominata "Funzioni aziendali" / "Business functions" (e' anche l'aria-label
  dell'albero: test org-chart aggiornati, REQUIREMENT CHANGED). Guide in-app IT+EN e manuale Claude Docs aggiornati.
  Vitest org-chart/help/i18n 428/428, `tsc -b --force` e ESLint puliti.

## Traduzioni moduli HR 0284/0286 — VERDE, NON COMMITTATO (2026-10-20)

- Audit chiavi i18n (codice FE + label inviate dal backend per colonne/filtri/azioni) di Corsi, Formazione, Catalogo
  competenze, Competenze: IT/EN allineati, tre difetti corretti.
- `staff-course-screens.tsx`: `courses.detail.loadError` -> `staffCourses.detail.loadError`, `labelKey`
  `navigation.courses` -> `navigation.staffCourses` (entrambe inesistenti: si vedeva la chiave grezza).
- Griglia Formazione, colonna Esito: senza `badges` ne' renderer la cella era vuota e il filtro mostrava
  `passed`/`failed`. Ora `StaffTrainingsTableDefinition::enumKeyFor('outcome')` = `staff_training_outcome` (filtro
  tradotto) e renderer `outcome` = `StaffTrainingOutcomeBadge` nel Registro. Etichette spostate da
  `staffTrainings.outcome.*` a `enums.staff_training_outcome.*` (unica fonte per griglia, filtro, dettaglio, form).
- Test nuovi: `staff-course-screens.test.tsx`, `staff-training-register.test.tsx`, Pest "outcome column carries its
  enum key". Aggiornato solo il percorso chiave in `staff-training-detail-sheet.test.tsx` (stessa etichetta).
- Verifica: vitest 87 file/688 test (moduli HR, i18n, help, data-table), Pest StaffTrainings 33/33, Pint, ESLint,
  `tsc -b --force` puliti. Manuale: nessun impatto (le guide descrivevano gia' l'Esito).
- Segnalato, NON corretto (trasversale): i messaggi 422 del server mostrano l'attributo grezzo ("Il campo name e' gia'
  stato utilizzato") perche' `lang/it/validation.php` non ha `attributes`; vale per tutti i moduli.

## Spec 0283 D-7 — Ruolo "Risorse Umane" esteso a Formazione e Competenze — VERDE, COMMITTATO f5353666 (2026-10-20)

- Decisione utente: l'ufficio del personale (Castaldo, Iorio, Sorrentino) apre anche i moduli HR nati dopo 0283.
  `HrOfficeRoleCatalogue::MODULES` + `staff-courses`, `staff-trainings`, `skills`, `user-skills` (24 permessi, tutti).
  Spec 0283 D-7 + AC-011; test `0283 AC-011` in `QualificaHrOfficeSeederTest`. Pest Seeding 180/180, Pint pulito.
- Guida in-app users IT/EN (elenco moduli del ruolo), help vitest 148/148. Manuale Claude Docs NON aggiornato: il doc
  non e' condiviso con l'account di questa sessione (sezione Utenti, blocco "Chi vede i dati contrattuali").
- DB locale: permessi dati al ruolo via `givePermissionTo` (additivo, nessun re-sync degli altri ruoli). In produzione:
  `php artisan db:seed --class=QualificaRoleSeeder` (risincronizza tutti i ruoli Qualifica).

## Spec 0283 — Ruolo "Risorse Umane" + dati contrattuali dietro la matrice dei campi — VERDE, COMMITTATO ee81ddac (2026-10-19)

- Spec `docs/specs/0283-hr-office-role-and-sensitive-user-fields.xml` (D-1..D-6, AC-001..AC-010).
- Seed: `QualificaCatalog/HrOfficeRoleCatalogue` (ruolo `risorse-umane`, moduli HR completi con delete, NO
  `users.impersonate`; extra `time-entries.viewAll|exportMonthly`, `companies.viewAny|view`; `SENSITIVE_FIELDS` D-2;
  `roles` in sola lettura). `QualificaRoleSeeder`: righe NASCOSTE su D-2 a ogni mansione, righe esplicite
  visibile+modificabile a HR e `admin` (l'unione conta solo i ruoli CON riga: senza grant esplicito vincerebbe il
  nascosto di `operatore-base`). Nuovo `QualificaHrOfficeSeeder` (persone della funzione
  `BusinessFunctionCatalogue::HUMAN_RESOURCES` di `OrgChartFunctions`, ruolo AGGIUNTO), step 7b del production seeder.
  Lanciato sul DB locale: Castaldo/Iorio/Sorrentino = operatore-base + risorse-umane.
- Backend: nuova chiave `employment.contracts` (`UsersAuthorization::CONTRACTS_FIELD`); `EmploymentResource` toglie le
  chiavi dei campi nascosti; storico contratti 403 se nascosto (GET) / non modificabile (POST/DELETE); griglia utenti
  `Users/UserSensitiveColumns` (colonne, filtri, righe, export). D-5: `EmploymentData::$contractData` = hired_at,
  terminated_at, durate con "assente = invariato" (prima assente = svuotato).
- Assenze (D-6, decisione utente): nessun permesso "team"; resta proprie + sottoposti automatici + `manageAll`.
  Il calendario 0279 (calendarOwn/Team/All) e' di un'altra sessione: le mansioni non ricevono quei permessi.
- Frontend: sezioni Rapporto/Storico per visibilita' di campo, azioni storico su `employment.contracts` editable,
  payload con date/durate solo se cambiate; tipi `EmploymentDetail` con chiavi contrattuali opzionali.
- Test: nuovi `UserSensitiveFieldsTest` (5), `QualificaHrOfficeSeederTest` (3), FE AC-009 (2). REQUIREMENT CHANGED:
  conteggi catalogo campi users 17->18 (FieldCatalogue/Meta/UserCompetenceScope), payload FE senza date invariate,
  gate storico su campo. Users 255, Authorization 109, Seeding 179, RequestManagement 851, Tables/Table/Migration
  verdi; vitest users+roles+help verdi; Pint, ESLint, `tsc -b --force` puliti.
- Manuale: guida in-app users IT/EN (nuova sezione `contract-visibility`, storico, pagamento) + Claude Docs (blocco
  "Chi vede i dati contrattuali" sotto la tabella Profilo/Rapporto). Il manuale Claude Docs ha ancora la riga
  "Qualifica" e niente Storico/Pagamento (debito di spec 0272, non toccato).
- Rischio residuo: un ruolo creato a mano SENZA righe vede tutto (semantica 0006): va ristretto dal form Ruoli.

## Spec 0282 — Proforma attiva / passiva nel dettaglio commessa — VERDE, COMMITTATO (2026-10-19)

- Spec `docs/specs/0282-work-order-invoices-view.xml` (D-1..D-9, AC-001..AC-011). Sola consultazione, nessuna azione contabile.
- Endpoint `GET /api/work-orders/{workOrder}/invoices` -> `{active[], passive[], totals:{active, passive}}`
  (`WorkOrderInvoiceController` + `Services/WorkOrders/WorkOrderInvoiceOverviewBuilder`, rotte `routes/api/work-order-invoices.php`).
  Attivi = `invoices.work_order_id`; passivi = documenti con almeno una RIGA sulla commessa (`purchase_invoice_lines.work_order_id`),
  `work_order_amount` = somma delle sole righe della commessa. Proforma e fatture insieme (colonna Tipo).
- Permesso nuovo `work-orders.viewInvoices` (policy `viewInvoices` = permesso + `isInScope`, come `viewCosts`), azione
  `view_invoices`; da assegnare ai ruoli dalla UI (super-admin passa da `Gate::before`). `WorkOrderSecurityTest`: 16 -> 17 permessi (REQUIREMENT CHANGED).
- Frontend `features/work-order-invoices/` (una sola query per le due schede), schede "Proforma attiva"/"Proforma passiva" in
  `work-order-detail-work-tabs.tsx`; il numero apre `InvoiceDetailSheet`/`PurchaseInvoiceDetailSheet` solo con `invoices.view`/
  `purchase-invoices.view`. `PurchaseInvoiceTypeBadge` estratto in `purchase-invoice-installments/purchase-installment-cells.tsx`.
- Verifica: Pest completo 11814/11818 (3 fail `QuoteDocumentPdfTest` = LibreOffice assente, non correlati), Pint ok; Vitest
  completo 8760/8760, `tsc -b --force` 0, ESLint ok.
- Manuale: guida in-app work-orders IT/EN sezione `invoices`; Claude Docs: nessuna modifica (sezione Commesse ancora "in fase di sviluppo").
- Da verificare a occhio: layout a 375px delle due tabelle (scroll orizzontale interno, `min-w-[720px]`).

## Piano HR ridotto — COMMITTATO dcb1e55b (2026-10-19)

- Decisione utente (D-10 in `docs/product/hr.md`): del piano HR restano solo calendario della squadra (0279),
  corsi e formazione, competenze. FUORI SCOPE, non riproporre: allocazioni e costo del personale, progetti interni,
  documenti con firma, obiettivi/KPI, onboarding, reparti.
- Competenze = cio' che la persona sa fare (catalogo + persona <-> competenza con livello + mansione <-> competenza
  richiesta), NON le "competenze commerciali" (appartenenza alle funzioni), che restano invariate.
- Ordine: 0279 -> corsi e formazione -> competenze. Campi *proposta* da confermare prima delle spec.

## Spec 0286 — Competenze + rinomina "Ambiti di assegnazione" — VERDE, COMMITTATO 2df5b0de (2026-10-19)

- Spec `docs/specs/0286-hr-skills.xml` approvata (D-1..D-7, AC-001..AC-007). Chiude il perimetro HR ridotto
  (`docs/product/hr.md` aggiornato: tutto sviluppato).
- D-1 rinomina SOLO UI: la "Competenza" della Configurazione assegnazione (`employment.product_lines`) ora e'
  "Ambito/Ambiti di assegnazione" (EN "Assignment scope(s)"), "Ambito su tutte le categorie"; colonna funzioni
  `competences_count` = "Persone con ambito di assegnazione". Chiavi, API e logica invariate. "operatore competente"
  (aggettivo) lasciato. Guide users/general/business-functions/product-categories/request-management/org-chart.
- Modulo: tabelle `skills`, `job_role_skill` (`min_level`), `user_skills` (unica persona+competenza); enum
  `SkillLevel` 1..4; `Services/Skills/{UserSkillScope,UserSkillVisibility,UserSkillService,UserSkillCoverageService}`.
  Permessi `skills.*`, `user-skills.viewAny|viewTeam|viewAll|manage`: `viewTeam` scrive sui discendenti attivi,
  nessuno su se stesso senza `manage`; fuori visibilita' 404, visibile non scrivibile 403; `can_edit` in risposta.
  Copertura: livello richiesto = max tra le mansioni, `covered|below|missing`, solo competenze attive.
- Frontend `features/skills`, `features/user-skills` (Persone/Copertura), livello con `SkillLevelBadge`
  (`skill-level.ts`), guide `skills`/`user-skills` (HELP_GUIDE_KEYS 95), icone `sparkles`/`list-checks`.
- Test cambiati (REQUIREMENT CHANGED): `FieldCatalogueEndpointTest` (+2 risorse), rollback `--step` +3 (222/71/86),
  `help-guide-keys.test.ts` 93->95, 4 test users + `assign-operators-dialog.test.tsx` per la rinomina.
- Verifica (lead): backend suite completa 11896/11922, rossi solo fuori scope (migrazione utenti 0285 di un'altra
  sessione in corso, `QuoteDocumentPdfTest` LibreOffice); Pint pulito. Vitest completo: 28 rossi da carico macchina
  (worker timeout), rilanciati isolati tutti verdi; `tsc -b --force` 0; ESLint pulito. Non verificato: 375 px.
- Manuale Claude Docs: rinomina applicata (17 punti), sezioni "Catalogo competenze" (prima di Corsi) e
  "Competenze" (dopo Formazione), voce nel menu laterale. Dopo il deploy: `php artisan permissions:sync`.

## Spec 0284 — Corsi e formazione del personale — VERDE, COMMITTATO 29fb83b6 (2026-10-19)

- Spec `docs/specs/0284-hr-staff-training.xml` approvata (D-1..D-6, AC-001..AC-008). Decisioni utente: registro +
  scadenze (niente pianificazione), obbligo per mansione, registra solo l'ufficio del personale, visibilita' a tre
  livelli. Legacy senza dati: nessun importer. Nomi `staff_courses` / `staff_trainings` (NON i corsi venduti).
- Backend: tabelle `staff_courses`, `job_role_staff_course`, `staff_trainings`; `Services/StaffTrainings/
  {StaffTrainingScope,StaffTrainingVisibility,StaffTrainingService,StaffTrainingComplianceService}`; allegato annidato
  `/api/staff-trainings/{id}/attachment` (morph map, NON in `attachable_types`); `config/staff_training.php`
  (`expiring_days` 60, env `STAFF_TRAINING_EXPIRING_DAYS`, non aggiunta a `.env.example`: file con modifiche altrui).
  Permessi `staff-courses.*`, `staff-trainings.viewAny|viewTeam|viewAll|manage`: `manage` implica la vista di tutti
  (altrimenti 404 sul record appena creato); il menu "Formazione" vuole `viewAny` (chi ha solo viewAll apre le API ma
  non vede la voce: assegnare viewAny insieme). Griglia corsi: colonna `duration_hours` (ore decimali, sort su
  minuti, nessun filtro). Eliminazione corso usato = 422.
- Frontend: `features/staff-courses`, `features/staff-trainings` (schede Registro/Situazione, dettaglio con
  attestato, form), i18n `{it,en}-staff-{courses,trainings}.ts` (in `*-hr.ts`), icone `graduation-cap`/`library`,
  guide `staff-trainings`/`staff-courses` (HELP_GUIDE_KEYS 93).
- Test cambiati (REQUIREMENT CHANGED): `FieldCatalogueEndpointTest` (2 risorse), rollback `--step` +2 in
  `QuoteWorkflowMigrationTest`, `PurchaseOrderMigrationTest`, `SupplierCommissionDirectionTest`;
  `help-guide-keys.test.ts` 91->93; `router.test.tsx` e `icon-map.test.ts` estesi.
- Verifica (lead): backend suite completa 11882/11886 (3 `QuoteDocumentPdfTest`: LibreOffice assente, 1 skipped),
  Pint pulito; Vitest 69 file/517 test (staff-*, help, routes, navigation, absences, i18n), `tsc -b --force` 0,
  ESLint pulito. Non verificato a vista: 375 px.
- Manuale: guide in-app nuove IT/EN + Claude Docs (sezione "Corsi" prima di "Tipi di assenza", sezione "Formazione"
  dopo Assenze, voce Formazione nel menu laterale). Dopo il deploy: `php artisan permissions:sync`.

## Spec 0279 — Calendario delle assenze — VERDE, COMMITTATO dcb1e55b (2026-10-19)

- Spec approvata dall'utente. Permessi (D-1 [revised in build]): `absence-requests.calendarOwn|calendarTeam|
  calendarAll` sulla risorsa del modulo (il form Ruolo raggruppa per voce di menu: una risorsa `absence-calendar`
  sarebbe finita in "Condivisi"). Vale il piu' ampio; nessuno = 403. Dopo il deploy `php artisan permissions:sync`;
  i ruoli NON ricevono i permessi dal seed (fuori scope; il ruolo HR di 0283, altra sessione, prende tutto
  `absence-requests.*`).
- Backend: `GET /api/absences/calendar` (`routes/api/absence-calendar.php` da `hr.php`), `AbsenceCalendarRequest`
  (max 62 giorni, `MAX_DAYS`), `Services/Absences/Calendar/{AbsenceCalendarScope,AbsenceCalendarBuilder}`,
  `AbsenceRequestVisibility::viewChecker()` (canView in blocco, per `can_open`). Solo approved+submitted, spezzate
  per giorno lavorativo; persone attive; query costanti.
- Frontend: `features/absences/calendar/*` (griglia con colonna nomi fissa e scroll proprio, cella button solo con
  `can_open`), schede "Richieste/Calendario" in `absences-page.tsx` (solo con un permesso calendario), il calendario
  si invalida con `absenceKeys.calendarAll` dopo ogni azione su una richiesta. i18n `absences.calendar.*`,
  `permissions.abilities.calendar*`.
- Test: `tests/Feature/AbsenceCalendar` 15/15; aree assenze 91/91 (`AbsenceFoundationTest` aggiornato:
  REQUIREMENT CHANGED, 3 permessi in piu'); Pint pulito. Vitest absences+help+roles 272/272, `tsc -b --force` 0,
  ESLint pulito. `FieldCatalogueEndpointTest` rosso per `employment.contracts` (lavoro di un'altra sessione su
  `UsersAuthorization`, non 0279). Non verificato a vista: 375 px nel browser.
- Manuale: guida in-app `absences` IT/EN (sezione `in-development` sostituita da `calendar`) + Claude Docs (Assenze:
  frase introduttiva e paragrafo Calendario con la tabella dei permessi).
  Solo documentazione: nessun codice, manuale: nessun impatto.

## Mansioni: link dalla scheda utente + FIX griglie Mansioni/Attivita' "[object Object]" — VERDE, NON COMMITTATO (2026-10-19)

- Decisione utente: in "Profilo > Mansioni" della scheda utente ogni mansione e' un `RecordLink` (clic = modale della
  mansione con le sue attivita'); testo semplice senza `job-roles.view`. `RefBadges` ha un `domain?` opzionale
  (usato solo per `job_roles`; ruoli e "Risponde a" invariati). Nessun cambio backend/contratto per questo.
- Bug griglie: le colonne `tags` (`TagsCountCell`) vogliono `string[]`; `JobRolesTableDefinition::mapRow`
  (`job_activities`) e `JobActivitiesTableDefinition::mapRow` (`job_roles`) mandavano `{id, label}` -> tooltip
  "[object Object]". Ora `pluck('name')`. I due test di griglia avevano fissato la forma sbagliata: corretti a `[name]`
  (prima rossi, poi verdi).
- Test: `user-detail.test.tsx` +2 (link con permesso / testo senza), mock `useAbilities` reso configurabile;
  JobRoles+JobActivities+HrStructure+Tables 83/83, Pint pulito; vitest users+help 321/321, `tsc -b --force` 0, ESLint ok.
- Manuale: guida in-app users IT/EN (riga Mansioni) + Claude Docs (riga "Mansione" della tabella campi utente, era
  ancora il vecchio testo libero da 255 caratteri).

## Direzione Generale (Enrico Ferrante) sopra tutti i responsabili — VERDE, NON COMMITTATO (2026-10-19)

- Decisione utente: nuova funzione "Direzione Generale" (BS), responsabile e unico membro Enrico Ferrante
  (`OrgChartFunctions::GENERAL_MANAGER`, fuori da `OrgChartRoster`: nessuna mansione/livello); i responsabili di
  livello I di tutte le altre funzioni ricevono Enrico in "Risponde a" (additivo, come il resto dello Step 5).
- `BusinessFunctionCatalogue::GENERAL_MANAGEMENT` (25 funzioni), `QualificaHrCatalogSeeder::assignFunctions`.
- Legacy (`../qnet`, non committato): `LegacyBusinessFunctions::NAMES` 26 => 'Direzione Generale' (non piu' "[OLD]",
  attiva). Spec 0281 D-2/D-2b aggiornata.
- DB locale: riga id 29 "[OLD] DIREZIONE GENERALE" (old_id 26) rinominata "Direzione Generale", attiva, BS; rilanciati
  `QualificaBusinessFunctionSeeder` + `QualificaHrCatalogSeeder`: 16 responsabili rispondono a Enrico, lui a nessuno.
- Test: nuovo "puts every function head under the general manager"; REQUIREMENT CHANGED: conteggio 24->25 in
  `QualificaBusinessFunctionSeederTest`, esclusione del direttore in "names only catalogue functions and org chart
  people". Seeding+Migration+BusinessFunctions 707/707, Pint pulito; vitest help 144/144, `tsc -b --force` 0, ESLint ok.
- Manuale: guida in-app business-functions IT/EN (nota) + Claude Docs (paragrafo import legacy e Conteggi).

## Fix filtri Scadenze attive/passive — VERDE, NON COMMITTATO (2026-10-19)

- Causa: le colonne text/number/date usano in griglia `agMultiColumnFilter` (payload `filterType: 'multi'`,
  `filterModels: [condizione, set]`). La colonna **Fattura** (`invoice_number_label`) passava da
  `InvoiceDerivedQuery::applyNumberLabel`, che legge solo `filter` (match esatto sul numero) -> col multi il
  filtro era ignorato (tutte le righe). Inoltre `/values` andava in 500 (fallback `SELECT DISTINCT` su colonna
  inesistente) per `invoice_number_label`, `invoice_document_date`, `days_overdue` (attive) e
  `purchase_orders`, `days_overdue` (passive).
- Fix Scadenze: `InvoiceDerivedQuery::applyNumberLabel` filtra sull'etichetta "n/anno" (tabella derivata +
  FilterApplier, `numberLabelSql()` unica fonte, riusata da `InstallmentSql::label`) -> vale per Fatture e Scadenze;
  `LISTED_COLUMNS` (Fattura, Data fattura) nei distinctValues; `valueList: false` -> `hasFilterValues: false` su
  `days_overdue` (entrambe) e `purchase_orders`.
- Sede operativa (scadenze): `Tables/Support/OperationalSiteLabelSql` = alias, altrimenti `line1` dell'indirizzo
  principale (subquery correlata, convenzione OperationalSiteColumn), id solo come ultima risorsa.
- Importo incassato/pagato: `Tables/Support/ZeroWhenEmptyAmountFilter` -> la CONDIZIONE numerica legge NULL come 0;
  il Set ("(Vuoti)") e la cella restano sul valore grezzo (contratto riga invariato, vedi InvoiceInstallmentUpdateTest).
- Seeder `McPayables::billOrder`: testata con la commessa quando tutte le righe concordano (regola di
  PurchaseInvoiceDraftBuilder) + sede operativa dell'offerta. DB `qnet2` riallineato con UPDATE (404 fatture).
- Stessa classe di bug in altre griglie (sonda su tutte le TableDefinition): `PurchaseRequestDerivedQuery::applyText`
  ignorava `multi` e l'operatore (Ordini, RDA, righe RDA, Assenze) -> ora spacchetta il multi e delega a
  FilterApplier nel whereHas; `ProductCategoryCountColumn`, `TaskTemplateItemsCountColumn`,
  `RewardedReferentDerivedColumns` (rewards_count, last_assigned_at) -> `UnwrapsMultiFilter`; `payable_amount`
  (fatture passive) `hasFilterValues: false`; liste valori in 500 sistemate in Provvigioni (invoice, quote, product,
  settlement) ed Estratti (code); Cliente in Fatture ignorava l'operatore.
- Test: nuovi in InvoiceInstallments, PurchaseInvoiceInstallments, Invoices (x2), ProductCategories, TaskTemplates,
  RewardedReferents, PurchaseOrders, PurchaseInvoices, CommissionAccrualsTable, CommissionSettlementTables.
  Verificato anche su MySQL (sonda scratchpad `probe3.php`). Manuale: nessun impatto.
- Aperti: (1) "non contiene"/"diverso" escludono le celle vuote (SQL NOT LIKE su NULL; AG Grid client le includerebbe)
  -> decisione utente, tocca FilterApplier di tutte le griglie; (2) DB locale con 7 migrazioni pending (absence_*,
  `is_active` business_functions, drop `hourly_cost`) -> filtri Funzioni aziendali/Assenze in 500 finche' non migrato;
  (3) le 3 copie di colonna-conteggio (Role/ProductCategory/TaskTemplate) andrebbero consolidate.

## Spec 0282 + dataset controllo di gestione 2025-2026 — VERDE, COMMITTATO, seed eseguito su qnet2 (2026-10-19)

- Spec `docs/specs/0282-labor-cost-by-function-membership.xml` (decisione utente 2026-10-19): nell'Andamento le ore
  senza commessa vanno alle funzioni di APPARTENENZA della persona (pivot `business_function_user`), in parti uguali;
  persona senza funzioni -> `structure`. Appartenenza attuale, senza storico (D-3). Shape invariata.
  `LaborCostSource::byMonth` porta `user_id` sulle righe senza commessa; `CostTrendAggregator::byFunction($rows,
  $months, $weights, $userWeights)`; `MonthlyTrendBuilder::memberWeights()` (+1 query). Test in
  `MonthlyTrendLaborTest` (0282 AC-001/AC-003). Guide IT/EN `management-control-trend` aggiornate.
  NB: il numero 0278 era stato preso in parallelo da `0278-hr-absence-requests.xml`.
- Dataset `DemoManagementControlSeeder` (in coda a `DemoDataSeeder`, dopo `DemoTimeEntrySeeder`), file in
  `database/seeders/ManagementControl/` (`Mc*`): gen 2025 - ott 2026, dati italiani, tutto via servizi reali con
  orologio simulato (`McTimeline`: coda eventi in ordine di data + `Carbon::setTestNow`, niente oltre oggi).
  ~360 trattative (55% vinte) -> contratto validato -> commessa; per riga (edizione corso / fase SAL) fattura
  attiva + registrazione + incassi (12% clienti morosi), ODA+fatture passive (docenti, aule, subappalto IT),
  costi manuali (materiali, trasferte); costi di struttura mensili (affitto, utenze, software, consulenze...),
  RDA trimestrali -> ODA; liquidazioni agenti trimestrali; organico con retribuzione mensile dalla data di
  assunzione + rinnovo CCNL 2026-01-01 (+2,8%), tutti con almeno una funzione; segnatempo completo (~45.800 righe,
  480 min/giorno, chiusure e festivi) con ore di commessa prima e generiche dopo.
  Prodotti venduti PROPRI del dataset (tipologia `consultancy` = PAID): quelli demo sono "Ente" (RECEIVED, ricavo =
  sola provvigione), che azzerava il ricavo da contratto. Durata ~105 s.
- Fix fuori scope necessario: `Support/ActiveInvoiceWriter::request()` non valorizzava `proforma_requests.quote_id`
  (obbligatorio dalla 0267): `DemoDataSeeder` falliva su MySQL in `DemoInvoiceSeeder`.
  Inoltre la pulizia iniziale di `DemoDataSeeder` non reggeva un RE-RUN su DB popolato: ora cancella
  `PurchaseOrderReceipt` prima di `PurchaseOrder` (le righe di ricevimento restringono le righe d'ordine) e
  `ProformaRequest` dopo `Invoice` (restringe commessa e offerta). Verificato con re-run su `qnet2_mc` popolato:
  nessun duplicato (anagrafiche, regole, prodotti), max 480 min/giorno.
- Verificato su DB MySQL separato `qnet2_mc` (migrate:fresh + DemoDataSeeder): 8 endpoint CdG 200, 0 minuti non
  valorizzati, numerazione fatture coerente con le date, max 480 min/giorno. Test `Seeding/ManagementControlDatasetTest`.
  Suite: 11812/11816 (1 fallimento solo ambiente: `QuoteDocumentPdfTest` senza LibreOffice), tsc -b, ESLint, Pint puliti.
  In locale `composer test` fallisce per il Composer globale (`@no_additional_args`); usare `PHP_INI_SCAN_DIR` con
  memory_limit=2G + `php artisan test --parallel` (Herd ha 128M).
  `DemoDataSeeder` non gira su SQLite (`locations:add` usa SQL MySQL), preesistente.
- Limiti noti: `DemoDataSeeder` usa `WithoutModelEvents` -> niente activity log, quindi nella pagina Fatturazione il
  ciclo proforma -> fattura (data conversione) resta vuoto per i dati demo. Utilizzo staff di produzione ~42%.
- Manuale Claude Docs: non condiviso con la sessione -> da aggiornare: Controllo di gestione > Andamento (ore senza
  commessa per funzione di appartenenza).
- Seed eseguito su `qnet2` locale (2026-10-19): CE 2025 99 commesse, contratto = fatturato 2,42 M, lavoro 392 k, 0 min non valorizzati.

## Persone -> Funzioni aziendali e responsabile di livello I dalla Pianta Organica — VERDE, COMMITTATO (2026-10-19)

- Fonte: un foglio per funzione di `Pianta_Organica_Qualifica_Aggiornato 8.09.xlsx`. Dati in
  `database/seeders/QualificaCatalog/OrgChartFunctions.php` (nome funzione del `BusinessFunctionCatalogue` =>
  `heads` = righe "Livello I", il primo e' il manager; `members` = gli altri). Stessi abbinamenti nome->email di
  `OrgChartRoster` (+ Yailin = `yailin.calderon`); esclusi Sepe Lorenzo e Zincone (senza account).
- Decisioni utente: il responsabile e' il livello I del file Excel; APL (riga livello I vuota) e Segreteria (nessun
  livello I) restano senza responsabile; A&C ha come manager Studio Casiello, e in "Risponde a" entrano Casiello e Centore.
- `QualificaHrCatalogSeeder` Step 5 `assignFunctions` (stesso seeder, nessun seeder nuovo), tutto ADDITIVO: membri con
  `syncWithoutDetaching` (anche i capi sono membri); `manager_id` riempito solo se vuoto (l'import legacy non lo scrive);
  i capi vengono AGGIUNTI a `reportsTo` degli altri membri, mai se stessi, e chi gia' ne aveva li tiene (es. i commerciali
  restano con Falzarano/Aliberti dal `ReportsToRoster` + Fabozzi).
- Lanciato sul DB locale: 24 funzioni con membri, 22 con manager, 145 appartenenze, 240 righe "Risponde a"; rilancio senza doppioni.
- Test: +3 in `QualificaHrCatalogSeederTest` (coerenza con roster/catalogo, capi = manager/superiori, additivo + manager
  manuale tenuto). Manuale: nessun impatto (solo dati).
- DECISO (utente 2026-10-19): "Risponde a" resta ADDITIVO, non va sostituito dal livello I della Pianta. Restano i
  supervisori del Mansionario di settembre (es. Falzarano/Aliberti su Formazione e APL, 46 persone). Verificato lo
  stesso giorno: DB locale allineato all'Excel 8.09 su funzioni, tipi BU/BS, membri, manager, livelli e mansioni.

## FIX scheda utente: badge Mansioni vuoto — VERDE, COMMITTATO (2026-10-19)

- Bug: in "Profilo > Mansioni" il badge era vuoto. Il backend (`EmploymentResource`) manda `job_roles` come `{id, name}`
  (contratto spec 0271, `ref`), il frontend lo tipizzava `EmploymentRelationRef` (`{id, label}`) e lo passava senza
  conversione: `RefBadges` leggeva `label` inesistente.
- Fix: `features/users/types.ts` `job_roles: NamedRef[]`; `user-record.ts` `toRelationRefs(employment?.job_roles)`
  (come `business_functions`). Fixture `user-form-employment.test.tsx` allineata al contratto reale (`name`).
- Test: nuovo `user-detail.test.tsx` "job roles (spec 0271)" (rosso prima del fix); `src/features/users` 175/175,
  `tsc -b --force` e ESLint puliti; verificato su /users/3 in browser headless. Manuale: nessun impatto.

## Spec 0281 — Funzioni aziendali della pianta organica + conteggi in griglia — VERDE, COMMITTATO (2026-10-19)

- Spec `docs/specs/0281-business-function-org-chart-and-usage-counts.xml` (D-1..D-6, AC-001..AC-006).
- Seed: `QualificaCatalog/BusinessFunctionCatalogue` (24 funzioni della Pianta Organica 8.09, nomi SENZA codice,
  tipo BU/BS; costante `EMPLOYMENT_AGENCY` = "Agenzia per il Lavoro") + `QualificaBusinessFunctionSeeder`, chiamato da
  `QualificaCatalogSeeder` step 5 prima del Link seeder. Esistente per nome (senza maiuscole) -> invariato, solo tipo
  mancante riempito. La categoria "APL" e gli operatori APL (`OperatorRoster::FUNCTION_WIDE_COMPETENCE`) ora puntano
  ad "Agenzia per il Lavoro". "Consulenza" e "No funzione aziendale" restano.
- Legacy (`../qnet`, non committato): `app/Support/Migration/LegacyBusinessFunctions.php` (id legacy -> nome qnet-2,
  fusione BACK OFFICE 7 -> SEGRETERIA 20). `BusinessFunctionController` invia nome mappato o "[OLD] ..." + `is_active`,
  esclude le fuse e unisce membri/responsabile; `UserMigrationController` e `ProductCategoryMigrationController`
  mandano `business_function_id` via `referenceId()`. Verificato con tinker sui dati reali: 25 righe, 6 [OLD] non attive
  (231, AVVALIMENTI, DIREZIONE GENERALE, HACCP, IT, PIANIFICAZIONE), Segreteria 11 utenti.
- qnet-2 `BusinessFunctionsSource`: su creazione prende `is_active` dal record (assente -> true); adottata tiene il suo.
- Griglia: `Tables/BusinessFunctions/BusinessFunctionUsageCounts` (sottoquery correlate, DISTINCT sulle righe prodotto):
  `users_count`, `product_categories_count`, `competences_count`, `opportunities_count` visibili; `projects_count`,
  `campaigns_count`, `purchase_requests_count`, `children_count` nascoste. Ordinabili, non filtrabili.
- Test: nuovi `QualificaBusinessFunctionSeederTest`, `BusinessFunctionUsageCountsTest`, +2 in
  `BusinessFunctionsSourceImportTest`; REQUIREMENT CHANGED (nome APL / colonne): `QualificaBusinessFunctionLinkSeederTest`,
  `AssignableSwitchSeedingTest`, `QualificaOperatorSeederTest`, `QualificaLegacyImportSeederTest`, `BusinessFunctionTableTest`.
  `composer test` 11803/11807 (solo `QuoteDocumentPdfTest`, LibreOffice assente); vitest help/business-functions/i18n
  425/425; `tsc -b --force` 0 errori; ESLint e Pint puliti.
- Manuale: guida in-app IT/EN sezione `counts`; Claude Docs (voce Funzioni aziendali + paragrafo import legacy).
- DA DECIDERE: un DB dove l'import legacy e' gia' girato coi vecchi nomi (DB locale: "APL" old 8, "CONTABILITA'"...)
  rilanciando il seed avrebbe doppioni (es. "APL" + "Agenzia per il Lavoro"): serve un riallineamento una tantum
  (rinomina per old_id, [OLD]+non attiva, fusione BACK OFFICE) oppure re-import da zero. Seed NON lanciato in locale.

## Mansioni, livello ed email condivise assegnati alle persone — VERDE, COMMITTATO (2026-10-19)

- Fonti: `Elenco_Dipendenti_Qualifica_Aggiornato 8.csv` (Mansione, Email aziendale) + `Pianta_Organica ... 8.09.xlsx`
  (Livello). Abbinamento per nome -> account: 129 persone; senza account Lorenzo Sepe e Alessandro Zincone. Abbinamenti
  confermati dall'utente anche se il nome differisce: Letterio=Lillo Irrera, Giuseppina=Pina De Rosa, Studio Centore=
  Raffaele Centore, Di Martino=#110 De Martino, Maria=#76 Marilisa Scafati Taglialatela. Mansioni CSV e Pianta coincidono.
- Dati: `database/seeders/QualificaCatalog/OrgChartRoster.php` (email account => roles, level, emails; `EXTERNAL` =
  studi esterni). Piu' livelli per persona -> il piu' alto. Email del CSV "g.gervasio@...itcv@..." divisa in due.
- Decisione utente: fuso in `QualificaHrCatalogSeeder` (Step 4 `assignPeople`), NON un seeder nuovo; per questo nella
  catena di `QualificaProductionDataSeeder` lo step si e' spostato in fondo (step 9, dopo ReportsTo: servono gli account).
  ADDITIVO: `syncWithoutDetaching` sulle mansioni, email aggiunte se mancanti (`normalized_value`), mai rimosse. Livello:
  profilo senza condizioni -> prima condizione da oggi, `employee` (studi esterni `other`), senza azienda, via
  `EmploymentContractService::create`; condizione in vigore senza livello -> livello riempito; livello gia' presente
  -> invariato. Email come contatto `email` non primario, `is_work`, etichetta "Email aziendale".
- Attivita': nessuna assegnazione per persona nel modello, arrivano dalla mansione.
- Lanciato sul DB locale: 67 mansioni, 150 assegnazioni, 129 condizioni con livello, 137 email. Rilancio senza doppioni.
- Test: `QualificaHrCatalogSeederTest` (+4: coerenza roster, assegnazione, solo-se-vuoto/additivo, account mancante);
  `tests/Feature/Seeding` 170/170. Manuale: nessun impatto (solo dati).
- Appartenenza alle Funzioni aziendali: fatta dopo dai fogli della Pianta (vedi voce "Persone -> Funzioni aziendali").
  67 utenti del DB non nel CSV: `~/Downloads/Utenti_DB_non_in_elenco_dipendenti.xlsx`, nessuna azione decisa.

## Spec 0278 — HR passo 6a: assenze (tipi, richieste, approvazione, saldi, segnatempo) — VERDE, COMMITTATO (2026-10-19)

- Spec `docs/specs/0278-hr-absence-requests.xml` (D-1..D-13, AC-001..AC-022, AC-018 [revised]); 0279 (calendario
  della squadra) solo impostata, D-1 aperta: una persona senza squadra vede solo se stessa o anche i colleghi?
- Decisioni utente: saldi in ORE (minuti interi) a ratei mensili 1/12 (mese conta con >= 15 giorni in forza),
  riporto automatico 1/1 00:45 (`absences:carry-over {year?}`); UN livello di approvazione (responsabile diretto;
  ripiego responsabili delle funzioni di appartenenza, poi titolari `absence-requests.manageAll`); assenza approvata
  riduce il target del segnatempo. Stati draft|submitted|approved|rejected|cancelled.
- Tabelle `absence_types`, `absence_requests` (minuti fissati all'invio), pivot `absence_request_approver` (snapshot),
  `absence_allowances`. Permessi `absence-types.*`, `absence-requests.viewAny|create|manageAll` (solo super-admin:
  dopo il deploy `php artisan permissions:sync`). Seed produzione `QualificaAbsenceTypeSeeder` (Ferie, Permesso,
  Malattia): su un DB gia' installato va lanciato a mano (`php artisan db:seed --class=QualificaAbsenceTypeSeeder`),
  altrimenti il catalogo e' vuoto e il form lo segnala (`absences.requests.form.typesEmpty`); lanciato sul DB locale. Morph map in `AppServiceProvider`. Menu "Assenze" dopo Segnatempo, "Tipi di assenza" in Configurazione.
- Backend: `Services/Absences/Requests/*` (DurationCalculator, OverlapGuard, ApproverResolver, `AbsenceRequestVisibility`
  = UNICA visibilita' 404, Capabilities, Service, Transitions, Notifier con `DB::afterCommit`), `Balances/*`
  (AccrualCalculator puro, BalanceBuilder, CarryOverService); rotte `routes/api/absence-{types,requests,balances}.php`
  da `hr.php`. Griglia: TRE domini (`absence-requests`, `-to-approve`, `-all`, base astratta) perche' il framework
  tabellare non passa parametri generici. Allegato su `/api/absence-requests/{id}/attachment`; `AttachmentPolicy`
  nega view/delete degli allegati fuori da `config('attachments.attachable_types')`.
- Segnatempo: `TimeEntryAbsenceResolver` (1 query) + `DayAbsenceIndex::reduceTarget` (formula unica, anche team
  pulse); DaySummary `absences[]`; overview `daily_target_minutes` = target base (prima: ultimo giorno lavorativo).
- Frontend: `features/absences/{requests,balances}`, `features/absence-types`, card giorno segnatempo; i18n
  `{it,en}-absence{s,-types,-balances}.ts`; guide `absences`, `absence-types` (HELP_GUIDE_KEYS 91), `time-entries`.
- Regola: una richiesta a cavallo d'anno conta tutta nell'anno di `starts_on` (da confermare con l'utente).
- Fix post-verifica (utente): l'ufficio del personale di ripiego include i super-admin (`Gate::before`, nessun
  permesso salvato: `User::permission()` non li trovava -> 422 "No approver is available"); header della pagina
  Assenze come il Segnatempo (solo breadcrumb + azione, niente titolo/sottotitolo).
- Verifica (verifier indipendente + rerun): `composer test` 11790/11795 (fallimenti: `QuoteDocumentPdfTest`
  LibreOffice; `AbsenceFoundationTest` rollback, corretto con step calcolato, rerun aree assenze+segnatempo 222/222);
  vitest 8740/8741 (1 flaky `use-task-kanban-filters`, fuori scope); `tsc -b --force` 0 errori; ESLint e Pint puliti.
  Non verificato a vista: assenza di scroll orizzontale a 375 px.
- Manuale Claude Docs aggiornato: Organigramma (Parte 1, mancava), Tipi di assenza (Parte 2), Assenze + target nel
  Segnatempo (Parte 3), elenchi del menu. Mancano ancora CCNL, Mansioni, Attivita' (HR 0271/0272).
- Prossimo: decisione su 0279 D-1, poi `/build-feature` 0279. Commit separato dagli altri lavori nel working tree
  (0280, barra azioni utente).

## Spec 0280 — Funzioni aziendali attive/non attive — VERDE, COMMITTATO (2026-10-19)

- Spec `docs/specs/0280-business-function-active-flag.xml` (D-1..D-9, AC-001..AC-011), forma della 0212 (settori).
- `business_functions.is_active` (migrazione `2026_10_24_100000`, default true). Autorita' unica
  `Services/BusinessFunctions/BusinessFunctionActivity` (`inactiveIds()`, `isActive()`, `inactiveAncestor()`): una
  funzione non attiva spegne tutto il ramo, risolto in lettura (D-1).
- `BusinessFunctionResource`: `is_active` (proprio) + `inactive_ancestor` {id,name}|null. For-select: esclude le non
  attive salvo `include_inactive=1` (parent picker, filtri avanzati Opportunita'/Progetti via `source.params`, filtro
  Conto economico); `ids[]` idrata sempre.
- `App\Rules\ActiveBusinessFunction($exemptIds)` al posto di `exists` su: `employment.business_function_ids.*`,
  `managed_business_function_ids.*`, competenze (`CompetenceLineSetValidator::rules` 3o argomento), categoria prodotto,
  RDA (form e cella), `to_function_id` dell'organigramma. Esenti gli id gia' collegati al record (D-2). Invariati:
  funzione derivata dalla categoria (D-3), guardie di copertura (D-4), `parent_id`, criteri dei Flussi offerta.
- Griglia: colonna `is_active` filtro `set` (come gli altri booleani). Factory: `BusinessFunctionFactory::inactive()`.
- Test: `BusinessFunctionActiveTest`, `BusinessFunctionActiveLinksTest`; aggiornati (REQUIREMENT CHANGED: nuovo campo)
  `BusinessFunctionMetaTest`, `BusinessFunctionTableTest`; rollback +1 (66 / 217 / 81, sopra il +4 delle assenze).
- FE: switch "Attiva" (`BusinessFunctionActiveRow` in `business-function-rows.tsx`) con avviso da `inactive_ancestor`,
  badge "Non attiva", cella griglia, payload solo se cambiato, i18n IT/EN, guida in-app IT/EN, manuale Claude Docs
  (voce Funzioni aziendali).
- `ValidatesEmployment.php` e' a 375 righe (soglia 300 gia' superata prima): candidato a uno split.

## Barra azioni nel dettaglio utente — VERDE, COMMITTATO (2026-10-19)

- FE `features/users/user-actions-bar.tsx` (banda `border-y bg-muted/40` tra identita' e KPI, come
  `TaskActionsBar`/`ContractActionsBar`, colori da `ACTION_BUTTON_VARIANT`): Reinvia benvenuto (spostato
  dall'header), Attiva/Disattiva (PATCH `is_active`, confirm warning solo per disattivare, mai su se stessi),
  Impersona (confirm, `useAuth().impersonate`, poi `/dashboard`; nascosto se gia' in impersonificazione),
  Elimina (confirm destructive; 403/422 -> `deleteForbidden`, 409 -> `deleteInUse`).
- Logica in `use-user-record-actions.ts`; `UserLeaveReason = 'deleted' | 'impersonated'`; `UserDetailView.onLeave`:
  screen -> `onSaved` (sheet) o `navigate('/users')` sul delete in pagina; sheet condiviso -> chiude.
- `user-action-errors.ts` (`backendErrorMessage`) condiviso con `use-resend-welcome-email.ts`.
- BE `UsersAuthorization::actionPermissions` + `actions()` aggiungono `impersonate` (policy + non se stessi +
  target attivo). Test `tests/Feature/Users/UserDetailActionsTest.php`; FE `user-actions-bar.test.tsx`
  (sostituisce `user-detail-welcome.test.tsx`, stessi casi AC-020); `userWrapper` ora fornisce `AuthContext`.
- i18n `{it,en}-users-detail-actions.ts` -> `users.detail.actions`. Guide `users` IT/EN + Manuale Claude Docs aggiornati.

## Mansioni, Attivita' e CCNL dalla "Pianta Organica" nel seed di produzione — VERDE, COMMITTATO (2026-10-19)

- Fonte: `Pianta_Organica_Qualifica_Aggiornato 8.09.xlsx` del cliente (24 fogli di settore). 67 mansioni
  (`job_roles`) e 365 attivita' (`job_activities`), ogni mansione collegata alle sue attivita'.
- Decisioni utente: niente sede o regione nel nome della mansione (una sola "Operatore Politiche Attive",
  "Segreteria Didattica", "Consulente Commerciale Junior", "Supervisor Commerciale Sedi Territoriali";
  "Referente Politiche Attive Lombardia" -> "Referente Territoriale Politiche Attive"); le mansioni "+" sono divise
  in due ("CFO" + "Supporto Operativo Amministrazione", "... + R&S" -> "Addetto Ricerca e Selezione", "Supervisor
  Attivita' Formative" + "Coordinatore Segreteria Didattica"); le attivita' quasi uguali sono unificate, senza nomi
  di persona ne' anni. "Svolgimento di tutte le attivita' dell'Operatore" -> le attivita' dell'operatore sono
  collegate anche a Supervisor e Referente.
- Dati: `database/seeders/QualificaCatalog/JobRoleCatalogue.php` (unione + `activities()`/`activityNames()`) e quattro
  classi per area (`Administration`/`Commercial`/`Training`/`ConsultancyJobRoles`). Voce attivita' = nome oppure
  `nome => descrizione`; un'attivita' condivisa ha lo STESSO nome in ogni mansione e si crea una volta sola.
- CCNL (richiesta utente: "c'e' la colonna livello"): la colonna "Livello" del foglio diventa il CCNL
  `CollectiveAgreementCatalogue::NAME` = "Pianta Organica Qualifica Group" (nome scelto da me, il foglio non ne da'
  uno; codice CCNL vuoto) con i livelli "Livello I".."Livello IV" e la legenda del foglio come descrizione.
- `QualificaHrCatalogSeeder` (step di `QualificaProductionDataSeeder` dopo i settori EA): create-only sul nome,
  passa da `JobActivityService`/`JobRoleService`/`CollectiveAgreementService`; rilanciarlo non duplica e non
  sovrascrive le modifiche fatte a mano (un CCNL rinominato pero' verrebbe ricreato col nome originale).
- Test: `tests/Feature/Seeding/QualificaHrCatalogSeederTest.php` (limiti di lunghezza, una sola grafia e una sola
  descrizione per attivita', nessun "+", conteggi 67/365, collegamenti, rilancio).
- Manuale: nessun impatto (solo dati, nessun cambio di comportamento). Prossimo passo possibile: assegnare le
  mansioni agli utenti dal foglio (nome e cognome -> utente), non richiesto.

## Spec 0277 — Costo orario dalla retribuzione dello storico contrattuale — VERDE, COMMITTATO (2026-10-19)

- Spec `docs/specs/0277-hourly-cost-from-contract-salary.xml` (D-1..D-5, AC-001..AC-006); supera CdG 0272 D-1.
- Tariffa: `EmploymentContract::hourlyCost()` (stringa 2 decimali, null senza retribuzione) via
  `SalaryPeriodEnum::hours()`: hourly 1, monthly `config('employment.monthly_salary_hours')` (173), yearly x12.
- `LaborCostSource`: sottoquery correlata `contract_id` = condizione con `starts_on` <= `time_entries.date` piu'
  recente, raggruppo per contract_id e leggo le tariffe con UNA query in piu' (`rates()`); senza condizione o
  retribuzione -> `unpriced_minutes`. Shape degli endpoint CdG invariata.
- Eliminati colonna `employment_profiles.hourly_cost` (migrazione 2026_10_22_100000, reversibile) e righe
  `role_field_permissions` `employment.hourly_cost` (2026_10_22_100100, down no-op); catalogo `employment.*` a 17.
- `EmploymentResource.hourly_cost` (number|null) solo con `users.manageCompensation` e `contracts` caricati, dalla
  condizione in vigore oggi; FE: riga in sola lettura nella sezione Pagamento (`user-payment-section.tsx`),
  rimossi `user-hourly-cost-field.tsx` e il campo da schema/defaults/payload/server-errors/assignment.
- Factory: `EmploymentProfileFactory::paidHourly($rate, $since)`; `mcTimeEntry()` la usa. Rollback 61 / 212 / 76.
- Guide aggiornate IT/EN: `users` (payment), `management-control`, `management-control-trend`.

## Merge `main` -> `feature/amministrazione` (HR 0271-0276 + controllo di gestione 0268-0272) — VERDE (2026-10-19)

- Conflitti risolti per unione: `employment.*` ha sia `hourly_cost` (CdG 0272) sia `iban`/`iban_holder` (HR 0272);
  catalogo campi 18 chiavi `employment.*`; HELP_GUIDE_KEYS 89; rollback migrazioni 59 / 210 / 74 (+12 HR, +1 CdG).
- Adeguati al contratto di main: `UserHourlyCostTest` (utente membro di una funzione, HR 0271 obbligatoria) e
  `user-form-employment.test.tsx` (`company_id` non e' piu' nel payload employment, ora nello storico contrattuale).
- Numerazione spec duplicata tra i rami: 0268-0272 esistono sia per CdG sia per HR/catalogo (file con nomi diversi).
- Verifica: Pest 11670/11674 (3 errori solo ambiente: `QuoteDocumentPdfTest` senza LibreOffice), Vitest verde,
  `tsc -b --force` pulito, ESLint/Pint puliti.
- PROSSIMO (decisioni utente 2026-10-19): costo orario calcolato dalla retribuzione dello storico contrattuale HR
  (condizione in vigore alla data dell'ora; hourly = importo, monthly / 173, yearly / (173 x 12), divisori in config;
  lordo senza coefficiente), eliminando il campo manuale `employment_profiles.hourly_cost`. Commit separato.

## Spec 0272 — Controllo di gestione F4: costo orario, ore nel margine, pagina Agenti — VERDE, NON COMMITTATO (2026-10-16)

- Spec `docs/specs/0272-management-control-labor-cost-and-agents.xml` (D-1..D-8, AC-001..AC-012). Chiude le 4
  fasi del modulo (7 pagine: Conto economico, Scadenzario ed esposizione, Cash flow, Andamento, Fatturazione,
  Conti, Agenti).
- Migrazione `2026_10_20_100000_add_hourly_cost_to_employment_profiles_table` (`hourly_cost` decimal(10,2) nullable,
  euro/ora, senza storico). Campo `employment.hourly_cost` in tutta la pipeline impiego (UsersAuthorization
  FieldDefinition number con lo stesso ceiling, ValidatesEmployment `nullable|numeric|min:0|max:9999.99`,
  EmploymentData, EmploymentResource); frontend `features/users/user-hourly-cost-field.tsx` in "Dati
  contrattuali". Contatori `migrate:rollback --step` aggiornati (+1): PurchaseOrderMigrationTest 47,
  QuoteWorkflowMigrationTest 198, SupplierCommissionDirectionTest 62; campi `employment.*` = 16.
- Costo del lavoro: `app/Services/ManagementControl/LaborCostSource.php` (per commessa e per mese; costo =
  minuti/60 x hourly_cost, arrotondato al centesimo per riga di aggregazione; utenti senza costo -> minuti in
  `unpriced_minutes`). Conto economico: Figures + `cost_labor`, `labor_minutes`, `unpriced_minutes`;
  `cost_actual` INCLUDE `cost_labor`; minuti ripartiti come le cifre. Trend: serie `labor` per funzione
  (`structure` per ore senza commessa), `cost` = passivo + labor, `previous_year.labor`, `labor_minutes`/
  `unpriced_minutes` per mese; filtro societa' sulle ore via `employment_profiles.company_id` (sedi non
  applicabili); `by_nature` solo passivo (riga di chiusura "Costi passivi totali" = cost - labor nel frontend).
- `GET management-control/agents` (periodo max 24 mesi, `company_id[]`, `top` 1..50 default 20): per agente
  (referent destinatario dei maturati) `accrued` (periodo), `to_settle` (a oggi, senza estratto e senza
  `settled_externally_on`), `in_statement` + `pending_document_count` (ISSUED|DOCUMENT_RECEIVED; ISSUED = in
  attesa documento), `approved_unpaid` (APPROVED net_payable - pagato, senza clamp), `paid` (rate della fattura
  passiva dell'estratto pagate nel periodo); `agents` top N per to_settle, `others` (con agents_count e
  pending_document_count) o null, `totals`, `by_month`. Menu `management-control-agents` ("Agenti", icona
  `hand-coins`); guida con la stessa chiave (HELP_GUIDE_KEYS = 85); 7 figli nel menu del modulo.
- Backend: `Agents/{AgentAccrualAggregator,AgentSettlementAggregator,AgentsBuilder}`; test `UserHourlyCostTest`,
  `IncomeStatementLaborTest`, `MonthlyTrendLaborTest`, `Agents*Test`; helper `mcTimeEntry`, `mcAgentAccrual`,
  `mcAgentSettlement`, `mcAgentPayable`.
- Frontend: `features/management-control/{income-statement-hours.tsx, agents-*}`, `scope-filter-fields.tsx`
  espone anche `PeriodFields` e `CompanyFilterField`; i18n `{it,en}-management-control-agents.ts`; guide
  `users`, `management-control`, `management-control-trend`, `management-control-agents` IT/EN aggiornate.
- Manuale Claude Docs: NON aggiornato (documento non condiviso con il connettore) per F2, F3a, F3b, F4:
  sette pagine del modulo + campo "Costo orario" in Utenti > Impiego. Testo pronto nelle guide IT.
- Prossimi possibili (non pianificati): budget annuale per funzione/societa' (nuovi dati); filtri da URL sulle
  pagine tabellari per il drill-down; robustezza del test flaky `stat-column-chart.test.tsx`; `Switch` da spento
  sotto 3:1; split di `backend/routes/api.php` (499 righe).

## Spec 0271 — Controllo di gestione F3b: fatturazione (da fatturare, ciclo proforma) e conti — VERDE, COMMITTATO ba14923a (2026-10-16)

- Spec `docs/specs/0271-management-control-billing-and-accounts.xml` (D-1..D-11, AC-001..AC-015). Voci di menu
  `management-control-billing` ("Fatturazione", `/management-control/billing`, icona `file-text`) e
  `management-control-accounts` ("Conti", `/management-control/accounts`, icona `landmark`); guide con le stesse
  chiavi (HELP_GUIDE_KEYS = 84). Router frontend spezzato: `src/routes/management-control-routes.tsx`
  (`MANAGEMENT_CONTROL_ROUTES`, 6 rotte) incluso con spread in `router.tsx` (480 righe).
- `GET management-control/billing` (date solo per il ciclo proforma; company/sites; `group_by`
  business_function|company|commercial): `backlog` a oggi sulle commesse NON chiuse forzatamente (contract e
  invoiced da `WorkOrderFigureSources::collect()`, `to_invoice` anche negativo = sovrafatturato; `by_dimension`
  con quote per funzione o offerta di riferimento via `WorkOrderReferenceQuote` (estratta da 0268, output
  invariato); `by_age` 4 fasce su start_date, soglie 90/180/365 giorni); `proforma` (issued = invoices created_at
  nel periodo; converted = prima riga di `activity_log` con `properties->attributes->type = invoice`; open = type
  proforma a oggi con 4 fasce di eta'; medie giorni proforma->fattura semplice e fattura->incasso pesata;
  `by_kind` 3 righe da proforma_requests.kind).
- `GET management-control/account-flows` (periodo max 24 mesi; company/sites): incassi (rate attive per
  collected_at, conto = invoices.financial_account_id) e pagamenti (rate passive per paid_at, conto = rata con
  fallback testata), `by_account` per importo con null ultimo, `by_month` completo. Estremi dei periodi esclusivi
  sul giorno dopo (SQLite salva le date con ora).
- Backend: `app/Services/ManagementControl/{WorkOrderReferenceQuote}.php`, `Billing/{BacklogAggregator,
  ProformaCycleAggregator,BillingBuilder}`, `Accounts/{CollectionAggregator,PaymentAggregator,AccountFlowsBuilder}`;
  test `Billing*Test`, `AccountFlowsTest`; helper `mcProforma`, `mcConvert` (passa da InvoiceService::updateDetails
  e retrodata l'activity log), `mcCollect`, `mcAccount`.
- Frontend: `features/management-control/billing-*`, `accounts-*`, i18n `{it,en}-management-control-{billing,
  accounts}.ts`, guide `help/content/{it,en}/management-control-{billing,accounts}.ts`.
- Non provato su MySQL: JSON path dell'activity log, DATEDIFF, GROUP BY su alias (solo SQLite).
- Test flaky preesistente sotto carico: `components/ui/stat-column-chart.test.tsx` "shows the skeleton fallback
  until the lazy chart chunk resolves" (asserisce lo skeleton PRIMA che il chunk lazy si risolva: con molti worker
  il chunk arriva prima); 3/3 verde da solo. Stessa classe dei 5 timeout non riprodotti visti in F3a. Da
  irrobustire (es. mock del lazy import) in un intervento a parte.
- Revisione ui-design applicata: `StatColumnChart` con prop `headingLevel` e `valueAxisWidth` (80 per gli euro);
  colore stabile per conto nei due grafici; thead opaco sulle tabelle con colonna sticky; hover della cella
  sticky via `color-mix`; titoli visibili sulle tabelle proforma; griglia filtri Fatturazione `sm:3 xl:6`.
- Manuale Claude Docs: NON aggiornato per F2, F3a, F3b (documento non condiviso con il connettore): sei pagine
  del modulo da descrivere; testo pronto nelle guide IT.
- Prossimo: F4 (provvigioni per agente: maturato/liquidato/da liquidare per agente e periodo; costo orario del
  personale: richiede nuovo campo su employment_profiles o utenti, da decidere con l'utente).

## Spec 0270 — Controllo di gestione F3a: cash flow previsionale e andamento mensile — VERDE, COMMITTATO 2ff79e0d (2026-10-16)

- Spec `docs/specs/0270-management-control-cash-flow-and-trend.xml` (D-1..D-14, AC-001..AC-018). Stesso permesso.
  Voci di menu `management-control-cash-flow` ("Cash flow previsionale", `/management-control/cash-flow`, icona
  `wallet`) e `management-control-trend` ("Andamento mensile", `/management-control/trend`, icona `trending-up`,
  aggiunta a `icon-map.ts`); guide in-app con le stesse chiavi (HELP_GUIDE_KEYS = 82).
- `GET management-control/cash-flow` (company/sites, `document_type` default all, `horizon_months` 1..24 default 6):
  `backlog` (scaduto a oggi), `months[]` dal mese corrente con `cumulative` SENZA arretrato, `beyond`, `by_company`.
  Entrate = rate attive aperte per mese di due_date; uscite = rate passive aperte + ODA impegnati con data attesa
  = `InstallmentScheduleCalculator::firstDueDate(order_date, metodo)` (nuovo metodo pubblico, `dueDate` invariato),
  senza metodo = order_date; ODA aggregati in SQL per (societa', metodo, data ordine) e datati in PHP.
  Scenario "arretrato entro 30 giorni" = frontend puro (`cash-flow-scenario.ts`), anche su by_company.
- `GET management-control/monthly-trend` (date obbligatorie, max 24 mesi, data finale esclusiva sul mese
  successivo; company/sites): `months[]`, `functions[]` (chiavi id funzione | `structure` | `unattributed`;
  fatturato = invoice_lines net di fatture type=invoice per mese documento, riga con quote_line_id -> funzione
  effettiva della categoria, senza -> quote di `WorkOrderFunctionShares` della commessa, altrimenti unattributed;
  costi = purchase_invoice_lines net (proforma e fatture, incluse quelle degli estratti conto provvigioni, D-14) con
  commessa -> quote, senza -> structure), `totals` + `previous_year` (mesi -12), `by_nature` (direct/indirect/
  general; riga null solo teorica). `WorkOrderFunctionShares` estratta da 0268 a output invariato.
- Backend: `app/Services/ManagementControl/{WorkOrderFunctionShares,FlowSupport}.php`, `CashFlow/{Receivable,
  Payable,Committed}FlowAggregator + CashFlowBuilder`, `Trend/{Revenue,Cost,Nature}TrendAggregator +
  MonthlyTrendBuilder`; test `CashFlow*Test`, `MonthlyTrend*Test`, `tests/Unit/Invoices/FirstDueDateTest`.
- Frontend: `features/management-control/cash-flow-*`, `trend-*`, `money-math.ts`, `month-label.ts`,
  `ScopeSelectionFields` (societa'+sedi, esportato da `scope-filter-fields.tsx`, che lo compone con il periodo), i18n in file
  separati `{it,en}-management-control-{cash-flow,trend}.ts`; `components/ui/trend-line-chart{,-impl,.test}.tsx`
  (multi-serie), `headingLevel` anche su `StatChart`, `ComparisonBarChart` con altezza minima proporzionale ai punti.
- ATTENZIONE: `frontend/src/routes/router.tsx` e' a 494 righe (hard limit 500): la prossima rotta richiede lo split
  del router (es. per sezione di menu). Idem `backend/routes/api.php` a 499.
- Migliorie segnalate, non fatte: `Switch` da spento sotto 3:1 (design system); hover della cella sticky in
  thead/tfoot; manuale Claude Docs per le due pagine (documento non condiviso con il connettore).
- Prossimo: spec 3b (da fatturare per funzione/societa'/commerciale con eta' commessa; ciclo proforma con data di
  conversione dall'activity log del cambio `type`; incassi/pagamenti per conto finanziario: attivo via
  `invoices.financial_account_id`, passivo via `purchase_invoice_installments.financial_account_id`), poi F4.

## Spec 0269 — Controllo di gestione F2: scadenzario, aging, impegni, esposizione, concentrazione — VERDE, COMMITTATO 8140bc9b (2026-10-16)

- Spec `docs/specs/0269-management-control-exposure.xml` (D-1..D-16, AC-001..AC-020). Stesso modulo e permesso
  `management-control.view`. Voce di menu `management-control-exposure` ("Scadenzario ed esposizione",
  `/management-control/exposure`, icona `calendar-clock`); guida in-app con la stessa chiave.
- Endpoint `GET management-control/exposure` (ExposureRequest/ExposureFilters/ExposureController, cache per attore +
  parametri + giorno): `applied`, `today`, `receivable` e `payable` (Side: open_total, overdue_total, not_due_total,
  aging a 7 fasce con le chiavi di LedgerCalculator, invoiced_in_period, settled_in_period, average_days = DSO/DPO
  pesato o null, by_status con documenti e aperto, top anagrafiche per scaduto con worst_bucket; il passivo anche
  by_cost_type), `committed` (CommittedOrderLineQuery: total, con/senza commessa, by_supplier top N, by_cost_type),
  `exposure` (receivable_open - payable_open - committed_total, by_dimension per group_by company|company_site|
  operational_site, "unattributed" ultimo), `concentration` (prime N per fatturato del periodo con quote e scaduto).
  Parametri: date_from/date_to (SOLO flussi: incassato/pagato/fatturato nel periodo; stock e aging a oggi, D-1),
  company_id[], company_site_id[], operational_site_id[], document_type all|proforma|invoice (default invoice),
  group_by (default company), top 1..25 (default 10).
- Regole: rata aperta = residual_amount > 0; passivo sugli importi rata (gia' al netto di ritenuta/ENASARCO),
  fatturato passivo = total - withholding - enasarco; fatture passive degli estratti conto provvigioni INCLUSE;
  sedi attive dall'offerta (fattura senza offerta -> "unattributed" per sede); SQL driver-dipendente (DATEDIFF vs
  julianday) in `Exposure/AgingSql`; nessun loop PHP sui documenti.
- Backend: `app/Services/ManagementControl/Exposure/{AgingSql, ExposureSupport, ScheduleAggregator (astratta),
  ReceivableScheduleAggregator, PayableScheduleAggregator, CommittedExposureAggregator,
  CustomerConcentrationAggregator, ExposureBuilder}`, `CommittedOrderLineQuery::topCommittedBy()` (metodi esistenti
  invariati). Test `tests/Feature/ManagementControl/Exposure*Test.php`; helper `mcDay`, `mcReceivable`, `mcPayable`,
  `mcExposureScenario` in ManagementControlHelpers.
- Frontend: `features/management-control/exposure-*.tsx`, `exposure-types.ts` (shape 0269; `types.ts` tiene
  `ScopeFilters`), `scope-filter-fields.tsx` (periodo + societa' + sedi, riusato dalla barra del Conto economico a
  comportamento invariato), `cost-type-label.tsx` condiviso, pagina `pages/management-control-exposure-page.tsx`,
  i18n `managementControl.exposure.*`, guide `help/content/{it,en}/management-control-exposure.ts`. Link al
  Partitario `/ledger?registry={id}` sempre sottolineati; raggruppamento come `Select`; `ComparisonBarChart` ha la
  prop opzionale `headingLevel` (2|3|4, default 3).
- Drill-down limitato: le pagine Scadenze non leggono filtri da URL (fuori scope); pulsanti semplici verso
  `/invoice-installments` e `/purchase-invoice-installments`.
- Test: backend 688/688 sulle cartelle toccate e adiacenti + Pint; frontend 403/403 mirati, ESLint e tsc puliti
  (dettaglio e verdetto del verifier nel riepilogo di sessione). Test esistente modificato: `help-guide-keys.test.ts`
  79 -> 80.
- Manuale Claude Docs: NON aggiornato (documento non condiviso con l'accesso del connettore); va aggiunto il
  capitolo "Controllo di gestione > Scadenzario ed esposizione" (testo pronto nella guida IT).
- Migliorie segnalate, non fatte: altezza del grafico proporzionale ai punti; prima colonna sticky sulle tabelle
  piu' larghe; `aria-label` + caption duplicati nelle regioni tabellari (tollerato).
- Prossimo: F3 cash flow previsionale per mese, trend mensile per funzione aziendale, incassi per conto finanziario,
  da fatturare, ciclo proforma; F4 provvigioni per agente, costo orario personale (nuovi campi, da decidere).

## Spec 0268 — Controllo di gestione F1: conto economico per commessa e per dimensione — VERDE, COMMITTATO 7276ab1c (2026-10-16)

- Spec `docs/specs/0268-management-control-income-statement.xml` (decisioni utente D-1..D-9, tecniche D-10..D-21,
  AC-001..AC-021). Fase 1 di 4: F2 scadenzario/aging/esposizione, F3 cash flow previsionale e indicatori,
  F4 provvigioni per agente e costo orario del personale (spec separate, da pianificare con `/plan-feature`).
- REVISIONE UTENTE in corso di build (D-22/D-23): la funzione aziendale e' il perno; NESSUN elenco commesse ne'
  drill-down (migliaia di commesse); andamento mensile per funzione rinviato a F3.
- Endpoint (routes/api/management-control.php, sola lettura, `AggregateCache`):
  `GET management-control/income-statement` -> `applied`, `totals` (Figures + `work_orders_count`),
  `by_business_function[]` (sempre, ripartizione D-11, "unattributed" ultimo con label null, conteggio = commesse con
  almeno una riga nella funzione, non additivo), `segments[]` (solo con `segment_by` != none: Figures intere del
  segmento + `by_business_function` annidato delle sue commesse). Parametri: `date_from/date_to` su
  `work_orders.start_date`, `company_id[]`, `company_site_id[]`, `operational_site_id[]`, `business_function_id[]`,
  `work_order_type[]`, `commercial_id[]`, `revenue_basis` contract|invoiced|collected, `segment_by`
  none|company|company_site|operational_site|work_order_type|commercial (default none).
  `GET management-control/structure-costs` (`by_cost_type`, `by_dimension`, totals; `group_by` company|company_site|
  operational_site). Shape congelata nella spec; importi `number` a 2 decimali, imponibile, calcolo in centesimi.
- Permesso unico `management-control.view` (ManagementControlPolicy abilities ['view'],
  ManagementControlAuthorization in `config/authorization.php`). Menu: sezione `management-control-section`
  ("Controllo di gestione", dopo Contabilita') con voce `management-control` ("Conto economico",
  `/management-control/income-statement`): la key della voce con route DEVE restare `management-control` perche'
  la guida in-app si aggancia a quella. Tutte le commesse visibili al titolare del permesso (D-7, niente
  WorkOrderVisibilityScope).
- CORREZIONE D-5 (2026-10-16): la funzione aziendale di una riga e' quella EFFETTIVA della categoria prodotto
  (propria, altrimenti del primo antenato: `CategoryHierarchy::effectiveBusinessFunctionSummaries()`), risolta in
  memoria con query costanti; vale anche per il filtro `business_function_id[]`. Senza, nei dati demo (prodotti in
  sottocategorie) tutto finiva in "unattributed". Nei dati demo restano a ricavo zero 11 commesse con righe di
  tipologia "Ente" (direzione RECEIVED: ricavo = sola provvigione fornitore, spec 0202) prive di provvigione
  fornitore configurata: dato demo incompleto, non un bug; le loro cifre vanno in "unattributed" (D-11).
- Backend: `App\Services\ManagementControl\{IncomeStatementBuilder, WorkOrderFigureSources, IncomeStatementGrouper
  (prospetto per funzione), IncomeStatementSegmenter (segmenti + funzioni annidate), StructureCostsBuilder}`, `App\Services\Purchasing\CommittedOrderLineQuery` (formula ODA impegnato estratta da
  WorkOrderPassiveCostAggregator, che ora la riusa a output invariato). Regole: offerta di riferimento =
  `quote_id` o min id delle sorgenti (D-10); ripartizione per funzione proporzionale al ricavo di riga, residuo di
  arrotondamento sull'ultima quota (D-11); provvigioni SUPPLIER solo se PAID, accrual firmati (D-8); fatture passive
  degli estratti conto escluse dai costi di struttura (D-9).
- Frontend: `features/management-control/*` (`income-statement-function-table.tsx` riusata da
  `income-statement-functions.tsx` e `income-statement-segments.tsx`; nessuna tabella commesse), pagina
  `pages/management-control-income-statement-page.tsx`,
  `components/ui/comparison-bar-chart{,-impl,.test}.tsx` (due serie, recharts solo nell'impl), token
  `--amount-negative` / `text-amount-negative` in `index.css` (AA su card e muted/40; `--destructive` come testo e'
  3.8:1 light e 1.3:1 dark). Il raggruppamento a sei valori e' un `Select` (SegmentedControl a sei segmenti tronca a
  375 px). i18n `{it,en}-management-control.ts`, guide `help/content/{it,en}/management-control.ts`.
- Test eseguiti (verifier, seconda passata dopo D-22, VERDE; rilancio del lead dopo la correzione D-5): Pest
  ManagementControl 17, WorkOrders 318, PurchaseInvoices 103, PurchaseOrders 259, Navigation 17, Authorization 106,
  FinancialAccounts 61, Ledger 31, ProductCategories 340, tutti verdi; Pint ok. Prima passata: suite completa 11356 verdi (unici rossi: 3
  `QuoteDocumentPdfTest` per `soffice` assente sulla macchina, ambiente). Vitest finale mirato 413/413 (suite
  completa prima passata 8295 verdi); ESLint pulito sui file toccati; `tsc -b --force` exit 0; fine riga LF ovunque.
- BUG LAYOUT risolto (segnalato dall'utente con screenshot): la tabella per funzione (13 colonne, `min-w-max`)
  allargava l'intera pagina (scroll orizzontale di documento) e la colonna ancorata finiva sopra il menu, perche'
  `SidebarInset` e' un flex item senza `min-w-0` e non poteva restringersi sotto la larghezza intrinseca del
  contenuto. Fix in `frontend/src/layouts/app-layout.tsx`: `<SidebarInset className="min-w-0">` (non in
  `components/ui/sidebar.tsx`, file shadcn gia' oltre il limite hard di 500 righe: il code-guard lo blocca).
  Verifica visiva da fare a mano: il browser automatizzato non raggiunge il dev server Vite (ascolta su [::1]:5173).
  Seconda causa (con base ricavo Fatturato/Incassato la pagina si riallargava): le etichette dell'asse valori del
  `ComparisonBarChart` agli estremi (es. "-22.424,35 €") sporgono dall'svg, che non ritaglia il testo, e creano
  overflow di documento. Fix in `comparison-bar-chart-impl.tsx`: `overflow-hidden` sul box del grafico e margine
  destro 48 px; test di sizing del componente aggiornato alla nuova stringa di classi (stesso intento).
- Revisione ui-design applicata: card segmento `bg-surface` con tabella `bg-card` dentro; contenitori scrollabili con
  `tabIndex=0 role=region aria-label`; prima colonna della tabella per funzione sticky `bg-card`; tile totali
  `grid-cols-2 sm:grid-cols-3`. Migliorie segnalate non fatte: altezza del `ComparisonBarChart` fissa (con molte
  funzioni le barre si stringono), colonna "Funzione" senza hover sulla cella ancorata.
- Test esistenti modificati (requisito cambiato dalla spec, dichiarato): `FieldCatalogueEndpointTest` (+ risorsa
  `management-control`), `help-guide-keys.test.ts` (78 -> 79 chiavi), `FinancialAccountSchemaTest` (ordine menu:
  accounting -> management-control-section -> administration), `tests/Pest.php` (+ `ManagementControlHelpers`).
- Ambiente test su questa macchina: il php di Laragon nel PATH non ha SQLite; usare
  `/c/Users/ciroc/.config/herd/bin/php84/php.exe` con `-d memory_limit=-1` (o `PHPRC` verso un php.ini copia),
  altrimenti out-of-memory. `npx tsc` risolve un pacchetto sbagliato: usare `./node_modules/.bin/tsc -b --force`.
- Segnalati, NON fatti (fuori scope): `routes/api.php` e' a 499 righe (un commento sugli import e' stato compattato
  per restare sotto il limite hard: prossimo require richiede uno split); `SegmentedControl` con `flex-1` tronca
  oltre 4-5 segmenti a 375 px; i messaggi di errore `text-destructive` in dark sono ~1.3:1 (tema globale);
  nessun seeder demo dedicato (il demo esistente alimenta gia' il modulo); nessun test su MySQL delle nuove query
  (solo `CASE WHEN`/`GROUP BY` standard).
- Manuale: guide in-app IT/EN fatte. Manuale Claude Docs "Manuale Utente QNet" NON aggiornato: il connettore
  risponde "doc non condiviso con questo accesso" (verificare la condivisione del documento con l'account del
  connettore). Da aggiungere a mano o nella prossima sessione con accesso: nuovo capitolo "Controllo di gestione >
  Conto economico" (menu e permesso `management-control.view`; filtri periodo/societa'/sedi/funzione/tipo/commerciale;
  base ricavo contrattuale-fatturato-incassato; ricavo, costi preventivati e reali per fonte, provvigioni, margine
  atteso e consuntivo, scostamenti; raggruppamenti e ripartizione per funzione; costi di struttura per tipo di costo
  e per societa'/sede). Fonte testuale pronta: `frontend/src/features/help/content/it/management-control.ts`.
## Spec 0276 — Responsabile di funzione dalla scheda utente, flag `is_manager` eliminato — VERDE, COMMITTATO DALL'UTENTE in c7bfe497 insieme a 0274/0275 (2026-10-19)

- Decisioni utente 2026-10-19: eliminato `employment_profiles.is_manager` (colonna DB + permesso di campo); la regola
  "un responsabile non riporta a nessuno" e' ELIMINATA (non trasferita: chiunque puo' avere superiori); colonna griglia
  `is_manager` -> `managed_business_functions` ("Responsabile delle funzioni", filtro set); card stats `managers` = utenti
  distinti manager_id di almeno una funzione; organigramma `Person.is_manager` = gestisce almeno una funzione (derivato,
  `withExists`), nessun blocco agli spostamenti (`manager_cannot_report` rimosso). Supera 0275 D-6.
- Contratto: `employment.managed_business_function_ids` tri-state come `business_function_ids` (assente/null = invariato,
  [] = nessuna). Scrittura `Services/BusinessFunctions/FunctionManagerWriter` (update via model -> activity log della
  funzione; la funzione passa da un responsabile all'altro, le tolte restano senza). Permesso di campo
  (`UsersAuthorization::managedFunctionsPermission`): editabile solo con campo utente editabile E `business-functions`
  `manager_id` editabile; altrimenti 422 "field not editable" (meccanismo dei permessi di campo, non 403). Il FE invia
  sempre il campo con il valore attuale (stesso valore = nessun errore).
- FE: `UserManagedBusinessFunctionsField` (user-employment-fields), riga inline in `user-record-sections.tsx`, prop
  `description` in `components/form/relation-multi-select-field.tsx`; header dettaglio (`user-detail-header.tsx`) con
  funzioni di appartenenza + gestite non di appartenenza, corona sulle gestite; aria-label corona
  `users.detail.employment.functionManager`. "Risponde a" sempre visibile.
- Migrazione `2026_10_21_101000_drop_is_manager_from_employment_profiles_table.php`. Legacy:
  `../qnet/.../UserMigrationController.php` non invia piu' `is_manager`/`livello_crm` (NON committato nel legacy).
- Verifica (verifier indipendente): `composer test` 11518 pass, 3 fail solo `QuoteDocumentPdfTest` (LibreOffice); Pint ok;
  vitest 8496/8496; `tsc -b --force` 0 errori; ESLint ok sulle aree toccate. AC-001..014 coperti; AC-011 (superiori
  importati non piu' scartati) solo indirettamente.
- Incidente 2026-10-19: un subagente ha eseguito `migrate:fresh --env=testing` senza `.env.testing` -> DB `qnet2`
  azzerato; ripopolato dall'utente (426 utenti, 28 funzioni, 16 responsabili, 206 righe pivot). Mai `migrate*`/`db:*`
  sul DB di sviluppo dai subagenti.
- Manuale Claude Docs aggiornato (Utenti: riga flag tolta, "Responsabile delle funzioni" modificabile, header; Funzioni
  aziendali). Guide in-app users, business-functions, org-chart IT+EN. Manca ancora la sezione Organigramma nel manuale.

## Spec 0275 — Funzione aziendale di appartenenza (reparto) unica tra Funzioni aziendali e scheda utente — VERDE, COMMITTATO (2026-10-19)

- Decisioni utente 2026-10-19: appartenenza e competenze restano SEPARATE (niente unione). Appartenenza = pivot
  `business_function_user` (lo stesso degli "Utenti associati"), multipla, OBBLIGATORIA per utente attivo non di
  servizio (la regola 0271 D-6 passa dalle competenze all'appartenenza; competenze facoltative, cade l'eccezione
  covers_all). Organigramma raggruppato per appartenenza. Corona gialla sul responsabile come nel segnatempo (D-9).
- Contratto: `employment.business_function_ids` (tri-state: assente/null = invariato, [] = svuota; DIVERSO da
  `job_role_ids` dove null svuota), resource `employment.business_function_ids/business_functions` e
  `UserResource.managed_business_functions` [{id,name}]. `EmploymentProfile::businessFunctions()` = belongsToMany con
  parentKey `user_id` (la riga sopravvive a `employment: null`). Errore 422 su `employment.business_function_ids`.
- Pagina funzioni: `Services/BusinessFunctions/BusinessFunctionMembershipGuard` -> 422 su `users` se una persona attiva
  resterebbe senza funzione, 409 sul delete. Griglia utenti: colonna `business_function` letta dal pivot.
- Organigramma: `unassigned_people` sostituisce `all_functions_people` (riquadro "Non assegnati", FE `UnassignedCard`,
  key `unassigned`); spostamento persona-funzione sul pivot, `from_function_id` nullable, 422 `not_in_source_function`,
  permesso `employment.business_function_ids`; `GET /org-chart/people` `business_functions` = [{id,name,is_manager}]
  dal pivot (is_manager = manager_id della funzione). Rimossi `covers_all`/`no_line_on_source`.
- FE: `UserBusinessFunctionsField` (user-employment-fields), `features/users/function-badges.tsx` (corona), elenco sola
  lettura "Responsabile delle funzioni"; i18n `{it,en}-users-employment.ts`, `{it,en}-org-chart.ts`; guide in-app
  users, business-functions, org-chart IT+EN.
- Verifica (verifier indipendente): AC-001..AC-024 PASS; `composer test` 11502 pass, 3 fail solo `QuoteDocumentPdfTest`
  (LibreOffice assente); Pint ok; vitest 8488/8488; `tsc -b --force` EXIT=0; ESLint 1 errore PREESISTENTE in
  `features/quotes/column-renderers.tsx:12` (fuori scope). Test cambiati per requisito (REQUIREMENT CHANGED): fixture
  utenti attivi con `business_function_ids`/`memberOf()` (helper `membershipFunctionId()` in tests/Helpers/UserHelpers),
  test OrgChart 0273, test FE organigramma ("Tutte le funzioni" -> "Non assegnati").
- Manuale Claude Docs: aggiornati Funzioni aziendali (Utenti associati) e scheda utente (Funzioni aziendali di
  appartenenza). Da fare: sezione Organigramma (manca tutto il modulo HR 0271-0274 nel manuale).
- Dati: 54 utenti attivi locali senza appartenenza: la regola scatta al primo salvataggio della loro scheda.
- Organigramma, fuori spec (richiesta utente 2026-10-19): fix "Sposta in..." che non apriva il dialog (bug 0274: il pan
  di `use-chart-viewport.ts` catturava il puntatore anche per eventi React risaliti da portal, ora esce se il target non
  e' nel DOM del viewport; test `use-chart-viewport.test.ts`). Animazioni espandi/comprimi solo CSS `tw-animate-css`:
  transizione left/top e `d` degli archi, ingresso fade/zoom, uscita trattenuta per `EXIT_MS` (`use-exiting-cards.ts`,
  card in uscita `aria-hidden`+`inert`), righe del pannello persone, `motion-reduce` rispettato. Resa visiva non ancora
  vista nel browser (DB locale `qnet2` azzerato per errore da un'altra sessione, ripristino in corso con l'utente).
- Vista Funzioni (utente 2026-10-19, supera 0274 D-2): le persone sono card sotto la funzione (`chartChildren`: prima
  persone, responsabili in testa poi per nome, poi sotto-funzioni; idem "Non assegnati"); pulsante membri e pannello
  restano. Limite noto: funzione con >4 persone E sotto-funzioni = una riga larga (la griglia a colonne scatta solo con
  figli tutti foglie). Responsabile di funzione emesso solo se attivo e non di servizio (`functionRows`, leftJoin).
  Guida org-chart IT+EN aggiornata (ceduta dalla sessione 0276, frasi 0276 mantenute).
- Prossimo: spec 0276 (sessione separata) elimina `employment_profiles.is_manager` e rende "Responsabile delle
  funzioni" modificabile dalla scheda utente (supera 0275 D-6); la corona d'angolo della card organigramma dipende
  ancora da `is_manager`.

## ORGANIGRAMMA GRAFICO "STILE BIZNEO" (SPEC 0274) — VERDE, COMMITTATO (2026-10-19)

- Decisioni utente 2026-10-19: l'albero a righe di 0273 e' ELIMINATO; resta un organigramma grafico (riquadri dall'alto
  al basso, linee curve SVG, pallino "+N", zoom), persone di una funzione in un pannello laterale (non riquadri),
  spostamenti con menu + trascinamento tra riquadri. Backend e contratto API invariati.
- Nuovi: `chart-layout.ts` (layout puro: genitore centrato, foglie >4 in griglia da 4 colonne, radici a scaffali oltre
  1240 px, linee calcolate dalle posizioni finali), `use-chart-viewport.ts` (zoom 0.3-1.5, pan trascinando lo sfondo),
  `components/org-chart-canvas.tsx` (treeitem piatti con aria-level/setsize/posinset, radici con gerarchia prima),
  `org-chart-card.tsx`, `org-members-sheet.tsx`. Eliminati `org-tree.tsx`, `org-node-content.tsx`, `org-tree.test.tsx`.
  `tree-nodes.ts`: `chartChildren` (le persone sotto una funzione non sono riquadri) e `memberPeople`;
  `use-org-tree-state` apre di default solo le radici.
- Test: `chart-layout.test.ts` nuovo; `org-chart-page.test.tsx` e `org-chart-move.test.tsx` riscritti sulle viste
  grafiche (REQUIREMENT CHANGED, spec 0274). Vitest org-chart + help 183/183, ESLint ok, `tsc -b --force` ok,
  verifica visiva con Chrome su dati locali (nessuno scroll orizzontale a 1440 e 375).
- Coordinamento: la sessione della spec 0275 (appartenenza a funzione via pivot `business_function_user`) cambia il
  backend dell'organigramma e rinomina `all_functions_people` -> `unassigned_people`; tocca il frontend org-chart
  SOLO dopo il via di questa sessione (`types.ts`, `build-function-tree.ts`, etichette `allFunctions`/`allFunctionsHint`,
  `isMovable` per i non assegnati).
- Prossimi passi: commit su richiesta; manuale Claude Docs sezione Organigramma.

## SEED PRODUZIONE: PRODOTTI DI COSTO NUOVI (NON [OLD]) — VERDE, COMMITTATO (2026-10-16)

- Decisioni utente 2026-10-16: le voci di costo legacy diventano prodotti NUOVI e attivi in qnet-2, catalogo SEPARATO
  da "[OLD] Costi" (nessun `old_id`/`old_source`: l'import `cost-products` crea ancora le copie [OLD] e le righe COSTO
  migrate di offerte/commesse puntano a quelle). Esclusi i livelli dipendenti (`user_levels`) e i record di prova
  legacy (Alpha, oust, Motosega). Nomi ripuliti (lista confermata dall'utente), importo legacy = cost = price,
  IVA 22% solo dove il legacy aveva rate_id 6, altrimenti vuota.
- Revisione utente 2026-10-16: tolti dalla lista Pompa derattizzante, i 3 veicoli, Commissioni partner formazione,
  Contributo discenti, Pagamento fornitore; spariscono le foglie Veicoli e Attrezzature. Ora 10 prodotti (Note spese 6,
  Articoli 4); `QualificaProductionDataSeederTest` 557 -> 550. Il seeder non cancella: le righe gia' seminate restano.
- Nuovi `database/seeders/QualificaCatalog/CostCatalogue.php` (dati: radice "Costi", foglie Note spese 6, Articoli 4
  = 10 prodotti) e `database/seeders/QualificaCostProductSeeder.php` (ProductService::create,
  usages [COST], radice non selezionabile senza funzione aziendale, idempotente su (name, category), IVA 22% adottata
  per percentuale come `CatalogProducts::plusVatRate`). Chiamato in `QualificaProductionDataSeeder` subito dopo
  `QualificaCatalogSeeder`.
- Test: nuovo `tests/Feature/Seeding/QualificaCostProductSeederTest.php` (3); `QualificaProductionDataSeederTest`
  conteggio prodotti 540 -> 550 (REQUIREMENT CHANGED). Seeding 162/162, Migration 416/416, Products 229/229, Pint ok.
- Manuale: guida in-app `migrations` IT+EN (nota Prodotti di costo) e manuale Claude Docs (paragrafo dopo
  "[OLD] Costi") aggiornati. Help vitest 131/131, ESLint ok, tsc -b ok.

## Spec 0273 — Modulo HR: Organigramma — VERDE, COMMITTATO (2026-10-16, branch feature/hr)

- Spec `docs/specs/0273-hr-org-chart.xml`; `docs/product/hr.md` §6 aggiornato. Nessuna migrazione, nessun importer.
- Pagina Amministrazione > Organigramma (`/org-chart`, permesso `org-chart.view` generato da `OrgChartAuthorization`
  come request-statistics): schede Funzioni (albero `parent_id`, persone = righe `employment_product_lines`, nodo
  "Tutte le funzioni" per covers_all) e Persone (Riporta a; chi ha piu' responsabili compare sotto ciascuno).
- API: `GET /api/org-chart/functions|people` (6 query per vista); spostamenti `POST /api/org-chart/people/{user}/manager`
  {from_manager_id,to_manager_id}, `/functions/{id}/parent` {parent_id}, `/people/{user}/function`
  {from_function_id,to_function_id} (righe di F1 -> una riga F2 a funzione intera). Vincoli: anti-ciclo persone
  (`TimeEntrySubordinateResolver`) e funzioni (`BusinessFunctionService::assertNoCycle`, ora pubblico, messaggi in
  `lang/*/business_functions.php`), is_manager, covers_all, persona senza profilo = 422. Permessi: users.update /
  business-functions.update + campo modificabile (403).
- Registro: activity log `org-chart`/evento `moved` sul soggetto; `ActivityLogEntryResource` lo rende come una voce
  {field: manager|function_parent|person_function}; frontend activity-log conosce l'evento `moved`.
- Frontend: `features/org-chart/` (albero accessibile role tree, ricerca, espandi/comprimi, "Sposta in…" + drag
  @dnd-kit solo puntatore fine), rotte HR in `routes/hr-routes.tsx` e Task in `routes/task-routes.tsx` (router.tsx 439).
- Note: `DemoOpportunitySeederTest` e' flaky (dati casuali, spec 0077), non legato a 0273. Manuale Claude Docs: nuova
  sezione Organigramma da scrivere.

## Spec 0272 — Modulo HR passo 2: scheda dipendente (storico contrattuale, CCNL, IBAN, recapiti di lavoro, cessati) — VERDE, COMMITTATO 1660f477 (2026-10-16, branch feature/hr)

- Spec `docs/specs/0272-hr-employee-record.xml` rev.3; `docs/product/hr.md` §3/§4 aggiornati. Nessun importer nuovo.
- Storico: `employment_contracts` (append-only: si cancella solo l'ultima, 409 altrimenti; `ends_on` derivata). API
  `GET|POST /api/users/{user}/employment-contracts`, `DELETE .../{employmentContract}` (isolamento nel controller, testato;
  utente senza profilo = lista vuota). `EmploymentContractService::syncCurrent` scrive la COPIA vigente su
  `employment_profiles.relationship_type/company_id`, non piu' scrivibili da `PUT /api/users`.
- CCNL: `collective_agreements` + `collective_agreement_levels` (array `levels` sostituisce i livelli, livello usato = 422),
  `/api/collective-agreements`, `/api/collective-agreement-levels/for-select` (label "CCNL – codice"), menu Configurazione.
  `qualification_type` (Inquadramento) e `QualificationTypeEnum` ELIMINATI (decisione utente).
- Dati retributivi: permesso `users.manageCompensation` (solo super-admin di default). IBAN cifrato (`ValidIban`, mod-97),
  IBAN + intestatario + retribuzione in `$hidden` (fuori dall'activity log); senza permesso chiavi assenti, scrittura 403.
- Recapiti: `contacts.is_work` (flag mostrato solo nella scheda utente). Cessazione: salvataggio inattivo se
  `terminated_at` <= oggi; comando `employment:daily-sync` alle 00:30 (disattiva e riallinea la condizione vigente);
  colonna griglia `employment_status` (employed/terminated).
- Rollback a step fisso: QuoteWorkflowMigrationTest 208, SupplierCommissionDirectionTest 72, PurchaseOrderMigrationTest 57
  (7 migrazioni 0272).
- Da fare: manuale Claude Docs (CCNL, Utenti > Rapporto contrattuale / Storico / Pagamento / Recapiti di lavoro /
  Cessazione, colonna Stato). `router.tsx` a 493 righe: il prossimo modulo deve estrarre le rotte HR.

## Spec 0271 — Modulo HR passo 1: Mansioni, Attivita', societa' delle funzioni, funzione obbligatoria — VERDE (2026-10-16, branch feature/hr)

- Documento di prodotto `docs/product/hr.md` (campi, non tabelle; decisioni D-1..D-9). Spec
  `docs/specs/0271-hr-org-structure.xml` rev.3: Divisioni ELIMINATE (utente), posizioni non servono, nessun importer
  (il legacy non ha questi dati), mansione = catalogo proprio SENZA legame con ruoli/permessi, niente SSO, Controllo di
  Gestione fuori. Mai sigle di dominio (BU/BS): "business unit" / "business service" per esteso (utente).
- Backend: tabelle `job_roles`, `job_activities`, `job_activity_job_role`, `employment_profile_job_role`,
  `business_function_company` (unique con nomi corti: MySQL max 64 caratteri, SQLite non lo intercetta); drop
  `employment_profiles.job_description` (valori persi, decisione utente); permesso di campo rinominato in
  `employment.job_role_ids`. CRUD `/api/job-roles`, `/api/job-activities` (+ for-select solo auth, ADR 0011; delete in
  uso 409, libero 204), tabelle omonime, permessi `job-roles.*`/`job-activities.*`, menu Configurazione. Rotte HR
  raggruppate in `routes/api/hr.php` (anche business-functions, per stare sotto 500 righe in `api.php`).
- Regola funzione obbligatoria (D-6, `Services/Users/BusinessFunctionCoverageGuard`): utente attivo, non di servizio,
  senza `covers_all_product_categories` deve avere almeno una riga `employment.product_lines`; solo via API (create,
  attivazione, update che tocca `employment`); 422 su `employment.product_lines`; l'import legacy la salta
  (`enforceCoverage: false`).
- Funzioni aziendali: `company_ids`/`companies`. Scheda utente: `employment.job_role_ids` (tri-state) /
  `job_roles`; griglia utenti colonna `job_roles`; segnatempo team `job_roles: string[]`.
- Frontend: `features/job-roles/`, `features/job-activities/`, i18n raggruppati in `{it,en}-hr.ts`, guide in-app
  `job-roles`/`job-activities` + aggiornate users, business-functions, time-entries.
- Test con rollback a step fisso aggiornati (+4 migrazioni 0271): QuoteWorkflowMigrationTest 201,
  SupplierCommissionDirectionTest 65, PurchaseOrderMigrationTest 50.
- Da fare: manuale Claude Docs (sezioni Mansioni, Attivita', Funzioni aziendali > Societa', Utenti > Mansioni e regola
  della funzione obbligatoria, segnatempo team). `OperatorRoster` porta ancora la colonna mansione del foglio cliente
  (inutilizzata): potrebbe popolare il catalogo Mansioni. Il legacy invia ancora `job_description` (ignorato).
- Prossimo passo HR: scheda dipendente estesa (storico contrattuale, CCNL e livelli, IBAN, recapiti di lavoro).

## Spec 0270 — Costi effettivi Commessa sul blocco catalogo + righe — VERDE, NON COMMITTATO (2026-10-16)

- Spec `docs/specs/0270-work-order-costs-catalog-editor.xml` (D-1..D-5, AC-001..AC-008). Solo frontend.
- `QuoteLineEditorFrame` (`features/quotes/line-editor/quote-line-editor-frame.tsx`) estratto da `QuoteLineEditor`:
  cornice unica, pannello righe (titolo, conteggio, vuoto, totali `{net, vat, gross, totalLabel}`), slot catalogo.
- Costi commessa: `WorkOrderCostsLinesField` = frame + `QuoteLineCatalog` (variant cost, `{kind:'all'}`, duplicati
  ammessi) + `WorkOrderCostCard` (livelli 1-2) + `WorkOrderCostCardDetails` (livello 3: data, tipo costo, fornitore,
  riferimento, descrizione). Hook `useWorkOrderCostsEditor.addProducts(items)` (createEmptyCostRow +
  costRowPatchFromProduct) al posto di `addRow`/`changeProduct`. Cancellato `WorkOrderCostRow` + grid classes.
- i18n: + `workOrders.costs.editor.rowsTitle/totalLabel`; - `editor.add`, `editor.product`, 8 `editor.headers.*`.
- Test: `work-order-costs-section.test.tsx` adeguato (REQUIREMENT CHANGED) + AC-003 duplicati/usage COST + totale.
  Lead: 175 file / 1419 test verdi (quotes, request-management, costi commessa, commesse, i18n); tsc -b pulito.
- Manuale: guida in-app `work-orders` IT/EN (sezione `costs`); manuale Claude Docs: Commesse ancora "in fase di
  sviluppo" (§4-bis), nessun aggiornamento.
- Verifier: AC-001..AC-008 PASS (vitest completo 8304/8304, tsc -b ed ESLint puliti). Riserva chiusa dal lead: nuovo
  `work-order-cost-card.test.tsx` (importi live, tutti i campi -> onChangeField, rimozione, sola lettura, triade errore).
  Il seeder costi (`cad1b2d5`) e' un lavoro parallelo, non di questa spec.

## Spec 0269 — Editor catalogo + righe nel form Offerte (Prodotti e Costi) — VERDE, COMMITTATO 119d980c (2026-10-16)

- Spec `docs/specs/0269-quote-lines-catalog-editor.xml` (D-1..D-8, AC-001..AC-013). Solo frontend, backend invariato.
- L'editor della 0268 e' ora modulo condiviso `features/quotes/line-editor/`: `QuoteLineEditor`, `QuoteLineCatalog`,
  `QuoteCatalogItem`, `QuoteLineCard`, `useQuoteLineEditor`, `useQuoteLineCatalog`, `useNextPageSentinel`, tipi in
  `types.ts` (`QuoteCatalogProduct`, `QuoteCatalogScope` {categories + `fullCatalog` | all},
  `QuoteLineCommissionsAdapter`, `QuoteLineCardSlots`). Richieste/Iscritti lo montano da `RequestOfferLinesField`.
- Offerte: `QuoteOfferTab` (scope opportunita', chip "Tutto il catalogo" = vecchio Sblocca con `unlockDialog`, single
  mode, slot provvigioni `QuoteLineCommissionsAction` + `QuoteLineAdditionalDescription`), `QuoteCostsTab` (`{kind:'all'}`,
  usage COST, `allowDuplicates`, slot `QuoteCostAssociationSelect`). Provvigioni in `useQuoteLineCommissions`
  (default al multi-add tutto-o-niente, conferma + default nella sostituzione single, re-sync al cambio ruoli).
- Helper `appendProductRows` (use-quote-lines-field.ts) con `client_key` per riga; `seedSoleProductRows` lo riusa.
- Cancellati: `QuoteLinesField`, `QuoteLineRow`, `quote-line-grid.ts`, `addRow` e i parametri remember* di
  `useQuoteLinesField`, chiavi i18n orfane (`offerTab.unlock/relock/hintScoped/hintUnlocked/hintSingleCategory`,
  `form.lineProduct/lineAdd/linesEmpty/lineRemoveHeader`). Tipi `QuoteLineRowErrors`/`QuoteCostOfferLineOption` in
  `quote-line-types.ts`, `knownVatRatesFrom` in `quote-line-values.ts`. i18n editor: `quotes.lineEditor.*`.
- Test: nuovi `use-quote-line-commissions.test.tsx`; riscritti `quote-offer-tab.test.tsx`, `quote-costs-tab.test.tsx`,
  `quote-line-additional-description.test.tsx`; adeguati `quote-form-body`/`quote-form-seeded-products` (REQUIREMENT
  CHANGED dichiarati). Lead: 222 file / 1793 test verdi su quotes, request-management, help, i18n, fatture, costi commessa,
  commesse, contratti; tsc -b pulito.
- Manuale: guide in-app `quotes` IT/EN; manuale Claude Docs (sezione righe offerta/costo + risoluzione problemi).
- Verifier: AC-001..AC-013 PASS (vitest completo 8298/8298, tsc -b pulito, backend non toccato). Riserve chiuse dal
  lead: `knownVatRatesFrom` interno a `QuoteLineEditor` (prop `knownVatRates` rimossa, `quote-line-values.ts` torna a
  311 righe come prima); nuovo `line-editor/quote-line-card.test.tsx` (codice, UdM, IVA manuale -> cache, slot);
  AC-007 apre `QuoteCommissionsDialog` dalla card; AC-010 test catalogo -> PATCH in `quote-detail-inline-edit.test.tsx`.
  Ultimo giro mirato: 223 file / 1798 test verdi.
- Ritocco utente: catalogo e righe sono UN blocco (`QuoteLineEditor`: una cornice `rounded-lg border bg-surface`,
  divisorio `border-r` affiancati / `border-t` impilati); i due pannelli non hanno piu' bordo proprio.
- Soft limit: `quote-detail-inline-edit.test.tsx` ora 327 righe (era 288): candidato a split del harness in fixture.
- Segnalato, fuori scope: `features/quotes/column-renderers.tsx` ha un errore ESLint react-refresh preesistente.

## Spec 0268 — Editor "Linee di prodotto" catalogo + righe (Richieste/Iscritti) — VERDE, COMMITTATO 8dd24577 (2026-10-16)

- Spec `docs/specs/0268-offer-lines-catalog-editor.xml` (D-1..D-7, AC-001..AC-017). Decisioni utente: layout catalogo a
  sinistra + righe a due livelli a destra; tutte e tre le superfici (dialog cella, scheda, Nuova richiesta) di Richieste
  e Iscritti; prodotto gia' nelle righe = badge "Gia' aggiunto" non selezionabile; filtri solo chip categoria.
- Backend: `ProductForSelectResource` espone `meta.description` (string|null, squished, additivo); `subtitle` invariato.
- Frontend: `features/request-management/offer-lines-editor/` (`OfferLinesEditor`, `OfferLinesCatalog`,
  `OfferCatalogItem`, `OfferLineCard`, hook `useOfferLinesEditor`/`useOfferLinesCatalog`/`useNextPageSentinel`, tipi
  `OfferCatalogProduct`/`OfferLineProductView`). `RequestOfferLinesField` monta `OfferLinesEditor` al posto di
  `QuoteLinesField`; il form Offerte (`features/quotes/**`) NON e' toccato. Riusati: `seedSoleProductRows` (multi-add,
  riempie prima le righe pristine), `useQuoteLinesField` (setField/removeRow/setProduct per la sostituzione single-mode),
  `aggregateLines`, `categoryPathLabel`, `useForSelectLabels` (nome dei prodotti di righe create dall'autofill).
- Contratti invariati: valori form `QuoteLineFormValues[]`, PATCH cella `offer_lines`, submit scheda/creazione.
- i18n nuove: `requestManagement.offerLines.catalog.*`, `requestManagement.offerLines.rows.*` (IT+EN). Etichette riga
  riusano `quotes.form.line*` ("Quantita' riga N", "Rimuovi riga N"...).
- Test esistenti adeguati (REQUIREMENT CHANGED, commentati): `offer-lines-dialog.test.tsx`,
  `request-work-panel-offer-lines.test.tsx`, `use-offer-lines-autofill.test.tsx` (niente combobox "Prodotto riga N",
  riga pristine non disegnata, probe autofill distinto dalla lista catalogo per `limit: 2`).
- Manuale: guide in-app IT/EN `request-management` (nuova sezione `offer-rows-editor`); manuale Claude Docs aggiornato
  (blocco "Editor delle righe dell'offerta" + 3 passaggi). Il PDF derivato va rigenerato.
- Ritocchi utente dopo il build: pannello righe su `bg-surface` (gemello del catalogo) con card `bg-card shadow-sm`,
  fascia importi `bg-muted/40`, numero riga `bg-primary/10`; select IVA alto come gli input (`min-h-8`); etichetta
  "Righe offerta" sopra l'editor solo sr-only (`MetaFieldRowContext`). Guida Iscritti IT/EN: sezione `offer-rows-editor`.
- Verifier: AC-001..AC-017 PASS (AC-016 responsive da guardare a mano a 375/1024px). Pest completo 11345/11349: i 3 rossi
  sono `QuoteDocumentPdfTest` (LibreOffice assente su questa macchina, ambientale). Vitest completo 8301/8302: 1 flaky
  da carico (`purchase-request-submit.test.tsx`, verde da solo). tsc -b e ESLint puliti.
- Prossimo: spec 0269 = stesso editor nel form Offerte (schede Prodotti E Costi; "Sblocca" diventa chip "Tutto il
  catalogo" con la stessa conferma) — decisioni utente 2026-10-16, da pianificare.
- Da sistemare (segnalato): questo HANDOFF pesa ~630 KB, ben oltre i 50 KB: va archiviato in `docs/handoff-archive/`.

## MERGE main (2f8d47ce) -> feature/amministrazione — RISOLTO (2026-10-16)

- Conflitti: HANDOFF (voci di entrambi i lati mantenute) e i contatori `migrate:rollback --step`, che sommano le 5
  migrazioni liquidazioni agenti (locale) e le 3 migrazioni commessa multi-offerta (main):
  `PurchaseOrderMigrationTest` (46), `QuoteWorkflowMigrationTest` (197), `SupplierCommissionDirectionTest` (61).
- Da sistemare (segnalato, non fatto): numero spec 0267 duplicato (`0267-agent-settlement-documents.xml` e
  `0267-multi-quote-work-order.xml`); timestamp migrazioni condivisi `2026_10_19_100000` (3 file) e
  `2026_10_19_100100` (2 file), ordine deciso dal nome file, nessuna dipendenza tra loro.

## MERGE origin/feature/amministrazione (aadb1494) -> locale — RISOLTO (2026-10-16)

- Conflitti: HANDOFF (voci di entrambi i lati mantenute) e i contatori `migrate:rollback --step`, che sommano le 5
  migrazioni spec 0267 (locale) e le 2 migrazioni geo spec 0189 G-28 (remoto): `PurchaseOrderMigrationTest` (43),
  `QuoteWorkflowMigrationTest` (194), `SupplierCommissionDirectionTest` (58).

## Spec 0267 — Liquidazioni agenti M3 (documento) + M4 (passivo) — VERDE, COMMITTATO (2026-10-15)

- Spec `docs/specs/0267-agent-settlement-documents.xml` (D-1..D-9, AC-001..AC-024 PASS dal verifier dopo i fix).
- Ciclo estratto: DRAFT -> ISSUED -> DOCUMENT_RECEIVED -> APPROVED (immutabile); CANCELLED solo da ISSUED.
  `CommissionSettlementStatus::validStatuses()` = ISSUED|DOCUMENT_RECEIVED|APPROVED, usato per massimale ENASARCO,
  delete parametri ENASARCO, status movimento `issued`.
- Endpoint (routes/api/agent-commissions.php): POST/DELETE `commission-settlements/{id}/document`
  (`SettlementDocumentController`, `SettlementDocumentService`, `DocumentToleranceChecker`), GET `approval-draft`,
  POST `supplier`, POST `approve` (`SettlementApprovalController`, `SettlementApprovalService`, `AgentSupplierService`,
  `AgentSupplierCard`). Trait `RespondsWithSettlement`. Permessi `commission-settlements.receiveDocument` / `.approve`;
  approve richiede anche `purchase-invoices.create`, creazione fornitore anche `registries.create`.
- Tolleranza `config('commissions.document_tolerance')` (env COMMISSION_DOCUMENT_TOLERANCE, default "1.00"); differenza
  entro tolleranza accettata senza rettifica. Fattura passiva creata solo via `PurchaseInvoiceService::create`, link
  `commission_settlements.purchase_invoice_id` (unique); stato pagamento estratto letto dalle rate.
- Fatture passive: `withholding_amount`, `enasarco_amount` (default 0) su tutte; payable = total - trattenute; rate sul
  payable; congelate dopo il primo pagamento (422 payments_frozen); `LinkedSettlementGuard` -> 409 `settlement_locked`;
  colonna `payable_amount`; partitario fornitore sul payable.
- `referents.supplier_registry_id` (is_supplier, bloccato con estratti APPROVED); profilo fiscale `payment_method_id`,
  `cost_type_id` = default dell'approvazione.
- REQUIREMENT CHANGED: step dei rollback in PurchaseOrderMigrationTest (40), SupplierCommissionDirectionTest (55),
  QuoteWorkflowMigrationTest (191); test tabelle/permessi estratti, PurchaseInvoicesTableTest, ReferentMetaTest,
  DemoCommissionSettlementSeederTest, agent-commissions-i18n-parity (5 stati).
- Verifica: Pest parallelo 11196/11200 (3 fail = LibreOffice `soffice` assente, ambiente; serve `extension=zip` nel
  php.ini, il php.ini di Laragon lo ha commentato), Vitest 8248/8252 (timeout flaky fuori scope, verdi in isolamento),
  `tsc -b --force` pulito, Pint/ESLint puliti.
- Attenzione: gli agenti su Windows possono salvare in CRLF file che nell'indice sono LF: controllare
  `git ls-files --eol | grep "i/lf.*w/crlf"` prima del commit.
- Avvisi 300 righe: CommissionSettlementsTableDefinition (325), PurchaseInvoiceService (321), locale
  {it,en}-commission-settlements.ts (330).
- Manuale Claude Docs: da aggiornare (Estratti, Fatture passive, Scadenziario passivo, Referenti, Profili fiscali, Partitario).
- Fix post-commit: `commission_settlements.status` era string(16) e `DOCUMENT_RECEIVED` (17) falliva su MySQL
  (500 su POST /document); migrazione `2026_10_19_100400_widen_status_on_commission_settlements_table` -> string(32).
  Lezione: SQLite non applica ne' riporta la lunghezza dei varchar, i test non la intercettano: verificare a mano la
  lunghezza delle colonne stringa contro i valori enum nuovi. Step rollback ora 41 / 56 / 192.
- Prossimo: resto di M3 (PDF estratto, email massiva, export paghe) e M5.
## COMMESSA MULTI-OFFERTA (spec 0267) — VERDE, NON COMMITTATO (2026-10-15)

- Flag di ramo root-owned `product_categories.multi_quote_work_order` (default false, `MultiQuoteWorkOrderInheritance`
  in RootOwnedSettingsWriter, show espone `multi_quote_work_order_source_category`). Radice del ramo:
  `CategoryRootResolver::rootSummariesFor()`.
- `work_orders.quote_id` NULLABLE: null = commessa multi-offerta. `WorkOrder::isMultiQuote()`, `sourceQuoteIds()`,
  scope `withQuoteLineOfQuote()` / `withQuoteLineOfRegistry()`; factory state `multiQuote()`. Il `down()` della
  migrazione lancia se esistono multi-offerta.
- Creazione SOLO dalla griglia Contratti (selezione 2..50, azione massiva "Programma commessa unica"):
  `GET /api/contracts/multi-programmable-lines?contract_ids[]=` e `POST /api/contracts/multi-quote-work-orders`
  (MultiQuoteContractGate: policy `program` -> 403 "This action is unauthorized."; contratto non ClosedWon -> 422
  `contract_ids` `work_orders.multi_quote.contracts_not_won` con "codice (cliente)", prima della cumulabilita' D-14 —
  decisione utente 2026-10-15, prima era un 403 vuoto mostrato come "errore imprevisto"; MultiQuoteWorkOrderProgrammer ->
  WorkOrderService::create). Regole righe in `MultiQuoteLineRules`: ricavo, ramo con flag, stessa radice, >=2 offerte;
  su PATCH della multi solo rimozione. `attribute_values` non inviato dal dialog FE (il Programma singolo non li ha).
- Fatturazione per offerta: `proforma_requests.quote_id` NOT NULL (backfill), default dal creating hook di
  ProformaRequest sulle mono-offerta (LogicException sulle multi senza quote_id). Generazione per (offerta, tipo,
  fornitore); bozza fattura, `invoices.quote_id` e InvoiceLineGuard sull'offerta della richiesta.
- Lettura: WorkOrderResource `is_multi_quote` + `source_quotes` (WorkOrderSourceQuotes), campi a offerta singola null
  sulle multi; griglia con clienti/codici uniti da " · " (ordinamento: multi in fondo); tab Commesse contratto/
  anagrafica e for-select via scope; budget costi da tutte le offerte; task da modello senza anagrafica; email senza
  variabili cliente. Eliminazione offerta bloccata anche da righe in commesse multi.
- Dettaglio multi-offerta (decisione utente 2026-10-15): rimossa la sezione "Offerte di origine"
  (`work-order-source-quotes-section.tsx`, chiavi i18n `sourceQuotes`/`openContract` cancellati). Ogni riga prodotto
  porta `registry` {id,name} del cliente della propria offerta: `WorkOrderResource.quote_lines[].registry`
  (eager `quoteLines.quote.opportunity.registry` in DETAIL_RELATIONS) e `contract-data lines[].registry`
  (LINE_RELATIONS). FE la mostra solo con `is_multi_quote` (prop `showRegistry` Section -> Table -> Row ->
  ProductCell; lista semplice senza view_contract_data). `source_quotes` resta nella risorsa ma il FE non lo legge
  piu': valutare se rimuoverlo. Spec 0267 D-7 aggiornata; guida IT/EN e manuale Claude Docs aggiornati.
- Test: `tests/Feature/{Contracts,WorkOrders}/MultiQuote/`, `ProductCategoryMultiQuoteWorkOrderTest`,
  `MultiQuoteProformaRequestsTest`, `MultiQuoteInvoiceTest`, `MultiQuoteWorkOrderSchemaTest`. Step di rollback
  alzati di 3 in 3 test di migrazione (REQUIREMENT CHANGED). Suite: 11201/11205 (rossi solo QuoteDocumentPdfTest,
  LibreOffice). Vitest 8238 verdi, tsc -b pulito.
- Guide in-app aggiornate (contracts, work-orders, product-categories, proforma-requests; section id
  `multi-quote-work-order` / `program-multi-quote-work-order`); guida categorie divisa in `content/*/parts/`.
- Aperti: `WorkOrdersTableDefinition.php` 402 righe (soft limit, valutare split); FormRequest dei nuovi endpoint
  validano prima del 403 (come il Programma singolo); errore ESLint preesistente in `quotes/column-renderers.tsx:11`.
  Fuori scope: aggiungere righe a una multi esistente, migrazione legacy.
- Spec 0267 D-14/D-15 (decisioni utente 2026-10-15): azione "Programma commessa unica" sempre cliccabile; un contratto
  non cumulabile (offerta senza righe di un ramo con flag) -> 422 `contract_ids` tradotto ("codice (cliente)") mostrato
  nel popup (`multiProgramRefusal`, nessun retry). Messaggi multi-offerta in `lang/{it,en}/work_orders.php`. Seed:
  "Formazione" cumulabile E con contratto (D-15, CatalogRootRules; APL resta senza contratto).
- Spec 0267 D-16: coerenza categorie, multi-offerta richiede "Prevede un contratto" (RootSettingsCoherence, 422 sul campo
  cambiato, `lang/{it,en}/product_categories.php`; form: `lockedReason` su product-category-root-flag-field).
- Colonna "Categoria madre" (`root_categories`, radici distinte dei prodotti delle righe di ricavo, filtro set per nome
  radice) dopo "Linee di prodotto" in Contratti e Offerte (`App\Tables\Shared\RootCategoriesColumn`, istanza iniettata
  per memoizzare CategoryRootResolver).
- ATTENZIONE: un'altra sessione Claude sta aggiungendo `registry` per riga in work-orders/work-order-contract-data
  (frontend): tsc -b rosso sulle sue fixture finche' non chiude; non e' parte di 0267. Al commit separare i file.
- Colonna "Linee di prodotto" (`offer_lines`, sola lettura, filtro set per prodotto) nelle griglie Contratti e Offerte
  via `OfferLinesColumn::readOnlyDeclaration()`; la proiezione condivisa porta anche `category_name`, mostrato come
  "Prodotto (Categoria)" da `features/quotes/offer-lines-cell.tsx` (Gestione Richieste resta coi soli nomi). Liste
  colonne di ContractTableTest/QuoteTableTest aggiornate (REQUIREMENT CHANGED).
- Decisione utente 2026-10-15: "Offerta unica per opportunita'" (`single_quote_per_opportunity`) vale Si' di default
  sulle NUOVE radici (RootOwnedSettingsWriter `?? true`, form `use-product-category-form.ts`). Categorie esistenti e
  default della colonna DB invariati. Test `ProductCategorySingleQuoteTest` aggiornato (REQUIREMENT CHANGED);
  guida in-app IT/EN e manuale Claude Docs (tabella regole categoria) aggiornati.

## CATALOGO: SOTTOCATEGORIE SOA E SICUREZZA CON PREFISSO DEL RAMO — VERDE, NON COMMITTATO (2026-10-15)

- Rinomina (decisione utente): `Ente SOA` -> `SOA - Ente`, `Consulenza SOA` -> `SOA - Consulenza`
  (`SoaCatalogue`); `Corsi di formazione`/`Documenti`/`Visite mediche`/`Sopralluogo`/`Trasferta` ->
  `Sicurezza - <nome>` (`SafetyCatalogue`). Il nome resta la chiave naturale del catalogo.
- Installazioni gia' seminate: `QualificaCatalog/CatalogCategoryRenames` (chiamata in testa a
  `QualificaCatalogSeeder::seedCatalog()`) rinomina in place i SOLI figli diretti della radice del ramo (stesso id,
  prodotti/opportunita' agganciati); no-op se il nuovo nome esiste gia'. Una "Documenti" fuori ramo non si tocca.
- Test: nuovo `QualificaCatalogCategoryRenamesTest`; liste letterali aggiornate in `QualificaCatalogSeederTest` e
  `QualificaQuoteLayoutSeederTest` (REQUIREMENT CHANGED). Suite verde salvo i 3 `QuoteDocumentPdfTest` (LibreOffice).
- Manuale Claude Docs aggiornato (paragrafo macrocategorie SOA/Sicurezza); guide in-app: nessun impatto.

## SEED SAMPLE: SOLO CATEGORIE VISIBILI E SELEZIONABILI — VERDE, NON COMMITTATO (2026-10-15)

- `qualifica:seed-sample` (catena e `--bulk`) ora pesca solo categorie `is_selectable` E effettivamente attive
  (`CategoryActivity::inactiveCategoryIds()`, spec 0208: flag proprio o di un antenato). Prima filtrava solo
  `is_selectable` e finiva sui rami `[OLD]`/"Old category" inattivi (in dev 190 categorie su 221).
- Punti toccati: `Concerns/PicksDemoOffers::selectableCategoryIds()` (righe richieste/opportunita'),
  `Concerns/ResolvesCategoryBusinessFunction` (lead, anche Demo project/campaign), `Support/SampleCategoryCoverage::sellableCategoryIds()`
  (copertura "una riga per categoria"), `QualificaSampleQuoteSeeder::activeCostProducts()` (righe COSTO; prodotti senza categoria ammessi).
- Test nuovi: `QualificaSampleRequestSeederTest` (inattiva + figlia di padre inattivo mai pescate),
  `QualificaSampleCategoryCoverageTest` (intera catena, righe costo incluse). Suite verde salvo i 3 `QuoteDocumentPdfTest` (LibreOffice assente in locale).
- `QualificaSampleQuoteSeeder.php` ~365 righe (era gia' 347, sopra il soft limit 300): candidato a split.
- Manuale: nessun impatto (strumento di sviluppo).

## MERGE main -> feature/amministrazione — RISOLTO (2026-10-15)

- Conflitti: HANDOFF (voci di entrambi i lati mantenute) e i contatori `migrate:rollback --step` di
  `PurchaseOrderMigrationTest` (38), `QuoteWorkflowMigrationTest` (189), `SupplierCommissionDirectionTest` (53):
  sommano le 3 migrazioni ricezioni ODA (spec 0254, branch) e le 2 migrazioni geo (spec 0189 G-28, main).
- Due migrazioni condividono il prefisso `2026_10_18_100000` (receipts / tuscan provinces): ordinate per nome file, nessun clash.

## Spec 0266 — Partitario clienti/fornitori tappa 1 — VERDE, COMMITTATO (2026-10-15)

- Spec `docs/specs/0266-registry-ledger.xml` (D-1..D-11, AC-001..AC-020 tutti PASS dal verifier).
- Endpoint `GET /api/registries/{registry}/ledger` (sola lettura): params `document_type` (all|proforma|invoice,
  default invoice), `date_from`/`date_to` (assenti = tutta la storia), `company_id[]`, `company_site_id[]`,
  `operational_site_id[]`. Response `{ registry, today, customer: LedgerSection|null, supplier: LedgerSection|null }`.
- Backend: `app/Services/Ledger/{LedgerService,LedgerCalculator,LedgerSide,ReceivableLedgerSource,PayableLedgerSource,BuildsLedgerEntries}`,
  DTO `app/DataObjects/Ledger/*`, `RegistryLedgerController` + `RegistryLedgerRequest` (authorize: `ledger.view` + almeno
  `invoices.view`/`purchase-invoices.view`; il service rimette il gate per sezione). Importi in centesimi interi.
  Sedi attive lette dall'offerta (`quotes.*_site_id`). Stato via `InvoicePaymentStatusResolver::forDueDate` (invariato).
- Permesso: `LedgerPolicy` (abilities `['view']`) + `LedgerAuthorization` registrata in `config/authorization.php`
  `definitions` (NON permission_only: altrimenti la voce comparirebbe due volte nel form Ruolo). Menu `ledger`
  in `config/navigation/accounting.php` (route `/ledger`, `ledger.view`). `FieldCatalogueEndpointTest` aggiornato (REQUIREMENT CHANGED).
- Frontend: `features/ledger/*` (LedgerPanel, filtri, riepilogo+anzianita', tabella documenti nativa espandibile —
  non esiste `components/ui/table.tsx`), `pages/ledger-page.tsx` (`?registry=id`; filtri solo stato locale),
  tab "Partitario" in `registry-related-records.tsx`, i18n `it/en-ledger.ts`, guida in-app `ledger` IT/EN.
- Ambiente test backend: serve un php.ini con pdo_sqlite/sqlite3/zip e memory_limit 2048M via `PHPRC`
  (i worker --parallel lo ereditano). Fallimenti noti non correlati: `QuoteDocumentPdfTest` (manca soffice),
  timeout Vitest flaky sotto carico (stat-chart, attribute-form-table-editor, request-dashboard-tabs).
- Prossimi passi: tappa 2 (vista Movimenti con taglio a data + posizione netta), tappa 3 (Saldi di tutte le
  anagrafiche + export estratto conto), spec separata note di credito/debito.

## Spec 0265 — Provvigioni agenti tappa 2: Estratti e Lanci allineati alle schede — VERDE, NON COMMITTATO (2026-10-15)

- Spec `docs/specs/0265-commission-settlements-runs-record-alignment.xml` (D-1..D-3). Solo frontend.
- Estratti: `commission-settlement-detail.tsx` su RecordBody (azioni in `RecordCardHeader.actions`, tab Attivita',
  RecordMeta), "Nuova rettifica" come `action` della sezione Movimenti (tolta da `CommissionSettlementActions`, prop
  `onAdjust` rimossa). Note = `CommissionSettlementNotesRow` (RecordInlineField + useRecordInlineEdit), `saveNotes`
  ora `mutateAsync`, `isSavingNotes` rimosso, chiave `notes.save` rimossa. Rettifiche (D-2): `commission-adjustment-sheet.tsx` + `commission-adjustment-rows.tsx` sostituiscono
  `commission-adjustment-dialog.tsx` (Estratti, Maturato, scheda estratto); `ForSelectFormField` ha `hideLabel`.
  Nota: `formState.isDirty` va letto nel render (RHF traccia solo i flag letti) per la conferma d'uscita.
- Lanci: `commission-settlement-run-detail-sheet.tsx` (Sheet + RecordCard) sostituisce `CommissionSettlementRunDetailDialog`;
  `commission-settlement-run-result.tsx` esporta `RunCreatedTable` / `RunSkippedTable` (riusate anche dall'esito del
  nuovo lancio). Maturato invariato.
- Nuovo lancio (D-4): `commission-settlement-run-sheet.tsx` + `commission-settlement-run-rows.tsx` sostituiscono
  `commission-settlement-run-dialogs.tsx` / `commission-settlement-run-form.tsx`; dopo il lancio mostra `RunRecord`
  (esportato da detail-sheet). Rimossa `CommissionSettlementRunResultView` e la chiave `result.summary`.
- Test aggiornati (REQUIREMENT CHANGED): note inline nel dettaglio estratto, dettaglio lancio in scheda laterale.
- Guide IT/EN commission-settlements e commission-settlement-runs aggiornate. Manuale Claude Docs: da aggiornare a mano.

## Spec 0264 — Provvigioni agenti tappa 1: Profili fiscali e Parametri ENASARCO inline — VERDE, NON COMMITTATO (2026-10-15)

- Spec `docs/specs/0264-agent-fiscal-profiles-enasarco-inline-editing.xml` (D-1..D-6). Rinumerata da 0263: quel numero
  e' della sessione parallela (0263-invoice-lines-inline-editing). Richiesta utente: modifica inline + allineamento
  visivo di tutta la sezione Provvigioni agenti; tappa 2 (Estratti, Maturato, Lanci) ancora da specificare.
- BE: `AgentFiscalProfileRequest` / `EnasarcoParameterRequest::prepareForValidation` (solo PATCH: salvato sotto
  l'input). Celle: profili valid_from, valid_until, withholding_rate/base (solo ritenuta attiva), enasarco_mandate_type
  (solo ENASARCO attivo), vat_rate (relazione `vat-rates`, editableField vat_rate_id) con `editable_columns` per riga
  (`Catalog::rowEditableColumnIds`); ENASARCO year, total_rate, agent_share_percent, i due massimali. `updateCell` =
  FormRequestCellValidator + service. `RelationValueScopeChecker` ramo `vat-rates`. Test: AgentFiscalProfileInlineEditTest,
  EnasarcoParameterInlineEditTest. Nota: il PHP 8.4 di Laragon richiede `-d extension=pdo_sqlite -d extension=sqlite3`.
- FE: schede sul kit (righe raggruppate Validita', Ritenuta, ENASARCO), creazione = replica (RecordCreateLayout +
  useDraftInlineEdit + conferma d'uscita), registry `generateEditRoute: false`. `update*` = PATCH diff con permissions.
  Kit: `useRecordInlineEdit` / `useDraftInlineEdit` accettano un 2o generico (valori validati, schema con transform).
  Rimossi `use-*-form-meta.ts` dei due moduli e chiavi i18n orfane. Guide IT/EN aggiornate (sezione `edit`).
- Manuale Claude Docs: connettore senza accesso al documento (non condiviso) -> aggiornare a mano Profili fiscali e
  Parametri ENASARCO (modifica in scheda/cella, niente pagina di modifica).

## Spec 0263 — Righe fattura attiva e passiva modificabili nella scheda — VERDE, NON COMMITTATO (2026-10-15)

- Spec `docs/specs/0263-invoice-lines-inline-editing.xml` (D-1..D-4). Richiesta utente: la finestra "Modifica
  documento" non aveva il nuovo stile; scelte: righe nella scheda, fatture attive e passive. Nessuna modifica backend.
- Kit: `useRoutedRecordInlineEdit` gestisce anche le righe (`lines` route, `startNewLine(append)`, `removeLine`);
  `applyServerErrors` ora ritorna `string[] | null` (messaggi non mappati). `record-lines.tsx` (`LineEditorTableRow`,
  `LineRowActions`, `RecordLinesSection`), `use-confirm-line-removal.ts`, `editingLineIndex` in
  use-lines-record-inline-edit. i18n `common.inlineEdit.lines.*`. `features/invoices/use-vat-refs.ts` condiviso.
- Schede: `InvoiceLinesRecord` / `PurchaseInvoiceLinesRecord` sulle tabelle righe esistenti (prop `editing`), riga
  dell'editor riusata in place (`footer`/`className`, `onRemove` opzionale; riga attiva generica sul form). Salva =
  PATCH con l'intero `lines`. Pulsante "Modifica righe" rimosso.
- Editor solo creazione: via modalita' edit, PUT client (`updateInvoice`, `updatePurchaseInvoice` + hook), anteprima
  con `invoice_id`/`purchase_invoice_id` (rimodulazione) e relative chiavi i18n; `sourceFromInvoice` -> `valuesFromInvoice`;
  `PurchaseInvoiceActionDialogs` rimosso (resta il delete dialog). Test `invoice-editor-rebalance` eliminato.
- Guide IT/EN invoices e purchase-invoices aggiornate. Manuale Claude Docs da aggiornare a mano.

## Spec 0262 — Ciclo passivo: celle e scheda fattura passiva modificabili — VERDE, COMMITTATO 825fd7c4 (2026-10-14)

- Spec `docs/specs/0262-payables-inline-editing.xml` (D-1..D-5). Menu Passiva = solo fatture e scadenze passive.
- BE: `PATCH /purchase-invoices/{purchaseInvoice}` (match put/patch): `PurchaseInvoiceWriteRequest::prepareForValidation`
  completa col salvato (righe con id, purchase_order_ids); metodo salvato valido se disattivato. Lista: `document_date`
  editabile (proforma senza pagamenti, `authorizeUpdate`), azioni update/details rimosse. Scadenze passive: due_date,
  payment_method_code in cella (non pagate), azione `edit` rimossa. Test: PurchaseInvoiceInlineEditTest (nuovo),
  PurchaseInvoicesTableTest + PurchaseInvoiceInstallmentsTableTest aggiornati (REQUIREMENT CHANGED).
- FE condiviso: `components/record-form/use-routed-record-inline-edit.ts` (gruppi di campi -> endpoint) e
  `record-field-editors.tsx` (RecordTextEditor/RecordRelationEditor); la scheda fattura attiva (0261) ora li usa.
- FE passivo: `use-purchase-invoice-inline-edit.ts` (useForm `values` = re-sync su cambi esterni),
  `purchase-invoice-record-sections.tsx`, `purchase-invoice-detail.tsx` (pulsante "Modifica righe" = onEdit, onDetails
  rimosso), `patchPurchaseInvoice`. Rimossi: details dialog(+test), use-purchase-invoice-editor-details-form,
  buildPurchaseInvoiceDetailsSchema; scadenze: edit dialog(+test), edit form, field-access, payment-method-field,
  use-payment-method-code-options, installment-schema, updatePurchaseInstallment; chiavi i18n orfane.
- Guide IT/EN purchase-invoices e purchase-invoice-installments aggiornate. Manuale Claude Docs da aggiornare a mano.

## Spec 0261 — Scheda fattura attiva modificabile per campo (tappa 2) — VERDE, COMMITTATO 700a3047 (2026-10-14)

- Spec `docs/specs/0261-invoice-record-inline-editing.xml` (D-1..D-4).
- BE: `PATCH /invoices/{invoice}` (route match put/patch): `InvoiceWriteRequest::prepareForValidation` completa i campi non
  inviati (righe comprese) col salvato, poi lo stesso `InvoiceService::update` (rate ricalcolate/ribilanciate, provvigioni).
  Metodo di pagamento e layout salvati validi anche se disattivati. Azioni riga `update`/`details` rimosse. Test:
  InvoicePartialUpdateTest (nuovo), InvoicesTableTest/InvoiceRegisteredLockTest aggiornati (REQUIREMENT CHANGED).
- FE: scheda `invoice-detail.tsx` con `useInvoiceInlineEdit` (`use-invoice-inline-edit.ts`: campi details -> PATCH details,
  testata/note -> `patchInvoice`), sezioni `invoice-record-sections.tsx`, schema `buildInvoiceRecordSchema`; "Modifica righe"
  apre `InvoiceEditorDialog` mode edit (righe + anteprima rate). Rimossi: invoice-details-dialog(+test),
  use-invoice-details-form, useUpdateInvoiceDetails, buildInvoiceDetailsSchema, chiavi i18n della finestra Dettagli e
  `invoices.actions.details`. Test: invoice-detail-inline-edit.test.tsx.
- Guide IT/EN invoices (actions) aggiornate. Manuale Claude Docs da aggiornare a mano.

## Spec 0260 — Fatture attive, Scadenze attive, Tag fatture modificabili in cella (tappa 1) — VERDE, COMMITTATO 4e4a0229 (2026-10-14)

- Spec `docs/specs/0260-invoicing-grids-inline-editing.xml`. Tappa 2 (scheda fattura inline al posto delle finestre
  Modifica/Dettagli) da specificare a parte.
- Tag fatture: name, description, color (editor palette), is_active via UpdateInvoiceTagRequest + InvoiceTagService.
- Scadenze attive: due_date, payment_method_code via UpdateInvoiceInstallmentRequest + InvoiceInstallmentUpdater;
  `authorizeUpdate` = rata non incassata + policy. Azione `edit` e finestra di modifica RIMOSSE (installment-edit-dialog,
  use-installment-edit-form, installment-schema, installment-field-access, use-installment-queries, updateInstallment,
  chiavi i18n invoiceInstallments.edit / actions.edit).
- Fatture: tag (editableField invoice_tag_id, relazione invoice-tags) e deviation via InvoiceDetailsRequest +
  InvoiceService::updateDetails (anche registrate). Numero/data esterni restano nella finestra Dettagli.
- RelationValueScopeChecker + invoice-tags. Test: tests/Feature/Tables/InvoicingGridsInlineEditTest.php.
- Guide IT/EN invoices (actions), invoice-installments (edit-due-date, permissions), invoice-tags aggiornate.

## Spec 0259 — ODA: campi di servizio modificabili dopo l'approvazione — VERDE, COMMITTATO 7e71b9e0 (2026-10-14)

- Spec `docs/specs/0259-purchase-order-service-fields-after-approval.xml` (decisione utente: opzione 2).
- Fonte unica `PurchaseOrderData::SERVICE_FIELDS`; `PurchaseOrderStatus::allowsServiceEdits()` (tutto tranne Cancelled).
  `PurchaseOrdersAuthorization`: update vero fino all'annullamento, campi non di servizio readonly dopo l'approvazione.
  Nuovo `PurchaseOrderWriteGuard` (allows/assertWritable, assorbe il blocco societa'): usato dalla richiesta (409 anticipato,
  messaggio `purchase_orders.only_service_fields`) e dal service. Righe, eliminazione e unificazione: solo Bozza/In validazione.
- Griglia: estensione generica riga `editable_columns` (null = tutte), FE `isRowCellEditable` in column-defaults; ODA la
  valorizza con `PurchaseOrderColumnCatalog::serviceColumnIds()` dopo l'approvazione.
- FE scheda: `toOrderResourcePermissions` segue la matrice server (lock solo senza update), `canEditOrderLines` per le righe,
  avviso `purchaseOrderForm.serviceOnlyNotice`. Test: PurchaseOrderServiceFieldsTest (nuovo), CrudTest/TableTest/InlineEditTest,
  FE detail/form-logic aggiornati (REQUIREMENT CHANGED). `patchPurchaseOrderCell` spostato in tests/Helpers.
- Guide IT/EN purchase-orders aggiornate (overview, edit). Manuale Claude Docs da aggiornare a mano.

## Spec 0258 — Cataloghi Acquisti modificabili in cella — VERDE, COMMITTATO 40b937e9 (2026-10-14)

- Spec `docs/specs/0258-purchasing-catalogs-grid-inline-editing.xml`. Tipi di costo (name, description, nature, color,
  is_active), Priorita' RDA (name, description, color, is_active, is_default), Stati ODA e Stati righe RDA (name, color).
  Icona e ordine restano dalla scheda / trascinamento. Le schede erano gia' inline (0235/0236/0249).
- BE: helper `App\Tables\Support\EditableColumns::apply()` nei 4 ColumnCatalog; ogni TableDefinition ha `updateCell()` =
  FormRequestCellValidator(Update*Request) + service->update (PurchaseOrderStatusLabelService usa updatableAttributes()).
  `nature` dichiara `options` CostTypeNature. Test: tests/Feature/Tables/PurchasingCatalogsInlineEditTest.php.
- FE: nuovo editor generico di cella `color` (`components/data-table/color-cell-editor.tsx`, chiave `table.colorEditor.list`).
- Guide in-app IT/EN dei 4 cataloghi: un passo "modifica in cella". Manuale Claude Docs da aggiornare a mano.
- Regola modificabilita' ODA dopo l'approvazione decisa: opzione 2 (spec 0259).

## Spec 0257 — ODA modificabili in lista e scheda senza Salva — VERDE, COMMITTATO 40b937e9 (2026-10-14)

- Spec `docs/specs/0257-purchase-order-inline-editing.xml` (D-1..D-8), stesso schema di 0256.
- BE: `PurchaseOrderWriteRequest` in modifica = PATCH parziale (sede validata sulla societa' salvata), `PurchaseOrderData::lines`
  nullable, `PurchaseOrderService::update` + `syncLines()` solo se inviate, cambio societa' verificato solo se inviato
  (service 306 righe: sopra la soglia 300 di poco). Griglia: `PurchaseOrderCellWriter`, colonne in
  `PurchaseOrderColumnCatalog::EDITABLE_RELATIONS/SCALARS` + sectors multiselect; `authorizeUpdate` = stato modificabile +
  policy; azione riga `update` rimossa. Motore cella: nuovo `relation.params` (filtri fissi del picker, es. is_supplier)
  emesso da `ResolvesColumnConfig`, letto da `RelationCellEditor`; `RelationValueScopeChecker` + payment-methods, quotes,
  opportunities, sectors. Test: PurchaseOrderInlineEditTest; PurchaseOrdersTableTest aggiornato (azioni).
- FE condiviso: `components/record-form/use-lines-record-inline-edit.ts` (editor unico campo/riga, PATCH parziale) e
  `line-edit-bar.tsx`, usati da RDA e ODA (riuso previsto per Fatture passive). ODA: `purchase-order-detail.tsx`,
  `purchase-order-record-sections.tsx`, `purchase-order-lines-record.tsx`, `purchase-order-line-view.tsx`,
  `use-purchase-order-inline-edit.ts`; creazione `PurchaseOrderForm` solo create in RecordCreateLayout con leave guard;
  Evasione/Ricezioni/Fatture come card sotto la scheda (stati non modificabili). Test: purchase-order-detail.test.tsx (nuovo),
  form/form-logic/api adattati (REQUIREMENT CHANGED).
- Guide in-app IT/EN `purchase-orders` (overview, create, nuova `edit`, merge). Manuale Claude Docs da aggiornare a mano.

## Spec 0256 — RDA modificabili in lista e scheda senza Salva — VERDE, COMMITTATO 412eac93 (2026-10-14)

- Spec `docs/specs/0256-purchase-request-inline-editing.xml` (D-1..D-8). ODA e Fatture passive: spec separate (utente).
- BE: `PurchaseRequestWriteRequest` in modifica = PATCH parziale (`sometimes`), controlli incrociati su valore inviato o
  salvato (`effective()`), `company_id` senza `company_site_id` -> 422 (`purchase_requests.company_site_required`);
  `PurchaseRequestData::lines` nullable, il service sincronizza le righe solo se inviate. Griglia: `PurchaseRequestCellWriter`
  (FormRequestCellValidator + service), colonne editabili in `PurchaseRequestColumnCatalog::EDITABLE_RELATIONS/SCALARS`
  (societa' NON editabile in cella), azione di riga `update` (matita) rimossa: si apre con `view`, `authorizeUpdate` = RDA aperta + policy; `RelationValueScopeChecker` +
  business-functions, purchase-request-priorities. Test: PurchaseRequestPartialUpdateTest, PurchaseRequestGridInlineEditTest.
- FE: `updatePurchaseRequest` = PATCH parziale. Scheda `PurchaseRequestDetailView` (`purchase-request-detail.tsx`) con
  `usePurchaseRequestInlineEdit` (un editor alla volta, `lines.<i>` per le righe; header diff `buildHeaderPatch`, righe =
  insieme completo `buildLinesPayload`), sezioni `purchase-request-record-sections.tsx`, righe
  `purchase-request-lines-record.tsx` + banda `purchase-request-line-view.tsx`. Creazione `PurchaseRequestForm` solo create
  in `RecordCreateLayout` con leave guard; `PurchaseRequestLinesSection` solo create. Test: purchase-request-detail.test.tsx
  (nuovo), line-supplier/form/quick-create/submit adattati (REQUIREMENT CHANGED: modifica in place).
- Guide in-app IT/EN `purchase-requests` (overview, create, nuova sezione `edit`, lines, documents, close) aggiornate.

## Spec 0255 — Collegamento ODA <-> fatture passive — VERDE, NON COMMITTATO (2026-10-14)

- Spec `docs/specs/0255-purchase-order-invoice-links.xml`. `PurchaseOrderResource.purchase_invoices` = [{id, document_type,
  document_number, document_date}] (niente importi, D-2), eager load `purchaseInvoices:...` in
  `PurchaseOrderService::RESOURCE_RELATIONS`; test `PurchaseOrderInvoiceLinksTest`.
- FE ODA: `purchase-order-invoices-section.tsx` (chip "Tipo numero · data", link con `purchase-invoices.view`, export
  `PurchaseInvoiceLink` riusato dal "da fattura N" in `purchase-order-receipts-section.tsx`); chiavi
  `purchaseOrderActions.invoices.*`. FE fatture: sezione ODA collegati in `purchase-invoice-detail-panel.tsx`.
- Guide in-app IT/EN purchase-orders (`receipts`) e purchase-invoices (`list`) aggiornate. Manuale Claude Docs da aggiornare
  a mano (ODA > Evasione righe e ricezioni; Fatture passive > L'elenco).

## Fatture attive: schede Tutte/Proforma/Fatture e filtri colonna — VERDE, NON COMMITTATO (2026-10-14)

- Bug: la scheda Proforma tornava su Tutte. Root cause: `POST /tables/invoices/values` per `type` andava in 500
  (pluck di una colonna con cast enum -> `(string)` su `InvoiceType`), la lista del set filter restava vuota e
  AG Grid scartava il valore impostato dalla scheda.
- Fix generico in `TableService::distinctFromColumn`: `select($column)` (la baseQuery con subselect/withSum faceva
  girare il DISTINCT sull'intera riga -> valori duplicati su MySQL) + mapping `BackedEnum -> value`. Corregge anche
  `purchase-invoices.document_type`.
- `InvoiceColumnCatalog`: `number_label`, `customer`, `last_reminder_at` ora `hasFilterValues: false` (colonne
  derivate senza colonna DB: prima SQL 1054 e checklist vuota; ora solo filtro testo/data).
- Test: 2 nuovi in `InvoicesTableTest`. Manuale: nessun impatto (comportamento gia' documentato).

## Fatture passive: master/detail Righe + Scadenze nell'elenco — VERDE, NON COMMITTATO (2026-10-14)

- Allineamento alle fatture attive: la riga della griglia `purchase-invoices` si espande e mostra Righe e Scadenze.
  Nuovo `purchase-invoice-detail-panel.tsx` (lazy `usePurchaseInvoice`, ResizeObserver come `InvoiceDetailPanel`),
  riusa `PurchaseInvoiceDetailLines`/`PurchaseInvoiceDetailInstallments`; `purchase-invoices-table.tsx` passa
  `masterDetail` + `detailCellRenderer` + `detailRowAutoHeight`. Solo frontend, nessun cambio di contratto.
- Test: `purchase-invoice-detail-panel.test.tsx`. Guida in-app IT/EN `purchase-invoices` sezione `list` aggiornata.
- Manuale Claude Docs: connettore senza accesso al doc -> da aggiornare a mano (Fatture passive > L'elenco).
- Fix: `PurchaseInvoiceService::RESOURCE_RELATIONS` selezionava `operationalSite:id,name` (colonna inesistente ->
  SQLSTATE 42S22 su MySQL; SQLite lo nascondeva) e la bozza da ODA mostrava l'id senza alias. Nuovo
  `OperationalSiteLabel::displayName()` (alias, altrimenti indirizzo) usato da PurchaseInvoiceResource,
  PurchaseInvoiceDraftBuilder e PurchaseOrderResource; eager load `operationalSite.addresses.city`.
  Test `PurchaseInvoiceOperationalSiteTest`.

## Spec 0254 — Ricezioni ODA per riga, stato derivato, fatturazione per quantita', fattura che evade — COMMITTATO (2026-10-14)

- Spec `docs/specs/0254-purchase-order-line-receipts.xml` (D-1..D-12, AC-001..016). Supera 0210 out "evasione per riga",
  0244 D-3 e 0250 D-8. D-2 rivista e D-12 aggiunta su richiesta utente (la FATTURA evade, la proforma no).
- Schema: purchase_order_receipts (created_by, purchase_invoice_id nullable), purchase_order_receipt_lines;
  purchase_order_lines += received_quantity (denormalizzato, solo via service), residual_closed_at/by_id/reason;
  purchase_invoice_lines: unique(purchase_order_line_id) -> indice semplice; backfill ricezione per gli ODA fulfilled.
- Fonte unica quantita': `PurchaseOrderLineBillingLedger::forLines(ids, ignoreInvoiceId, lock)` ->
  `PurchaseOrderLineBalance` (ceiling = ricevuto se residuo chiuso altrimenti ordinato; available() = ceiling -
  invoicedTotal, uguale per proforma e fattura; invoicedInvoice usato da D-8/D-12; fulfillment open/partial/complete).
- Servizi: PurchaseOrderReceiptService (create/record/delete), PurchaseOrderLineResidualService (close/reopen),
  PurchaseOrderFulfillmentSynchronizer (stato D-3 + propagazione RDA per riga D-7), PurchaseOrderInvoiceReceiptService
  (ricezione automatica da fattura su POST invoice e PATCH details proforma -> invoice, una per ODA, nota "Ricezione da
  fattura n. X"). Matrice ridotta: partially_fulfilled/fulfilled solo derivati; ODA con ricezioni o residuo chiuso non
  torna in validazione/bozza ne' si annulla. Permesso nuovo purchase-orders.receive (la ricezione da fattura non lo richiede).
- Endpoint: GET/POST /purchase-orders/{po}/receipts, DELETE .../receipts/{receipt}, POST/DELETE
  .../lines/{line}/close-residual (scopeBindings). Draft e invoiceable-orders SENZA document_type.
- Consuntivo (0252 D-2 rivista): impegnato = round(max(ceiling - fatturato, 0) x unit_price, 2) per riga ODA.
- FE: sezioni "Evasione righe" e "Ricezioni" sul dettaglio ODA, dialog Registra ricezione, Chiudi/Riapri residuo;
  editor fattura passiva con "max X", avviso ricezione automatica (editor e Dettagli fornitore), ricezione "da fattura n. X".
- Seeder: DemoPurchaseOrderSeeder e DemoSamplePurchaseOrderSeeder passano dalle ricezioni vere; DemoSamplePurchaseInvoiceSeeder
  fattura solo gli ODA evasi (proforma sugli altri, altrimenti la fattura li evaderebbe).
- Test aggiornati per requisito cambiato: rollback step (PurchaseOrderMigrationTest 33->36, QuoteWorkflowMigrationTest
  184->187, SupplierCommissionDirectionTest 48->51), matrice stati, propagazione RDA, D-8 billed once, bozza.
- Verifica 2026-10-14: Pest parallelo 10967/10971 (3 soffice, 1 skip); Vitest 8172/8176 con 4 flaky chart/dashboard
  sotto carico, verdi isolati (4/4); tsc -b --force pulito; Pint pulito; ESLint solo
  l'errore preesistente in quotes/column-renderers.tsx. Seed demo completo NON verificabile su SQLite (locations:add usa SQL
  MySQL, preesistente) e non eseguito su MySQL (migrate:fresh distruttivo): coperto dai test dei seeder.
- Aperti: manuale Claude Docs (sezioni Ordini di acquisto e Fatture passive) non aggiornabile dal connettore (doc non
  condiviso con la sessione); dopo il deploy: `php artisan migrate`, `permissions:sync`, `roles:create-super-admin`.
- AppServiceProvider (357 righe gia' prima) ha 2 voci morph map in piu': fuori soglia soft preesistente.

## Spec 0253 — Motore importi unico bcmath + IVA per aliquota (ADR 0018 decisione 4, COS-09, ATT-06) — COMMITTATO (2026-10-14)

- Spec `docs/specs/0253-unified-amounts-vat-per-rate.xml` (+ casi condivisi `0253-amount-cases.json`), AC-001..011.
- `App\Support\Amounts\LineAmountCalculator`: line(qty, price, ?rate, quantityScale), documentPerRate (IVA per aliquota,
  centesimo di resto alla riga con imponibile maggiore), sum, roundToCents, isPartial, normalize. Scale quantita':
  2 fatture attive e costi commessa, 3 RDA/ODA/fatture passive. PurchaseRequestAmountCalculator rimosso; ODA, RDA,
  fatture attive/passive, WorkOrderCostWriter e pagamenti/incassi delegano al motore (nessun '0.005' fuori dal motore,
  restano solo le provvigioni, fuori scope).
- FE: `frontend/src/lib/amounts/line-amounts.ts` (cent math intero) usata dagli editor attivo/passivo, RDA e costi
  commessa; `invoice-amounts.ts` rimosso. Prezzo unitario fattura attiva max 2 decimali (422 tradotto + errore di campo).
- Verifica 2026-10-14: Pest parallelo 10866/10870 (3 QuoteDocumentPdfTest = soffice assente, 1 skip); Vitest 8145/8149
  con 4-6 flaky chart/dashboard/purchase-request-submit sotto carico, verdi isolati (6/6); tsc -b --force, Pint puliti;
  ESLint 1 errore preesistente in features/quotes/column-renderers.tsx (react-refresh, file non toccato).
- Ambiente: PHPRC verso un php.ini con pdo_sqlite/sqlite3/zip e memory_limit=2G (i -d non arrivano ai worker).
- Aperti: manuale Claude Docs (sezioni Fatture attive/passive: IVA per aliquota, prezzo a 2 decimali) da aggiornare.
- Prossimo: spec 0254 ricezioni ODA per riga (approvata 2026-10-14).

## CATALOGO: MACROCATEGORIA SICUREZZA — VERDE, NON COMMITTATO (2026-10-15)

- Fonte: "Sicurezza 81_08.docx" + "SICUREZZA 81_09.xlsx" (127 prodotti + note in fondo). Decisioni utente: radice
  "Sicurezza" (funzione "Sicurezza", adotta il legacy "SICUREZZA") con le sottocategorie della colonna C (Corsi di
  formazione, Documenti, Visite mediche, Sopralluogo, Trasferta), tutte selezionabili; regole Consulenza; tipologia
  `consultancy` su tutto; stati di default; campi SOLO Offerta (nessun set Commessa); visura camerale = boolean;
  titolo doppio "Formazione dei datori di lavoro ... 16 ore" distinto col codice "(RSPP/DAT.)" / "(DL)".
- Dati: `QualificaCatalog/SafetyCatalogue` (albero, 12 campi `safety_*`, form "Sicurezza 81/08" + "Patentino
  saldatori" via `QualificaQuoteLayoutSeeder::OWN_FORMS`, tipologie, fornitori) e `SafetyProductCatalogue`
  (generato dall'xlsx: prezzo, fornitore, mesi; mesi null per le 4 "Aula di formazione ...", 20 per l'integrazione esami).
- Fornitori: GONDOLA SRLS (i 2 prodotti F-gas, esiste nel legacy come fornitore), ND CONTROL (4 prodotti di
  saldatura: NON esiste nel legacy con nessuna grafia -> si collega solo quando arriva un fornitore con quel nome).
- `SoaProducts` reso generico -> `BranchProducts::seed($products, $typologies, $vatRateId)` (prezzo opzionale, mesi
  null = non scritti); `EXPIRATION_MONTHS_FIELD` spostato lì. CatalogProducts step 7 lo chiama per SOA e Sicurezza.
- Test: `QualificaSafetyCatalogueTest` (5) + REQUIREMENT CHANGED (prodotti 413->540, +5 selezionabili, +1 layout
  offerta, funzione "Sicurezza" nelle liste BF). Suite completa verde salvo i 3 PDF (LibreOffice assente).
- 2026-10-15, NON COMMITTATO: `safety_requested_course_types` + `safety_course_count_per_type` (textarea) sostituiti da
  `safety_requested_courses` "Numero e tipo di corsi richiesti" e `safety_required_documents` "Documenti richiesti"
  (entrambi `text`). Ritiro sulle installazioni gia' seedate: `SafetyCatalogue::RETIRED_ATTRIBUTES` +
  `PREVIOUS_QUOTE_SECTIONS`, letti da `QualificaQuoteLayoutSeeder::RETIRED_ATTRIBUTES` / `PREVIOUS_OWN_FORMS` (ex
  `retireAplAttributes` -> `retireCatalogueAttributes`). Valori gia' salvati nelle offerte non toccati. Test +1
  (controprova: rosso senza ritiro e senza form precedente). Products 225/225, Seeding 159/159, Pint ok. Manuale
  Claude Docs aggiornato (paragrafo catalogo); guide in-app: nessun impatto.

## CATALOGO: MACROCATEGORIA SOA — VERDE, TIPOLOGIA E MESI SCADENZA NON COMMITTATI (2026-10-15)

- Fonte: "15.10.2026 Q-crm SOA.docx" + "PRODOTTI SOA.xlsx". Decisioni utente: sottocategorie "Ente SOA" e
  "Consulenza SOA" (non "Ente"/"Consulenza": il nome e' la chiave e "Consulenza" e' gia' una radice), codici inglesi
  nuovi (i `servizio_soa`/`soa_*` del legacy restano sulle [OLD]), regole come Consulenza (`CONSULTING_RULES` in
  CatalogRootRules), "Stato Lavorazione" commessa NON e' un campo: stati di default (nessun workflow SOA).
- Dati in `QualificaCatalog/SoaCatalogue` (+ `SoaCategoryOptions`, 52 OG/OS): radice "SOA" -> funzione "SOA"
  (adottata per nome dal legacy), campi Offerta sulla radice + form in `QualificaQuoteLayoutSeeder::OWN_FORMS`,
  campi/form Commessa da `QualificaSoaWorkOrderSeeder` (solo radice: le figlie ereditano, spec 0115).
- 18 prodotti (prezzo 0, IVA 22%) via `QualificaCatalog/BranchProducts` (ex SoaProducts, da `CatalogProducts` step 7):
  fornitore = registry `is_supplier` con lo stesso nome (senza maiuscole), tipologia dal nome categoria
  (`SoaCatalogue::TYPOLOGIES`: Ente SOA -> `institution`, Consulenza SOA -> `consultancy`, per codice),
  "Mesi scadenza" nel CAMPO PERSONALIZZATO prodotti `expiration_months` (QualificaTemplateSeeder) via
  `CustomFieldWriter` (attestazioni 0, consulenza I/II/III anno 0/12/24). Re-run: riempie fornitore e mesi se vuoti,
  riallinea la tipologia solo se ancora sul default `institution`; un valore a mano vince.
- REQUIREMENT CHANGED (2026-10-15, dopo il commit 599df582): l'attributo prodotto `soa_expiry_months` sulla radice
  SOA e' tolto (l'utente vuole il campo personalizzato); QualificaCatalogSeederTest torna a "nessun attribute_values,
  nessuna assegnazione di contesto prodotto". `ProductTypologySeeder` ora gira da QualificaCatalogSeeder (step 3,
  prima dei prodotti), non piu' dallo step 4 di QualificaProductionDataSeeder.
- Split: `CatalogPickLists` (fonti, tipi premio) fuori da QualificaCatalogSeeder (era 497 righe).
- Test: `QualificaSoaCatalogueTest` (8) + conteggi REQUIREMENT CHANGED (prodotti 395->413, selezionabili, layout, funzioni).
- Aperti: codice `SOA_Qualifica` dei prodotti consulenza non ha campo in qnet-2.
  HANDOFF.md e' ~590 KB: va archiviato in `docs/handoff-archive/`.

## MIGRAZIONE: STATI [OLD] DELLE OFFERTE (spec 0189 G-29) — VERDE, NON COMMITTATO (2026-10-15)

- Decisione utente: offerta legacy con esito negotiation / call_back / stand_by finita in un set di stati (workflow
  scelto dalle categorie, o set globale) senza lo stato con quel nome -> `LegacyQuoteStatusResolver` crea UNA volta per
  set "[OLD] In trattativa" (pending) / "[OLD] Da Richiamare" (open) / "[OLD] Stand-by" (open) e ci mette l'offerta,
  senza avviso. Nuovo `WorkflowStatusWriter::appendCustom()` (accoda prima di closed_won/closed_lost, resequence).
  `quotes:realign-legacy-status` sposta le offerte gia' importate rimaste aperte; --dry-run fa rollback (niente stati).
- Test: QuotesSourceStatusImportTest (REQUIREMENT CHANGED: prima "lasciata aperta con avviso"), Realign dry-run.
  Contatori `--step` aggiornati (+2 migrazioni geo) in QuoteWorkflowMigrationTest (186), SupplierCommissionDirectionTest
  (50), PurchaseOrderMigrationTest (35): il commit ba5285ce li aveva rotti.
- Guida IT/EN + manuale Claude Docs aggiornati. Prossimo: reimport offerte o `php artisan quotes:realign-legacy-status`.

## MIGRAZIONE: BLOCCO GEOGRAFICO DAL LEGACY (spec 0189 G-28) — VERDE, NON COMMITTATO (2026-10-15)

- Contratto: `country` = codice ISO alpha-2 in TUTTI gli endpoint con indirizzo. qnet-2 `MigrationGeoResolver`
  cerca per `countries.iso2` (un nome non e' piu' accettato). Fixture dei test di migrazione `'country' => 'IT'`
  (REQUIREMENT CHANGED) + test nuovo ISO/nome in OperationalSitesSourceImportTest.
- Legacy (`../qnet`, non committato): `LegacyNation` (249 `nations` per id -> ISO, mappa approvata dall'utente;
  nomi troncati "Per"/"Citt" mappati a mano), `LegacyGeo::place()` (comune riconosciuto su `listacomuni`,
  completa provincia/regione/stato, sigle sarde soppresse, regioni nel nome del dataset qnet-2),
  `LegacyComuneSpelling` (204 comuni ISTAT -> grafia del dataset qnet-2). Usati da Referent, Registry (+addresses),
  User, CompanySite, OperationalSite, Company MigrationController. Verificati con tinker sui dati reali.
- qnet-2 `ItalianGeoLocalizer`: tolti `padova`->Padua e `mantova`->Mantua (nel dataset i comuni sono Padova/Mantova).
- Dataset qnet-2: `ItalianProvinceRegionSeeder` (dati di riferimento, chiamato da DatabaseSeeder dopo
  ItalianMunicipalitySeeder) sposta in Toscana le 8 province toscane che world.sql mette in Friuli–Venezia Giulia
  (Grosseto, Livorno, Lucca, Massa and Carrara, Pisa, Pistoia, Prato, Siena) con comuni e indirizzi gia' salvati;
  la migrazione 2026_10_18_100000 lo applica ai DB gia' popolati (applicata in locale: 8 indirizzi riallineati).
- Toppe al dataset (opzione A scelta dall'utente, NON COMMITTATE): `ItalianProvinceSeeder` crea la provincia Aosta
  (AO) e ci mette i 91 comuni della Valle d'Aosta senza provincia + i loro indirizzi; `ItalianMunicipalitySeeder`
  aggiunge 38 comuni usati dal legacy e assenti (o solo sotto un'altra provincia: Carpi, Marcon, Fontanafredda...),
  Montoro (fusione 2013); `ItalianGeoLocalizer` AO -> Aosta. Migrazione 2026_10_18_110000 per i DB popolati
  (applicata in locale). Legacy: LegacyComuneSpelling recupera 7 grafie perse + Montoro Inferiore/Superiore ->
  Montoro; LegacyGeo manda SU per i comuni ex-CA fuori dalla Citta' metropolitana di Cagliari (riforma 2016).
- Misura su tutti gli indirizzi legacy: record con avvisi geo 14.524 -> 1.040 (referenti 10.171 -> 216).
- APERTO: ~336 comuni ISTAT ancora assenti dal dataset ma non usati dal legacy; restano ~1.040 indirizzi con dati
  sbagliati all'origine (CAP condivisi al posto del comune, omonimi senza provincia, "ROMA" con provincia BN...).
  Alternativa strutturale proposta e non scelta: elenco ufficiale ISTAT con codice in qnet-2 (opzione B).
- Verifiche: Pest Migration+Geo+Imports 824/824 e Seeding+Geo 174/174 (dopo le toppe), Vitest help 130/130,
  tsc -b pulito, Pint pulito, php -l legacy, endpoint legacy chiamati con tinker.
- Reimportare referents, registries, users, company-sites, operational-sites, companies per vedere l'effetto.

## MIGRAZIONE: NESSUNO SCARTO SILENZIOSO (spec 0189 G-26) — VERDE, NON COMMITTATO (2026-10-14)

- Decisione utente: opportunita'/offerte/commesse non importate = FALLITE col motivo; una riga scartata fa fallire
  l'intero record. Legacy: tolti i filtri (Opportunity: join companies; Quote: join companies -> leftJoin
  opportunities; WorkOrder: joins + `whereHasLines`, rimosso); `registry_id` null se manca il cliente. qnet-2:
  MapsLegacyQuoteLines/CostLines, WorkOrdersSource, LegacyWorkOrderCostWriter, OpportunitiesSource (categorie,
  cliente mancante) lanciano RuntimeException (transazione per riga). Test aggiornati (REQUIREMENT CHANGED) +
  nuovi casi: Migration 429/429. Guida/requisiti IT/EN aggiornati.
- Stima: ~10.7k opportunita' senza cliente, ~30k offerte (19.5k con opportunita' cancellata), 677 offerte con righe
  non importabili (681 senza prodotto, 279 prodotto non migrato) e le loro commesse risultano fallite.
- G-27: responsabili/partecipanti cancellati -> primo gestore account dell'offerta (legacy `LegacyWorkOrderPeople`);
  senza gestori la commessa fallisce (43). qnet-2 `assertUsersMigrated`. Migration 431/431.
- APERTO (domanda all'utente): stessa regola per 79 regole del configuratore escluse (74 destinatario cancellato),
  532 provvigioni di righe offerta con destinatario cancellato (oggi tolte in silenzio dal legacy), riferimenti
  orfani delle anagrafiche (24 referenti, 3 tag, 1 responsabile).
- DB locale: CANCELLATE tutte le opportunita'/offerte/commesse migrate e i loro migration_runs (stato pagina
  /dev/migrations azzerato). L'utente reimporta opportunities -> quotes -> work-orders.

## MIGRAZIONE COMMESSE: COSTI, PAGAMENTI PER RIGA, NIENTE COMMESSE SENZA RIGHE (spec 0189 G-23/G-24/G-25) — VERDE, NON COMMITTATO (2026-10-14)

- Decisioni utente 2026-10-14: commesse senza righe NON si creano; costi del riquadro "Offerta" dell'Analisi
  preventiva legacy = preventivo (righe COSTO offerta, gia' G-13) + consuntivo (`work_order_costs`), record
  indipendenti dopo l'import; pagamenti per riga dentro `work-orders` (sorgente `work-order-line-payments`
  ELIMINATA, emendata spec 0201 D-14); costi dipendenti come prodotti di costo ("[OLD] Dipendenti", `user_levels`).
  Rinviati: task e assegnazioni, note, allegati, ODA/RDA, proforma, email, provvigioni, storico avanzamento.
- Legacy (`../qnet`, NON committato): nuovi `app/Support/Migration/LegacyQuoteCosts.php` (note spese + dipendenti,
  id "<tabella>:<id>", `product_source`), `LegacyWorkOrderLines.php` (risoluzione righe + `whereHasLines` SQL),
  `LegacyWorkOrderCosts.php` (ogni costo a UNA commessa: prima con riga del servizio, altrimenti prima dell'offerta
  senza riga). `WorkOrderMigrationController` (+filtro righe, +`costs`, -logica righe interna),
  `QuoteMigrationController` (cost_lines da LegacyQuoteCosts), `CostProductMigrationController` (+`user_levels`).
  Scansione reale: 15.835 commesse (329 senza righe escluse), 7.579 costi (53 dipendenti), 146 non attribuiti, 0 doppi.
- qnet-2: `LegacyWorkOrderLinePaymentWriter`, `LegacyWorkOrderCostWriter` (via WorkOrderCostWriter) in
  `app/Migrations/Support`; `WorkOrdersSource` (skip senza righe, mappa old_id->riga anche per copie G-10);
  `CostProductCatalogue::EMPLOYEE_LEVELS`; `MapsLegacyQuoteCostLines` usa `product_source` (testo warning cambiato:
  "cost product (expense_reports legacy id N)"). Fixture commesse: default `quote_line_ids` [900] + pagamenti +
  `costs` (REQUIREMENT CHANGED: il legacy non manda piu' commesse senza righe). Nuovo
  `WorkOrdersSourceCostsAndPaymentsImportTest` (7). Rimossi source + test + i18n `work-order-line-payments`.
- NON risolvibili (segnalati all'utente): 117 commesse con `stato_consulenza` di un flusso diverso da quello delle
  loro categorie (nessun equivalente); 17 con righe in categorie senza card ("No categoria", "[OLD] PROGETTI-...")
  perdono `status_contabile`.
- Test: Migration 402/402, Unit/Migrations 20/20, WorkOrders+PaymentStatuses+Seeding+Quotes 802/805 (3 rossi
  QuoteDocumentPdfTest, LibreOffice assente). Pint ok. FE: Vitest help/i18n/migrations 388/388, tsc -b ok, ESLint ok.
- Manuale: guida in-app migrations IT/EN + requisiti aggiornati. Claude Docs: aggiornare "Migrazioni" (Commesse:
  costi, pagamenti, commesse senza righe; Prodotti di costo: Dipendenti; sorgente pagamenti rimossa).
- FIX legacy (stesso giro): `LegacyWorkOrderAttributeValues::childRows` legava le tabelle `orderiso_*` via
  `orderisos.id`, ma `orderiso_id` = id COMMESSA (schermate legacy): verifiche/standard/PAL/Gare/Avvalimenti finivano
  su commesse sbagliate. Ora `orderiso_id` = `orders.id` (G-20 bis). Le commesse importate prima vanno reimportate.
- FIX legacy: numeri all'italiana ("19947,00") nei campi flessibili commessa (`format()` + `number()`): prima 780
  importi GOL da decreto scartati.
- Audit completo 2026-10-14 (script in scratchpad, non nel repo): export -> qnet-2 run 31: 15 campi base 100% uguali
  su 15.748 commesse; differenze solo nei campi flessibili delle tabelle `orderiso_*` (bug sopra). Legacy -> export:
  non portati GOL data accredito anticipo/saldo (30/53) e "Nome corso / Ed." (`edition_id`, 1.090): fuori catalogo,
  "Data di assegnazione" su card non ISO (3.526) non visibile nel legacy. DECISO: 12 campi edizione corso GOL
  (G-20 ter) accodati al catalogo (id posizionali 356-367, i 355 esistenti invariati, verificato). DB locale: gia'
  eseguiti attributes (+12), product-category-attributes (+5 categorie [OLD] GOL), cancellati e ricreati i 5 layout
  GOL (nessuno modificato a mano). `odl`/`progressive`: utente "non prevedere ancora nulla".
  Commesse cancellate (terza volta): DB pronto per `work-orders`; poi ripetere l'audit (script in scratchpad/audit).
- "0 errori" (G-20 quater): inventario di 230 campi della scheda commessa legacy (3 agenti) -> nuovo
  `LegacyWorkOrderDisplayRules` (default delle scelte, Standard ISO multiselect `isos`, PAL tipo_elenco/somministrata,
  verifiche Stage/Off Site, GOL "0"), stato su flusso della categoria (`WorkflowStates::targetCode/foreignStates`,
  cache 10 min), campi comuni a categorie senza card (`linksForCards(..., hasOwnProducts)`), date gg/mm/aaaa
  (`MigrationFieldFormatter`). ATTENZIONE: `LegacyWorkOrderAttributeValues.php` e' stato svuotato per errore da uno
  script e ricostruito (base HEAD + modifiche di oggi, metodi verificati). DB locale: 17 definizioni attributi
  riallineate al catalogo legacy (script sync in scratchpad, anche 5 attributi offerta gia' divergenti), link
  categorie +20, commesse reimportate.
- Reimport: prima `cost-products` (crea i livelli dipendenti), poi `work-orders`. Le 42 offerte con costi dipendenti
  gia' importate NON hanno le righe COSTO dipendenti finche' non si reimportano le offerte.

## FIX: `quote_date` NULL NEL SEED DEMO (2026-10-14) — VERDE, NON COMMITTATO

- Bug: `db:seed --class=DemoDataSeeder` falliva in `DemoQuoteSeeder` con "Column 'quote_date' cannot be null".
  Causa: il default "oggi" della spec 0228 AC-001 stava solo nell'hook `creating` di `Quote`, e `DemoDataSeeder`
  usa `WithoutModelEvents`, che lo spegne.
- Fix: `CreateQuoteData::attributes()` mette `quote_date` = oggi quando non arriva, quindi ogni `QuoteService::create()`
  e' coperto anche senza eventi. L'hook del model resta per gli insert Eloquent diretti (factory/test).
- Test: `QuoteDateTest` +1 (creazione dentro `Quote::withoutEvents`). Quotes 364/367 (3 rossi solo QuoteDocumentPdfTest,
  LibreOffice assente), Migration 401/401, Seeding 150/150, Unit/Models 300/300, Leads/Opportunities/RequestManagement verdi.
  Pint ok. Manuale: nessun impatto.
- Secondo errore dello stesso seed: "attribute values.deliverable policies ... not greater than 4". Causa:
  `Database\Seeders\Support\DemoAttributeValueFaker` generava interi 1..100 e decimali 1..1000 ignorando `config.min/max`
  (Politiche erogabili, APL/orientamento, ha max 4). Ora rispetta min/max (`bounds()`), range demo dove mancano.
  `DemoAttributeValueFakerTest` +1, Seeding 151/151. Nessun test esegue l'intera catena `DemoDataSeeder` (su SQLite
  non gira: `locations:add` e' SQL MySQL), quindi altri errori a valle si vedono solo rilanciando il seed su MySQL.

## SEED/IMPORT: CATALOGO LEGACY SOTTO "Old category" + FUNZIONI FORMAZIONE/APL DAL SEED (2026-10-14) — VERDE, NON COMMITTATO

- Decisione utente: tutte le categorie legacy (radici [OLD] e "[OLD] Costi") sotto un'unica radice "Old category",
  non attiva, non selezionabile, senza funzione; non piu' sotto "Consulenza". Le funzioni "Formazione" e "APL" le
  crea il seed, l'import le adotta. Spec 0013 emendamento "quinquies", 0174 AC-008.
- Nuovo `app/Migrations/Support/LegacyCategoryContainer` (NAME, `id()`, `gather()`): sposta ogni categoria con old_id
  appesa alla radice o a una categoria qnet (anche quelle gia' sotto Consulenza) + "[OLD] Costi" in cima. Chiamato da
  `ProductCategoriesSource::afterImport` e `CostProductsSource::afterImport` (vale anche per l'import da UI).
  `CostCategoryResolver` crea "[OLD] Costi" nel contenitore e la riusa per nome. Tolti
  `QualificaLegacyImportSeeder::nestImportedCategories()`/`LEGACY_CATEGORY_ROOT`.
- `QualificaBusinessFunctionLinkSeeder` crea ogni funzione di LINKS (Formazione, APL, Consulenza, No funzione
  aziendale) se manca, nome confrontato senza maiuscole; ora gira PRIMA dell'offerta di import in
  `QualificaCatalogSeeder` (497 righe) e non e' piu' ripetuto in `QualificaProductionDataSeeder`.
  `BusinessFunctionsSource` adotta la funzione con old_id vuoto e stesso nome (LOWER): old_id + utenti in aggiunta,
  nome qnet invariato.
- Test: Migration 398/398, Seeding 150/150 (REQUIREMENT CHANGED: Consulenza -> Old category, funzioni seedate; +4
  test nuovi). Suite completa 10.849 verdi, 3 rossi solo QuoteDocumentPdfTest (LibreOffice assente). Pint ok.
  Vitest help+i18n 358/358, ESLint ok, tsc -b ok.
- ATTENZIONE test: `backend/bootstrap/cache/routes-v7.php` (13/10) e' una cache rotte vecchia: con quella ~189 test
  (Enasarco, provvigioni, fatture passive...) danno 404. `php artisan route:clear` prima di `composer test`.
- Manuale: guida in-app migrations IT+EN e manuale Claude Docs (paragrafo [OLD] + Prodotti di costo) aggiornati.
- DB locale: le 34 categorie [OLD] sotto Consulenza si spostano al prossimo import di `product-categories`.
  Spec 0174 era gia' XML non valido in HEAD (tag `source_id` riga 46), non toccato.

## MIGRAZIONE COMMESSE: CAMPI FLESSIBILI, DATI CONTABILI, FASI (spec 0189 G-20/G-21/G-22) — VERDE, NON COMMITTATO (2026-10-14)

- Richiesta utente: "/migrations di commesse come il lavoro fatto con offerte: tutti i campi, campi flessibili, costi,
  tutte le sfaccettature". Decisioni utente 2026-10-14: perimetro = campi flessibili + fasi; `status_contabile` e
  `totale_commessa_manuale` come NUOVI campi flessibili; solo nuovi import (commesse gia' importate saltate per old_id).
  NON scelti in questo giro: costi orari (`quotationemployees`, 85 righe), ODA legacy (`proformfora_heads` 750 ->
  purchase_orders), note (~88k in activity_log), documenti (23.597 file), email, provvigioni maturate, task delle fasi.
  I costi preventivo (`quotationexpensereports`) arrivano gia' come righe COSTO dell'offerta (G-13).
- Spec 0189: G-20/G-21/G-22, shape `migration/work-orders` (+`attribute_values`, +`stages`), AC-015..017; corretto
  XML gia' invalido (G-19: `R&S`, `<titolo card>` non escapati).
- Legacy (`../qnet`, develop, NON committato): nuovi `app/Support/Migration/LegacyWorkOrderAttributeValues.php`
  (formatta per tipo/opzioni del catalogo `AttributeMigrationController::attributes()`, ora public; esclusi
  stato_pagamento/accordo/insoluti = pagamenti per riga spec 0201 C-2; stato delle righe avv/gare solo se la commessa ha
  card 5/12) e `LegacyWorkOrderStages.php` (fasi unite per titolo, chiusa se tutte chiuse, closed_at = dateend max,
  autore = ultima voce activity_log "ha chiuso la fase: <titolo>"). `WorkOrderMigrationController` (+service_cards,
  attribute_values, stages). Catalogo: `standard` da enum isos a text (testo libero nel legacy), `sup_vi` opzioni 1/2
  (`inspectionSupportOptions`), nuovi `status_contabile` boolean "Proforma emessa" e `totale_commessa_manuale` decimal
  (`CardAttributes::accounting()`, comuni in `MigrationAttributeCards`, sezione "Dati contrattuali"); card ISO +
  `ISO_HISTORY_ATTRIBUTES` (11 campi orderisos non mostrati da manageorder) nella sezione "Dati storici ISO".
  `MigrationAttributeCards.php` ora 318 righe (>300 soft). Verifica: `php -l`; scansione completa via controller:
  16.164 commesse, 0 eccezioni, 30 s; 53.448 fasi (27.293 chiuse con autore); HTTP reale qnet-2 -> legacy ok.
- qnet-2: `LegacyQuoteAttributeValueApplier` rinominato `Migrations\Support\LegacyAttributeValueApplier`
  (`applyToQuote` / `applyToWorkOrder`, relazioni `users` + `registries`); nuovo `LegacyWorkOrderStageWriter`;
  `WorkOrdersSource` (+dipendenza opzionale `product-category-attributes`, colonna anteprima `stages`). Fixture
  commesse spostate in `tests/Helpers/WorkOrderMigrationHelpers.php` (require in `tests/Pest.php`); nuovo
  `WorkOrdersSourceAttributeValuesImportTest` (3 test).
- Test: `tests/Feature/Migration` 401/401, `tests/Unit/Migrations` 20/20, WorkOrders 275/275, Quotes 363/366 (3 rossi
  `QuoteDocumentPdfTest`: LibreOffice non installato, ambientale). Pint ok; Vitest help/i18n/migrations 388/388;
  `tsc -b --force` 0; ESLint 0.
- Manuale: guida in-app `migrations` IT/EN + requisiti `work-orders` IT/EN aggiornati. Manuale Claude Docs NON
  accessibile da questa sessione: aggiornare in "Migrazioni" il punto Commesse (campi flessibili, fasi, solo nuovi
  import) e la nota "non vengono importati" (restano note, documenti, task).
- Per vedere i dati: reimport da zero di Attributi, Categorie prodotto — collega attributi, Layout attributi, poi
  Commesse (le nuove definizioni/collegamenti non toccano un DB gia' importato).

## Spec 0252 — Consuntivo commessa dal ciclo passivo (ADR 0018 decisione 2, COS-01) — COMMITTATO (2026-10-13)

- Spec `docs/specs/0252-work-order-actuals-from-passive-cycle.xml` (D-1..D-9, AC-001..009 PASS). 0210 D-7 superata.
- `App\Services\WorkOrders\WorkOrderPassiveCostAggregator`: 3 query aggregate group by cost_type_id (manuali,
  righe ODA impegnate = stati INVOICEABLE_STATUSES e non referenziate da righe passive, righe passive proforma+invoice
  per work_order_id della riga) + 1 sui tipi; centesimi interi. GET /api/work-orders/{id}/costs: comparison.totals
  += manual_cost_net, committed_order_cost_net, invoiced_cost_net, total_actual_cost_net, total_actual_margin_net,
  total_delta_net (actual_cost_net resta = soli manuali); comparison.by_cost_type [{cost_type{id,name,color,nature}|null,
  tre fonti, total}].
- FE tab confronto: work-order-costs-source-totals.tsx + work-order-costs-by-type-table.tsx.
- Seeder demo: PayableDocumentWriter collega le righe ODA; DemoPurchaseOrderSeeder 5 ordini; DemoPurchaseInvoiceSeeder
  scenari [1,3],[2],[4] (nessuna riga ODA fatturata due volte). DB gia' seminati non vengono ricollegati (migrate:fresh).
- Aperti: by_cost_type somma tutti i manuali della commessa (coincide col totale finche' quote_line_id resta valido);
  copertura test arrotondamenti a 3 decimali sottile.
- Prossimo: spec 0253 motore importi unico bcmath + IVA per aliquota sulle fatture attive (COS-09, ATT-06), ultima dell'Onda 3.

## Spec 0251 — Tipo di costo su prodotti e costi commessa (ADR 0018 decisione 1, COS-02) — COMMITTATO b10cf647 (2026-10-13)

- Spec `docs/specs/0251-cost-type-on-products-and-work-order-costs.xml` (D-1..D-7, AC-001..010 PASS).
- products.cost_type_id e work_order_costs.cost_type_id nullable FK restrict (migrazione 2026_10_17_190000; il
  backfill dei costi commessa dal prodotto e' inerte alla migrazione perche' i prodotti partono vuoti, D-1).
- Nessuna mappatura categorie -> tipi: azione massiva POST /api/products/cost-type {product_ids, cost_type_id|null}
  (products.update, activity log per prodotto) + dialog nella griglia Prodotti. for-select prodotti meta.cost_type;
  colonna cost_type (text con {id,name,color}) nella griglia Prodotti.
- Precompilazione dal prodotto: righe ODA (solo se vuota), nuove righe fattura passiva (prevale il default di
  testata), costi commessa (sempre, modificabile). Delete guard tipi di costo esteso a prodotti e costi commessa.
- Contatori --step: PurchaseOrderMigrationTest 33, SupplierCommissionDirectionTest 48, QuoteWorkflowMigrationTest 184.
- Aperti: work-order-cost-row.tsx 331 righe (soft limit); "Genera ODA" non precompila dal prodotto (non sceglie prodotti).
- Prossimo: spec 0252 consuntivo commessa ibrido per tipo di costo (manuali + righe ODA impegnate + righe fattura
  passiva al posto dell'ODA quando fatturato), poi 0253 motore importi unico + IVA per aliquota.

## ADR 0018 + Spec 0250 — Fatture passive fase 2 (audit Onda 3, primo passo) — COMMITTATO d254c6c6 (2026-10-13)

- ADR `docs/adr/0018-unified-cost-model.md`: tipi di costo = tassonomia unica dei costi (categorie solo vendite);
  consuntivo commessa ibrido in lettura; fatture passive con righe; IVA attive per aliquota. Ordine: 0250 -> 0251
  (cost_type_id su prodotti e work_order_costs, mapping ramo "Costi") -> 0252 (consuntivo per tipo di costo) -> 0253
  (motore importi unico bcmath + IVA per aliquota).
- Spec `docs/specs/0250-purchase-invoices-phase-2.xml` (D-1..D-11, AC-001..014 PASS dopo 1 fix FE + 1 fix race).
- BE: `purchase_invoice_lines` (purchase_order_line_id UNIQUE nullable restrict, cost_type_id required, work_order_id,
  quantity 12,3), `purchase_invoices.document_year` + unique (supplier_id, document_number, document_year) solo invoice;
  backfill una riga per documento esistente. Servizi App\Services\PurchaseInvoices\{PurchaseInvoiceService,
  PurchaseInvoiceScheduleRebalancer, RegisteredPurchaseInvoiceGuard, PurchaseInvoiceLineGuard (lock + lockForUpdate su
  righe gia' fatturate), PurchaseInvoiceAmountCalculator, PurchaseInvoiceHeaderGuard, PurchaseInvoiceDraftBuilder
  (INVOICEABLE_STATUSES)}. Endpoint /api/purchase-invoices (show/store/update/details/delete/installment-preview/draft/
  invoiceable-orders); `permissions` al top-level della risposta (FE lo fonde in api.ts). Policy += create, update,
  delete, export, viewActivity, downgrade. Lista SSRM purchase-invoices, azione open_document sulle scadenze passive,
  allegati purchase_invoice. Servizi attivi NON toccati (copie adattate, deduplica Onda 4).
- FE: features/purchase-invoices (editor da zero/da ODA, dettagli fornitore, lista, scheda, documenti), "Registra
  fattura" nel dettaglio ODA, "Apri documento" dalle scadenze; i18n purchaseInvoices (FE-A) + purchaseInvoiceViews.
  Etichetta permesso downgrade resa generica ("Correggi/annulla registrazione").
- Seeder demo: PayableDocumentWriter crea i documenti via servizio con righe libere (lo stesso ODA fatturato due volte
  nei demo: costi duplicati nei dati fake, da rivedere per il consuntivo 0252).
- Aperti: "Registra fattura" visibile anche per ODA gia' interamente fatturati (editor con 0 righe); /new e
  /:id/duplicate generati dal registro moduli; race D-8 coperta da indice univoco (non riproducibile su SQLite).
  Contatori --step: PurchaseOrderMigrationTest 32, SupplierCommissionDirectionTest 47, QuoteWorkflowMigrationTest 183.

## Spec 0249 — Tipi di costo: natura, colore, modifica in linea, riordino (audit Onda 2-C) — COMMITTATO 12f9248b (2026-10-13)

- Spec `docs/specs/0249-cost-types-nature-inline-edit.xml` (D-1..D-6, AC-001..008 PASS). Onda 2 completata.
- `App\Enums\CostTypeNature` (direct|indirect|general, enumKey `cost_type_nature` in config form_enums, alias FE
  enums.cost_type_nature -> costTypes.nature); cost_types.nature (default direct) e color (default slate), migrazione
  2026_10_17_170000. sort_order solo via POST /api/cost-types/reorder (LookupOrderManager, placeNew); prohibited nei form.
- for-select meta {color, nature}; colonna nature in tables/cost-types; cella cost_type delle Scadenze passive
  {name,color}|null (StatusBadgeCell); PurchaseOrderResource lines[].cost_type ha anche color.
- FE: dettaglio inline (generateEditRoute: false), PATCH parziale, riordino in lista, badge nei select righe ODA /
  Genera ODA, dettaglio ODA, Scadenze passive. Guide cost-types e purchase-orders aggiornate.
- Contatori --step: PurchaseOrderMigrationTest 31, SupplierCommissionDirectionTest 46, QuoteWorkflowMigrationTest 182.
- Prossimo: Onda 3 "modello dei costi" (COS-01 consuntivo da ODA/fatture passive, COS-02 tassonomia unica, COS-04,
  COS-16, COS-09/ATT-06 calcolo importi e IVA per aliquota): richiede ADR e decisione utente sulla tassonomia.

## Spec 0248 — Stati ODA e righe RDA configurabili (solo presentazione, audit Onda 2-B) — COMMITTATO 5a322dd7 (2026-10-13)

- Spec `docs/specs/0248-purchasing-status-presentation-catalogs.xml` (D-1..D-7, AC-001..011 PASS).
- Tabelle `purchase_order_statuses` (7) e `purchase_request_line_statuses` (6), sole righe di sistema create dalla
  migrazione (2026_10_17_150000/160000): system_key = valore enum (immutabile), name/color/icon modificabili, riordino
  di tutte le righe (LookupOrderManager). Policy senza create/delete, nessuna rotta POST/DELETE. Model
  `PurchaseOrderStatusLabel` / `PurchaseRequestLineStatusLabel`, service `*StatusLabelService`, controller ODA
  `PurchaseOrderStatuses\PurchaseOrderStatusController`, righe `PurchaseRequestLineStatuses\PurchaseRequestLineStatusLabelController`.
  Gli enum restano la logica; le API documenti espongono ancora `status` grezzo.
- Griglie ODA e righe RDA: badges della colonna status dal catalogo (filtro/chip con i nomi), sort per sort_order.
- FE: hook `usePurchaseOrderStatusCatalog` / `usePurchaseRequestLineStatusCatalog` (for-select, staleTime 5 min) per
  badge, tab, dialog, storico, riepilogo; rimosse mappe VARIANTS/LINE_VARIANTS/STATUS_ICONS e chiavi i18n
  purchaseOrders.statuses / purchaseRequests.lineStatuses (i nomi sono dati, non piu' tradotti, D-3). StatusReorderToggle
  ha la prop `allRowsReorderable`. Menu Acquisti: Stati ODA, Stati righe RDA.
- Contatori --step migrazioni: PurchaseOrderMigrationTest 30, SupplierCommissionDirectionTest 45, QuoteWorkflowMigrationTest 181.
- Aperti minori: il registro moduli genera /new e /:id/duplicate anche per questi cataloghi (protette da un permesso
  inesistente: mostrano "forbidden"); naming Label non uniforme tra controller/resource ODA e RDA; sort_order iniziale
  1..7 (ODA) vs 10..60 (RDA); PurchaseOrdersTableDefinition 323 righe. Manuale Claude Docs da aggiornare.
- Prossimo: spec 0249 tipi di costo (COS-10 natura/gruppo, COS-11 modifica in linea + riordino), poi Onda 3.

## Spec 0247 — Cataloghi semplici Acquisti/Contabilita' (audit Onda 2-A) — COMMITTATO 4b38f6f2 (2026-10-13)

- Spec `docs/specs/0247-purchasing-accounting-simple-catalogs.xml` (D-1..D-8, AC-001..017 PASS; D-8/AC-004/AC-008
  [revised]: filtri set di priorita' e tag per NOME, non per id).
- Priorita' RDA = catalogo `purchase_request_priorities` (modello TaskPriority: default unico, riordino, 409 in uso);
  `purchase_requests.purchase_request_priority_id` (FK restrict), enum PurchaseRequestPriority rimosso. API: scrittura
  `purchase_request_priority_id`, lettura `priority {id,name,color,icon}`. Menu Acquisti, permessi
  purchase-request-priorities.*. FE features/purchase-request-priorities, i18n root `purchaseRequestPriorities`
  ({it,en}-purchase-request-priorities.ts).
- Tag fattura = catalogo `invoice_tags` (Preventivo/Consuntivo, niente default/icona); `invoices.invoice_tag_id`
  nullable FK restrict, enum InvoiceTag rimosso; `tag_label` nei layout = nome. Menu Contabilita' > Attiva,
  permessi invoice-tags.*. FE features/invoice-tags, i18n `invoiceTags` (in {it,en}-invoices.ts, esposto da *-accounting.ts).
- `product_typologies.proforma_kind` (consultancy|institution|null): ProformaRequestService e InvoiceDraftBuilder
  leggono proforma_kind, non piu' il code; campo nel dettaglio Tipologie prodotto.
- Motivazione obbligatoria per righe RDA -> rejected / on_hold (BE required_if + FE dialog). In UI on_hold = "Stand by".
- Stato richieste proforma resta enum fisso (nota in 0193).
- Migrazioni 2026_10_17_120000/130000/140000 (reversibili, rinominano anche role_field_permissions). Contatori
  --step aggiornati in PurchaseOrderMigrationTest (28), SupplierCommissionDirectionTest (43), QuoteWorkflowMigrationTest
  (179): ogni nuova migrazione successiva li fara' crescere di nuovo.
- Aperti: flaky CampaignCrudTest AC-028 e Vitest chart/dashboard sotto carico; manuale Claude Docs da aggiornare
  (RDA, righe RDA, Priorita' RDA, Fatture, Tag fattura, Tipologie prodotto).
- Prossimo: spec 0248 stati ODA e stati riga RDA configurabili (solo presentazione), poi 0249 tipi di costo
  (natura/gruppo, modifica in linea, riordino).

## Spec 0245 RDA + 0246 Tipi di costo (audit 2026-10-13, Onda 1 chiusa) — COMMITTATO 101af875 (2026-10-13)

- 0245 `docs/specs/0245-purchase-request-integrity-fixes.xml` (D-1..D-8, AC-001..012 PASS): approvatore RDA =
  diretto superiore attivo del RICHIEDENTE (`User::employment` -> `EmploymentProfile::reportsTo()`), ripiego sul manager
  della funzione se attivo e != richiedente; regola solo alla creazione o se cambiano requester/manager/funzione (RDA
  legacy valide). `PurchaseRequestApproverResolver` unico (validazione `enforceApprover` nel WriteRequest + endpoint
  `GET /api/purchase-requests/approver-options` -> {source: reports_to|business_function|none, approvers[]}).
  `PurchaseRequestCapabilityResolver`: [] senza purchase-requests.view o visibilita'; Approve mai al richiedente.
  update() RDA -> closeIfComplete; sync righe estratta in `PurchaseRequestLineSynchronizer`. Riga RDA con riga ODA non
  esce da ordered/received dal cambio stato RDA (422 `purchase_request_lines.line_linked_to_order`).
  FE: campo Responsabile da approver-options (`use-purchase-request-approvers.ts`, preselezione singola, reset al cambio
  richiedente, valore legacy mantenuto). Helper test `purchaseRequestPayload` crea richiedente con superiore.
- 0246 `docs/specs/0246-cost-type-delete-guard.xml` (AC-001..006 PASS): tipo di costo usato da righe ODA o fatture
  passive -> 409 `cost_types.in_use`, delete nascosta in griglia; FK purchase_invoices.cost_type_id restrictOnDelete
  (migrazione 2026_10_17_110000). Il dettaglio tipo di costo espone ancora delete col solo permesso (409 al click).
- Aperti: messaggi inglesi hard-coded in PurchaseRequestLineSynchronizer (preesistenti); flaky DemoOpportunitySeederTest:147
  (seed casuale); manuale Claude Docs da aggiornare (RDA, righe RDA, Tipi di costo).
- Onda 1 dell'audit completata (0243-0246). Prossimo: Onda 2 "cataloghi acquisti e contabilita'" (RDA-03, RDA-04,
  RDA-13, ODA-03, ATT-10, ATT-11, ATT-12, COS-10, COS-11) — servono decisioni su ATT-11 e RDA-13.

## Spec 0244 — Correzioni integrita' ODA (audit 2026-10-13, ODA-01/02/04/06/07/08) — COMMITTATO b14653d2 (2026-10-13)

- Spec `docs/specs/0244-purchase-order-integrity-fixes.xml` (D-1..D-6, AC-001..AC-016 tutti PASS dal verifier).
  0210 D-18 marcata come superata.
- Regole: company_id immutabile dopo la creazione (422 + field readonly); rilascio righe RDA nel
  `PurchaseRequestLineLinker` (unico punto, metodo `transition()` stato+log): ordered -> approved, o on_hold se RDA
  chiusa; altri stati perdono solo oda_reference. `PurchaseOrderStatusService::propagate()`: cancelled -> unlink +
  purchase_order_lines.purchase_request_line_id null; fulfilled -> righe ordered -> received (`Linker::receive()`) +
  closeIfComplete per RDA; partially_fulfilled nessun effetto. Merge: update su ogni sorgente, delete su quelle
  svuotate (403), `PurchaseOrderDeletionGuard::assertDeletable` (409 fattura passiva) condiviso con delete.
  `PurchaseOrderStatus::isSendable()` (approved, sent_to_supplier): 409 su bozza email e invio; abilities.send dipende.
- Lang nuovi: `lang/{it,en}/purchase_orders.php`, `purchase_request_line_links.php`; rimosse 2 chiavi orfane da it.json.
- FE: invalidazione `purchaseRequestKeys.all` dopo cambio stato / eliminazione ODA. Guide IT/EN purchase-orders,
  purchase-requests, purchase-request-lines aggiornate. Manuale Claude Docs: non accessibile, da aggiornare (ODA, RDA).
- Test esistenti adeguati per requisito: PurchaseOrderCrudTest, PurchaseOrderEmailTest, PurchaseOrderMergeTest.
- Prossimo: spec 0245 RDA (RDA-01 responsabile = diretto superiore da employment_profile_manager, RDA-02, RDA-05,
  RDA-06), poi 0246 COS-03.

## LINEE PRODOTTO: MENU DELLE SELECT PIU' LARGO (2026-10-14) — VERDE, COMMITTATO

- Richiesta utente: in Gestione richieste le select delle linee prodotto (radice, filtro, categoria) aprivano un
  menu largo quanto la colonna, troppo stretto; ora come il prodotto delle righe offerta (`quote-product-select.tsx`):
  `w-[min(36rem, spazio disponibile)]`, mai piu' stretto del trigger.
- `SearchableSelect` riceve `contentClassName` (stessa manopola di `AsyncPaginatedSelect`), unito al popup con `cn()`.
  Costante `CATEGORY_POPUP_WIDTH_CLASS` in `features/product-lines/category-popup-width.ts`, passata dalle tre select
  `ProductCategory{Root,Filter,Tree}Select` -> vale ovunque c'e' `ProductLinesField`/`CompetenceLinesField`.
- Test: +1 in `searchable-select.test.tsx`; Vitest components + request-management + product-lines verdi, tsc -b ok,
  ESLint ok. Manuale: nessun impatto (solo larghezza del menu).

## MIGRAZIONE UTENTI: ACCOUNT GIUSTO + [OLD] SUGLI ATTIVI NON COLLEGATI (2026-10-14) — VERDE, COMMITTATO

- Decisione utente: ogni utente legacy si collega all'account qnet-2 della stessa persona; un utente legacy ATTIVO
  non collegato viene creato con "[OLD] " davanti al first_name (`UsersSource::UNMATCHED_NAME_MARK`; users.name ne
  deriva). Inattivi e adottati: nessun marchio. Il marchio sta in qnet-2 perche' solo qnet-2 conosce l'esito del
  matcher. `ExistingUserMatcher` invariato. Spec 0013 amendment "quater".
- Legacy (`../qnet`, committato 0a8067d2): `UserMigrationController` ordina `active DESC, last_name, name, id` (negli
  omonimi D'Aleo/De Rosa/Giordano il seed si collega al record attivo; tolto il TODO commentato `where('id', 449)`);
  nuovo `app/Support/Migration/LegacyUserAccountAliases` (legacy id => email qnet-2) per 9 nomi scritti diversamente
  (87, 118, 135, 300, 309, 343, 345, 424, 466).
- Verifica su dati reali (feed legacy + account del seed, matcher simulato): 0 errori, 9 alias + 3 omonimi sui giusti
  account, 12 attivi -> [OLD] (Ciro Creator, Admin Ciro, Api, Test, QTEST, QTASK, Supporto IT, Qualifica Backoffice,
  Enrico Locascio, Pasquale Persico, Antimo Dalvini, Sara Mariano).
- Test: `tests/Feature/Migration` 394/394 (UsersSourceImportTest "[OLD] Ada" e adoption test aggiornati =
  REQUIREMENT CHANGED, +2 test). Vitest help+i18n+requirements 341/341, tsc -b ok, Pint ok. Testi: requisito
  `users` IT/EN, guida in-app migrations IT/EN, manuale Claude Docs (paragrafo dopo "La sorgente Utenti riconosce...").
- DB locale: l'import utenti del 2026-10-14 (run #20) e' gia' avvenuto con la regola vecchia (account doppi per i 9
  alias e i 3 omonimi, nessun [OLD]); un re-run salta le righe con old_id: serve un import utenti pulito.

## MIGRAZIONE: NIENTE FUNZIONI "FORMAZIONE OLD" / "APL OLD" (2026-10-13) — VERDE, COMMITTATO

- Decisione utente: le funzioni aziendali "FORMAZIONE OLD" e "APL OLD" non devono piu' esistere; le categorie legacy
  restano sulla funzione con cui arrivano (rimappata via old_id), quindi "FORMAZIONE" e "APL".
- `CategoryBusinessFunctionLinker`: tolti `REDIRECTED_FUNCTIONS`, `redirect()`, `closeRedirected()` (supera direttive
  2026-09-16 e 2026-10-05 sul redirect). `ProductCategoriesSource::afterImport` chiude solo il ramo "[OLD] APL"
  (`LegacyAplBranch`, invariato). Spec 0013 amendment "ter".
- Testi: prerequisito `product-categories` IT/EN in `*-migration-requirements.ts` (tolta anche la frase stale
  "categoria con lo stesso nome viene riusata"); guida in-app migrations IT+EN e manuale Claude Docs: frase sulla
  funzione aziendale mantenuta.
- Test: `tests/Feature/Migration` 392/392 (3 test REQUIREMENT CHANGED). Vitest help+i18n 338/338, tsc -b ok, Pint ok.
- DB locale: le categorie gia' importate (id 46, 36, 51) sono ancora sulle funzioni OLD (id 29, 30): il cambio vale dal
  prossimo import pulito, salvo pulizia manuale.

## MIGRAZIONE: CATALOGO LEGACY MARCATO [OLD] (2026-10-13) — VERDE, NON COMMITTATO

- Decisione utente: categorie prodotto, prodotti e prodotti di costo (+ sotto-categorie di magazzino) dal legacy
  hanno il nome "[OLD] ...". Il ramo Costi e i rami fissi (Articoli/Veicoli/Attrezzature/Note spese) restano senza.
- Legacy (`../qnet`, NON committato): nuovo `app/Support/Migration/LegacyNameMark` (PREFIX `[OLD] `, null se
  blank) usato da `ProductCategoryMigrationController` (name), `ProductMigrationController` (name),
  `CostProductMigrationController` (name, category_name). Verificato via tinker: "[OLD] ISO", "[OLD] Alpha"/"[OLD] Veleni".
- qnet-2: `ProductCategoriesSource` NON adotta piu' per nome (tolti adopt/adoptableAttributes/
  cutManualChildrenFromLegacyFields; supera direttiva 2026-09-07 e spec 0183 F-6/AC-004).
  `CategoryBusinessFunctionLinker::fillFreeSlot` rimosso (morto). `LegacyAplBranch`: LEGACY_BRANCH = "[OLD] APL"
  (era "APL old"), tolti manualNodeNamed/legacyName/suffisso; PRODUCT_CATEGORIES confrontato con prefisso.
  `QualificaLegacyImportSeeder`: tolta la guardia `$staticRootIds` (serviva solo all'adozione).
  `MapsExternalProductRecord::productName()` taglia il nome prodotto a 191 (30 titoli legacy > 185 caratteri).
- Spec 0013 `<amendments>` 2026-10-13; spec 0183 AC-004 marcato SUPERATO. Guida in-app migrations IT+EN e
  manuale Claude Docs: paragrafo "[OLD]" prima di quello sui Prodotti di costo.
- Test: `tests/Feature/Migration` 390/390 (7 test di adozione tolti, 1 nuovo sul taglio a 191); suite completa
  10.397 verdi, 3 rossi solo in QuoteDocumentPdfTest (LibreOffice non installato, estraneo). Vitest help 122/122, tsc -b ok.
- Aperto: i dati gia' importati (183 categorie, 1.464 prodotti nel DB dev) NON vengono rinominati: decisione utente
  = il prefisso vale dal prossimo import pulito. Non toccate le redirect delle funzioni aziendali ("FORMAZIONE OLD",
  "APL OLD").
- BIS (stesso giorno): catalogo [OLD] solo storico. Legacy `ProductCategoryMigrationController` manda
  `is_active: false`; `ProductCategoriesSource` mappa `is_active` (assente = true). `CostProductCatalogue`: ROOT
  "[OLD] Costi" + rami "[OLD] Articoli/Veicoli/Attrezzature/Note spese"; `CostCategoryResolver` crea la radice non
  attiva. `ProductsSource` rende non attiva "No categoria" al primo uso (il `QualificaCatalogSeeder` e' a 504 righe,
  l'hook blocca ogni modifica: non toccato). APL: tolto lo spostamento dei prodotti sulle categorie manuali
  (`LegacyAplBranch::productCategoryFor`/PRODUCT_CATEGORIES rimossi), restano sul ramo "[OLD] APL". Nuovi test:
  opportunita' e offerta (ricavo + costo) importate su categorie non attive. Migration 392/392. Spec 0013 emendamento
  bis, guida migrations IT+EN, manuale Claude Docs aggiornati.
- Token migrazione: quello nel `.env` era firmato con chiavi Passport diverse da quelle del legacy locale (401);
  rigenerato per l'utente legacy 449 (Nicola Eliseo) il 2026-10-13.
- APL manuale: confermato dall'utente che bastano le categorie gia' seedate (Apprendistato, Tirocinio, Orientamento
  specialistico, con i loro campi); nessuna categoria nuova.
- Suite completa: 10.396 verdi. Rossi estranei: QuoteDocumentPdfTest (LibreOffice assente) e 3 casi di
  RecordActorWriteTest causati dalla cache rotte locale `bootstrap/cache/routes-v7.php` (creata 17:08): con
  `APP_ROUTES_CACHE` puntato altrove passa 10/10. Rimedio: `php artisan optimize:clear`.

## MIGRAZIONE: RIGHE PRODOTTO OPPORTUNITA' DALLE OFFERTE (2026-10-13) — VERDE, NON COMMITTATO

- Bug: opportunita' 19561 (legacy 173033) con 2 righe prodotto e 1 solo prodotto in offerta. Il legacy mandava la
  categoria propria "SICUREZZA 81/08" (radice non selezionabile); l'import offerta aggiungeva la sottocategoria del
  prodotto (`OpportunityProductLineCoverage::ensure()` confronta l'id esatto). 5.781 opportunita' padre + figlia.
- Fix nel legacy (`OpportunityMigrationController::productCategoryIds`): `product_category_ids` = categorie dei
  prodotti delle offerte, fallback alle categorie proprie solo se le offerte non ne hanno. Verificato via tinker:
  173033 -> [76]. 16.536 opportunita' con categorie da offerta, 10.101 avevano anche categorie proprie.
- qnet-2: nessun cambio di codice (solo docblock `OpportunitiesSource`); spec 0189 emendamento 2026-10-13 + AC-009
  + shape (tolto `quote_product_category_ids`, mai esposto). `tests/Feature/Migration` 396/396, Pint ok.
- Aperto: i dati gia' importati NON si correggono con un rilancio (il backfill scrive solo su opportunita' senza
  righe). Serve reimport pulito delle righe prodotto o una pulizia (decisione utente).

## MIGRAZIONE: WORKAROUND SPOSTATI NEL LEGACY (2026-10-13) — VERDE, NON COMMITTATO

- Regola nuova e permanente: `.claude/rules/backend.md §9` + riga in `CLAUDE.md §ROUTING`. Il legacy che INVIA e'
  responsabile della forma dei dati; qnet-2 riceve, collega via old_id, valida in modo minimo, scrive. Supera D-7
  della spec 0172.
- Legacy (`../qnet`, master, NON committato). Nuovi helper `app/Support/Migration/`: `PersonNameSplitter`,
  `LegacyRegistryFields` (vat/website/tax code), `EaSectorCatalog`, `LegacyCityLabel`, `TaskTemplateTree`,
  `LegacyQuoteOutcome`. Controller toccati: Registry, EaSector, Source, OperationalSite, TaskTemplate, PaymentMethod,
  ProductCategory, Attribute, CostProduct, Product, Opportunity, Quote, WorkOrder, CommissionConfiguration.
  Verificati via tinker sui dati reali (task template: 41 modelli/1314 azioni/263 warning identici al vecchio
  flattener; esiti offerta: 17.281 offerte, 0 mismatch con la vecchia logica qnet-2).
- qnet-2: cancellati `PersonNameSplitter`, `TaskTemplateTreeFlattener`, `LegacyOpportunityNotes`; nuovo enum
  `Support/LegacyQuoteOutcome`. Rimossi: re-split nomi, pulizia VAT/website/CF, settori per nome, trim fonti,
  difese ridondanti (roles piatti, active stringa, date 0000, fallback site_type), decodifica stati offerta/
  contratto/commessa, composizione note, fallback categorie/date/titolo/quote_date/quantita', regola %-vince,
  slug codice pagamento, `_`->`-` entity_type, multiple->multiselect, radici auto-referenziate, relink categorie,
  mapping campi costo->cost_*, filtro 0 pagamenti commessa.
- Contratti nuovi (dettaglio nelle spec 0013/0172/0174/0189/0201/0203): registries `sector_ids`; operational-sites
  `alias`; quotes `outcome` + `contract` + `customer_differs_from_opportunity` + commissions
  `{commission_type: PERCENTAGE|FIXED_AMOUNT, value}`; opportunities `general_notes` + `preliminary_info_blocks`;
  work-orders `force_close_reason` + `internal_notes`; task-templates albero pulito + `is_active` + `warnings[]`;
  cost-products `attribute_values`. Legacy e qnet-2 vanno rilasciati INSIEME.
- Test: `tests/Feature/Migration` 396/396, `tests/Unit/Migrations` 20/20, QuoteWorkflows 71/71, WorkOrders 250/250,
  PaymentMethods 95/95, Pint ok; FE help 122/122, `tsc -b --force` ok. `tests/Feature/Quotes`: 3 rossi PDF per
  LibreOffice assente in locale (non correlati). REQUIREMENT CHANGED su fixture/test di Registries, OperationalSites,
  Attributes, ProductCategories, PaymentMethods, TaskTemplates, CostProducts, Quotes, QuotesStatus, Realign,
  Opportunities, WorkOrders, CommissionConfigurations, LegacyAplBranch, QualificaLegacyImportSeeder.
- Guida in-app migrazioni IT/EN: frase categorie opportunita' allineata (precedenza alle categorie delle offerte).
  Manuale Claude Docs: stessa frase nella sezione Migrazioni.
- Fuori lotto / aperti: id settori posizionali (stabili solo se non si inseriscono settori a meta' alfabeto);
  `for_select_resource` legacy ancora con underscore; casi grigi (LegacyAplBranch, REDIRECTED_FUNCTIONS, ownership
  righe condivise `QuoteLineDuplicator`, fornitore riga costo, tetto manager slot, guardia id 0 nel trait
  condiviso); codice prodotto legacy (decisione utente pendente). Warning persi per scelta: data/titolo commessa,
  stato commessa sconosciuto, quantita' mancante.

## MIGRAZIONE: CONTRATTI LEGACY ROTTI CORRETTI (2026-10-13) — VERDE, NON COMMITTATO

- Contesto: audit "sotterfugi" del modulo migrazioni (principio utente: qnet-2 pulito, il legacy si adegua e
  manda i dati gia' nella forma di qnet-2). Questo e' il lotto 0 (contratti rotti); il resto del piano e' sotto.
- Legacy (`../qnet`, branch master, NON committato; `AppServiceProvider.php`/`config/database.php` erano gia'
  modificati dall'utente, non toccati):
  - `UserMigrationController`: `relationship_type` via const `RELATIONSHIP_TYPES` (1 employee, 2 self_employed,
    3 other; 0/null -> null), `qualification_type` via const `QUALIFICATION_TYPES` su `level_id` (8 livelli 1:1
    con `QualificationTypeEnum`); tolto l'eager load `level`. Prima arrivavano int/descrizione -> sempre null.
  - `WorkOrderMigrationController`: espone `stato_pagamento` (Field::id), `accordo_pagamento`, `insoluti` (bool)
    da `orderisos` (id piu' basso per commessa). Prima mancavano: righe pagamento sempre vuote.
  - `CompanySiteMigrationController`: `province` = `sigla` (era il nome per esteso, mai risolto dal geo resolver).
  - `CommissionConfigurationMigrationController`: `amount` solo se > 0 (era != 0); 0 righe negative nei dati.
  - Verificati chiamando i controller via tinker sui dati reali.
- qnet-2: `PaymentMethodsSource` legge il codice fiscale da `payment_method_code` (prima da `code`, che il legacy
  usa per uno slug). REQUIREMENT CHANGED: fixture di `PaymentMethodsSourceImportTest` allineate al contratto reale.
- Spec 0201 D-14 aggiornata. Test: `tests/Feature/Migration` 416/416, `tests/Unit/Migrations` 30/30, Pint ok.
- Aperto (decisione utente): `ProductsSource` legge `code` ma il legacy non lo manda (sempre sequenza PRD);
  `services.code` valorizzato su 1322/1444, 77 codici duplicati, qnet-2 `code` unique max 32.
- Prossimi lotti (audit): stati offerta/contratto/commessa -> `outcome`/`contract`/`force_close_reason` dal legacy;
  `TaskTemplateTreeFlattener` nel legacy (supera D-7 spec 0172); categorie in ordine topologico; nomi/comuni/
  settori dalle anagrafiche; codice modalita' di pagamento gia' univoco dal legacy.

## MIGRAZIONE OFFERTE: STATI LEGACY + NOTE INFO PRELIMINARI OPPORTUNITA' (2026-10-13) — VERDE, COMMITTATO

- Spec 0189 aggiornata: G-6 riscritto, nuovi G-18 (fase opportunita' -> stato offerta Presentata), G-19 (note Info
  preliminari), AC-013/AC-014, contratti API `migration/quotes` (+`opportunity_phase`, `won_by_other_quote`) e
  `migration/opportunities` (+`preliminary_info_notes`).
- Legacy (`../qnet`, branch develop, commit 2e3eb13a, non pushato): `QuoteMigrationController` (+2 campi, const WON_STATUSES),
  `OpportunityMigrationController` (+`preliminary_info_notes`), nuovo `App\Support\Migration\LegacyPreliminaryInfoNotes`
  (tabelle pre_info_* tranne R&S, prima riga per id). Verificati via curl su qnet.test.
- qnet-2: `LegacyQuoteState` (DTO dal record), `LegacyQuoteStatusResolver` (mapping, per nome poi per gruppo),
  `LegacyQuoteStatusApplier` (non applica piu' il vincolo riga ricavo: warning "Legacy status N closed the offer without
  product lines."), `QuotesSource::eachLegacyRecord()`, comando `quotes:realign-legacy-status [--dry-run]` (solo offerte
  su stato di sistema `open` e senza activity_log). `LegacyOpportunityNotes` (note + riservate + card),
  `OpportunitiesSource::backfillExisting()` (categorie + note mancanti, senza toccare updated_at).
- Decisioni utente: Rifiutata con accepted_data resta closed_lost; Accettata dal cliente senza pending -> primo validated;
  Pro-forma/Accettato -> closed_lost se altra offerta vinta, altrimenti "In trattativa".
- Test: REQUIREMENT CHANGED `QuotesSourceImportTest` (offerta chiusa senza righe ora si chiude); nuovi
  `QuotesSourceStatusImportTest`, `RealignLegacyQuoteStatusCommandTest`, 2 test in `OpportunitiesSourceImportTest`.
  Pest parallelo 10432 verdi (rossi solo i 3 `QuoteDocumentPdfTest`, LibreOffice assente); Pint pulito;
  Vitest help 122/122, ESLint + `tsc -b --force` puliti.
- Dry-run sui dati locali: 975 offerte da riallineare (388 -> Persa, 253 -> In trattativa, 121 -> VINTO, 106 -> Da
  Richiamare, 52 -> Rimandata, 33/12 globale chiuse, 7 -> Validato). NON eseguito in scrittura.
- Manuale: guida `migrations` IT/EN + manuale Claude Docs (sezione Migrazioni). PDF da rigenerare.
- Prossimi passi: eseguire `php artisan quotes:realign-legacy-status`; rilanciare la sorgente Opportunita' per le note.

## OLD ID FILTRABILE, ORDINABILE E CERCABILE IN OGNI GRIGLIA (2026-10-13) — VERDE, COMMITTATO

- Richiesta utente: "la colonna id legacy deve essere filtrabile e sortabile ovunque".
- `InjectsDefaultIdColumn::defaultLegacyIdColumn()`: `filterable: true`, `filterType: 'number'` (era solo sortable);
  `LEGACY_ID_COLUMN` da `private` a `protected const`. `id` di default resta non filtrabile (invariato).
- Bug trovato: `CommissionConfigurationsTableDefinition::resolveConfig` filtra le colonne con `visibleColumnIds()`
  (costruita da `columns()` grezze) e scartava `old_id` iniettato -> colonna mai mostrata. Fix: `->push(self::LEGACY_ID_COLUMN)`
  (l'iniezione resta gated da `LegacyIdVisibility`).
- Test `TableLegacyIdColumnTest`: REQUIREMENT CHANGED `filterable` true; nuovi "filters the rows by old_id" e
  sweep su TUTTI i domini di `config('tables.definitions')` la cui tabella ha `old_id` (28): colonna esposta,
  sortable+filterable, rows con filtro+sort 200. Verificato che fallisce senza il fix provvigioni.
- Verifica: Pest parallelo 10406 verdi, rossi solo i 3 `QuoteDocumentPdfTest` (LibreOffice); Pint pulito; Vitest help
  122/122; ESLint + `tsc -b --force` puliti. Nessun codice FE (il filtro numerico e' generico).
- Segnalazione utente: in Opportunita' la ricerca rapida "173029" non trovava il record (old_id 173029, id 19558):
  filtri di colonna OK, ma la ricerca rapida copriva solo `name`. Fix: la colonna `old_id` iniettata e' anche
  `searchable: true` -> entra nella ricerca rapida (OR-LIKE) di tutte le 28 griglie, solo per chi vede l'ID legacy.
  Test: ricerca opportunita' per old_id, negativo senza permesso, sweep esteso (`searchable` contiene `old_id` +
  search 200). Pest parallelo 10407 verdi; rossi i 3 LibreOffice + `DemoOpportunitySeederTest` INV-1/INV-2
  instabile (1 fallimento su 6 run isolati, non legato a questa modifica).
- Manuale: guida `general` IT/EN (righe "Colonne" e "Cerca…") + manuale Claude Docs (stesse righe). PDF da rigenerare.
- FIX (COMMITTATO): nel filtro di colonna "ID legacy" di Opportunita' "173029" non compariva nella checklist: la
  checklist (Set Filter) carica al massimo 200 valori (`TableValuesRequest::MAX_LIMIT`) e la sua ricerca e' client-side
  su quei 200 (19.561 opportunita'). `defaultLegacyIdColumn()` ora ha `hasFilterValues: false` -> solo filtro numerico
  (Uguale a / intervallo), nessuna checklist, in tutte le griglie. Nessun codice FE (`resolveFilter` gestisce gia' il
  flag). Test nuovo "filters old_id with the number condition only..." + sweep sulle 28 griglie con `old_id` ora
  verifica anche `hasFilterValues: false`; `tests/Feature/Table` 303 verdi, Pint pulito.
  Manuale: nessun impatto (la guida dice gia' "filtrare come numero").

## Permessi ODA/contabilita' + seeder additivo acquisti/contabilita' — VERDE, NON COMMITTATO (2026-10-13)

- Causa del "Non hai il permesso di visualizzare gli ordini di acquisto": catalogo permessi del DB locale non
  risincronizzato (mancavano 59 permessi: purchase-orders.*, purchase-invoices.*, cost-types.*, commission-*,
  enasarco-*, agent-fiscal-*, api-clients.*, invoices.downgrade, tasks.viewTeam, legacy-ids.view). Risolto sul DB
  con `permissions:sync` + `roles:create-super-admin` + i soli nuovi permessi dati ad `admin`. Nessun codice
  cambiato: dopo ogni deploy di un modulo va lanciato `php artisan permissions:sync`.
- Nuovo `DemoAccountingSampleSeeder` (SOLA AGGIUNTA, mai cancella; `php artisan db:seed --class=DemoAccountingSampleSeeder`,
  agisce come il primo super-admin, mail silenziate): step `DemoSamplePurchaseRequestSeeder` (RDA, righe in ogni stato),
  `DemoSamplePurchaseOrderSeeder` (meta' righe approvate -> ODA via PurchaseOrderFromRequestLinesService + ODA diretti,
  righe su commessa, tutti i 7 stati via PurchaseOrderStatusService, righe RDA degli ODA evasi -> received),
  `DemoSamplePurchaseInvoiceSeeder` (documento passivo per ODA fatturabile non ancora fatturato, pagamenti misti),
  `DemoSampleInvoiceSeeder` (proforma + fattura attiva sulle commesse senza richieste, incassi, registrazione, una
  richiesta lasciata in attesa), poi maturato/ENASARCO/profili (solo se tabelle vuote)/liquidazione.
  Volontariamente NON richiamato da DemoDataSeeder (deroga a backend.md 3.1: quel percorso copre gia' gli stessi moduli).
- Estratti `Database\Seeders\Support\ActiveInvoiceWriter` e `PayableDocumentWriter` (usati anche da DemoInvoiceSeeder e
  DemoPurchaseInvoiceSeeder). Fix incluso: DemoInvoiceSeeder chiamava `updateDetails` senza `$actor` (rotto dalla 0243);
  incasso parziale su piano a rata unica -> residuo in nuova rata (prima: 422 "no later open installments").
- Test: `tests/Feature/Seeding/DemoAccountingSampleSeederTest.php` (3) verde; Invoices 73, CommissionAccruals 26,
  PurchaseInvoiceInstallments 58, PurchaseRequests 102, PurchaseOrders 160/161 (rosso preesistente rollback migrazione).
- Ambiente: il PHP locale va in segfault a caso (anche su test non toccati, es. QualificaStaffSeederTest); in seriale
  usare `-d zend.max_allowed_stack_size=8M -d zend.reserved_stack_size=512K`. Paratest non accetta piu' path insieme.
- Step 0 aggiunto (senza test, richiesta utente): `DemoCommissionShowcaseSeeder` (regole provvigionali + commesse
  "Demo commissioni - ...", idempotente) prima degli acquisti, cosi' lo step fatture fattura commesse con provvigioni.
  DB locale: 13 provvigioni su righe d'offerta, 3 movimenti di maturato aperti, 0 estratti (il lancio di settembre
  esisteva gia' e il seeder non ripete un periodo gia' lanciato).
- Manuale: nessun impatto (solo dati di esempio e catalogo permessi).

## Spec 0243 — Correzioni integrita' Contabilita' attiva (audit 2026-10-13, Onda 1, ATT-01..04) — COMMITTATO 857b87c4 (2026-10-13)

- Spec `docs/specs/0243-active-accounting-integrity-fixes.xml` (D-1..D-4, AC-001..AC-020 tutti PASS dal verifier).
  Fonte: PDF "Audit Acquisti, Costi e Attiva" 2026-10-13 (77 rilievi, 4 onde). Prossime spec Onda 1: 0244 ODA
  (ODA-01/02/04/08), 0245 RDA (RDA-01/02/05/06), 0246 Tipi di costo (COS-03).
- Decisione utente per 0245 / RDA-01: l'elenco "Responsabile di funzione" della RDA si riempie SOLO con il diretto
  superiore del richiedente, preso da `employment_profiles` / `employment_profile_manager`.
- Regole introdotte (409 dal service, azioni di riga e blocco `permissions` coerenti, FE non ricalcola):
  - Richiesta proforma: `ProformaRequest::isDeletable()` (pending e senza fattura); `createForWorkOrder` -> 409
    `already_pending` / `already_issued` (una sola generazione per commessa, D-2).
  - Commessa: `WorkOrder::hasAccountingDocuments()` + scope `withAccountingDocumentFlags()` (proforma, fatture attive,
    ODA testata/righe, fatture passive) -> 409 su delete, `delete` nascosta; nuove relazioni invoices/purchaseOrders/
    purchaseOrderLines/purchaseInvoices su WorkOrder.
  - Fattura: `Invoice::isRegistered()` (type=invoice); `RegisteredInvoiceGuard` (PUT/DELETE 409 `registered_locked`;
    PATCH details che cambia external_number/date -> 403 senza `invoices.downgrade`); `InvoiceLineGuard` (riga d'offerta
    in una sola fattura, 422 `lines.N.quote_line_id`); bozza esclude righe gia' fatturate. Nuova ability `downgrade`
    in InvoicePolicy, `permissions.actions.downgrade` nel detail. `updateDetails` riceve `User $actor`.
  - Migrazione `2026_10_17_100000_restrict_work_order_accounting_foreign_keys.php`: restrictOnDelete su
    proforma_requests.work_order_id, invoices.proforma_request_id, invoices.work_order_id (reversibile).
  - Lang nuovi: `lang/{it,en}/{invoices,proforma_requests,work_orders}.php`.
- Test esistenti adeguati (requisito cambiato D-2/D-3): `InvoiceIssueTest` AC-004 (righe libere), `InvoiceLifecycleTest`
  AC-005 (+downgrade), `tests/Helpers/InvoiceHelpers.php` (permesso downgrade).
- Suite: backend 10599/10606; i 4 rossi sono preesistenti (rollback migrazioni PurchaseOrder/QuoteWorkflow/
  SupplierCommission, QuoteDocumentPdf senza soffice). Vitest 7905 pass, 5 rossi in file non toccati (chart skeleton,
  request-dashboard-tabs) da verificare a parte. tsc -b --force pulito.
- Ambiente: il PHP di Laragon non carica sqlite; per i test usare Herd `/c/Users/ciroc/.config/herd/bin/php84`
  (con memory_limit alto per --parallel).
- Manuale completo Claude Docs: NON aggiornato (doc non condiviso col connettore). Da aggiornare: Fatture attive,
  Richieste proforma, Commesse. Guide in-app IT/EN aggiornate.
- Aperti minori: messaggio "The line does not belong to the work order..." ancora non tradotto (InvoiceLineGuard);
  WorkOrderService 363 e WorkOrdersTableDefinition 365 righe (oltre il soft limit di 300).

## Spec 0213 — Liquidazioni Agenti M2: lanci, estratti, rettifiche, ritenute/ENASARCO — VERDE, NON COMMITTATO (2026-10-13)

- Spec `docs/specs/0213-agent-commission-settlements.xml` (approvata, D-1..D-9, AC-001..AC-024). Emendamento in build:
  `commission_accruals.commission_settlement_id` restrictOnDelete (security L-2).
- Dati: `enasarco_parameters` (year unique, total_rate, agent_share_percent, single/multi_firm_ceiling),
  `commission_settlement_runs` (filters/skipped json), `commission_settlements` (snapshot profilo/aliquote, importi,
  numero per company+anno all'emissione), `commission_settlement_number_sequences`; `commission_accruals` +company_id
  (backfill dalla fattura), +commission_settlement_id, +notes, +created_by, recipient_role nullable (ADJUSTMENT);
  `agent_fiscal_profiles.vat_rate_id`. Migrazioni 2026_10_16_100000..100500.
- Servizi `App\Services\Commissions\Settlements\*`: SettlementCalculator (bcmath, D-2/D-3), OpenMovementSelector (lock),
  SettlementComposer, SettlementRunner (una transazione per (referent, company), ordine lock referent -> estratto ->
  movimenti; ordine salti draft_exists, non_positive, below_threshold, missing_profile, missing_enasarco_parameters),
  CommissionSettlementService (ricalcola solo in aggiunta, emetti, annulla, elimina, note), CommissionAdjustmentService
  (activity log manuale `commission-adjustment.*`). Synchronizer M1 scrive company_id; ADJUSTMENT mai stornati;
  `--settled-before` esclude ADJUSTMENT e movimenti agganciati.
- Endpoint in routes/api/agent-commissions.php: enasarco-parameters CRUD, POST/GET commission-settlement-runs,
  GET/PATCH/DELETE commission-settlements/{id} + recalculate/issue/cancel, POST/PUT/DELETE commission-adjustments.
  422 di ricalcola/emetti: `errors.reason[0]` = codice (missing_profile|missing_enasarco_parameters). Date Y-m-d strette.
- Tabelle: commission-settlement-runs, commission-settlements (grouping + somme), enasarco-parameters; commission-accruals
  +settlement ("n/anno"|"Bozza"), +company, status open|draft|issued|settled_externally; agent-fiscal-profiles +vat_rate.
  Helper condiviso `App\Tables\Support\SetExpressionQuery`. Guardie 409: profilo usato da un estratto, aliquota IVA usata
  da un profilo (anche bulk delete, VatRatesTableDefinition ora usa VatRateService).
- FE: features commission-settlement-runs, commission-settlements (dettaglio sheet + pagina /commission-settlements/:id,
  rettifiche), enasarco-parameters; profili con aliquota IVA; Maturato con colonne estratto/societa' e filtro "In estratto".
  Guide IT/EN nuove/aggiornate, "in fase di sviluppo". help-guide-keys 67->70.
- Test cambiati per requisito (6 migrazioni): rollback step PurchaseOrderMigrationTest 11->17, SupplierCommissionDirection
  23->29, QuoteWorkflowMigration 159->165; FieldCatalogueEndpointTest (+2 risorse); CommissionAccrualsTableTest e
  AgentFiscalProfileTableTest (colonne nuove).
- Verifier: Pest parallelo 10273 (unici rossi QuoteDocumentPdfTest x3 = soffice, ambiente); 0213 294/294 due volte;
  Vitest 7496/7499 (3 timeout stat-* fuori scope, verdi da soli); tsc -b e ESLint puliti; Pint pulito. Espressioni JSON
  della tabella lanci NON provate su MySQL.
- Aperto: manuale Claude Docs (access denied): sezioni Provvigioni agenti > Lanci, Estratti, Parametri ENASARCO, Profili
  fiscali (IVA), Maturato (estratto, rettifiche). Review security da confermare col cliente: L-6 chi vede gli estratti vede
  codici fattura/offerta; limite noto: annullare un estratto emesso non ricalcola il massimale ENASARCO dei successivi.
  Dopo il deploy: permissions:sync, assegnare commission-settlements.* / enasarco-parameters.*, inserire i parametri
  ENASARCO reali (i demo non sono di legge).
- Prossimo: M3 (PDF estratto, email massiva, registrazione fattura/notula agente = DOCUMENT_RECEIVED, export paghe user).

## Spec 0212 — Liquidazioni Agenti M1: profili fiscali + maturato provvigioni — VERDE, NON COMMITTATO (2026-10-12)

- Brief modulo completo M1-M5: `docs/specs/0212-agent-commission-settlements-brief.md`. Spec M1
  `docs/specs/0212-agent-commission-accruals.xml` (approvata, D-1..D-11, AC-001..AC-022; D-10 emendata in build:
  `commission-accruals` abilities viewAny, view, export).
- Dati: `agent_fiscal_profiles` (referent_id restrict, ritenuta rate/base, enasarco_enabled + mandate mono|pluri,
  validita' non sovrapposta per referent) e `commission_accruals` (libro mastro a delta, senza unique: bucket
  (invoice_id, quote_line_id, recipient_role, recipient_type, recipient_id); snapshot di destinatario/ruolo; FK
  informative nullOnDelete; ACCRUAL positivi, REVERSAL negativi; `settled_externally_on`). Migrazioni 2026_10_15_100000/100100.
- Calcolo (D-1): quota per fattura cumulativa sulla riga d'offerta (tetto 100%) x r = incassato/total_amount, bcmath.
  `App\Services\Commissions\Accruals\{CommissionShareCalculator,AccrualStateLoader,CommissionAccrualSynchronizer}`.
  Agganci SINCRONI in `InvoiceCollectionService::record/clear` e `InvoiceService::update` (304 righe). Lock in ordine
  di id su tutte le fatture che condividono righe d'offerta + letture con lock dei movimenti (review security M1).
  Storni datati oggi; primo sync di un bucket = replay per data di incasso.
- Comando `commissions:sync-accruals {--invoice=*} {--from=} {--settled-before=} {--dry-run}`, schedulato 02:00
  withoutOverlapping + onOneServer. Dopo il deploy: lanciarlo una volta con `--settled-before=<data taglio>` per lo storico.
- UI: Contabilita' > Provvigioni agenti (`accounting-commissions`): `/agent-fiscal-profiles` (CRUD, permessi di campo,
  sort/filter vietati sui campi nascosti) e `/commission-accruals` (sola lettura, row grouping, totali, quick filter).
  FE `features/{agent-fiscal-profiles,commission-accruals}`, rotte in `routes/agent-commission-routes.tsx`, guide
  in-app IT/EN "in fase di sviluppo".
- Fix fuori scope tenuto: `TableBulkDeleteService::loadModels` usa la chiave qualificata (500 su tabelle con JOIN).
- Test cambiati per requisito (2 migrazioni nuove): PurchaseOrderMigrationTest step 9->11, QuoteWorkflowMigrationTest
  157->159, SupplierCommissionDirectionTest 21->23; FieldCatalogueEndpointTest (+2 risorse); help-guide-keys 65->67.
- Verifier: Pest parallelo 10112 (unici rossi residui: 3 QuoteDocumentPdfTest per soffice assente, ambiente); 0212
  97/97 due volte; tsc -b pulito; ESLint pulito; Vitest completo con 6-7 timeout sotto carico (fuori scope, verdi da soli).
- Aperto: manuale Claude Docs NON aggiornato (connettore: access denied) -> nuova sezione Contabilita' > Provvigioni
  agenti (Profili fiscali, Maturato), "in fase di sviluppo". Dopo il deploy `php artisan permissions:sync`.
  Review security L1: l'activity log del profilo mostra i valori a chi ha viewActivity anche se il campo e' nascosto.
- Prossimo: M2 (lanci, estratti in bozza, rettifiche, ritenute + tabella `enasarco_parameters`, soglia, solo referent).

## Spec 0211 — Scadenze passive (fase 1) — VERDE, NON COMMITTATO (2026-10-12)

- Spec `docs/specs/0211-purchase-invoice-installments.xml` (approvata, D-1..D-10, AC-001..AC-019). Decisioni utente:
  tabella documento unica `purchase_invoices` (document_type proforma|invoice), N:N con ODA subito.
- Fix MySQL (2026-10-12): unique `(purchase_invoice_id, sequence)` nominato esplicitamente `purchase_invoice_installments_sequence_unique`
  (il nome automatico superava i 64 caratteri di MySQL; SQLite non lo rileva). Nomi di indici/FK lunghi: sempre espliciti.
  `PurchaseInvoiceMigrationTest` AC-001 ora fa rollback/migrate per `--path` dei 3 file, non `--step`.
- BE: migrazioni `2026_10_14_1000{00,10,20}` (purchase_invoices, purchase_invoice_purchase_order, purchase_invoice_installments
  con VIRTUAL residual_amount + indice). Model PurchaseInvoice, PurchaseInvoiceInstallment (alias morph purchase_invoice,
  purchase_invoice_installment); enum PurchaseInvoiceType, PurchaseInstallmentStatus (unpaid|paid). Servizi PARALLELI in
  App\Services\PurchaseInvoices (Planner, Updater, PaymentService record/clear, BulkPayer); riusati invariati
  InstallmentScheduleCalculator/InstallmentRebalancer/ResidualMode. Servizi attivi NON toccati.
  TableDefinition `purchase-invoice-installments` (+ app/Tables/PurchaseInvoiceInstallments/*) con row grouping
  (supplier, cost_type, work_order, company, company_site, operational_site, payment_method_code, financial_account, due_month).
  Rotte routes/api/purchase-invoices.php (show/PATCH, PUT/DELETE `/payment`, POST `/payments` massivo).
  Permessi: purchase-invoices.{viewAny,view,pay}, purchase-invoice-installments.{viewAny,view,update,export}.
  Nav: gruppo `accounting-payable` "Passiva" > "Scadenze". Pagamento registra financial_account_id (stessa societa'
  del documento o null, altrimenti 422). ODA collegato non eliminabile (409 in PurchaseOrderService::delete).
  payment-methods/for-select espone ora meta.payment_method_code. DemoPurchaseInvoiceSeeder in DemoDataSeeder dopo
  DemoInvoiceSeeder (servono i metodi di pagamento). Rollback step bumpati: PurchaseOrderMigration 9,
  SupplierCommissionDirection 21, QuoteWorkflowMigration 157.
- FE: features/purchase-invoice-installments/* (tabella, quick filter Da pagare/In scadenza/Scadute/Pagate/Tutte,
  footer totali, dialog modifica con select modalita', pagamento con conto + residuo, annulla, pagamento massivo
  `bulk_pay` raggruppando per fornitore). Route in routes/purchasing-routes.tsx. HELP_GUIDE_KEYS = 65 (guida
  purchase-invoice-installments IT/EN, nota "registrazione documenti in fase di sviluppo"). Ritocchi framework:
  table-view.tsx (posizione actionsColumnWidthFor), use-bulk-actions-slot.tsx (span title solo su voci disabilitate).
- Verde (verifier): Pest seriale completo 9924/10003 con 21 failure solo ambiente (PDF soffice / ZipArchive),
  PurchaseInvoiceInstallments 58/58; Pint; Vitest 7376/7382 (6 timeout da carico, verdi isolati); tsc -b --force; ESLint
  (1 errore preesistente in features/quotes/column-renderers.tsx, fuori scope).
- Aperto: fase 2 = form/CRUD/PDF documenti passivi e conversione proforma->fattura; export xlsx coperto solo con queue
  finta; manca test esplicito "ODA non collegato eliminabile"; refactor base comune servizi pagamento attivo/passivo;
  bulk payment: select conto non filtrato per societa' (422 lato server); `pest.sh --parallel` non funziona (worker senza
  php.ini); manuale Claude Docs: nuova sezione Contabilita' > Passiva > Scadenze.

## Spec 0209 — Fornitore sulle righe RDA + righe compatte — VERDE, COMMITTATO 8473e971 (2026-10-12)

- Spec `docs/specs/0209-purchase-request-line-suppliers.xml` (approvata, D-1..D-11, AC-001..AC-018). Modifica 0208.
- Dati: `purchase_request_lines.supplier_id` (FK registries nullOnDelete); `purchase_requests.supplier_id` ELIMINATA.
  Migrazione `2026_10_12_110000_move_supplier_to_purchase_request_lines` (copia testata -> righe, pulizia
  `role_field_permissions`; `Schema::withoutForeignKeyConstraints` perche' su SQLite il drop ricostruisce la tabella e
  cancellerebbe le righe in cascata). Applicata al DB dev.
- Regola unica D-3: `PurchaseRequestCapabilityResolver::canUpdateSupplier` = RDA aperta AND (pending + update RDA OR
  approved + fulfill|manage); esposta come `lines[].abilities.update_supplier` (Resource + righe SSRM). Da `ordered` in
  poi il fornitore e' congelato. Verso `ordered` il fornitore e' obbligatorio: `POST /purchase-request-lines/status`
  accetta `suppliers: [{line_id, supplier_id}]` (solo con to_status=ordered), 422 `line_ids.N`.
- Nuovo `POST /api/purchase-request-lines/supplier` {line_ids, supplier_id} -> {updated_count}
  (`PurchaseRequestLineSupplierController/Request/Service`, lock condivisi in `PurchaseRequestLineLocker`).
- Liste: RDA colonna `suppliers` (NamedRef[], filtro text + /values, non ordinabile, ricerca P.IVA sulle righe);
  Gestione righe colonna `supplier` (filtro, sort, /values) + azione massiva "Assegna fornitore" (`line-supplier-dialog.tsx`).
- FE: PUT invia `lines[].supplier_id` per OGNI riga (PUT completa). Select fornitore di riga attivo solo con
  `abilities.update` della RDA AND `update_supplier` (chi ha solo evasione usa Gestione righe). Riga compatta
  (`purchase-request-line-row.tsx`, `-lines-header.tsx`, `-line-layout.ts`, `-line-actions.tsx` azioni solo icona +
  popover motivazione, `-supplier.tsx` SupplierPicker). i18n `purchaseRequests.columns.suppliers`,
  `purchaseRequestLines.columns.supplier`, `supplierDialog.*`. Guide in-app IT/EN aggiornate (sezione `assign-supplier`).
- Verde: Pest suite completa in parallelo 9810/9816 -> sistemati i 2 test di rollback a step (148 e 12 step: chi
  aggiunge una migrazione li aggiorna), i 3 rossi residui sono `QuoteDocumentPdfTest` (LibreOffice assente in locale).
  Per il parallelo serve `PHPRC` verso un php.ini con pdo_sqlite/sqlite3/zip e memory_limit=2G (i `-d` non arrivano ai worker). Vitest purchase-requests/help 193/193 (suite completa
  7220 con 2-3 flaky diversi per run, verdi da soli); ESLint, Pint, `tsc -b --force` puliti. Rimosso
  `line-status-dialog.head.tmp`.
- Ambiente: php CLI Laragon senza pdo_sqlite/zip -> Pest con `-d extension=pdo_sqlite -d extension=sqlite3 -d extension=zip`.
- Aperto: `PurchaseRequestService.php` 330 righe (soft limit); manca test della griglia Gestione righe (azione massiva);
  verifica visiva desktop/375px; manuale Claude Docs (RDA righe/testata, cambio stato Ordinato, Gestione righe) da aggiornare.

## Spec 0210 — Modulo ODA (Ordini di Acquisto) — COMPLETA: A+B COMMITTATI, C VERDE NON COMMITTATO (2026-10-12)

- Checkpoint C (MT-6, MT-13, MT-14) verde, gate finale: AC-001..AC-023 PASS. Pest 10104/10109 (2 QuoteDocumentPdfTest
  = soffice; 1 SupplierCommissionDirectionTest rollback step = migrazioni 0212 di altra sessione), PurchaseOrders+CostTypes
  144/144, Vitest 7435 (14 timeout da carico, verdi isolati), tsc -b --force EXIT=0; probe MySQL dev senza 5xx.
- BE C: PDF = case `purchase_orders` in DocumentLayoutModule, DocumentLayoutPurchaseOrderVariableDefinitions, sorgente
  tabella `purchase_order_lines` (DocumentLayoutProductSources), Rendering/PurchaseOrders/*, PurchaseOrderDocumentLayoutResolver
  (layout di default attivo del modulo, 422 se assente), PurchaseOrderPdfRenderer ("ODA-{n}-{anno}.pdf"),
  PurchaseOrderPdfController `GET /api/purchase-orders/{id}/pdf`; ramo purchase_orders in DocumentLayoutPreviewController.
  Email = case `purchase_orders` in EmailTemplateModule, PurchaseOrderEmailOwner (alias purchase_order, view/send),
  PurchaseOrderEmailAttachmentImporter (sorgenti purchase_order_pdf, documents), PurchaseOrderEmailService,
  variabili email, rotte routes/api/purchase-order-emails.php (richiesto da purchasing.php), senza reminder.
  Nessun layout/template di default seminato: l'utente crea un Layout del modulo "ODA" per stampare.
- FE C: guide in-app complete purchase-orders (overview, create, lines, numbering, status, merge, rda-link, delete,
  pdf-email, permissions), document-layouts e purchase-requests (colonna ODA = link) aggiornate; DOCUMENT_LAYOUT_MODULES
  + sorgente purchase_order_lines + starter ODA (createPurchaseOrderLayoutStarter); allegato "PDF dell'ODA" nel composer.
- Ambiente test: i test che generano docx richiedono ext-zip (`$TEMP/pestzip.sh`); il parallelo richiede PHPRC con sqlite+zip.
- Manuale Claude Docs (KwvSrXafsGqT9qzZULxJhh): NON aggiornato, il doc non e' condiviso con la sessione (verdict access).
  Sezioni da aggiornare: Acquisti > ODA (nuova: creazione, righe, numerazione, stati, Unifica, RDA, PDF/email, permessi),
  Acquisti > Tipi di costo (nuova), Acquisti > Gestione righe RDA (Genera ODA), Acquisti > RDA (colonna ODA link),
  Configurazione > Layout (modulo ODA, righe ODA), Contabilita' > Passiva > Scadenze (ODA collegata non eliminabile).

- Checkpoint B committato: MT-5 (`POST /purchase-request-lines/purchase-orders` Genera ODA da righe RDA,
  `PurchaseOrderFromRequestLinesService`; Unifica `GET merge-candidates` / `POST merge` / `GET merge-logs`,
  `PurchaseOrderMergeService`), MT-11 (FE dialog stato/unifica/storico, hook PDF/email), MT-12 (FE Genera ODA in
  Gestione righe RDA, link colonna ODA via `purchase_order{id,code}` sulle righe). Verde: Pest PurchaseOrders+
  PurchaseRequests 218/218, Vitest purchase-orders/purchase-requests/table/i18n 674/674, tsc -b --force, ESLint, Pint.
- Griglia condivisa: opzione opt-in `inlineAllActions` (row-actions + table-view, `inlineActionsColumnWidth`) attiva
  su liste ODA e RDA = niente menu "..." (richiesta utente); icona `file-down` mappata per l'azione PDF ODA (prima
  ricadeva sull'icona di fallback a tre puntini). Pulizie post-verifier: docblock di TableView
  rimesso sopra il componente; in use-bulk-actions-slot il wrapper `<span title>` solo per le voci con disabledReason.
- Verifier B: Pest 9941/9945 (3 QuoteDocumentPdfTest = soffice), Vitest mirato 815/815, probe MySQL dev senza 5xx.
- Resta checkpoint C: MT-6 (PDF + email ODA lato BE: gli hook FE use-purchase-order-pdf/email chiamano endpoint non
  ancora presenti), MT-13 (guide complete), MT-14 (gate completo).

- Spec `docs/specs/0210-purchase-orders.xml` (approvata, D-1..D-20, AC-001..AC-023). Build in 3 checkpoint:
  A (fatto) = MT-1..4, MT-7..10; B = MT-5 (Genera ODA da RDA, Unifica + storico), MT-11 (FE stato/unifica/PDF/email
  azioni), MT-12 (FE Genera ODA in Gestione righe RDA); C = MT-6 (PDF + email BE), MT-13 (guide complete), MT-14 (gate).
- BE: migrazioni `2026_10_13_1000*` (cost_types con 4 valori seed, purchase_order_number_sequences, purchase_orders,
  purchase_order_lines, purchase_order_sector, purchase_order_merge_logs); status/source_action sono string (enum PHP
  PurchaseOrderStatus con allowedTransitions/isEditable, PurchaseOrderMergeAction). Model CostType, PurchaseOrder
  (code() = n/anno), PurchaseOrderLine, PurchaseOrderMergeLog, PurchaseOrderNumberSequence; alias morph purchase_order,
  purchase_order_line, cost_type. Services/PurchaseOrders: PurchaseOrderService (create con copie, createOne(header,
  lines[purchase_request_line_id], sectorIds, actor, copyGroupId) per MT-5, update, delete, assertEditable),
  PurchaseOrderNumberAllocator, PurchaseOrderAmountCalculator (refreshTotals), PurchaseOrderStatusService
  (change, transitionsFor), PurchaseRequestLineLinker::unlink (link() da aggiungere in MT-5). Rotte
  routes/api/purchasing.php -> purchase-requests.php, cost-types.php, purchase-orders.php (api.php a 500 righe).
  TableDefinition purchase-orders (app/Tables/PurchaseOrders*) e cost-types. Menu purchasing: purchase-orders, cost-types.
  MetaController: fallback `{resource}.view` se manca viewAny (sblocca /meta/purchase-orders e /meta/purchase-requests).
  registries/for-select: meta.is_qualified_supplier. DemoPurchaseOrderSeeder dopo DemoRegistrySeeder.
  Test rollback bumpati: QuoteWorkflowMigrationTest 154 step, SupplierCommissionDirectionTest 18 step;
  FieldCatalogueEndpointTest +cost-types, +purchase-orders.
- FE: features/cost-types (modello units-of-measure), features/purchase-orders (lista con tab stato + pannello righe,
  form create/edit/view, modale fornitore non qualificato, copie). Punto di estensione azioni MT-11:
  PurchaseOrderFormActions in purchase-order-form-actions.tsx. Rotte acquisti in routes/purchasing-routes.tsx
  (router.tsx sotto 500). HELP_GUIDE_KEYS = 64 (guida purchase-orders marcata in sviluppo). i18n purchaseOrders,
  purchaseOrderForm, costTypes.
- Verde (verifier): Pest 9906/9911 (3 QuoteDocumentPdfTest = soffice mancante; 1 flaky QualificaECampusCatalogueTest
  verde da solo), Vitest 7300 (4 test skeleton dei grafici lazy rossi anche da soli, file non toccati; resto timeout da
  carico verdi al rerun), tsc -b --force e Pint puliti. Dopo lo split rotte: Pest acquisti 197/197, Vitest routes 12/12.
- Fix post-checkpoint: `/api/tables/purchase-orders/columns` dava 500 ("isEditable() on null": la TableDefinition passa
  un'istanza non salvata senza status) -> `PurchaseOrdersAuthorization::isLocked` tollera status null; test di
  regressione in PurchaseOrdersTableTest. Verificati su MySQL dev: columns/rows/values (13 colonne)/show/meta 200.
- Aperto: azione griglia `pdf` esposta ma inerte fino a MT-6/MT-11; DemoDataSeeder non verificabile su sqlite
  (locations:add MySQL-only); ambiente locale: php nel PATH senza sqlite/zip -> usare `$TEMP/pest.sh` / `$TEMP/pestp.sh`;
  manuale Claude Docs da aggiornare a fine modulo (sezione Acquisti: ODA, Tipi di costo, Genera ODA).

## Spec 0208 — Modulo RDA (Richieste di Acquisto) — VERDE, COMMITTATO b794c960 (2026-10-09)

- Spec `docs/specs/0208-purchase-requests.xml` (approvata, D-1..D-17, AC-001..AC-022). Nomi inglesi: risorsa/dominio
  `purchase-requests` / `purchase-request-lines`, tabelle `purchase_requests`, `purchase_request_lines`,
  `purchase_request_line_status_logs` (migrazioni `2026_10_12_1000*`). Enum `PurchaseRequestStatus` (open|closed),
  `PurchaseRequestLineStatus` (pending_approval, approved, ordered, received, rejected, on_hold; matrice in
  `allowedTransitions`), `PurchaseRequestPriority`, `PurchaseRequestCapability` (approve|fulfill|manage).
- Iter: approva/rifiuta SOLO il Responsabile di funzione assegnato (`function_manager_id`); evasione con
  `purchase-requests.fulfill`; `purchase-requests.manageStatuses` = qualsiasi stato. Terminali per auto-chiusura:
  received, on_hold, rejected (D-7). Permessi `purchase-requests.{view,viewAll,create,update,delete,deleteLine,fulfill,
  manageStatuses,close,export,viewActivity}`. Visibilita' senza viewAll: richiedente/responsabile/autore.
- BE: `Services/PurchaseRequests/*` (Service, AmountCalculator, CapabilityResolver con `effectiveCapabilities`,
  LineStatusService, ClosureService, Notifier, VisibilityScope), `routes/api/purchase-requests.php`,
  TableDefinition `purchase-requests` + `purchase-request-lines` (`app/Tables/PurchaseRequests/*`; colonne derivate:
  filtri testo/set e `/values` via `PurchaseRequestDerivedQuery::distinctRelated` — senza, il menu filtro va in 500),
  notifica `PurchaseRequestSubmittedNotification`, menu `config/navigation/purchasing.php`, alias attachments
  `purchase_request`/`purchase_request_line`, `BusinessFunctionForSelectResource.manager`, `DemoPurchaseRequestSeeder`.
  Righe: `abilities.{update,delete,transitions,capabilities}` sia nel Resource sia nelle righe SSRM.
- FE `features/purchase-requests`: form create/edit (righe con "Cambia stato della riga", "Scorpora IVA" nelle azioni,
  ODA segnaposto "—"), lista RDA con master/detail (`purchase-request-detail-panel.tsx`), Gestione righe con cambio
  stato massivo, modale stile QNet (`line-status-dialog.tsx` + `line-status-summary-table.tsx` + `line-status-options.tsx`;
  stato attuale preselezionato su riga singola). Il form si rimonta su `updated_at|status|stati righe`.
  Quick-create prodotto + `presets` (is_supplier) su quick-create registry. Guide in-app `purchase-requests`,
  `purchase-request-lines` (HELP_GUIDE_KEYS = 62). Test rollback: conteggi step aggiornati dall'agente backend.
- Hook `typecheck.sh`: `NODE_OPTIONS=--max-old-space-size=8192` (con heap di default tsc va in segfault 139 e il gate
  segnalava falsi errori a output vuoto).
- Menu: sezione `purchasing` PRIMA di `accounting` (test FinancialAccountSchemaTest: accounting subito prima di
  administration). `FieldCatalogueEndpointTest`: aggiunta risorsa `purchase-requests` all'elenco atteso.
- Verde: suite completa Pest 9775/9779 -> i 3 rossi sistemati (menu, catalogo campi) o instabili in parallelo
  (`DemoOpportunitySeederTest` verde da solo); rieseguiti 88/88 + navigazione 277/277. Vitest 7203/7207: i 4 rossi
  (grafici lazy + request-dashboard-tabs) verdi da soli 31/31, instabili sotto carico. `tsc -b --force`, ESLint, Pint puliti.
- Aperto: conteggi sulle tab per stato (non nel contratto; `meta.aggregates` e' calcolato sul set filtrato);
  verifica visiva a 375px; manuale Claude Docs (sezione Acquisti > RDA / Gestione righe, permessi) NON aggiornato;
  HANDOFF.md ~515 KB, va archiviato in `docs/handoff-archive/`.

## Spec 0207 — Note generali sull'anagrafica — VERDE, COMMITTATO (2026-10-08)

- Colonna `registries.general_notes` (text nullable, max 5000), migrazione `2026_10_11_100000_add_general_notes_to_registries_table`.
  Model fillable, Store/UpdateRegistryRequest, Create/UpdateRegistryData (`generalNotes` ultimo parametro, default null:
  `RegistriesSource` invariato), RegistryResource, RegistriesAuthorization (`FieldDefinition('general_notes','textarea')`
  dopo `employee_count`, editabile con update/create).
- FE: `features/registries/registry-general-notes.tsx` (`RegistryGeneralNotesRow` su `NotesCalloutRow`, callout ambra di
  Gestione richieste) in cima alla colonna destra del dettaglio (avvolto in `<Form {...editor.form}>`) e della creazione
  (dopo l'avviso duplicati). Schema/defaults/payload/diff/SERVER_ERROR_FIELDS, i18n `registries.form.generalNotes*`,
  activity log, guida in-app `registries` IT/EN.
- Test rollback bumpati: `QuoteWorkflowMigrationTest` 144 step, `SupplierCommissionDirectionTest` 8 step.
- Verde: Pest 9698/9699 (1 skipped), Vitest 7147/7147, `tsc -b --force` pulito, ESLint e Pint puliti.
- Manuale Claude Docs: NON aggiornato (doc non accessibile dalla sessione) — sezione Anagrafiche > scheda/modifica.

## MERGE main -> feature/amministrazione — VERDE, COMMITTATO e8153ecf + fix test non committato (2026-10-08)

- 16 file in conflitto risolti tenendo entrambi i lati. Integrazioni semantiche:
  - `useWorkOrderRowActions` (ora `.tsx`, main): proforma "€" (spec 0193) + chiusura forzata/riapertura + `openCreateWith`.
    Icone unite in `ROW_ACTION_ICONS` = `WORK_ORDER_ACTION_ICONS` (lock, `use-work-order-closure`) + `PROFORMA_ACTION_ICONS`
    (euro, rinominato da `WORK_ORDER_ACTION_ICONS` in `proforma-row-action.ts`). I consumer usano `iconMap` dell'hook.
  - Tab Commesse dell'anagrafica (spec 0199): `RegistryRelatedGrid` inoltra `resolveActionState`; montato `WorkOrderProformaDialog`.
  - `DetailError` richiede `error` (main, 404/403 controllati): passato in financial-accounts, invoices, proforma-requests.
  - `WorkOrdersTableDefinition::baseQuery` eager-load `quote.opportunity.registry` + EXISTS proforma.
  - Test rollback: `QuoteWorkflowMigrationTest` 143 step, `SupplierCommissionDirectionTest` 7 step (solo conteggio migrazioni).
  - HELP_GUIDE_KEYS = 60; guida work-orders IT/EN con sezioni `contract-data` + `proforma-request`.
- Merge committato `e8153ecf`. Riparata anche una regressione del merge precedente: `01b6877e` aveva perso il layout di stampa
  fatture di `4d71a636` (spec 0196) in `invoices/types.ts`, `invoice-schema.ts`, `invoice-editor-source.ts`,
  `invoice-editor-header-section.tsx`, `{it,en}-invoices.ts`: ripristinato (incluso in `e8153ecf`). `InvoiceLayoutSelectionTest`
  AC-001: l'incasso parziale di setup ora passa `residual_mode` (obbligatorio da spec 0196 D-5), NON COMMITTATO.
- Verde: Pest 9692/9693 (1 skipped), Vitest 7141/7141, `tsc -b --force` EXIT 0.

## RICHIESTE PROFORMA: COLONNA "CLIENTE" — VERDE, NON COMMITTATO (2026-10-08)

- Nuovo campo `customer` ({id,name} | null) = `workOrder.quote.opportunity.registry` (stesso cliente di default
  dell'emissione per le Consulenze). Display-only, non ordinabile/filtrabile. Catalogo colonne (dopo `company`),
  `ProformaRequestsTableDefinition` (eager load + mapRow), `ProformaRequestService::RESOURCE_RELATIONS`, `ProformaRequestResource`.
- FE: `types.ts`, `column-renderers.tsx` (RelationCell), dettaglio, i18n IT 'Cliente' / EN 'Customer', guide in-app IT+EN.
- Test: nuovo caso in `ProformaRequestTablesTest.php`. Manuale Claude Docs NON aggiornato (doc non condiviso):
  sezione Richieste Proforma, aggiungere colonna "Cliente".

## FATTURE: LABEL "INTESTATARIO" -> "CLIENTE" — VERDE, NON COMMITTATO (2026-10-08)

- Solo i18n: `it-invoices`/`it-invoice-editor` `customer` = 'Cliente' (+ `selectCustomer`, `customerRequired`);
  EN 'Bill to' -> 'Customer'. Guida in-app IT `invoices.ts` aggiornata. Test editor ora cercano il combobox `/Customer/`.
- Manuale Claude Docs NON aggiornato (doc non condiviso con la sessione): sezione Fatture, campo "Intestatario" -> "Cliente".

## SPEC 0198 INCASSO MULTIPLO SCADENZE — VERDE, NON COMMITTATO, BRANCH feature/amministrazione (2026-10-08)

- Spec `docs/specs/0198-installments-bulk-collection.xml` (D-1..D-10). Raggruppando per Cliente le scadenze da incassare
  sono selezionabili (stesso cliente), azione "Incassa selezionate" nel menu Azioni, modale con importi vuoti per riga,
  data incasso unica, footer Totale incassato / Totale selezionato. Riga vuota/0 non inviata; importo > scadenza = errore.
- BE: `POST /api/invoice-installments/collections` {collected_at, items[{installment_id, collected_amount}]} ->
  {collected_count, residual_count}. `InvoiceBulkCollectionRequest` (invoices.collect, max 100), `InvoiceBulkCollectionController`,
  `Services/Invoices/InvoiceBulkCollector` (transazione unica, lock fatture in ordine id, Gate collect per fattura,
  422 `items` clienti diversi / `items.N.installment_id` gia' incassata / `items.N.collected_amount` oltre importo; ogni riga
  passa da `InvoiceCollectionService::record` con residual_mode new_installment e residual_due_date = due_date originale).
  Riga SSRM invoice-installments: nuovo campo `customer_id`.
- FE generico: `TableView` prop `onRowGroupColumnsChange(ids)`; `useBulkActionsSlot.clearSelection` ora fa anche
  `gridApi.deselectAll()`. FE modulo: `installment-bulk-collection.ts` (puro), `use-installment-bulk-collection(-form).ts`,
  `installment-bulk-collection-dialog.tsx`, api `bulkCollectInstallments`, i18n `invoiceInstallments.bulkCollection.*`,
  guida in-app sezione `bulk-collection` IT/EN. Test tabella: aggiunto mock `useAbilities` (nuova dipendenza del componente).
- Verificato: Pest Invoices 63, InvoiceInstallments 49, Table 289, Tables 7 (tutti verdi); Vitest 694/694 (help, invoices,
  invoice-installments, table, i18n); ESLint ok; Pint ok; tsc -b --force EXIT=0 (npx va in segfault: usare
  `node node_modules/typescript/bin/tsc -b --force`).
- NON verificato nel browser (estensione Chrome non connessa): provare checkbox con raggruppamento SSRM per Cliente,
  attivazione/disattivazione al cambio raggruppamento, refresh dopo il salvataggio.
- Manuale Claude Docs: aggiungere "Incasso multiplo" in Contabilita' > Attiva > Scadenze.

## FIX MERGE 2ba2bb5a/a1ba1cef — LAYOUT FATTURA (spec 0196 layout) PERSO NEL FE — VERDE, NON COMMITTATO (2026-10-08)

- I merge su feature/amministrazione avevano scartato parte di `4d71a636` (per-invoice print layout): ripristinati
  `types.ts` (Invoice.layout, layout_id nei payload, InvoiceDraft.defaults.layout), `invoice-schema.ts` (layout_id in write
  e details), `invoice-editor-source.ts` (layout/layout_id), `invoice-editor-header-section.tsx` (`InvoiceLayoutField`),
  i18n `invoices.*.layout/layoutDefault` IT/EN. Backend era integro.
- `QuoteWorkflowMigrationTest`: rollback `--step` 134 (mancava `add_layout_id_to_invoices_table`).
- `InvoiceLayoutSelectionTest`: l'incasso parziale ora passa `residual_mode: spread` (requisito 0196 ribilanciamento).
- Permessi `invoice-installments.{view,viewAny,update,export}` creati nel DB locale con `permissions:sync` +
  `roles:create-super-admin` (da rilanciare in ogni ambiente dopo il deploy). Altri ruoli: nessun permesso invoices*.
- Verificato: tsc -b EXIT=0, Vitest invoices/help/i18n 327/327, Pest Invoices+InvoiceInstallments+DocumentLayouts+migrazioni 236/236.
- Nota ambiente: php Herd bloccato da Device Guard; usare `C:/laragon-6/bin/php/php-8.4.19/php.exe`.

## SPEC 0197 MODULO SCADENZE (Contabilita' > Attiva) — VERDE, NON COMMITTATO, BRANCH feature/amministrazione (2026-10-08)

- Spec `docs/specs/0197-invoice-installments-module.xml` (approvata, D-1..D-8). Vista trasversale di `invoice_installments`:
  NESSUNA tabella nuova, niente create/delete di rate (nascono solo dalla fattura). Edit solo due_date/payment_method_code
  (409 "Collected installments cannot be edited." se c'e' incasso; due_date >= invoice.document_date). Incasso/annullo =
  endpoint esistenti `invoice-installments/{id}/collection` (authz invoices.collect).
- Migrazione `2026_10_10_100000_add_residual_amount_to_invoice_installments_table`: colonna VIRTUAL `residual_amount` +
  indice (residual_amount, due_date). status/overdue in SQL = CASE identico a InstallmentStatus (non da residual).
- Framework tabellare: row grouping SSRM OPT-IN. Backend `TableDefinition::supportsRowGroups/groupableColumns/groupAggregates`
  (default nel trait `ResolvesRowGroups`, passthrough in `DelegatesUnaugmentedTableMethods`), `Services/Table/RowGroupQuery`
  + `RowGroupValidator`; payload rows `rowGroupCols` + `groupKeys` (max 3, `__null__` = valore assente, 422 senza opt-in o con
  tree/kanban). Item di gruppo `{group,column,key,label,child_count,aggregates}`. Config columns: `row_grouping` + `groupable`/`aggFunc`
  solo per domini opt-in. FE: `features/table/row-grouping.ts`, `components/data-table/row-grouping-grid-options.ts`,
  `buildSideBar(rowGroupingEnabled)` in data-table-overlays.
- Modulo: domain `invoice-installments` (`InvoiceInstallmentsTableDefinition` + `Tables/InvoiceInstallments/*`), Policy
  `InvoiceInstallmentPolicy` (viewAny, view, update, export), `InvoiceInstallmentsAuthorization` (campi gated amount,
  collected_amount, collected_at, residual_amount), GET/PATCH `/api/invoice-installments/{id}` (`InvoiceInstallmentController`,
  `UpdateInvoiceInstallmentRequest`, `InvoiceInstallmentUpdater`, `InvoiceInstallmentResource`). Sedi via join quotes
  (company_site, operational_site). LogsModelActivity su InvoiceInstallment (solo due_date, payment_method_code, collected_*).
  Nav `invoice-installments` sotto accounting-receivable. FE `pages/invoice-installments-page.tsx`, `features/invoice-installments/`,
  i18n `invoiceInstallments.*`, guida in-app IT/EN `invoice-installments`.
- Verifier: Pest 9477/9481 (3 soffice ambientali, 1 skip), Pint ok, Vitest full 5 flaky sotto carico (verdi isolati: request-dashboard-tabs,
  4 chart skeleton), tsc -b --force ok, ESLint ok. `composer test` non parte con questo Composer: usare `php artisan test --parallel`.
- Da fare: split file vicini al limite (data-table.tsx 487, router.tsx 488, AbstractTableDefinition 466, TableDefinition 465,
  TableRowsRequest 375); test FE di assenza azioni incasso senza collect; select payment_method_code (oggi input testo).
  Manuale Claude Docs NON accessibile: aggiungere sezione "Contabilita' > Attiva > Scadenze".
- Fix post-0197 (NON COMMITTATI, 2026-10-08), tutti nel framework tabellare FE:
  - `useTableLayoutPersistence`: `handleFilterChanged` ignora un gridApi distrutto (crash "Azzera tutto" con filtri forzati);
    lo stato `filterModel` si risincronizza a `initialFilterModel` SOLO al cambio di gridApi (nuova griglia), non a ogni
    refresh config: prima l'eco del salvataggio filtri ri-applicava i `forcedFilterModel` e la toolbar Aperte/Scadute/Tutte
    tornava sempre su Aperte (stesso difetto latente su anno/mese della toolbar Fatture).
  - Row grouping: pannello AG Grid "Trascina qui" disattivato (`rowGroupPanelShow: 'never'`), sostituito da
    `features/table/row-grouping-bar.tsx` ("Raggruppa per" + chip per livello) con `use-row-group-columns.ts`
    (useSyncExternalStore su `columnRowGroupChanged`). Guida in-app grouping IT/EN aggiornata.
- Spec 0197 D-9 (NON COMMITTATO, 2026-10-08): stato `partially_paid` RIMOSSO (InstallmentStatus = unpaid|paid; collected_amount > 0
  = paid; InstallmentSql::partiallyPaid/open eliminati, overdue = unpaid + due_date < oggi). Etichette uniche FE in
  `enums.installment_status` / `enums.installment_overdue` (Si'/No), lette da celle, set filter (backend `enumKeyFor` in
  InvoiceInstallmentsTableDefinition) e dettaglio fattura; backend Label 'Not collected'/'Collected' (it.json Da incassare/Incassata).
  Filtri rapidi: unpaid (default) | due | overdue | paid | all. Colonne: Data fattura, N. rata, Codice modalita', Importo incassato,
  Data incasso (anche tabella rate del dettaglio fattura). Verde: Pest 9478/9482 (3 soffice), Vitest 6838/6838, tsc, ESLint, Pint.

## SPEC 0196 RIMODULAZIONE RATE + INCASSO PARZIALE — BRANCH feature/amministrazione (2026-10-07)

- Spec `docs/specs/0196-invoice-installment-rebalancing.xml` (approvata, D-1..D-15).
- Rimodulazione: PUT /api/invoices/{id} con incassi NON e' piu' 409. document_date/payment_method_id/customer_registry_id
  bloccati (422 per campo); rate incassate intatte, partially_paid chiusa a collected_amount, residuo in parti uguali
  sulle rate aperte (vat_allocation ignorata); totale minore dell'incassato -> 422 `lines`; uguale -> rate aperte eliminate;
  nessuna rata aperta e residuo maggiore di 0 -> nuova rata ultima due_date + 30. Azzera tutti i `redistribution_snapshot`.
- Incasso: `InvoiceCollectionService::record/clear` (record/clear USCITI da InvoiceService). PUT collection su rata gia'
  incassata -> 409. Parziale: `residual_mode` spread|new_installment (enum ResidualMode), `residual_due_date` per
  new_installment; la rata si chiude a collected_amount. Snapshot json `redistribution_snapshot` sulla rata incassata
  ({installments:[{id,sequence,amount}], created_installment_id}). DELETE: ripristino esatto; 409 "Clear the later
  collections first." se una rata spalmata/creata e' incassata dopo; senza snapshot azzera solo l'incasso.
- Servizi: `InstallmentRebalancer` (puro, centesimi, RESIDUAL_INSTALLMENT_DAYS=30), `InvoiceScheduleRebalancer`,
  `InvoiceCollectionService`. Preview: `invoice_id` opzionale, righe con `collected_amount` + `locked`.
- FE: dialog incasso con scelta residuo (`invoice-collection-residual*.ts(x)`), "Registra incasso" (default + HandCoins)
  solo su rate aperte, "Annulla incasso" (outline + Undo2) con AlertDialog di conferma (`invoice-clear-collection-dialog.tsx`);
  editor con campi bloccati e preview con invoice_id. Guide in-app invoices IT/EN aggiornate.
- `DemoInvoiceSeeder` ora registra gli incassi via InvoiceCollectionService (parziale = spread).
- Manuale Claude Docs (KwvSrXafsGqT9qzZULxJhh) NON accessibile da questa sessione: da aggiornare a mano le sezioni
  Fatture > Modifica documento (rimodulazione con incassi) e Fatture > Incassi (incasso parziale, conferma annullamento).
- Ambiente: il PHP Laragon in bash non ha pdo_sqlite/zip attive; i test girano con PHPRC su un php.ini temporaneo.
  QuoteDocumentPdfTest fallisce in locale per assenza di LibreOffice (ambientale).

## FIX LINK MAIL TESTUALI — VERDE, NON COMMITTATO (2026-10-07)

- `emails/reset-password-plain` e `emails/welcome-user-plain` stampavano `{{ $url }}` → `&amp;email=` nel corpo testo:
  il parametro `email` si perdeva e la pagina reset/set-password mostrava subito "link non valido". Ora `{!! $url !!}`
  (testo puro, URL costruito server-side). Test: `tests/Feature/Mail/PlainTextEmailLinkTest.php`.
- Dati demo fatture: `DemoPaymentMethodSeeder` + 9 forme rateali (codici `riba_30_60`, `riba_30_60_90`, `riba_30_60_90_eom`,
  `bank_transfer_30_120_eom_10`, `bank_transfer_60_90_120`, `bank_transfer_30_60_90_vat_first|vat_last`,
  `vat_upfront_30_60_90`, `direct_debit_12_monthly`). Nuovo `DemoInvoiceSeeder` (dopo DemoWorkOrderSeeder in DemoDataSeeder):
  1 proforma request + documento per commessa via `InvoiceService`, date relative a oggi, incassi misti; `DemoDataSeeder`
  ora cancella le Invoice prima delle WorkOrder (restrict su registry/company). Seeder eseguiti sul DB locale; il re-run
  completo di DemoDataSeeder NON e' stato provato.
- Locale: `backend/.env` MAIL_MAILER=log (nessun SMTP catcher su 1025); i link reset finiscono in `storage/logs/laravel.log`.

## SPEC 0195 PDF + EMAIL/SOLLECITO FATTURE — VERDE, NON COMMITTATO, BRANCH feature/amministrazione (2026-10-06)

- Spec `docs/specs/0195-invoice-pdf-and-email.xml` (approvata; D-13 rev: rate scadute del sollecito come elenco `<ul>`,
  EmailHtmlSanitizer non ammette tabelle).
- Rendering layout reso generico: interfaccia `DocumentRenderSubject` (module/prepare/resolveVariable/productRows),
  `DocumentGenerator::generate(subject, layout, actor)` (QuoteDocumentGenerator ELIMINATO), `QuoteRenderSubject::for()`,
  `InvoiceRenderSubject` + `InvoiceFieldResolver` + `InvoiceSampleFactory` (Rendering/Invoices). Sorgenti products_table
  per modulo in `DocumentLayoutProductSources::for()` (invoices: invoice_lines, installments). DocumentLayoutModule::Invoices.
- PDF: GET /api/invoices/{id}/pdf?layout_id= (layout default attivo del modulo invoices, altrimenti 422
  'No document layout available for invoices.'); `InvoicePdfRenderer::render(Invoice, User, ?layout)`. Preview layout accetta invoice_id.
- Email generalizzate per owner: interfaccia `EmailOwner` + `EmailOwnerRegistry` (config outbound_emails.owners:
  work_order, invoice), controller base `Http/Controllers/OwnerEmails/*`, `OutboundEmailComposeContextBuilder` generico,
  `outbound_emails.purpose` document|reminder. Rotte commesse INVARIATE; fatture: invoices/{id}/emails/* (routes/api/invoice-emails.php)
  + POST .../emails/reminder (409 senza rate scadute). EmailTemplateModule::Invoices con variabili reminder.*.
  SSRM invoices: colonna last_reminder_at, azioni pdf/email/remind. Permessi invoices.viewEmails/sendEmail
  (permissions.actions view_emails/send_email).
- FE: features/work-order-emails -> features/outbound-emails (owner `{type:'work-orders'|'invoices', id}`, namespace
  i18n outboundEmails); document-layouts modulo Fatture + layout di partenza (invoice-layout-starter.ts); invoices: azioni
  PDF/Email/Sollecita + tab Email nel dettaglio; `normalizeBlobError` condiviso in lib/download.ts. Guide IT/EN aggiornate
  (invoices, document-layouts, email-templates).
- Test esistenti toccati (solo nomi classe/import/fixture/sentinel): Quote document tests (DocumentGenerator), DocumentLayoutCrudTest
  e VariableEndpointTest (sentinel invoices -> contracts), QuoteWorkflowMigrationTest (131), InvoicesTableTest (+colonna/azione),
  FE outbound-emails (rename), document-layout-schema.test.ts (sentinel).
- Verifier: Pest 9400/9404 (3 soffice, 1 skipped), Vitest 6785 (flaky sotto carico, verdi da soli), tsc -b, Pint puliti,
  ESLint solo i 2 errori preesistenti. AC-001..007 PASS.
- Dopo il deploy: `php artisan permissions:sync` (fatto in locale) e creare un Layout Documento modulo Fatture come
  predefinito (senza layout il PDF da' 422). Logo: immagine del layout (nessuna variabile logo sede per le fatture).
- Fix post-verifica: DocxToPdfConverter costruiva `file://C:\...` (soffice exit 1 su Windows; ora `fileUri()` ->
  `file:///C:/...`) e l'output cp1252 di soffice rompeva la risposta JSON (`mb_scrub`). Riguarda tutti i PDF (offerte,
  anteprima layout, allegato PDF offerta nelle email, fatture). In locale serve `LIBREOFFICE_BINARY` nel .env (percorso
  completo di soffice.exe). Layout di partenza Fatture: totali come token `{totals.x}`, descrizione sola, niente riga
  incassato/residuo, scadenze solo N./Scadenza/Importo. lang/it.json: etichette enum fatture. Layout "Fattura standard"
  (invoice-standard) creato a mano nel DB locale. Aperto: scelta del layout per singola fattura (A al download / B salvato).
- Aperto: manuale Claude Docs non aggiornato (access denied) -> Fatture Attive (PDF/Email/Sollecita/Ultimo sollecito),
  Layout (modulo Fatture), Modelli email (modulo Fatture). HANDOFF.md ~400 KB: archiviare in docs/handoff-archive/.

## SPEC 0194 FATTURAZIONE ATTIVA (invoices) — VERDE, NON COMMITTATO, BRANCH feature/amministrazione (2026-10-06)

- Spec `docs/specs/0194-active-invoicing.xml` (approvata). Tabelle `invoices` (type proforma|invoice, company_id emittente,
  number+year unici per societa', customer_registry_id, proforma_request_id unique, importi dec(15,2), external_number/date,
  tag estimate|final, deviation), `invoice_lines` (IVA per riga, quote_line_id sorgente), `invoice_installments`
  (collected_amount/at), `invoice_number_sequences` (lockForUpdate). payment_methods +installments_count,
  days_between_installments, end_of_month, end_of_month_extra_days, vat_allocation split|first|last|vat_first
  (payment_days = giorni alla prima scadenza).
- Regole: un documento per richiesta (crea -> richiesta issued; elimina -> torna pending; numeri mai riusati); importi
  SEMPRE ricalcolati dal server; PUT/DELETE -> 409 con incassi; PATCH details sempre (external_number -> type invoice);
  stato pagamento calcolato (`App\Services\Invoices\InvoicePaymentStatusResolver`, soglia
  `InvoicePaymentStatus::SERIOUSLY_OVERDUE_DAYS`=21); scadenze `InstallmentScheduleCalculator` (centesimi interi, FM in mesi).
- Endpoint: GET/POST /api/proforma-requests/{id}/invoice-draft|invoice; POST /api/invoices/installment-preview;
  GET /api/invoices/monthly-summary; GET/PUT/DELETE /api/invoices/{id}; PATCH /api/invoices/{id}/details;
  PUT/DELETE /api/invoice-installments/{id}/collection; SSRM `invoices` (colonne nascoste document_year/document_month
  come set filter, meta.aggregates). Rotte contabilita' raggruppate in routes/api/accounting.php (api.php a 499 righe).
  Riga proforma-requests: `invoice_id` + azione `invoice` (file-plus-2, invoices.create). Menu Contabilita' > Attiva > Fatture Attive.
- FE: features/invoices (modale `InvoiceEditorDialog` create|edit, elenco con tab/striscia mesi/footer/master-detail,
  dialog dettagli/incasso/elimina). Infra tabella: nuove prop additive `forcedFilterModel`/`onFilterModelChange` e
  `TableViewHandle.setFilterModel`. i18n contabilita' in it/en-accounting.ts (en.ts era a 503 -> 499: risolto).
  Guide in-app IT/EN: `invoices` nuova, `proforma-requests` e `payment-methods` aggiornate.
- Test toccati per requisito cambiato: QuoteWorkflowMigrationTest (rollback 125 -> 130), FieldCatalogueEndpointTest
  (+invoices), PaymentMethodMetaTest/TableTest (ordine campi), help-guide-keys.test.ts (57 -> 58), test FE payment-methods (fixture).
- Verifier: Pest parallelo 9373/9377 (3 = soffice, 1 skipped), Vitest 6777 (flaky sotto carico, verdi da soli), tsc -b, Pint
  puliti; ESLint 2 errori preesistenti fuori scope (quotes/column-renderers.tsx, registries/registry-form-metadata.test.tsx).
  AC-001..010 PASS (copertura FE debole su incasso da riga espansa).
- Dopo il deploy: `php artisan permissions:sync` (fatto in locale) + assegnare invoices.* ai ruoli non super-admin.
- Aperto: manuale Claude Docs NON aggiornato (connettore: access denied) -> sezioni Contabilita' > Attiva > Fatture Attive,
  Richieste Proforma (azione Emetti proforma), Modalita' di pagamento (rate). Prossima spec 0195: PDF documento + invio
  email/sollecito. `prompt_temp.md` in root non va committato.

## SPEC 0193 RICHIESTE PROFORMA (proforma-requests) — VERDE, COMMITTATO, BRANCH feature/amministrazione (2026-10-06)

- Spec `docs/specs/0193-proforma-requests.xml` (approvata). Tabella `proforma_requests` (work_order_id cascade, kind
  consultancy|institution, supplier_id -> registries, payment_method_id snapshot dall'offerta, status pending|issued,
  issued_at, note, assigned_to/assigned_by = utente che invia). Tipologie risolte per `product_typologies.code`.
- Regole (decisioni utente): una richiesta per le Consulenze + una per fornitore distinto degli Enti (Ente senza
  fornitore = richiesta con supplier NULL; righe senza tipologia ignorate; nessuna riga utile -> 422 'No billable
  lines on this work order.'); con una pending -> 409 'This work order already has a pending proforma request.'.
  Nessuna azione manuale di evasione: `issued` lo impostera' la fatturazione (prossimo prompt; rigenerazione fattura).
- Endpoint: GET/POST /api/work-orders/{wo}/proforma-requests[/summary]; GET/PATCH(note)/DELETE
  /api/proforma-requests/{id}; SSRM `proforma-requests`. Pulsante € = azione di riga `proforma` nella colonna Azioni
  (Commesse e tab Commesse del contratto), colore/disabilitato dal campo riga `proforma_status` none|pending|issued
  (withExists, solo con proforma-requests.create) via la nuova opzione generica FE `resolveActionState`
  (features/table/row-actions.tsx). Con 4 azioni la 4a (Attivita') finisce nel menu "...".
  Dopo il deploy: `php artisan permissions:sync` (in locale i permessi proforma-requests.* mancavano). Notable `proforma-requests`. Menu Contabilita' > Attiva
  (`accounting-receivable`) > Richieste Proforma.
- Fuori scope toccati: `PermissionCatalogueBuilder` (figli di un sottogruppo senza permesso ora entrano nel form
  Ruoli); FE `features/table/*` prop additiva `defaultFilterModel` (filtro iniziale status=pending; un filtro svuotato
  e salvato torna al default). `QuoteWorkflowMigrationTest` riallineato (rollback step 125 + 2; era rosso dopo la merge).
- Verifier: Pest parallelo 9342/9346 (3 = soffice assente, 1 skipped), Vitest 6757 (3 flaky sotto carico, verdi da
  soli), tsc -b, ESLint, Pint puliti. AC-001..008 PASS.
- Ambiente: per la suite parallela serve PHPRC con memory_limit=2G e pdo_sqlite/sqlite3/zip abilitati
  (php-8.4.19 li ha commentati); `-d memory_limit` non arriva ai worker paratest.
- Aperto: `frontend/src/i18n/locales/en.ts` a 503 righe (501 gia' a HEAD dopo la merge): va diviso. Manuale Claude
  Docs non aggiornato (connettore: access denied) -> sezioni Commesse (pulsante €) e Contabilita' > Attiva >
  Richieste Proforma. Guide in-app IT/EN fatte.

## SPEC 0189 GESTIONE CONTI (financial-accounts) — VERDE, COMMITTATO, BRANCH feature/amministrazione (2026-10-05)

- Spec `docs/specs/0189-financial-accounts.xml` (approvata). Tabella unica `financial_accounts` con `type`
  bank_account|card|cash (immutabile dopo create); `name` = "Banca" (conto/carta) o "Nome" (cassa); `company_id` ->
  `companies` (societa' madre, NON company_sites); geo piatte + `address_line`/`postal_code`; carta: `card_type`
  credit|prepaid, `card_circuit` visa|mastercard|amex, `linked_account_id` (obbligatorio solo credit, solo verso
  bank_account, restrictOnDelete), `card_holder`, `card_number` (cast `encrypted` + hidden), `card_last_four`, `card_expiry` MM/YYYY.
- Decisioni utente: CVV e password/PIN carta MAI salvati (PCI DSS; chiavi cvv/card_ccv/card_password -> 422);
  numero in chiaro solo via `GET /api/financial-accounts/{id}/card-number` con permesso `financial-accounts.revealCardNumber`
  (scrive activity log `card_number_revealed`); IBAN con controllo mod-97, normalizzato, unico; nessuna migrazione dei
  conti legacy (reinserimento manuale); nuova sezione menu `accounting` "Contabilita'" (config/navigation/accounting.php) prima di administration.
- Delete di un conto con carte collegate -> 409 'This account has linked cards and cannot be deleted.'.
- Permessi `financial-accounts.*` senza `import`. Morph map: `financial_account` in AppServiceProvider (senza -> 500 dal log).
- Test toccati per requisito cambiato: FieldCatalogueEndpointTest (+ financial-accounts), QuoteWorkflowMigrationTest
  (rollback step 123 -> 124), frontend help-guide-keys.test.ts (55 -> 56). routes/api.php era a 501 righe: commento Referents accorciato.
- Verifier: Pest parallelo 9226/9230 (3 fail = `soffice` LibreOffice assente in QuoteDocumentPdfTest, ambiente), Vitest
  6663/6665 (2 flaky sotto carico, verdi da soli), tsc -b, ESLint, Pint puliti. AC-001..025 PASS.
- Ambiente locale: il php.ini di Laragon non ha pdo_sqlite/sqlite3/zip e il runner parallelo vuole memory_limit ~2G.
- Aperto: manuale Claude Docs (sezione Contabilita' > Gestione Conti) NON aggiornato: il connettore non ha accesso al documento.
  Guide in-app IT/EN `financial-accounts` fatte. Nell'elenco la modifica passa dal dettaglio (azioni riga: view/delete/activity), come UoM.
## STATI CONSULENZA: NOTA OBBLIGATORIA SUGLI ESITI NEGATIVI — VERDE, NON COMMITTATO (2026-10-08)

- `WorkflowStatusCatalogue::SECTIONS` accetta ora una chiave opzionale `requires_note` per stato (default false,
  letta in `statusesFor()`). CONSULENZA: `Persa` (riga di sistema closed_lost), `Annullata`, `Non pertinente` =
  nota obbligatoria; `Irreperibile` e `Numero inesistente` no (decisione utente 2026-10-08). Altre liste invariate.
- Test: `tests/Feature/Seeding/QualificaConsultingStatusNotesTest.php`.
- Attenzione: `QualificaWorkflowSeeder` salta i workflow gia' esistenti, quindi su un DB gia' seminato il flag va
  impostato a mano dal configuratore stati (o con un seed su DB pulito).

## GRIGLIA COMMESSE: COLONNA ANAGRAFICA — VERDE, COMMITTATO (2026-10-08)

- Nuova colonna `registry` ("Anagrafica"/"Registry") nella griglia `work-orders`, dopo `quote`: `{id, name}` derivato
  da `quote.opportunity.registry` (stessa catena di `WorkOrderResource::summarizeRegistry` e della griglia Contratti).
  Sola lettura (il form non la edita), `set` filter sul nome, sortable, distinct values scoped. Nessuna voce "(Vuoti)":
  `work_orders.quote_id`/`quotes.opportunity_id`/`opportunities.registry_id` sono tutti NOT NULL.
- Backend: nuova `Tables/WorkOrders/WorkOrderRegistryColumn` (filtro/sort/distinct) delegata da
  `WorkOrderDerivedColumns`; eager load `quote.opportunity.registry` in `WorkOrdersTableDefinition::baseQuery()`.
  `WorkOrderDerivedColumns` (320) e `WorkOrdersTableDefinition` (318) oltre il soft limit 300.
- Frontend: renderer `RelationCell` + icona `Building2`; i18n `workOrders.columns.registry`; guida in-app IT/EN
  sezione `list-editing`. Spec 0093 aggiornata (delta 2026-10-08).
- Manuale Claude Docs: NON aggiornato (documento non condiviso con la sessione) — sezione Commesse > elenco da allineare.

## GRIGLIE OFFERTE/COMMESSE/ANAGRAFICHE/OPPORTUNITA': OGNI COLONNA EDITABILE IN CELLA CON LE REGOLE DEL FORM — VERDE, COMMITTATO (2026-10-07)

- Spec `docs/specs/0206-main-modules-grid-inline-editing.xml` (decisioni utente: cambio Anagrafica opportunita' in
  cella = azzera Referente + riempie solo i ruoli VUOTI; "Chiusura forzata" commessa resta azione di riga).
- Regola: cella editabile sse il form la edita come campo singolo. Scrittura = STESSA FormRequest di update + STESSO
  service: nuovo `Services/Table/FormRequestCellValidator` (costruisce la FormRequest con payload JSON della sola
  cella, attore, riga come parametro di rotta, `validateResolved()`), writer per dominio `WorkOrderCellWriter`,
  `RegistryCellWriter`, `QuoteCellWriter`, `OpportunityCellWriter` chiamati da `updateCell()` (Offerte via trait
  `Tables/Quotes/Concerns/WritesQuoteCells`: `QuotesTableDefinition` e' a 499 righe, va splittata alla prossima modifica).
- Gestori (`managers` -> `manager_slots`): cella multiselect di persone; `ManagerPositions::slotsFor/slotsFromIds/
  positionsOf` conservano le posizioni di chi resta, i nuovi nel primo slot libero.
- `RelationValueScopeChecker` + `referents`, `companies`, `company-sites`. `CellValueValidator::typeRules` usa
  l'`editor` scalare se dichiarato (registries `is_supplier` editor boolean, `agreement_status`/`size_class` editor
  enum con `enumKey`), cosi' `type`/filtri restano invariati.
- Offerte: stato = editor `select` notable (catalogo condiviso `Tables/Shared/QuoteWorkflowStatusOptions`, usato
  anche da Gestione Richieste) + `quote_workflow_status_options` per riga (eager load offerLines.product.category,
  opportunity.productLines/customFieldValueRow); cambio Societa' azzera la Sede societa'; sede scoped su `company`.
- Opportunita': `name` ora editabile (RETTIFICA 0171, passa da OpportunityNameWriter); `product_category` = coppie
  (`Tables/Shared/ProductLinePairsColumn`, estratto da RequestRowMapper) con editor `product_lines`; FE renderer
  condiviso `features/product-lines/product-categories-cell.tsx`. Tolte le `rules` di catalogo duplicate del form.
- Export: una lista di riepiloghi in una colonna non-tags esce come nomi separati da `; ` (prima JSON).
- Test: 4 nuovi `*GridInlineEditTest` (27 test); RETTIFICA requisito in `OpportunityTableTest` (AC-105) e
  `opportunities/column-renderers.test.tsx` (product_category a coppie). Backend completo 9457/9459 (1 skipped;
  1 fallimento intermittente preesistente `DemoOpportunitySeederTest` multi-line, verde 3/3 isolato), Vitest
  7012/7012, `tsc -b --force` 0, ESLint pulito, Pint ok. Guide in-app IT/EN (sezione `list-editing`) dei 4 moduli.
- Manuale Claude Docs NON aggiornato (doc non accessibile): sezioni Opportunita', Offerte, Commesse, Anagrafiche ->
  "Modifica rapida dall'elenco".
- Da verificare a mano nel browser (non fatto): editor nelle quattro griglie. Nota preesistente: le date in riga
  arrivano ISO (Carbon), l'editor `date` le mostra vuote all'apertura (stesso comportamento gia' nei Task).

## APL TIROCINIO: MODULO OFFERTA RIVISTO (SOGGETTO OSPITANTE, VIA REGISTRI/DECRETO/NUMERO PRATICA) — VERDE, NON COMMITTATO (2026-10-07)

- Direttiva utente 2026-10-07 su "Tirocinio" (`AplInternshipAttributeCatalogue`): Tutor scartato; il tirocinante e'
  l'anagrafica cliente collegata all'offerta (nessun campo); nuovo `host_registry` "Soggetto ospitante" (relation ->
  `registries`, come `user_registry` dell'orientamento); tolti `registers_status`, `decree_status`, `practice_number`.
- Modulo: "Testata" (`reporting_id`, `decree_id`) / "Dati tirocinio" (`internship_type`, `vacancy_code`,
  `inail_position_number`, `liability_policy_number`, `insurance_company` — tutti `text` — + date) / "Soggetto
  ospitante" (`host_registry`).
- Installazioni gia' seminate: `AplPracticeCatalogue::RETIRED_ATTRIBUTES` -> `QualificaQuoteLayoutSeeder` Step 1 li
  stacca SOLO da Tirocinio (`RetiresAttributes::retireCategoryAttributeCodes`, nuovo; `decree_status` resta su
  apprendistato/orientamento) e li toglie dal suo layout; il vecchio modulo (anche grigio) e' riconosciuto via
  `PREVIOUS_FORMS` e ricomposto. Righe attributo e valori salvati restano.
- Test: Tirocinio aggiornati + nuovo upgrade test (white/grey); apprendistato aggiornato; il test "grigio -> bianco"
  dell'orientamento ora usa l'apprendistato. Pest Products+Seeding+ProductCategories+Migration 653/653, Pint ok.
- Aperto: "Tipologia tirocinio" e' testo libero (nessuna lista valori fornita); il tipo di un attributo gia' seminato
  non viene riallineato dal seeder (`firstOrCreate` su `code`), quindi convertirlo a enum dopo il deploy richiede un
  passo esplicito. Manuale Claude Docs non accessibile.

## ANAGRAFICHE: TAB "COMMISSIONI CONFIGURATE" SU OGNI ANAGRAFICA — VERDE, NON COMMITTATO (2026-10-07)

- Richiesta utente: la tab deve comparire a prescindere da `is_supplier` ("tutte le anagrafiche sono potenzialmente
  con commissioni"). Spec 0204 D-2/AC-002 emendate.
- `registry-related-records.tsx`: rimossi `supplierOnly` e la prop `isSupplier` (gate solo su
  `commission-configurations.viewAny`); `registry-detail.tsx` non passa piu' `is_supplier`. Back-end invariato (lo scope
  `registryId` e il destinatario `registry` del ruolo SUPPLIER non richiedono il flag fornitore).
- Test `registry-related-records.test.tsx` aggiornati al nuovo requisito. Guide in-app IT/EN `registries` e
  `commission-configurations` aggiornate.
- Verificato: Vitest registries/help/commission-configurations 263/263, `tsc -b --force` 0, ESLint pulito.

## RIGHE PRODOTTO DI CONTRATTI, COMMESSE, GESTIONE RICHIESTE (STESSE REGOLE OFFERTE) — VERDE, COMMITTATO (2026-10-07)

- Regola comune: min-width del wrapper = tracce + 8px*gap + 16px (`px-2`), icone `icon-sm` con `mt-0.5` sulle righe
  `items-start` con input h-9, dialog con lista lunga = flex-col + `max-h-[85vh]` + corpo `min-h-0 flex-1 overflow-y-auto`.
- Commesse: `work-order-costs/work-order-cost-row.tsx` `COST_ROW_MIN_WIDTH_CLASS` 1180 -> 1284, cestino `mt-0.5`.
- Contratti: `contract-program-lines-table.tsx` min-w 560 -> 598; `contract-program-dialog.tsx` ora con altezza massima,
  header/footer fissi e corpo scorrevole (prima, con molte righe, usciva dallo schermo). Righe del dettaglio contratto
  gia' corrette via `QuoteLinesReadOnlyList` condiviso.
- Gestione Richieste: `offer-lines-dialog.tsx` stesso fix di scroll del popup commissioni; righe del pannello gia'
  corrette via `QuoteLinesField` condiviso.
- Verificato: tabella "Dati contrattuali" della commessa e' una `<table>` reale, gia' corretta (nessuna modifica).
- Screenshot Playwright di contratto 20, commessa 32 (Costi effettivi), richiesta 44, scrollati a destra: ok.
  Vitest completo 7012/7012, `tsc -b --force` 0, ESLint pulito. Manuale: nessun impatto (solo layout).

## OFFERTE: RIGHE PRODOTTO, DATI AVANZATI, POPUP COMMISSIONI — VERDE, COMMITTATO (2026-10-07)

- Header righe spezzato allo scroll: i `min-w-[...]` erano piu' stretti della somma colonne+gap+`px-2` -> la griglia
  sforava il wrapper e la tinta dell'header si fermava. Ricalcolati in `quote-line-grid.ts` (1180/1136/1304/776/732) e
  `quote-lines-read-only.tsx` (1138/1306). Regola: min-w = tracce + 8px*gap + 16px. Se cambi una colonna, ricalcola.
- Icona commissioni disallineata: riga read-only `items-center`; riga in modifica bottoni `icon-sm` con `mt-0.5`
  (centrati sugli input h-9).
- "Margine per prodotto" spostato DENTRO `QuoteSummary` (nuove prop `productMargins`, `showProductMargins`), chiuso di
  default dietro il toggle "Mostra dati avanzati" (aria-expanded). Card ora `bg-card` (sta su `bg-surface`).
  `QuoteLiveSummary` e `PersistedLinesSummary` passano i dati; niente piu' `QuoteProductMargins` standalone.
- Popup commissioni: `DialogContent` flex-col (era grid: la riga `auto` cresceva oltre `max-h` e il contenuto usciva);
  corpo `min-h-0 flex-1 overflow-y-auto`. STESSO DIFETTO NON CORRETTO in `request-management/offer-lines-dialog.tsx`.
- Popup commissioni (solo modificabile): per ogni ruolo "Calcolo di sistema" da `POST /quotes/commission-defaults`
  (nuovo `quoteCommissionDefaultsQueryKey`), componente `quote-commission-system-default.tsx`; "Applica calcolo di
  sistema" sostituisce l'override mantenendo `id` e nota. Read-only: non mostrato (l'endpoint esige update+commissions
  editable).
- Popup commissioni RIFATTO (richiesta utente "come i migliori CRM"): logica in `use-quote-commissions-draft.ts`
  (bozza, query destinatari/default, patch/add/applySystemDefault/remove, canSave, totali); riga ruolo in
  `quote-commission-role-row.tsx` (icona ruolo, destinatario, badge origine, SegmentedControl %/EUR, valore con suffisso,
  importo, cestino; nota apribile con "Aggiungi nota"; in sola lettura valori in chiaro, non controlli disabilitati);
  `quote-commissions-dialog.tsx` = guscio size "lg" con striscia KPI (imponibile riga, costi imputati, base, totale).
  Nomi accessibili invariati (Recipient/Value/Calculated amount/Remove commission/...). Verificato con screenshot
  Playwright 1440 e 390 px (token Sanctum temporaneo creato e poi eliminato).
- Badge origine: `quote-commission-origin-badge.tsx` -> "Regola: Prodotto"/"Regola: Categoria" (prefisso solo per questi due),
  "Regola personale" e "Modifica manuale" invariati (AC-017); tooltip `originHints.*`, focusabile da tastiera. Test dedicato.
- i18n popup: `quotes.form.commissions.{base.*,columns.*,typeShort.*,addNote,originRule,originHints.*}` (IT/EN).
- i18n: `quotes.form.summary.{showAdvanced,hideAdvanced}`, `quotes.form.commissions.systemDefault.*` (IT/EN).
- Test: RETTIFICA requisito in `quote-summary.test.tsx`/`quote-detail.test.tsx` (blocco chiuso di default);
  3 nuovi test in `quote-commissions-dialog.test.tsx`. Vitest completo 7010/7010, `tsc -b --force` 0, ESLint pulito
  sui file toccati (errore preesistente in `quotes/column-renderers.tsx`).
- Guide in-app IT/EN (quotes) aggiornate. Manuale Claude Docs NON aggiornato (doc non accessibile): sezione Offerte
  -> riepilogo/Margine per prodotto e Commissioni di riga.

## NOTE GENERALI/INTERNE NELLO STILE DI GESTIONE RICHIESTE (Opportunita', Commesse, Offerte) — VERDE, NON COMMITTATO (2026-10-07)

- Richiesta utente: note generali/interne di Opportunita', Commesse e Offerte con lo stesso stile di Gestione Richieste.
- Nuovo `components/record-form/notes-callout-row.tsx` (`NotesCalloutRow`): callout ambra sempre presente se il campo
  e' modificabile (vuoto -> placeholder), testo dentro il callout, editor inline (matita/clic) aperto DENTRO il callout
  con textarea senza bordo. Read-only + vuoto -> niente. Sostituisce le tre righe duplicate
  (`OpportunityGeneralNotesRow`, `WorkOrderInternalNotesRow`, `QuoteInternalNotesRow`, ora wrapper sottili).
- `layout.ts`: nuove costanti `GENERAL_NOTES_TEXT_CLASS`, `GENERAL_NOTES_TEXTAREA_CLASS` (riusate anche dai due file
  note di Gestione Richieste e da `GeneralNotesCallout`). `WorkOrderTextAreaField` ha prop `className` opzionale.
- Offerta: "Note generali dell'opportunita'" (ereditate, read-only) tolte dalla sezione Contesto -> `GeneralNotesCallout`
  a piena larghezza sotto le Note interne.
- i18n nuove: `quotes.form.internalNotesPlaceholder`, `workOrders.detail.internalNotesPlaceholder` (IT/EN).
- Test: nuovo `notes-callout-row.test.tsx`; RETTIFICA requisito in `opportunity-detail-sections.test.tsx` (vuoto +
  modificabile ora mostra il callout); `quote-create-payment.test.tsx` query per ruolo textbox (il nome ora e' anche
  della regione). Vitest completo 7007/7007, `tsc -b --force` 0, ESLint pulito.
- Guide in-app IT/EN: tip nelle sezioni "Modificare ..." di opportunities/work-orders/quotes.
- Manuale Claude Docs: NON aggiornato (doc non accessibile dalla sessione) -> sezioni Opportunita'/Commesse/Offerte
  "Modificare ...".

## ERRORE CONTROLLATO 404/403 SU TUTTI I DETTAGLI/EDIT RECORD — VERDE, COMMITTATO 4cf92317 (2026-10-07)

- Richiesta utente (es. `/work-orders/10` inesistente mostrava "errore + Riprova"). `DetailError` ora richiede
  `error: unknown`: 404/403 -> `RecordUnavailable` (nessun Riprova, testi `common.recordUnavailable.*`), altro ->
  messaggio (`role="alert"`) + Riprova. Il typecheck obbliga ogni chiamante a passare `error`.
- Esteso a tutte le schermate dettaglio + edit loader dei moduli (blocchi errore inline duplicati sostituiti da
  `DetailError`), pagine prodotto/referente/anagrafica/import lead (`useLeadImportDetail` espone `error`),
  `RequestWorkPanelScreen` (chiavi `requestManagement.workPanel.unavailable.*` rimosse, ora in `common`).
- Task: il 403 con contatti (`TaskAccessDenied`, spec 0155 D-7) resta prioritario; il 404 passa da `DetailError`.
- Test nuovo `components/detail/detail-error.test.tsx`; FAQ guida `general` IT/EN.
- Verifica: `tsc -b --force` 0; ESLint pulito sui file toccati; Vitest completo 6979/6987, gli 8 falliti sono in
  `product-typology-schema/form-payload` (campo `color` in lavorazione da altra sessione, file non toccati).
- Aperto (non implementato, fuori scope): backend logga + alert Teams sui 403 da policy (`handleControllerException`);
  per le Commesse il 403 da scoping membership e' un caso quotidiano -> valutare 403 con contatti (Responsabili) come Task.
- Manuale Claude Docs: da aggiornare FAQ/Domande frequenti (record non trovato / accesso negato).

## COMPLETA TASK — SEGNATEMPO PER TUTTI GLI ASSEGNATARI A SCELTA (spec 0205) — VERDE, COMMITTATO (2026-10-07)

- RETTIFICA spec 0155 D-6 (allineamento a q-net). BE: `for_all_assignees` omesso -> true (`CompleteTaskData`
  default true); false esplicito -> solo l'attore. Bulk invariato (sempre true).
- FE: `TaskCompleteDialog` ha il campo form `for_all_assignees` (casella, default spuntata, visibile solo se si
  registra un segnatempo) e lo invia sempre. Prop `forAllAssignees` rimossa da dialog e chiamanti (dettaglio, lista,
  cella stato, kanban, pannello sottotask). i18n `tasks.actions.completeDialog.forAllAssignees`; guida `tasks` IT/EN.
- Test cambiati per requisito cambiato: `TaskCompleteForAllAssigneesTest` (default true, false esplicito),
  `task-complete-dialog.test.tsx` (casella), `task-subtasks-section.test.tsx` (mock senza prop).
- `QualificaSampleTaskSeeder::complete()` passa `forAllAssignees: false` (un segnatempo per task, come prima).
- Verifica: Pest suite completa 9429/9430 (1 skip); Pint ok; Vitest tasks+help 604/604; `tsc -b --force` 0;
  ESLint pulito sui file toccati.
- Manuale Claude Docs: NON aggiornato (doc non condiviso con la sessione) — sezione Task > Completamento/Segnatempo.
- Al commit ESCLUDERE i file di altre sessioni (contract-data FE, seeder showcase commissioni).

## COMMISSIONE FORNITORE PER TIPOLOGIA (spec 0202) + REGOLE PROVVIGIONI LEGACY (spec 0203) — VERDE, COMMITTATO 48d2bff0 + legacy qnet 06f523fa (2026-10-07)

- Spec 0202 (decisioni utente): impostazione sulla TIPOLOGIA prodotto, mai piu' sul codice `institution`:
  `product_typologies.supplier_commission_enabled` + `supplier_commission_direction` (enum
  `App\Enums\SupplierCommissionDirection` RECEIVED|PAID). Snapshot congelato `quote_lines.supplier_commission_direction`
  scritto SOLO da `QuoteLineWriter` alla creazione della riga REVENUE o al cambio prodotto (duplicator copia).
  Calcolo NO = nessuna commissione Fornitore. Margine (server `QuoteTotalsCalculator`, FE `commission-calculator.ts`):
  RECEIVED s−c−p, PAID n−c−p−s, null n−c−p; ricavi/IVA/documenti invariati. `POST /api/quotes/commission-defaults`
  risponde `{commissions, supplier_commission_direction}`. Contract data: `lines[].supplier_commission_direction`
  (non piu' `is_institution`). D-14: anche il tab Costi usa il ricavo effettivo, regola unica in
  `App\Services\WorkOrders\LineEffectiveRevenue`. Migrazione: institution -> RECEIVED, altre -> PAID, nuove tipologie
  spente; `ProductTypology::factory()->supplierCommission()` nei test che vogliono la commissione Fornitore.
- Spec 0203: repo legacy `qnet` nuovo `Api/V2/CommissionConfigurationMigrationController` +
  `GET api/v2/migration/commission-configurations` (sola lettura, 615 regole: 595 commercial/contact, 19
  supplier/company, 1 senza ruolo; `type` = tipo destinatario, `tipology` = ruolo). qnet-2: sorgenti
  `commission-configurations` (via `CommissionConfigurationService`, `old_id` nuovo su `commission_configurations`) e
  `product-suppliers` (imposta `products.supplier_id` solo se vuoto), fase subito dopo `registries`. Stima: 30 regole
  falliranno comunque (29 senza destinatario + 1 senza ruolo); le altre importabili solo con anagrafiche/referenti
  migrati. Nel dev DB nessuna anagrafica migrata: import non provato end-to-end.
- Verifica (verifier): Pest suite completa 9418/9419 (1 skip); Pint ok; Vitest 6970/6971 (timeout sotto carico
  `attribute-form-table-editor`, verde da solo); `tsc -b --force` 0; ESLint 1 errore preesistente
  `quotes/column-renderers.tsx:11`; HTTP reale + endpoint legacy reale; screenshot `v3-*`. Dopo D-14: Pest WorkOrders
  227/227, Vitest help+work-order-costs 131.
- Aperto: `margin_net` persistito delle offerte esistenti si aggiorna solo al prossimo salvataggio (0145 D-5);
  canali Gestione Richieste/Lead senza test dedicato sullo snapshot (passano dal writer condiviso); percentuali
  legacy ora applicate al margine riga, non al prezzo.
- Al commit: due repo (qnet-2 e qnet legacy); in qnet-2 ESCLUDERE i file di altre sessioni (registries,
  commission-configurations FE, `RegistryScopedTableDefinition`, `TableRegistry`).
- Seguito (NON COMMITTATO): `DemoCommissionShowcaseSeeder` (+ `DemoCatalog/DemoCommissionShowcaseCatalogue`) in
  `DemoDataSeeder` dopo `DemoContractSeeder`: 6 commesse "Demo commissioni - ..." (dev DB: id 11-16) con un caso
  ciascuna (PAID, RECEIVED %, RECEIVED fissa, fornitore mancante, tipologia `demo_training` senza calcolo, mista con
  costi e stati pagamento). Regole scope PRODUCT solo sui prodotti showcase. Test Seeding 142/142, Pint ok.

## ANAGRAFICA — TAB "COMMISSIONI CONFIGURATE" (spec 0204) — VERDE, COMMITTATO (2026-10-07)

- Richiesta utente: "aggiungere un tab commissioni configurate (collegamento tramite commissioni configuratore) su
  anagrafica". Scelte utente: perimetro = regole con destinatario QUESTA anagrafica (`recipient_type='registry'`,
  `recipient_id`), niente referenti/prodotti forniti; tab SOLO su anagrafiche fornitore (`is_supplier`) + Nuovo.
- Backend: `commission-configurations` aggiunto a `TableRegistry::REGISTRY_SCOPE_COLUMNS`
  (`commission_configurations.recipient_id`) + nuova `REGISTRY_SCOPE_MORPH_TYPE_COLUMNS` (`recipient_type`);
  `RegistryScopedTableDefinition` ha il param opzionale `morphTypeColumn` (where = alias morph di `Registry`). Chiavi
  `registryId`/`registry_id` della 0199 invariate. Test: `tests/Feature/Tables/RegistryCommissionConfigurationScopeTest.php` (6).
- Frontend: azioni di riga del Configuratore estratte in `use-commission-configuration-row-actions.tsx`
  (`CommissionConfigurationsTable` ci si appoggia); tab `commission-configurations` (`supplierOnly`, icona Percent) in
  `RegistryRelatedRecords` (nuova prop `isSupplier`, passata da `registry-detail.tsx`); `RegistryCommissionConfigurationsPanel`.
  Create param `registry_id` (`COMMISSION_CREATE_REGISTRY_PARAM`) -> `CommissionConfigurationFormMode` create
  `supplierRegistryId` -> defaults SUPPLIER/RECIPIENT/registry (`createDefaults`); label del destinatario via
  `useForSelectLabels`. i18n `registries.detail.related.commissionConfigurations`. Guide IT/EN: `registries#configured-commissions`
  (nuova) + tip in `commission-configurations#create-configuration`.
- Test aggiornati (solo setup, nessuna asserzione cambiata): mock `./api` con `COMMISSION_CONFIGURATIONS_DOMAIN`,
  mock pannelli con il nuovo export, prop `isSupplier={false}` nei casi esistenti. Nuovi casi: tab fornitore/permessi,
  pannello (5 casi della describe.each), preset form/screen/body.
- Verifica: Pest Tables+Table+Exports+CommissionConfigurations+Registries 513 verdi; Pint ok; Vitest registries+
  commission-configurations+help+i18n 54 file / 444; `tsc -b --force` 0; ESLint pulito. Browser NON verificato
  (backend dev non attivo, Playwright non installato).
- Manuale Claude Docs: da aggiornare (Anagrafiche > dettaglio fornitore, tab Commissioni configurate; Configuratore
  Commissioni > creazione dal fornitore).
- Commit con i soli file di questa feature (esclusi quelli delle spec 0202 e 0203-legacy-commission-rules-migration,
  altra sessione). Spec rinumerata 0204 per collisione con la 0203 creata in parallelo.

## COMMESSA — TAB "DATI CONTRATTUALI" + STATI PAGAMENTO PER RIGA (spec 0201) — VERDE, COMMITTATO 1e512ce4 (2026-10-07)

- Richiesta utente: nel dettaglio commessa gli stati di pagamento per ogni prodotto e una sezione che spiega i calcoli
  per riga (riferimento legacy `qnet` `manageorder/{id}` "Dati contrattuali commessa", `Manageorder::calcolaDatiContrattuali`).
- Decisioni utente: ricavo per riga visibile in DUE letture (legacy: tipologia `institution` = commissione Fornitore,
  altre = imponibile; spec 0145: netto commissioni); catalogo stati CONFIGURABILE con flag `allows_delivery`; per riga
  stato + accordo + insoluti; notifica "si puo' consegnare"; migrazione dal legacy; terzo tab dopo Task/Costi;
  D-15 totali PER TIPOLOGIA flessibili (tutte le `product_typologies`, zero-filled, come `QuoteTypologySummaryCalculator`).
- Backend: lookup `work_order_payment_statuses` (`WorkOrderPaymentStatus`, `WorkOrderPaymentStatusService`, CRUD +
  for-select `{id,label,name,color,allows_delivery}` + reorder, TableDefinition `work-order-payment-statuses`, seeder
  `WorkOrderPaymentStatusSeeder` in DatabaseSeeder e QualificaProductionDataSeeder, 10 stati legacy `old_id` 1..10);
  `work_order_line_payments` (`WorkOrderLinePayment`, audit, UNIQUE quote_line_id, status FK restrictOnDelete),
  cancellato da `WorkOrderLineWriter` quando la riga esce; `GET /api/work-orders/{id}/contract-data` e
  `PATCH .../contract-data/lines/{quoteLine}` (`WorkOrderContractDataBuilder`, `WorkOrderLinePaymentWriter`,
  `routes/api/work-order-contract-data.php`); permessi `work-orders.viewContractData`/`managePayments`, flag
  `view_contract_data`/`manage_payments`; `WorkOrderLineDeliverableNotification` (+ `AssignmentTargetEnum::WorkOrder`,
  `RecordLinkResolver`); sorgente migrazione `work-order-line-payments` (fase 11 `MigrationOrder`). Commissioni
  nascoste con la regola di `QuoteResource::summarizeTotals`. Piu' commissioni Fornitore: amount = somma, type/value
  della prima per id.
- Frontend: `features/work-order-contract-data/` (tab, tabella con formule sotto la riga, card totali per tipologia,
  editor inline pagamento), `features/work-order-payment-statuses/` (modulo impostazioni, route
  `/work-order-payment-statuses`, nodo nav in `config/navigation/configuration.php`), i18n `{it,en}-work-order-contract-data.ts`
  e `{it,en}-work-order-payment-statuses.ts`, guide in-app `work-orders#contract-data` + nuova `work-order-payment-statuses`.
- Verifica (verifier indipendente): Pest 883/883 aree feature (suite completa backend 9366/9368: il fallimento era
  `RegistryTableTest` delle modifiche registries di un'altra sessione, ora verde 12/12); Pint ok; Vitest 72 file / 588;
  `tsc -b --force` 0; ESLint 0; HTTP reale su commessa 1 (GET/PATCH/404/422/for-select) poi ripristinato; screenshot
  1280/375 light/dark, nessuno scroll orizzontale.
- Aperto: (1) API legacy `work-orders` (repo `/Users/Repository/qnet`) deve esporre `stato_pagamento`,
  `accordo_pagamento`, `insoluti` di `orderisos` — NON autorizzato, sorgente testata solo con client fake;
  (2) C-2: eventuali attributi commessa `stato_pagamento`/`accordo_pagamento`/`insoluti` diventano doppioni;
  (3) manuale Claude Docs: accesso negato -> aggiornare a mano (dettaglio Commessa tab "Dati contrattuali", nuovo modulo
  "Stati pagamento commessa"); (4) la regola "ricavo = commissione Fornitore" resta legata al codice tipologia
  `institution`; (5) nel dev DB la commessa 89 non esiste.
- Seguito (utente): tab "Dati contrattuali" PRIMO e aperto di default (ordine Dati contrattuali, Task, Costi) in
  `work-order-detail-work-tabs.tsx`; 2 test del file aggiornati (requisito cambiato), guide `work-orders` IT/EN.
- PROVATI E ANNULLATI su richiesta utente ("torna a quando ti ho chiesto di spostare dati contrattuali con il tab", da
  rivedere con calma): flag tipologia `revenue_from_supplier_commission`, commissioni di tutti i ruoli per riga + card
  per ruolo, riepilogo stile offerta (costi/margine), seeder `DemoSupplierCommissionSeeder`. Codice e spec riportati
  allo stato D-15 + tab primo; non riproporli senza nuova richiesta. Residuo NON ripristinabile nel DB dev: le
  commissioni delle offerte demo sono state rigenerate (Commerciale/Segnalatore/Supervisore/Fornitore) e puntano a
  regole "Demo commissione ..." ora SUSPENDED (le non referenziate eliminate); prodotti tutti `institution`, nessun
  `supplier_id`, anagrafica fornitore demo eliminata. Per un DB pulito serve un reseed (solo su conferma utente).
- Verifica dopo il ripristino: Pest WorkOrders+WorkOrderPaymentStatuses+source+ProductTypologies+Authorization 368/368;
  Vitest contract-data/payment-statuses/work-orders/product-typologies/help/i18n/routes 77 file / 628, quotes 354/354;
  `tsc -b --force` 0; ESLint 0; nessun residuo di identificatori annullati (grep).
- Al commit: ESCLUDERE i file registries non di questa feature (altra sessione).

## ANAGRAFICHE — TABELLA: COLONNE COMMERCIALE / SUPERVISORE / SEGNALATORE / OPERATORI — VERDE, COMMITTATO (2026-10-07)

- Richiesta utente: "aggiungere nella tabella anagrafiche le colonne commerciali, supervisori, segnalatori e operatori".
  Decisione utente: "Operatori" = TUTTI i gestori account (`managers`, pivot `registry_user`, in ordine di position),
  non il solo GA2.
- Backend: nuovo `Tables/Registries/RegistryRelationColumns` (filtro set whereHas per nome, sort subquery correlata,
  distinct + "(Vuoti)") per `source`/`commercial`/`supervisor`/`reporter` + `managers` (non ordinabile), come
  `OpportunityRelationColumns`. `RegistriesTableDefinition` delega (logica `source` spostata li', 323 -> 273 righe),
  eager load `commercial`, `supervisor.avatar`, `reporter`, `managers.avatar`; righe: `commercial`/`reporter`
  `{id,name}|null`, `supervisor` `{id,name,avatar_url}|null`, `managers` array. Catalogo: 4 colonne prima di
  `created_at` + filtri. Spec 0020 (contratto columns/rows) aggiornata.
- Frontend: `registryColumnRenderers` -> `RelationCell` (commercial Briefcase, reporter UserRound), `UserCell`,
  `UserStackCell`. Label `registries.columns.{commercial,supervisor,reporter,managers}` IT/EN ("Operatori"/"Operators").
- Test: `RegistryTableTest` colonne 8 -> 12 (REQUIREMENT CHANGED); nuovo `RegistryTableTeamColumnsTest` (7);
  `column-renderers.test.tsx` +3 casi. Guida in-app `registries` IT/EN (passo in "Cercare un'anagrafica").
- Manuale Claude Docs: NON aggiornato (doc non accessibile dalla sessione) -> sezione Anagrafiche > tabella/ricerca.

## TEAM — EDITOR GESTORI ACCOUNT COME COMMESSE (ANAGRAFICHE/OPPORTUNITA'/OFFERTE) — VERDE, COMMITTATO (2026-10-07)

- Richiesta utente: "modificando il team lo spazio e' davvero poco, fallo come in commesse".
- Causa: `ManagerSlotsField` senza `labels` usa il badge numerico compatto (numero + picker + 3 pulsanti su una riga,
  picker schiacciato nella colonna valore del dettaglio). Commesse passa sempre le etichette -> layout "named"
  (etichetta su riga propria, picker a tutta larghezza).
- Fix: nuovo `managerSlotLabels(t, labels)` in `features/shared/manager-position-label.ts` (mappa COMPLETA 1..
  `MAX_MANAGER_SLOTS`, override di categoria sopra il default "Gestore account n"). Usato da `RegistryManagersField`,
  `OpportunityManagersField` (rimosso `toSlotLabels` locale), `QuoteManagersField` (non usa piu' `toManagerSlotLabels`,
  che resta per Gestione richieste). `ManagerSlotsField` invariato (solo commenti): Gestione richieste mantiene il badge.
- Test: `opportunity-relation-fields.test.tsx` aggiornato (requisito cambiato: ora inoltra sempre la mappa completa);
  nuovo `manager-position-label.test.ts`. Manuale: nessun impatto.
- Nota: `help-guide-keys.test.ts` (55 -> 56) e gli errori `tsc` in `work-order-payment-statuses/` sono del lavoro
  parallelo spec 0201, non di questa modifica.

## ANAGRAFICA — CREAZIONE: CAMPI RAPIDI CONTATTI/INDIRIZZO RIPRISTINATI (2026-10-07)

- Richiesta utente: "in creazione anagrafica prima c'erano precompilati i contatti e indirizzi, voglio che siano
  comunque precompilati per essere modificati". Annulla la scelta di 0155abca ("in creazione niente piu' campi rapidi").
- `PersonalDataChildCards` (`personal-data-record-cards.tsx`): nuove prop opzionali `createMode` +
  `requiredCreateTypes`, passate a `ContactsManager`/`AddressesManager` (quick field email/telefono/PEC/fax + form
  indirizzo inline). `registry-form-body.tsx` le passa con `REQUIRED_CREATE_CONTACT_TYPES = ['phone']` (asterisco sul
  Telefono). Il dettaglio resta invariato (niente createMode).
- REQUIREMENT CHANGED (di nuovo) nei test: `registry-form-required-phone` ripristina il test del quick field
  Telefono obbligatorio (+ indirizzo visibile), `-custom-fields` digita nel campo Telefono; rimossi
  `ContactsManagerStub`/`STUB_PHONE` e l'`eslint-disable` ormai inutile dalle fixture.
- Guida in-app IT/EN `registries`: creazione con campi gia' pronti, passi del flusso nuovo cliente aggiornati.
- SEGUITO (utente: "aggiungi sempre in anagrafica, aggiungi indirizzo per aggiungere piu indirizzi"):
  `AddressesManager` nuova prop opt-in `multipleOnCreate` (con `createMode`): form inline + lista + "Aggiungi
  indirizzo" (dialog). Stato `inlineKey` = `_key` dell'indirizzo posseduto dal form inline, cosi' un indirizzo aggiunto
  dal dialog non ci "salta" dentro; il principale resta unico (`normalizePrimary`), svuotato l'inline passa al primo
  degli altri. `PersonalDataChildCards` la attiva con `createMode` (solo anagrafica; referenti/utenti invariati).
  Test: 3 nuovi in `addresses-manager.test.tsx` + "Add address" visibile in `registry-form-required-phone`. Guide
  IT/EN aggiornate. `addresses-manager.tsx` 405 righe (sopra soft limit 300, era gia' 368): split candidato = estrarre
  lista+dialog in `addresses-list.tsx`.
- Manuale Claude Docs: sezione Anagrafiche > creazione da allineare.

## ANAGRAFICA — DETTAGLIO EDITABILE IN PLACE (CAMPI FLESSIBILI COMPRESI) + CREAZIONE COME IL DETTAGLIO (spec 0200) — VERDE, COMMITTATO b9d9f79b + SEGUITO (2026-10-06)

- SEGUITO (dopo b9d9f79b), richiesta utente "/registries/new allineato a come e' stato fatto l'edit":
  - Creazione: Dati anagrafici come riga chiusa (`RegistryIdentityRecordSection` unica per dettaglio e creazione, prop
    `identity` = card o bozza; `PersonalDataIdentityRows` accetta `PersonalDataIdentity`). Stato in
    `use-registry-draft-inline-edit.ts` (snapshot della scheda per Ripristina, Fatto bloccato se scheda incompleta,
    `cardSignal`); `useRegistryFormSubmit` ha `onRefused(section)` e il body riapre la riga della scheda.
  - Contatti/Indirizzi: `PersonalDataChildCards` (presentazionale, buffer del chiamante, `persistence` opzionale) in
    `personal-data-record-cards.tsx`; `PersonalDataRecordCards` ci si appoggia. In creazione niente piu' campi rapidi
    (telefono obbligatorio solo al Salva). `registry-form-page.tsx`: pulsante Indietro come il dettaglio.
  - Comuni: `CityPickerField.onChange(id, city)` + `PersonalDataIndividualFields.onCityPicked` + stato `cities` in
    `PersonalDataCardForm`: la bozza porta `birth_city`/`residence_city` (etichetta) per le righe chiuse.
  - BUG (utente: "da azienda a persona fisica mi ritorna errore"): toast "expected array, received null" = Tag
    (`registries.tags`, relazione many) salvato `null` => OGNI salvataggio inline falliva. Fix generico in
    `build-custom-fields-schema.ts`: relazione many e enum multiselect `.nullable()`. Test schema + regressione nel
    dettaglio; backend `RegistryCardTypeSwitchTest` (PATCH azienda -> persona fisica ok, nome rinominato). Sul record
    demo 171 il salvataggio si ferma ora solo per la Partita IVA demo non valida (messaggio nell'editor).
  - REQUIREMENT CHANGED nei test: campo rapido Telefono rimosso (`registry-form-required-phone`), scheda chiusa
    (`-metadata`, `-duplicate-warning`, `-custom-fields`); stub `ContactsManagerStub` + `fillCardNames` nelle fixture.
  - Verifica: Vitest completo 905 file / 6862 verdi; `tsc -b --force` 0; ESLint pulito sui file toccati (1 warning
    preesistente in `table-field-control.test.tsx`); Pest Registries 101 verdi, Pint ok. Browser: `/registries/new`
    desktop e 375px senza scroll orizzontale, Milano visibile nella riga chiusa; `/registries/171` errore riprodotto e
    sparito.

- Richiesta utente: "prendi anagrafica e fai le stesse modifiche di commesse ... soprattutto per i campi flessibili.
  Anche per creazione ... come fatto in task". Solo frontend: la PATCH `/registries/{id}` gia' restituiva
  `permissions` (ora `updateRegistry` li espone, `RegistryDetailWithPermissions`).
- Dettaglio (`RegistryDetailView`: niente `onEdit`, nuovo `onChanged`; `ResourcePermissionsProvider` attorno):
  `useRegistryInlineEdit` su `useRegistryForm` edit (`values` + `keepDirtyValues`, reset dopo PATCH). Submit estratto in
  `use-registry-form-submit.ts` (`useRegistryFormSubmit`; in edit la scheda anagrafica si valida SOLO se e' nel PATCH:
  un'anagrafica storica incompleta non blocca gli altri campi). Sezioni condivise dettaglio/creazione:
  `registry-record-identity.tsx` (Dati anagrafici = UNA riga `REGISTRY_CARD_FIELD` che apre `PersonalDataCardForm`,
  matita da `personal_data.type`; in creazione sempre aperta), `-record-relations.tsx` (Relazioni, Team con slot vuoti
  visibili, Referenti a tutta larghezza), `-record-business.tsx` (Fornitore qualificato solo se fornitore). Campi in
  `registry-relation-fields.tsx` / `-business-fields.tsx`. Valori in `registry-record.ts` (`persistedRegistryValues`).
- Campi flessibili: NUOVO generico `features/custom-fields/custom-field-record-sections.tsx`
  (`CustomFieldRecordSections`: gruppi come il form, riga = `RecordInlineField` field `custom_fields.<raw>` metaKey
  `custom.<raw>`, editor = `CustomFieldItem` ora esportato da `CustomFieldsSection.tsx`) + `custom-field-value-display.tsx`
  (relazione via `useForSelectLabels`, enum label, Si/No, tabella read-only). Ordinamento/gruppi estratti in
  `custom-fields-grouping.ts`. Riusabile da ogni modulo con campi personalizzati.
- Contatti/Indirizzi: `personal-data-read-only-cards.tsx` RINOMINATO `personal-data-record-cards.tsx`
  (`PersonalDataRecordCards`, `git mv` => rename in staging) con prop `editing` (persistenza immediata dei manager,
  invalida il dettaglio). `referent-detail.tsx` aggiornato solo nell'import (resta read-only).
- Creazione: `registry-form-body.tsx` (replica del dettaglio, side = avviso duplicati + Contatti/Indirizzi con campi
  rapidi, telefono obbligatorio) + `registry-form-header.tsx` (monogramma + nome live, `RegistryStatsStrip` condiviso) +
  `registry-create-sections.tsx` + `use-registry-draft-values.ts`. `RegistryForm` solo creazione (niente `mode`, meta via
  `useResourceMeta`); `guarded-registry-form.tsx` (`useFormLeaveGuard`) usato da `RegistryFormScreen` e `RegistryFormPage`.
  Rotta `registries/:id/edit` rimossa; registry `generateEditRoute: false`. Quick-create (`module-entries.tsx`) senza `mode`.
- Rimossi: `registry-form-details-tab`, `-form-summary`, `-form-team-section`, `registry-detail-team`,
  `use-registry-form-meta`, `RegistryDetailStats` (-> `RegistryStatsStrip`); PlannedField "Codici ATECO". i18n orfane
  rimosse: `form.editTitle/editSubtitle`, `form.groups.*`, `form.atecoCodes*`, `detail.summary.*`,
  `form.sections.*.description`; nuove `form.leaveConfirm.*`.
- REQUIREMENT CHANGED dichiarati nei test: `registry-detail.test` (niente Edit), `registry-form-metadata.test` (riscritto:
  righe chiuse, casi edit spostati), `registry-form-custom-fields.test` (riga chiusa + Fatto), pagine form/detail (niente
  edit). Nuovi: `registry-detail-inline-edit.test.tsx` (11), fixture `registry-test-fixtures.tsx`.
- Verifica: Vitest completo 904 file / 6857 verdi; `tsc -b --force` 0; ESLint pulito sui file toccati. Browser reale
  (Playwright, `/registries/1`, `/registries/new`): desktop light/dark e 375px senza scroll orizzontale, nessun errore
  runtime; editor Gruppo IVA e Dati anagrafici aperti in place.
- Guida in-app IT/EN `registries`: nuova `editing-a-registry`, `registry-record` e `new-client-flow` riscritte.
- Da fare / fuori scope: manuale Claude Docs (doc NON accessibile da questa sessione: "not shared") — sezione
  Anagrafiche: modifica in place (campi personalizzati, scheda anagrafica, contatti/indirizzi), creazione a righe
  chiuse. Su mobile Contatti/Indirizzi (telefono obbligatorio) stanno sotto il Salva del corpo, come nei RecordBody degli
  altri moduli. Chiavi i18n orfane preesistenti `registries.detail.title/subtitle/details`, `form.managersPlaceholder`,
  `form.managersRemove`.

## ANAGRAFICA — TAB RECORD COLLEGATI (Opportunita'/Offerte/Commesse/Task) + "NUOVO" PRECOMPILATO (spec 0199) — VERDE, COMMITTATO (2026-10-06)

- Richiesta utente: "in anagrafica tabelle di appoggio per commesse, opportunita', offerte e attivita' associate
  all'anagrafica ... tab sotto con le tabelle filtrate per il cliente". Scelte utente: attivita' = modulo Task;
  ogni tab ha anche "Nuovo" precompilato.
- Backend: scope di righe `registry` (come `opportunityId` 0067 / `quoteId` 0095). Chiave `registryId` su
  rows/values/export, query `registry_id` su columns. Interfaccia `App\Tables\RegistryScopable`; nuovo decoratore
  `Tables\Registries\RegistryScopedTableDefinition` (opportunities -> `opportunities.registry_id`, tasks ->
  `tasks.registry_id`, esterno in `TableRegistry::REGISTRY_SCOPE_COLUMNS`); `OpportunityScopedTableDefinition`
  (quotes, via opportunita' del cliente) e `QuoteScopedTableDefinition` (work-orders, via offerta -> opportunita')
  implementano anche `RegistryScopable` (un secondo wrapper li nasconderebbe agli `instanceof`). Controlli sempre
  con `instanceof RegistryScopable`. `GET /quotes/for-select?registry_id=` (solo pagina, non l'hydration `ids`).
  Test: `tests/Feature/Tables/RegistryRowScopeTest.php` (29).
- Frontend framework: `TableRowScope.registryId` propagato come `quoteId` (table-view, controller, grid-state,
  slots, ssrm-datasource, data-table, column-def-builder, column-filters, export). Chiave filtri locali: segmento
  anagrafica aggiunto SOLO se presente (chiavi esistenti invariate).
- UI: `registry-related-records.tsx` (card a tab sotto `RecordBody`, tab gated da `<modulo>.viewAny`, contatore
  dopo il primo caricamento, griglia montata solo nella tab attiva), `registry-related-panels.tsx` (un pannello per
  modulo sul SUO hook azioni, sheet forzato), `registry-related-grid.tsx` (Nuovo + `TableView rowScope`).
  Tab Task: override di visita `assignment` = `visible` (con viewAll/viewSite) o `all`, invece di "assegnati a me".
- Estratti: `useOpportunityRowActions` (da `OpportunitiesTable`, secondo call site), `opportunities/action-icons.ts`,
  `tasks/action-icons.ts` (`TASK_ACTION_ICONS`), `tasks/task-form-defaults.ts` (`use-task-form.ts` superava 500).
  `useWorkOrderRowActions` espone `openCreateWith`.
- Create param `registry_id`: Opportunita' -> `OpportunityCreateFormMode.registryId` (default + ruoli ereditati
  una volta, `useOpportunityRegistryPreset`; ignorato con `lead_id`); Offerte -> `QUOTE_CREATE_REGISTRY_PARAM`
  restringe il selettore Opportunita' (ignorato con `opportunity_id`); Commesse -> `registryId` fino a
  `WorkOrderQuoteField` (selettore Offerta ristretto); Task -> `TaskFormMode.registryId`.
- CONFLITTO SEGNALATO: spec 0093 D-13 diceva "nessuna creazione commessa dalla lista"; ora la tab Commesse
  dell'anagrafica offre "Nuova commessa" (scelta utente esplicita), la pagina elenco resta senza.
- Verifica: Vitest completo 903 file / 6853 verdi (+ test registries dopo override Task: 90); `tsc -b --force` 0;
  ESLint sui file toccati 0; Pest completo 9326 passati / 1 skipped; Pint pulito. Non verificato nel browser reale.
- Manuale: guida in-app IT/EN `registries` nuova sezione `related-records`. Manuale Claude Docs NON accessibile
  (doc non condiviso con la sessione) -> da aggiornare la sezione Anagrafiche.
- Fuori scope segnalato: errore ESLint preesistente `registry-form-metadata.test.tsx:271` (`_omit`);
  HANDOFF.md ~430 KB, va archiviato in `docs/handoff-archive/`.

## OFFERTE: DOPPIO "CREA OFFERTA" SU /quotes/new — VERDE, COMMITTATO (2026-10-06)

- Causa: dalla spec 0197 il form offerta disegna la propria banda identita' (titolo "Crea offerta" + Annulla/Salva),
  ma `moduleScreen` di `quotes` non era `formOwnsHeader`, quindi `ModuleFormPage` (e lo Sheet modale) aggiungevano
  il proprio titolo/sottotitolo sopra. Fix: `formOwnsHeader: true` in `quote-screens.tsx` (+1 test in
  `quote-screens.test.tsx`). Vitest quotes+modules 414 pass, ESLint ok, `tsc -b --force` pulito. Manuale: nessun impatto.
- Stesso difetto chiuso su `work-orders` (spec 0196, /work-orders/new): `formOwnsHeader: true` in
  `work-order-screens.tsx` + nuovo `work-order-screens.test.tsx`. Vitest work-orders+modules 288 pass, ESLint ok.
  COMMITTATO. Al momento del fix `tsc -b --force` era rosso solo per lavori in corso altrui (`personal-data`,
  `registries`), nessun errore in work-orders.

## ANAGRAFICHE: "TAG" COME CAMPO PERSONALIZZATO — VERDE, NON COMMITTATO (2026-10-06)

- Richiesta utente: select con i tag in anagrafica, come campo personalizzato (non colonna nativa). Nessun codice
  di prodotto nuovo: il tipo `relation` con `cardinality: many` verso `tags` (for-select `GET /tags/for-select`)
  esisteva gia'; il form anagrafica lo rende in "Altri campi" con `AsyncPaginatedMultiSelect` (chip).
- `QualificaTemplateSeeder`: nuova voce TEMPLATES `registries` -> `tags` / "Tag" / `relation`
  (`TAGS_RELATION_TARGET` = `{entity_type: tags, cardinality: many, for_select_resource: tags}`). Valore salvato
  in `custom_fields.tags` come array di id; id inesistente -> 422 `custom_fields.tags`.
- Test: `QualificaTemplateSeederTest` (+1), nuovo `tests/Feature/Registries/RegistryTagsCustomFieldTest.php` (2).
  Pest Registries + CustomFields + suite che usano i seeder Qualifica: 264 pass. Pint ok. Guida in-app
  registries IT/EN (riga "Altri campi"): Vitest help 108 pass, ESLint ok, `tsc -b --force` pulito.
- Da fare: eseguire `php artisan db:seed --class=QualificaTemplateSeeder` su locale/staging/prod per creare il campo.
  Il dettaglio anagrafica (sola lettura) NON mostra i campi personalizzati: il Tag si vede nel form di modifica e
  come colonna della griglia. Tag delle anagrafiche legacy non migrati (gia' fuori scope della migrazione).
  Manuale Claude Docs non accessibile da questa sessione: aggiungere "Tag" tra i campi della scheda anagrafica.

## OPPORTUNITA' — DETTAGLIO EDITABILE IN PLACE + CREAZIONE COME IL DETTAGLIO (spec 0198) — VERDE, COMMITTATO (2026-10-06)

- Richiesta utente: stesse modifiche delle Commesse (spec 0196) sulle Opportunita', creazione come nei Task. Solo
  frontend: la PATCH `/opportunities/{id}` gia' restituiva `permissions` (ora `updateOpportunity` li espone).
  L'Opportunita' NON ha campi flessibili (Attributi passati all'Offerta con la spec 0084).
- Dettaglio (`OpportunityDetailView`: niente `onEdit`, nuovo `onChanged`): `useOpportunityInlineEdit` su
  `useOpportunityForm` edit (`values` + `keepDirtyValues`, reset dopo PATCH, `clearSubmitErrors`). Sezioni condivise
  dettaglio/creazione: `opportunity-record-details.tsx` (Note generali callout, Dettagli: titolo + pianificazione),
  `-record-client.tsx` (Anagrafica con cascata e avviso "opportunita' aperta" nell'editor, Referente solo con
  anagrafica, Commerciale, Segnalatore con i buoni, Lead in sola lettura), `-record-classification.tsx` (Fonte, Righe,
  Prodotti di interesse; Team: Supervisore + G.A. in una riga). Tipi/hook in `opportunity-record.ts`
  (`useCascadeEditable`: editor non apribile se la cascata scrive un campo non editabile). Campi in
  `opportunity-fields.tsx`, `-relation-fields.tsx`, `-registry-field.tsx` (senza `forceDisabled`), `-reporter-field.tsx`.
  BR-2: `registry_id`/`source_id` in `locked_fields` = nessuna matita. Premi: `RewardChipsSection` resta sola lettura.
- Creazione: `opportunity-form-body.tsx` + `-form-header.tsx` (KPI condivisi `OpportunityStatsStrip`) +
  `-create-sections.tsx` (Lead di origine sempre aperto in testa) + `use-opportunity-draft-values.ts` (etichette via
  `useForSelectLabels`/albero categorie). `GuardedOpportunityForm` in `opportunity-screens.tsx` (`useFormLeaveGuard`);
  registry `generateEditRoute: false`. Default in `opportunity-form-defaults.ts`.
- Condiviso: `useOutsidePointerDismiss` ignora i pointerdown dentro `[role="alertdialog"]` (conferma "Sostituire i
  ruoli?" dell'anagrafica). `useRegistryRoleInheritance`: memoria dei ruoli ereditati in `WeakMap` per form (il picker
  vive solo a riga aperta). Riusa `components/record-form/first-error-message.ts` (spec 0197 Offerte).
- Rimossi: `opportunity-{attribution,client,team,general-notes,title,planning,product-lines,lead}-section`,
  `-form-summary`, `use-opportunity-selected-items`, `use-opportunity-form-meta`, `OpportunityFormSkeleton`; stato lead
  senza `operationalSite`/`managers`; `OpportunityFromLeadContext.managerRefs`. i18n orfane rimosse
  (`form.header/summary/editTitle/editSubtitle`, `sections.attribution/planning`, `sections.*.description`); nuove
  `detail.sections.details`, `form.leaveConfirm.*`.
- REQUIREMENT CHANGED dichiarati nei test (righe chiuse, matita invece di disabilitato, niente modo edit). Nuovi:
  `opportunity-detail-inline-edit` (7), `-create-rows` (6), `-screens-leave-guard` (2), `-fields` (5),
  `-relation-fields` (4). Verifica: Vitest completo 900 file / 6817 verdi; `tsc -b --force` 0; ESLint 0 errori.
- Guida in-app IT/EN `opportunities`: nuova `editing-an-opportunity`, `create-an-opportunity` riscritta.
- Da fare / fuori scope: manuale Claude Docs (sezione Opportunita': modifica in place, creazione a righe chiuse).
  Chiavi i18n orfane preesistenti `form.sections.workflowStatus`, `form.workflowStatus*`, `form.operationalSite*`.
  Non verificato nel browser reale.

## ORIENTAMENTO SPECIALISTICO — CAMPO "ANAGRAFICA UTENTE" (seed produzione) — VERDE, COMMITTATO (2026-10-06)

- Richiesta utente: "per productionSeeder, per Orientamento inserire un campo flessibile in offerta Anagrafica Utente
  che e' un collegamento ad anagrafica".
- `AplOrientationAttributeCatalogue::ATTRIBUTES`: nuovo `user_registry` ("Anagrafica Utente"), `relation` →
  `{entity_type: registries, cardinality: one, for_select_resource: registries}` (stesso schema di `teaching_tutor`).
  Prima riga della sezione "Testata" (da sola), descrizione sezione aggiornata. Il docblock non dice piu' che "Utente"
  e' l'anagrafica dell'opportunita'.
- Installazioni gia' seedate: `PREVIOUS_SECTIONS` (form del 2026-10-05) + `AplPracticeCatalogue::PREVIOUS_FORMS` +
  `QualificaQuoteLayoutSeeder::isPreviousOwnForm()` riconoscono il blob vecchio byte per byte e lo ricompongono; un
  layout ritoccato a mano resta intatto. `DATA_SECTION`/`PATH_SECTION` private condivise tra form attuale e precedente.
- Test: `QualificaAplOrientationCatalogueTest` (16 campi, relation_target, layout, ricomposizione). Verdi: Products
  198, Seeding 139. Pint pulito. Nessun impatto FE (relation generico su `/registries/for-select`).
- Manuale: guide in-app nessun impatto (non descrivono i campi Orientamento); manuale Claude Docs non accessibile
  dalla sessione → verificare se descrive i campi offerta dell'Orientamento specialistico.

## OFFERTE — DETTAGLIO EDITABILE IN PLACE + CREAZIONE COME IL DETTAGLIO (spec 0197) — VERDE, COMMITTATO (2026-10-06)

- Richiesta utente: "prendi offerte e fai le stesse modifiche di commesse ... soprattutto per i campi flessibili.
  Anche per creazione ... come fatto in task". Solo frontend: la PATCH `/quotes/{id}` gia' restituiva `permissions`
  e gia' risincronizza provvigioni (cambio Commerciale/Segnalatore/Supervisore) e buoni (cambio Segnalatore).
- Dettaglio (`QuoteDetailView`, niente `onEdit`, nuovo `onChanged`): `useQuoteInlineEdit` su `useQuoteForm` edit
  (`values` + `keepDirtyValues`, reset dopo PATCH, `clearServerError`). Sezioni: `quote-record-identity.tsx` (Note
  interne callout; "Dati offerta" = Codice, Titolo, Stato con nota di transizione via `QuoteWorkflowStatusControls`,
  Opportunita'), Contesto read-only in `quote-detail-sections.tsx`, `quote-record-people.tsx` (Anagrafica e contatti
  con Segnalatore+buoni; Team = Supervisore + G.A. in una riga), `quote-record-company.tsx` (Societa'/sedi con
  cascata sede; Documento e pagamento), `quote-attributes-section.tsx` (un Attributo = una riga, layout
  `attribute_view_layout`; `QuoteNewAttributesFields`). Banda righe `quote-detail-lines.tsx`: Righe offerta / Righe
  costo = due `RecordInlineField` block; aperta una, il riepilogo diventa `QuoteLinesLiveSummary`.
  `useQuoteFormContext(ids, persisted)` non chiama form-context finche' i prodotti sono quelli salvati.
- Creazione: `quote-form-body.tsx` (replica: `quote-form-header.tsx` con KPI live via `useQuoteLiveTotals`,
  `quote-create-sections.tsx` a righe chiuse con nomi da `useQuoteDraftLabels`, `quote-create-lines.tsx` con griglie
  APERTE + riepilogo live + pallino errori), `useQuoteCreateDefaults` (eredita' ruoli/sede/G.A. dall'Opportunita',
  Opportunita' forzata da params, prodotti seminati, layout predefinito), `useFormLeaveGuard`; registry
  `generateEditRoute: false`; `QuoteForm` solo create (`QuoteCreateFormMode`, `RecordFormSkeleton`).
- Estratti: `quote-form-defaults.ts`, `use-quote-line-caches.ts` (da `use-quote-form.ts`), `use-quote-live-totals.ts`
  (da `quote-summary.tsx`), `QuoteStatsStrip`, campi `quote-identity-fields.tsx`/`quote-relation-fields.tsx`,
  `quote-field-strings.ts`, `quote-lines-editors.tsx`; `components/record-form/first-error-message.ts` condiviso
  (rimossa la copia identica in `use-work-order-inline-edit.ts`). `QuoteOfferTab`/`QuoteCostsTab` senza FormSection.
- Rimossi: `quote-team-section`(+test), `quote-sites-section`, `quote-layout-section`, `quote-notes-tab`,
  `quote-detail-attributes`; chiavi `form.editTitle/editSubtitle`, `form.sections.{layout,notes,costs}`, le
  `description` di identity/team/sites, `form.tabs.notes`. Nuove: `form.leaveConfirm.*`, `detail.rewardsCount_*`,
  `detail.newAttributes`. `QuoteWorkflowStatusField`/`QuoteDynamicFieldsSection` restano (Gestione Richieste).
- REQUIREMENT CHANGED nei test: `quote-form-body.test` (creazione a righe chiuse; i casi edit spostati nel nuovo
  `quote-detail-inline-edit.test`, 11), `quote-form-opportunity-{roles,params}`, `-seeded-products`,
  `quote-sites-section.test` (aprono le righe), `quote-layout-section.test`->`quote-create-layout.test`,
  `quote-notes-tab.test`->`quote-create-payment.test`, `quotes-i18n.test`, `quote-screens.test` (nessun form edit),
  `quote-detail.test` (wrapper con `ConfirmContext`). Helper `quote-test-helpers.ts`.
- Verifica: Vitest Offerte+Gestione Richieste+Contratti+Commesse+moduli verdi; suite completa 6777 verdi, 20 rossi
  tutti in `opportunities/*` + `product-lines-wiring-parity` (refactor Opportunita' spec 0198 in corso in un'altra
  sessione, file loro). `tsc -b --force`: 0 errori fuori da `opportunities/*`. ESLint pulito sui file toccati.
  NON verificato nel browser reale (Playwright non installato nel progetto).
- Guida in-app IT/EN `quotes`: nuova sezione `editing-a-quote`, `create-a-quote` e `change-the-quote-status` riscritte.
- Da fare: verifica browser (desktop light/dark, 375px) di `/quotes/:id` e `/quotes/new`; manuale Claude Docs
  (sezione Offerte: modifica in place, campi flessibili, stato, righe, creazione) — il connettore c'era ma il doc
  non e' condiviso con la sessione (accesso negato).

## COMMESSE — DETTAGLIO EDITABILE IN PLACE + CREAZIONE COME IL DETTAGLIO + CHIUSURA FORZATA COME AZIONE (spec 0196) — VERDE, COMMITTATO (2026-10-06)

- Richiesta utente: stesse modifiche dei Task (spec 0195) sulle Commesse, "soprattutto per i campi flessibili";
  poi "chiusura forzata la voglio come un action, non come un campo, stessa action sulla tabella commesse".
- Condiviso (estratto dai Task, nessun cambio di comportamento): `components/record-form/record-inline-field.tsx`
  (`RecordInlineField` + tipo `InlineEdit`, prop nuove `metaKey`/`className`), `components/record-form/
  use-draft-inline-edit.ts` (`useDraftInlineEdit<T>`), `hooks/use-outside-pointer-dismiss.ts`; i18n
  `common.inlineEdit.*` (rimosso `tasks.detail.inlineEdit.*`). Blocco `common` spostato in `{it,en}-common.ts`
  (en.ts era a 499 righe). `AttributeLayoutView.renderField` (opzionale, default riga read-only) e
  `AttributeLayoutField.hideLabel`.
- Commesse FE: `useWorkOrderForm` edit = `values` + `keepDirtyValues`, `editDefaults` seminati, reset dopo PATCH,
  `clearServerError`; `useWorkOrderFormContext(ids, persisted)` non chiama form-context finche' le righe sono quelle
  salvate. `useWorkOrderInlineEdit` (toast se l'errore non e' nell'editor aperto; le righe portano con se'
  `attribute_values`). Dettaglio: `work-order-record-identity.tsx` (Note interne come callout, Dati), `-record-team.tsx`,
  `work-order-detail-sections.tsx` (Contratto con Righe in place + `WorkOrderNewAttributesFields`, Societa'),
  `work-order-attributes-section.tsx` (un Attributo = una riga). Campi in `work-order-identity-fields.tsx` e
  `-relation-fields.tsx`. Creazione: `work-order-form-body.tsx` + `-form-header.tsx` + `-create-sections.tsx`
  (etichette via `useForSelectLabels`), `useFormLeaveGuard`; registry `generateEditRoute: false`.
  `updateWorkOrder` ora restituisce i `permissions`. Rimossi: `work-order-{team,closure,notes,dynamic-fields}-section`,
  `work-order-detail-{team,attributes}` e i loro test; chiavi i18n orfane `form.editTitle/editSubtitle`,
  `form.sections.*` (resta `offer.title`), `form.hints.*Locked`, `form.description/internalNotes`.
- Chiusura forzata = azione: BE `WorkOrderColumnCatalog` (`force_close` lock / `reopen` lock-open confirm,
  `work-orders.update`), `actionsFor` (una sola delle due, Gate update + stato), `WorkOrdersAuthorization`
  `permissions.actions.force_close/reopen`. FE `use-work-order-closure.ts` (PATCH esistente, invalida board + task),
  `work-order-force-close-dialog.tsx` (motivo obbligatorio, avviso task aperti letto dal record se dalla griglia),
  `work-order-closure-actions.tsx` (header, `destructive`/`outline bg-card`), `use-work-order-row-actions.tsx`
  (rinominato da .ts, espone `forceCloseDialog`, montato da `WorkOrdersTable` e `ContractWorkOrdersSection`, con
  `iconMap`). Form/schema/payload senza `is_force_closed`/`force_close_reason`.
- REQUIREMENT CHANGED dichiarati: `WorkOrderQuoteScopeTest` AC-052 (`['view','force_close']`), `work-order-detail.test`
  (nessun Edit), `work-order-form-body.test` (riscritto: creazione a righe chiuse), payload/schema/hook test (niente
  chiusura nel form). Nuovi: `WorkOrderForceCloseActionTest` (5), `api.test.ts`, `work-order-detail-inline-edit.test`
  (12), `work-order-attributes-section.test` (6), `use-work-order-row-actions.test` (2), casi edit in
  `use-work-order-form.test`.
- Verifica: Pest completo 9293 verdi (1 skipped); Pint ok; Vitest completo 898 file / 6808 verdi; `tsc -b --force` 0;
  ESLint pulito sui file toccati. Browser reale (Playwright, `/work-orders/95`, `/work-orders/new`, `/work-orders`):
  desktop light/dark e 375px senza scroll orizzontale, nessun errore runtime. NB: dopo il rename `.ts`->`.tsx` il
  dev server Vite serviva un 404 finche' non sono stati toccati gli importatori (non serve riavviare).
- Guida in-app IT/EN `work-orders`: sezioni `editing-a-work-order`, `force-close`, `creating-a-work-order`.
- Da fare / fuori scope: manuale Claude Docs (sezione Commesse: modifica in place, campi flessibili, chiusura forzata
  come azione + Riapri, creazione). Chiavi orfane preesistenti `workOrders.detail.tasks.*`. Il titolo della pagina
  di creazione ripete "Crea commessa" come nei Task.

## COMMESSA: TASK E COSTI IN UNA CARD A TAB (TASK DI DEFAULT) — VERDE, NON COMMITTATO (2026-10-06)

- Richiesta utente: nel dettaglio commessa Costi e Task stanno nello stesso punto, come tab da alternare, con icona
  sul tab Task, Task aperto di default. Solo frontend, nessun contratto API toccato.
- Nuovo `features/work-orders/work-order-detail-work-tabs.tsx` (`WorkOrderDetailWorkTabs`): una `RecordCard` con
  strip `Tabs` (Task `ListChecks` | Costi `Euro`), gate invariati (`tasks.viewAny`, `view_costs`), nessun tab = niente
  card. `WorkOrderDetailView` monta solo questo al posto delle due card.
- `WorkOrderTaskBoard` non ha piu' card/header: nuova prop obbligatoria `actionsContainer: HTMLElement | null`, il
  bottone "Nuovo task" e il menu "..." vanno via `createPortal` nello slot a destra della strip (spariscono col tab
  Costi). Rimosso il badge conteggio dell'header (i KPI mostrano gia' i totali) e le chiavi `taskBoard.countLabel_*`;
  `workOrders.taskBoard.title` ora vale "Task"/"Tasks" (etichetta del tab). `WorkOrderCostsSection` non ha piu'
  card/header (il titolo e' il tab), resta `p-4` + sotto-tab Confronto/Costi effettivi.
- Tab Radix con smontaggio dei contenuti inattivi: passando a Costi e tornando, filtri/selezione del board si
  azzerano (la vista Lista/Board resta, e' persistita).
- REQUIREMENT CHANGED dichiarato nei test: `work-order-detail.test.tsx` (blocco Costi seleziona prima il tab Costi),
  `work-order-costs-section.test.tsx` (rimossa l'asserzione sull'heading "Costs"), `work-order-task-board.test.tsx`
  (passa `actionsContainer`). Nuovo `work-order-detail-work-tabs.test.tsx` (default Task, switch, gate, portal).
- Guida in-app IT/EN `work-orders` (sezione `costs`) aggiornata. Manuale Claude Docs: accesso negato -> aggiornare a
  mano la parte Commesse/Costi (Task e Costi nella stessa card a tab, Task di default).
- Verifica: Vitest work-orders/work-order-costs/help 45 file / 358 verdi; `tsc -b --force` 0; ESLint 0; screenshot
  reali `/work-orders/95` desktop light/dark e 375px, nessuno scroll orizzontale.

## TASK: RIGHE SOTTO-TASK COLORATE PER TIPO (stile q-net) — VERDE, NON COMMITTATO (2026-10-06)

- Contratto (spec 0101 data_contract + 0155): `subtasks[]` aggiunge `task_type` (badgeRef nullable) e `task_status`
  diventa `statusRef()` (con `group`). Eager load `taskType` in `TaskService::subtaskEagerLoad` e
  `TaskSubtaskReorderService`. Test Pest nuovo in `TaskSubtaskPermissionsTest`; Pest Tasks 634 verdi (anche N+1).
- Frontend: `TaskSubtask.task_status: TaskStatusRef`, `task_type: TaskLookupRef | null`. Riga tinta per tipo via
  `tintClassFor` (nuovo campo `tint` in `BADGE_COLOR_TOKENS`) passato a `SortableList.itemClassName` (nuova prop);
  tile del tipo (`badgeColorClass` + `DynamicIcon`, role img = nome tipo).
- Check di completamento = stesso disegno della cella Titolo: classi estratte in `task-complete-icon-styles.ts`
  (usate da `TaskTitleCell` e `TaskSubtaskRow`). Completato = `group closed_positive` (REQUIREMENT CHANGED in
  `task-subtask-progress.test.ts`: prima 100%), icona piena; se `uncomplete` e' permesso l'icona piena e' "Riapri".
- Riferimento visivo: `/Users/Repository/q-net` (`task-type-meta.ts`, `getSoftPanelStyle`): non esiste una cartella
  "qtask" in Repository, assunto q-net.
- Verifica: Vitest 113 file / 868 verdi (tasks, ui, custom-fields, help); tsc -b 0; ESLint/Pint puliti; screenshot
  light/dark/375px. Manuale Claude Docs: stesso paragrafo del pannello Sotto-task da aggiornare (accesso negato).

## TASK: EDITOR RICORRENZA RIFATTO (RESTYLE, SOLO FE) — VERDE, NON COMMITTATO (2026-10-06)

- Richiesta utente: l'editor aperto della Ricorrenza (dettaglio task, `TaskRecurrenceFields`) era "bruttissimo":
  descrizioni lunghe in corpo grande, griglia 2 colonne disallineata, checkbox giorni disordinate.
- Nuovo layout: switch "Ricorrenza attiva" (hint in tooltip `MetaField hint`), poi pannello `bg-card` a bande:
  Frequenza (Select, invariata) + "Ripeti ogni [n] <unita'>" (`tasks.form.recurrence.intervalUnit.*`, `custom` = giorni);
  giorni settimanali = cerchi `role="checkbox"` con iniziale (`weekdayInitial`) e aria-label giorno intero;
  mensile/annuale in `task-recurrence-day-fields.tsx` (Tipo di giorno = segmentato Data fissa / Giorno della settimana);
  Fine in `task-recurrence-end-fields.tsx` (segmentato Mai / A una data / Dopo N volte, ordine `END_MODE_ORDER`);
  "Solo giorni lavorativi" compatto; in fondo `RecurrencePreview` (aria-live) = `formatTaskRecurrenceRule` sulla bozza.
- `recurrencePreviewRule` (`task-recurrence-preview.ts`): null finche' manca un campo. `formatTaskRecurrenceRule` ora
  accetta `TaskRecurrenceRule = Omit<TaskRecurrenceDetail, 'id'>` (compatibile con i chiamanti esistenti).
- Nuovo `components/ui/segmented-control.tsx` (radiogroup, roving tabindex, frecce): estratto da
  `task-board-segmented-field.tsx`, che ora lo riusa (stesso aspetto).
- Costanti/utility condivise in `task-recurrence-field-props.ts` (`RECURRENCE_META_KEY`, `numberInputProps`, `intervalUnitKey`).
- i18n IT/EN: nuove `intervalUnit.*`, `occurrenceUnit_*`, `preview`, `previewIncomplete`; accorciate
  `monthModeOption.*`, `endsOption.after_count`, `ordinalWeekday`. Guida in-app `tasks` IT/EN aggiornata.
- REQUIREMENT CHANGED (redesign richiesto): `task-form-recurrence-section.test.tsx` usa `radio` per Fine e Tipo di giorno
  (prima `combobox`); aggiunto test anteprima. Nuovi test: `task-recurrence-preview.test.ts`, `segmented-control.test.tsx`.
- Verifica: `tsc -b --force` pulito, ESLint pulito, Vitest tasks/help/i18n/work-orders/components-ui 146 file / 1192 test verdi.
  Manuale Claude Docs: da aggiornare la sezione Task > Ricorrenza (etichette Fine/Tipo di giorno, anteprima).

## TASK: SOTTO-TASK E RICORRENZA NEL DETTAGLIO (RESTYLE) + SOTTO-TASK IN MODALE — VERDE, COMMITTATO (2026-10-06)

- Ricorrenza: `TaskRecurrenceSummary` (nuovo, `task-recurrence-summary.tsx`) = valore letto della riga in place.
  Spento: riquadro tratteggiato + hint. Acceso: velo `bg-primary/5`, eyebrow frequenza, frase
  `formatTaskRecurrenceRule` (invariata, la stessa del badge header), striscia giorni per `weekly`
  (`weekdayInitial` nuovo in `task-recurrence-format.ts`, Intl narrow), chip fine + "Solo giorni lavorativi".
- `TaskInlineField` ha `layout?: 'row' | 'block'` (default `row`): `block` = valore a tutta larghezza, label sr-only;
  usato solo dalla ricorrenza (niente piu' `RecordFieldList` in quella sezione). Label/pencil "Regola" invariati.
- Sotto-task: riga estratta in `task-subtask-row.tsx` (cerchio iniziale = Completa / spunta verde = Riapri, stessi
  aria-label; titolo barrato a 100%; assegnatari via `UserAvatarStack`; cestino visibile su hover/focus/touch).
  `task-subtask-progress.ts` (`isSubtaskDone`, `subtaskProgress`) alimenta la banda "N di M completati" + barra
  `overallProgress` (media arrotondata). Empty state tratteggiato con `subtaskPanel.emptyHint`.
- REQUIREMENT CHANGED (utente 2026-10-06): dal dettaglio task un sotto-task si apre SEMPRE in modale sopra il padre
  (`TaskDetailScreen` usa l'opener `forceMode: OPEN_MODE_MODAL` anche per `openView`; rimosso l'opener a
  preferenza). AC-085 della spec 0101 aggiornato; test `task-screens.test.tsx` aggiornato e dichiarato.
- i18n nuove: `tasks.detail.recurrenceCard.*`, `tasks.detail.subtaskPanel.{doneOf_*,overallProgress,emptyHint}`.
  Guida in-app IT/EN `tasks` (sezione `subtasks-and-recurrence`) aggiornata.
- Verifica: Vitest tasks/help/modules/i18n 107 file / 832 test verdi; `tsc -b --force` 0; ESLint 0. Screenshot
  Playwright light/dark/375px e clic reale sul sotto-task (Sheet aperta, URL del padre invariato).
- Da fare: manuale Claude Docs non accessibile in sessione (accesso negato) -> aggiornare a mano la sezione
  "Sotto-task, ricorrenza e modelli". Fuori scope segnalato: in dark il % rosso di `CompletionBar` ha contrasto basso.

## "INFORMAZIONI AGGIUNTIVE" COL LAYOUT NEI DETTAGLI (OFFERTA/COMMESSA/PRODOTTO) — VERDE, COMMITTATO (2026-10-06)

- I dettagli Offerta, Commessa e Prodotto ora mostrano gli attributi divisi nelle sezioni del layout configurato
  (spec 0062): nuovo `features/attributes/attribute-layout-view.tsx` (`AttributeLayoutView`). Stile = quello delle
  sezioni record sopra (`RecordField` etichetta/valore, hairline), ogni sezione con banda di intestazione
  (`bg-muted/50`, `highlighted` -> `bg-primary/10`, icona `Rows3`, titolo + descrizione), sezioni affiancate su 2
  colonne a `@2xl`; sezione con `columns >= 2` = larghezza piena e campi su 2 colonne (`width: full` attraversa).
  Niente collapse nel dettaglio (decisione utente: stile legacy QNet "info", sezioni sempre aperte).
- Condivisi: `attribute-layout-sections.ts` (`resolveLayoutSections`: ordine + "Altre informazioni", usato anche da
  `AttributeLayoutRenderer` del form), `attribute-value-display.tsx` (`AttributeValueDisplay`, formattazione per
  tipo + tabella; sostituisce le copie in quote/work-order detail).
- Contratto (additivo): `QuoteResource.attribute_view_layout` = `QuoteAttributeResolver::layout(View)` (fallback sul
  layout `all`); `attribute_layout` resta Edit per il form. Commessa usa gia' View, Prodotto gia' View.
  Prodotto: solo attributi valorizzati (regola AC-014 invariata), sezione unica "Attributi" a piena larghezza.
- Test: `attribute-layout-view.test.tsx` (7), casi in `quote-detail.test.tsx`, `work-order-detail.test.tsx`,
  `QuoteAttributeValuesTest` (+2). Guida in-app Prodotti IT/EN aggiornata (scheda: attributi per sezioni).
- Manuale Claude Docs: NON accessibile dal connettore (access denied) -> da aggiornare a mano la voce scheda
  Prodotto/Offerta ("valori degli attributi divisi nelle sezioni della categoria").
- Verifiche: Pest Quotes+WorkOrders+Products 735/735, Pint ok; Vitest attributes/products/quotes/work-orders 745/745,
  help 108/108, ESLint ok, `tsc -b --force` ok.

## COMMESSE: "SOCIETA' E SEDI" NEL DETTAGLIO — VERDE, COMMITTATO (2026-10-06)

- Dettaglio commessa: nuova sezione "Societa' e sedi" / "Company and sites" (dopo "Contratto e righe prodotto"),
  stessi tre campi e link del dettaglio Contratto: Societa' (`/companies/{id}`), Sede (`/company-sites/{id}`),
  Sede operativa (`/operational-sites/{id}`, label = indirizzo). `DetailEmpty` se null.
- Contratto API (additivo): `WorkOrderResource` espone `company {id,name=denomination}`, `company_site {id,name}`,
  `operational_site {id,label}` (`OperationalSiteLabel::summarize`), proiettati LIVE da `quote` come fa
  `ContractResource` (il contratto non ha colonne proprie). `WorkOrderService::DETAIL_RELATIONS` + `quote.company`,
  `quote.companySite`, `quote.operationalSite.addresses.city`. FE: campi opzionali in `WorkOrderDetail` (come `registry`),
  chiavi i18n `workOrders.detail.{company,companySite,operationalSite}` + `sections.company`.
- Test: 2 casi in `WorkOrderContractSummaryTest`, 2 in `work-order-detail.test.tsx`.
- Dato reale commessa 95: contratto 100, offerta senza Societa'/Sede (vuote), Sede operativa id 25.
- Manuale: guida in-app Commesse ancora "in fase di sviluppo" -> nessun impatto.
- Verifiche: Pest WorkOrders+Contracts 321/321, Pint ok; Vitest work-orders 225/225, ESLint ok, `tsc -b --force` ok.

## TASK — PERMESSI CAMPO DEL DETTAGLIO IN PLACE (spec 0195 D-6a/b/c) — VERDE, NON COMMITTATO (2026-10-06)

- Audit (sonda Pest, poi rimossa): la matrice per ruolo arrivava gia' al dettaglio, ma (1) l'osservatore con
  `tasks.update` riceveva 6 campi editabili + `change_status` e la PATCH dava 403; (2) su task congelato tutti i 27
  campi erano editabili e la PATCH dava 422; (3) la cascata anagrafica falliva se referente/opportunita'/lead erano
  bloccati; (4) i campi nascosti restavano in header/KPI/righe di sola lettura.
- Backend: `TasksAuthorization::actorMayWrite()` su un task ESISTENTE (`exists`) = `$actor->can('update', $task)`
  (Policy); il ceiling aggiunge `TaskWriteLock::isStructurallyLocked()` (nuovo, pubblico, riusato da
  `assertStructuralWriteAllowed`) -> solo `OPERATIVE_KEYS` editabili. `new Task` della griglia e create invariati.
  `EnforcesFieldPermissions::fieldNotEditableMessage()` (hook nuovo, default 'field not editable');
  `UpdateTaskRequest` lo sovrascrive col messaggio frozen e salta il controllo campi se la Policy rifiuta (403).
  `TaskWriteLock::STRUCTURAL_WRITE_MESSAGE` ora public.
- Frontend: header/KPI/data completamento/feedback chiusura filtrati per `field(x).visible`; nuovo
  `task-inline-cascade.ts` (`useTaskCascadeEditable`) passato come `canEdit` a anagrafica/commessa/opportunita'/padre.
- Test: nuovo `TaskDetailFieldPermissionsTest` (6), `task-inline-cascade.test.ts` (6),
  `task-detail-field-permissions.test.tsx` (5). REQUIREMENT CHANGED dichiarato in `TaskMetaTest` AC-054 (fixture:
  l'attore e' il creatore, altrimenti la Policy da' 403 prima del 422 di matrice).
- Verifica: pest completo 9283 (1 fallito -> AC-054 corretto e rieseguito verde); vitest 888 file / 6746 test verdi
  (1 errore non gestito "window is not defined" da `request-work-panel-transfer.test.tsx`, verde isolato 2/2,
  preesistente); `tsc -b --force` 0; eslint pulito sui file toccati; pint ok. Guida in-app tasks IT/EN aggiornata.
- Clic fuori (spec 0195 D-2a, NON COMMITTATO): `use-outside-pointer-dismiss.ts` (pointerdown su `document` +
  `onPointerDownCapture` della riga: le pressioni in portal del campo bubblano nell'albero React e contano come
  "dentro"); `TaskInlineEdit.dismiss` = `cancel` nel dettaglio, `save` ("Fatto") nella bozza di creazione; inattivo
  durante `isSaving`. Test: hook (3), dettaglio (1), bozza (1). Guida IT/EN aggiornata. Vitest tasks/help/modules
  83 file / 632 test verdi, tsc 0, eslint pulito.
- Da fare: manuale Claude Docs (doc non condiviso con la sessione), sezione Task "Modificare un task" (matite e
  permessi, campi nascosti, cascate, clic fuori = annulla; in creazione clic fuori = Fatto).
  Non verificato a occhio nel browser. Fuori scope: l'API restituisce i valori dei campi nascosti (UI-only).

## TASK — DETTAGLIO EDITABILE IN PLACE + CREAZIONE COL LAYOUT DEL DETTAGLIO (spec 0195) — VERDE, NON COMMITTATO (2026-10-06)

- Niente piu' pagina edit: registry `generateEditRoute: false` (nuovo flag `ModuleRegistryEntry`, default true) =>
  nessuna rotta `tasks/:id/edit`, `ModuleDetailPage`/`useModuleOpener` non passano `onEdit`. `TaskFormScreen` senza
  ramo edit (ritorna null), `TaskEditScreen` rimosso, header del dettaglio senza "Modifica".
- Dettaglio (`task-detail.tsx`, layout invariato) = righe `TaskInlineField` (matita / clic sul valore -> editor del
  campo + Salva/Annulla, Esc annulla, Invio salva negli input). Un editor alla volta. Stato `useTaskInlineEdit`
  (sopra `useTaskForm` edit): Salva = `handleSubmit` -> PATCH diff (`buildUpdatePayload`) col solo campo cambiato +
  cascate (anagrafica -> referente/opportunita'/lead). Sezioni in `task-detail-sections.tsx` (Dati, Classificazione,
  Persone, Pianificazione) e `task-detail-link-sections.tsx` (Record collegati, Chiusura, Ricorrenza). Sola lettura:
  creatore, data completamento, feedback chiusura, bloccato. Stato editabile solo con `canAction('change_status')`,
  referente solo con anagrafica.
- `useTaskForm`: `values` + `resetOptions.keepDirtyValues` in edit (riallinea il form al task in cache dopo azioni di
  dominio senza perdere il campo aperto), `form.reset(editDefaults(saved))` dopo il PATCH, `statusMeta` ri-inizializzato
  quando cambia lo stato persistito, nuovo `clearServerError`, export `TaskFormState`.
- Nuovo `ModuleDetailScreenProps.onChanged` (cablato da `useModuleOpener` sul suo `onSaved`): il pannello modale
  aggiorna griglia/board a ogni salvataggio inline senza chiudersi.
- Creazione/duplica (`TaskFormBody`, solo `TaskCreateFormMode`) = REPLICA del dettaglio: RecordCanvas, card con
  `TaskFormHeader` (monogramma + titolo live + Annulla/Salva) e `TaskStatsStrip` live, righe CHIUSE di default
  (`task-create-sections.tsx` + `task-create-link-sections.tsx`, display in `task-create-displays.tsx` via
  `useForSelectLabels`, stessa cache dei picker) che si aprono col controllo del campo; stato `useTaskDraftEdit`
  ("Fatto" = tiene + `trigger` del campo, "Ripristina" = reset allo snapshot dell'apertura). Salva valida tutto,
  errori mostrati sotto le righe chiuse (`TaskInlineField` legge `getFieldState`). Card laterale con tab Allegati,
  Sotto-task come `RecordSection`. Rimossi `task-form-summary.tsx` e i wrapper `Task*Section`.
- Popup di uscita dalla creazione (sempre): `useFormLeaveGuard` (features/modules) = Annulla + `useSheetCloseGuard`
  (X/overlay/Esc del pannello, `SheetCloseGuardContext` in `useModuleOpener`) + `NavigationLeaveBlocker`
  (`useBlocker`, montato solo sotto data router: i test con MemoryRouter non lo hanno) + `beforeunload`;
  `allowLeave()` prima di `onSuccess`. Cablato in `TaskFormScreen`.
- Editor inline (NON COMMITTATO, dopo b388f49d): riga aperta = pannello evidenziato (`EDITING_ROW_CLASS`: bordo/alone
  `ring`, velo `bg-muted/40`, etichetta in evidenza), Annulla/Salva `size="xs"` affiancati senza a capo, nessun
  testo di scorciatoie (richiesta utente). `MetaFieldRowContext` (features/authorization): il `MetaField` del campo
  della riga nasconde la propria etichetta (sr-only) e mette l'hint accanto al controllo; i sotto-campi (ricorrenza,
  fase, notifiche) restano etichettati. Titolo con input di misura standard.
- FIX (NON COMMITTATO): `updateTask` ora restituisce `TaskDetailWithPermissions` (`withPermissions`, il backend
  risponde `okWithPermissions`): il salvataggio inline scriveva in cache un task senza `permissions` e il dettaglio
  crashava ("Cannot read properties of undefined (reading 'actions')"). Test in `api.test.ts`.
- Regola generale persone (NON COMMITTATO): `components/user-avatar-stack.tsx` (`UserAvatarStack`, max 5 avatar +
  chip "+N" con hover card dei restanti) estratto da `UserStackCell` (griglia) e usato da `TaskPeopleList` oltre
  3 persone (fino a 3 resta la lista con i nomi). Candidati da valutare: chip Responsabili nella scheda utente.
- FIX stack avatar (NON COMMITTATO): gli avatar si fondevano uno sull'altro (Assegnatari/Osservatori del dettaglio e
  celle multi-utente della griglia). `AvatarGroup` mette il ring solo sui figli DIRETTI `[data-slot=avatar]`, ma lo
  stack avvolge ogni avatar nel trigger della hover card -> nessun separatore. Ora `STACK_SEPARATOR_CLASS`
  (`relative rounded-full ring-2 ring-card`) sul trigger: `relative` serve perche' l'avatar e' posizionato e
  altrimenti dipinge sopra il ring del trigger precedente; `ring-card` anche sul chip "+N" (entrambi gli host stanno
  su `--card`). Verificato nel browser (Playwright headless). Utente: nessun altro campo del dettaglio da rendere
  editabile (creatore, bloccato, data completamento, feedback chiusura restano sola lettura).
- FIX editor griglia Assegnatari/Osservatori (NON COMMITTATO): `MultiSelectCellEditor` mostrava "Questa riga non ha
  ancora un ambito" su ogni colonna SENZA `relation.scope` (bloccava come "scope vuoto"). Ora il blocco/sblocco
  d'ambito vale solo se la colonna dichiara `scope`; altrimenti catalogo completo, nessun footer. Vale anche per gli
  attributi multi-relazione di request-management. + avatar per `users` (`showAvatar` dal registry) + nuovo contratto
  `relation.exclude` (id colonne riga da non offrire; emesso da `ResolvesColumnConfig` solo se dichiarato):
  watchers dei Task = `['creator','requester','assignees']` (come il picker del dettaglio, opzione gia' scelta resta
  visibile). Helper `resolveExcludedIds` in `multi-select-scope.ts`. Test: `TaskTableTest` + editor/registry.
- Data fine "come in commesse" (NON COMMITTATO): `components/due-date-chip.tsx` (`DueDateChip`, chip rosso + triangolo
  scaduto / tinta + calendario oggi, con proprio `TooltipProvider`) estratto dalle due copie (board commessa,
  `TaskKanbanDueChip`). Regola `taskEndDateState` (`task-due-state.ts`): non chiuso && end_date < oggi = scaduto,
  == oggi = oggi; `TaskEndDate`/`TaskEndDateCell` (`task-end-date.tsx`) in griglia (`end_date`), riga Pianificazione e
  riquadro "Data fine" del dettaglio (`TaskStatsStrip.statusGroup` opzionale; la creazione non lo passa).
- Toggle Completa nella cella Titolo (NON COMMITTATO): `TaskTitleCell` = icona `CheckCircle2` prima del titolo:
  completato (`group closed_positive`) = verde piena (`fill-success text-card`); riga con azione `complete` = bottone
  (cursor-pointer, hover verde + fill tenue + scale, active press, motion-safe) che apre il dialogo Completa via
  `TaskCompleteRowContext` (provider in `TasksTable`, valore `completeRow` di `useTaskDomainRowActions`); altrimenti
  icona sbiadita. Listener NATIVI (click/dblclick/Enter-Spazio con stopPropagation): con `singleClickEdit` AG Grid
  aprirebbe l'editor del titolo prima del onClick React. Vista Sintetica: `buildTreeDataGridOptions` ora usa il
  renderer della colonna sostituita come `innerRenderer` della colonna di gruppo.
- Verifica: vitest completo 6767/6768 (l'unico rosso, `task-screens.test.tsx` subtask modal, passa isolato: flaky
  sotto carico); tasks+work-orders+components 1208 verdi; Pest Tasks 633, Table 289, RequestManagement 838, Leads 156;
  tsc -b 0; eslint/pint puliti. Verificato in browser (Playwright headless): editor Assegnatari con 27 utenti+avatar,
  chip rossi, clic icona -> dialogo Completa senza editor, icona piena sui completati. Guida in-app IT/EN aggiornata
  (`list-editing-and-bulk`). Manuale Claude Docs: da aggiornare stessa sezione.
- ATTENZIONE RHF: `resetOptions` di `useForm` si fonde in OGNI `reset` -> in edit `keepDirtyValues: true` serve solo
  al re-sync `values`; ogni reset che deve scartare passa `keepDirtyValues: false` (test di regressione in
  `task-detail-inline-edit.test.tsx`: un annullo non finisce nel PATCH successivo).
- i18n: + `tasks.detail.inlineEdit.{edit,save,cancel,apply,revert}`, `tasks.detail.recurrenceRule`,
  `tasks.form.leaveConfirm.*`; rimosse le chiavi orfane (anche `form.header.*`, `form.sections.subtasks.title`)
  (`form.summary.*` tranne `recurrenceOff`, `form.header.status`, `form.editTitle/editSubtitle`, titoli/descrizioni
  delle sezioni del vecchio form). Guida in-app `tasks` IT/EN: nuova sezione `editing-a-task`, tabella creazione.
- Test cambiati per requisito (dichiarati nei file): chiusura sempre visibile, regola ricorrenza x2, change_status =
  nessuna matita, blocco ricorrenza verificato in creazione; casi edit migrati in `task-detail-editors.test.tsx`;
  nuovi `task-detail-inline-edit.test.tsx`, helper `task-detail-test-helpers.tsx`, test rotte tasks in
  `module-routes.test.tsx`.
- Verifica: `tsc -b --force` 0; eslint pulito sui file toccati (2 errori preesistenti in `quotes/column-renderers.tsx`
  e `registry-form-metadata.test.tsx`, non toccati); vitest completo 885 file / 6732 test verdi. NON verificato a
  occhio nel browser.
- Da fare: manuale Claude Docs (doc non accessibile da questa sessione) — sezioni Task: modifica dal dettaglio,
  creazione. Chiavi i18n orfane preesistenti: `tasks.detail.status`, `detail.minutesValue`, `form.completionDate`,
  `form.closureFeedback*`. HANDOFF ~400 KB: va archiviato.

## UTENTE "ASSEGNABILE" (spec 0194) — VERDE, COMMITTATO (2026-10-05)

- Colonna `employment_profiles.is_assignable` (default true, migrazione `2026_10_05_100000_...`; rollback
  `QuoteWorkflowMigrationTest` ora `--step 126`). API `employment.is_assignable`, field permission propria (chiavi
  `employment.*` da 14 a 15). Scrittura tri-state: chiave assente = invariata (`EmploymentData::$isAssignable` ?bool,
  NON come `is_manager`).
- Enforcement (D-1, solo assegnazione operatori): `LeadOperatorDistributor::operatorIdsBySite` (pool Sede di
  `AssignmentCandidates`), `OperatorCompetence::configuredProfiles` (solo assegnabili), `UserService::forSelect` ramo
  `operational_site_id`, `RequestAttributionWriter::isSwitchedOff` (offerta senza Sede). Task/commesse/team invariati.
- Seed (D-4/D-5): `OperatorRoster::FUNCTION_WIDE_COMPETENCE` (Gervasio, Distico, Crispo, Ascione -> funzione APL,
  categoria null, assegnabili); righe con categorie -> assegnabili; righe senza -> spente + riga `UNASSIGNABLE_FUNCTION`
  "Formazione" (match case-insensitive: in DB e' "FORMAZIONE") categoria null. `TestUsersSeeder`: profilo spento.
  `QualificaStaffSeeder`: profilo spento creato solo se manca (resta create-only sui profili esistenti).
- FE: switch in `user-form-assignment-section.tsx`, blocker `'disabled'` in `summarizeAssignment`, i18n IT/EN, guida
  `users` IT/EN. Factory `EmploymentProfileFactory::notAssignable()`.
- Verifica: `composer test` 9276 pass/1 skip; vitest completo 6719 pass (fe teammate) + users/help 222 rieseguiti;
  `tsc -b --force` 0; pint/eslint puliti. Test cambiati per requisito: chiavi employment 14->15 (FieldCatalogue,
  Meta, UserCompetenceScope), step migrazione, seeder APL/Baldi, ReportsTo (profilo gia' creato), fixture FE.
- Da fare: in ambiente eseguire `php artisan migrate` e `db:seed --class=QualificaOperatorSeeder`,
  `TestUsersSeeder`, `QualificaStaffSeeder`. Manuale Claude Docs non condiviso con la sessione: aggiornare Utenti >
  Configurazione assegnazione (nuova impostazione Assegnabile, tre condizioni).

## SCHEDE CATEGORIA: DEFAULT DALLA COMPETENZA (spec 0193) — VERDE, NON COMMITTATO (2026-10-05)

- Gestione Richieste + Gestione Iscritti: senza riga in `user_category_tab_preferences`, la GET
  `/api/{module}/category-tab-preferences` propone come preferite le categorie dello strip coperte dalla competenza
  dell'utente, con `show_only_favorites=true`; nuovo campo `is_default` (true = nessuna riga salvata). La GET non
  scrive mai; il primo PUT crea la riga e da li' vale solo la scelta dell'utente (D-1, default non dinamico).
  Jolly (`covers_all_product_categories`), senza competenza o competenza fuori strip: `[]`, false (D-2).
- Backend: `OperatorCompetence::coveredCategoryIdsFor(int $userId, array $categoryIds): array` (solo il profilo
  dell'utente, riusa `CompetenceProfile::covers()`; `profileOf()` condiviso con `configuredProfiles()`; [] per
  jolly/senza profilo); Action `App\RequestManagement\ResolveDefaultCategoryTabPreference::handle(User, RequestModule)`
  (strip da `RequestCategoryTabsResolver` ∩ competenza, crescente); `CategoryTabPreferencesController::preferenceOf`
  la usa solo se `! exists`; `CategoryTabPreferencesResource` espone `is_default = ! exists`.
- Frontend: `CategoryTabPreferences.is_default` + `CategoryTabPreferencesPayload` (il PUT invia solo i due campi);
  `useCategoryTabPreferences` (ottimistico con `is_default:false`); prop `favoritesAreDefault` table → tabs → picker;
  avviso i18n `requestManagement.categoryTabs.favoritesDefaultHint` (IT/EN). Guide in-app `request-management` IT/EN
  aggiornate; `enrollee-management` non documenta lo strip (non toccata).
- Verifica (verifier): `composer test` 9266 pass/1 skip; vitest 6714 pass; `tsc -b --force` 0; pint/eslint puliti.
- DA FARE: manuale Claude Docs (accesso negato a questa sessione: aggiornarlo a mano, sezione schede/preferite di
  Gestione Richieste e Iscritti). Secondo passo: stesso default per Statistiche richieste (/request-statistics,
  strip per ramo reportable, chiavi stringa) — spec separata.

## FIX RIGHE OFFERTA AL CAMBIO LINEA DI PRODOTTO (Gestione Richieste) — VERDE, NON COMMITTATO (2026-10-05)

- Bug: in creazione (`/request-management/new`) e in lavorazione, sostituendo la linea di prodotto la riga d'offerta
  restava sul prodotto della categoria tolta (il picker offriva gia' solo i prodotti della nuova); al salvataggio
  `OpportunityProductLineCoverage` rimetteva la vecchia categoria.
- Nuovo `frontend/src/features/request-management/use-offer-lines-coherence.ts`: `resetUncoveredOfferRows` (pura) +
  `useOfferLinesCoherence(control)`; stesso schema di `useProductsOfInterestCoherence` (funzione chiamata
  nell'`onChange` del `ProductLinesField`, non un effect). Categoria del prodotto da `meta.category_id` della query
  for-select by-ids; riga scoperta -> `createEmptyLineRow()` in place (l'autofill mono-prodotto la riempie se la nuova
  categoria ha un solo prodotto); prodotto non ancora risolto = tenuto; `offer_lines` non scrivibile = nessuna azione.
  Toast `requestManagement.offerLines.prunedNotice` (IT/EN).
- Cablato in `request-product-lines-section.tsx` (lavorazione) e `request-create-form.tsx` (creazione). Harness di
  `product-lines-wiring-parity.test.tsx` aggiornato (QueryClient + `offer_lines: []`), asserzioni invariate.
- Guida in-app `request-management` IT/EN aggiornata (voce "Linee di prodotto"). Manuale Claude Docs NON aggiornato
  (doc non condiviso con la sessione): sezione "Lavorare una richiesta" / "Creare una nuova richiesta".
- Tabella (decisione utente 2026-10-05, "cancella le righe"): `RequestManagementService::updateWork`, se la classificazione
  cambia SENZA `offer_lines` nel payload (cella "Categoria prodotto"), chiama `RequestOfferLineWriter::dropUncovered`:
  riscrive via `apply()` solo le righe REVENUE ancora coperte (ids invariati -> provvigioni/descrizione aggiuntiva
  conservate), le altre vengono eliminate. Le righe COSTO non sono toccate. `OfferLinesColumn::project` ora espone anche
  `category_id`; `product-lines-cell-editor.tsx` mostra l'avviso `table.productLinesEditor.droppedOfferLines` prima del
  commit. Test: `RequestManagementProductLinesOfferPruneTest.php`. Iscritti usa lo stesso pannello/tabella: coperto.
- Nota: vale anche per il pannello se arriva `product_lines` senza `offer_lines` (es. attore senza scrittura sulle righe
  offerta): le righe scoperte vengono eliminate lo stesso, come effetto della classificazione.

## APL MANUALE + "APL OLD" DALLA MIGRAZIONE — VERDE, COMMITTATO e5236a2c + FIX NON COMMITTATO (2026-10-05)

- FIX (non committato, richiesta utente: "APL old" compariva in "Categoria padre" di /request-management/new, che per
  spec 0132 D-1 elenca TUTTE le radici): "APL old" NON e' piu' radice, sta sotto Consulenza come le categorie di
  FORMAZIONE OLD (scelta utente fra "sotto Consulenza" e "radice nascosta nel select"; D-1 resta valida, nessun cambio
  FE). `ProductCategoriesSource`: tolta la forzatura a radice, il gemello va sotto il suo padre legacy;
  `QualificaLegacyImportSeeder`: tolta l'esclusione dal nesting. `LegacyAplBranch::LEGACY_ROOT` -> `LEGACY_BRANCH`,
  il nodo e' trovato per nome + old_id in qualunque posizione (`legacyBranchTop()`, `isWithin()`).
- FORMAZIONE OLD non selezionabile (decisione utente 2026-10-05): `CategoryBusinessFunctionLinker::closeRedirected(
  $createdIds)` (Step 2 di `afterImport`, accanto a `closeLegacyBranch`) -> ogni categoria CREATA nella run la cui
  funzione EFFETTIVA e' una sostitutiva di `REDIRECTED_FUNCTIONS` (FORMAZIONE OLD, APL OLD) nasce `is_selectable=false`;
  adottate e altre funzioni invariate. Prodotti senza sostituto sotto "APL old": restano li' (decisione utente).
  Test: `ProductCategoriesSourceImportTest` FORMAZIONE OLD (+3 asserzioni, nuova riga "Bandi" su altra funzione) e
  adozione. Suite completa 9266 passed / 1 skipped. Sul DB locale le categorie gia' importate (es. "ALFA FORMAZIONE")
  NON cambiano: vale dal prossimo import pulito.
- DB locale `qnet2`: "APL old" (id 77) e' ancora RADICE dall'import di prima; il re-seed non lo sposta (lo snapshot
  `$staticRootIds` lo tratta come radice gia' esistente) -> serve un update una tantum di `parent_id` su Consulenza.

- Decisioni utente (AskUserQuestion 2026-10-05): il ramo APL legacy NON e' piu' adottato da quello manuale; diventa
  la categoria "APL old" + funzione aziendale "APL OLD" (come FORMAZIONE OLD); APL manuale = regole IDENTICHE
  a Formazione (contratti compresi); TUTTI i prodotti del ramo legacy sulle nuove categorie; nessuna conversione
  dei DB gia' importati ("come fa con Formazione": vale a ogni import).
- Nuovo `app/Migrations/Support/LegacyAplBranch.php`: `manualNodeNamed()` (nodo del ramo manuale "APL" con quel nome,
  confronto case-insensitive in PHP — SQLite e MySQL si comportano uguale), `legacyName()` (suffisso " old"),
  `LEGACY_ROOT` = "APL old", `PRODUCT_CATEGORIES` (Formazione Apprendistato -> Apprendistato, Tirocini extracurriculari
  privati -> Tirocinio, Orientamento Specialistico -> Orientamento specialistico), `productCategoryFor()`.
- `ProductCategoriesSource::processRow`: un record legacy con nome di un nodo del ramo APL manuale non viene adottato,
  viene creato come "<nome> old" (solo se collide: "APL old", "Orientamento Specialistico old"; gli altri tengono il
  nome). Warning "Legacy twin ..." nel report.
- `ProductsSource`: categoria risolta -> `LegacyAplBranch::productCategoryFor` (nodo del ramo "APL old" -> categoria
  manuale; senza sostituto resta sul nodo legacy con warning "no manual replacement", deciso cosi' dall'utente).
- "APL old" NON selezionabile (richiesta utente successiva, stesso giorno): `ProductCategoriesSource::afterImport`
  = Step 1 `relinkDetached()` + Step 2 `LegacyAplBranch::closeLegacyBranch($createdIds)` -> radice e TUTTO il ramo
  `is_selectable=false`, solo sui nodi creati in QUELLA run (una riapertura a mano sopravvive al re-import).
  Nota: per FORMAZIONE OLD il codice non forza nulla, `is_selectable` arriva dal legacy.
- `CategoryBusinessFunctionLinker::REDIRECTED_FUNCTIONS` + `'APL' => 'APL OLD'`.
- Seed: categorie rinominate `Apprendistato` / `Tirocinio` / `Orientamento specialistico` (costanti CATEGORY dei tre
  cataloghi APL, `CATALOG`, `REPORTABLE_CATEGORIES`, `SINGLE_OFFER_CATEGORIES` -> anche il prodotto seedato si chiama
  "Orientamento specialistico"). `CatalogRootRules::TRAINING_RULES` condivise da Formazione e APL (single, una offerta,
  `generates_contract=false`, riga semplificata, etichette G.A. Tutor/Operatore/Partner commerciale/Segnalatore).
- Test: nuovo `Migration/LegacyAplBranchImportTest.php` (3), `ProductsSourceImportTest` (+1), `QualificaLegacyImportSeederTest`
  end-to-end riscritto (Formazione adottata, APL old sotto Consulenza su APL OLD, prodotti sulle nuove categorie, mappa legata al
  catalogo reale), `QualificaCatalogRootRulesTest` (+1 APL = Formazione). Requisito cambiato: i test generici di adozione
  usavano "APL" come esempio -> ora "Consulenza"/"Presa Appuntamenti"; liste nomi aggiornate in 6 test seed.
  Suite completa 9254 passed / 1 skipped, Pint pulito.
- Attenzione: un DB gia' importato con l'adozione vecchia NON viene convertito; un re-seed li' crea "Apprendistato" e
  "Tirocinio" accanto ai nodi vecchi. Strada pulita: migrate:fresh + QualificaProductionDataSeeder.
- Manuale: guide in-app nessun impatto (non citano APL); manuale Claude Docs non condiviso con la sessione — verificare
  se cita i nomi delle categorie APL o che APL genera contratti.

## ALLEGATI .ZIP AMMESSI — VERDE, COMMITTATO (2026-10-05)

- `config/attachments.php` `allowed_mime_types` + `application/zip`. Unica allow-list condivisa: vale per
  `POST /api/attachments` (tutte le sezioni Documenti), allegati email Commessa (`StoreWorkOrderEmailAttachmentRequest`)
  e import `DocumentBundlesSource`. La regola `mimetypes:` valida il MIME rilevato dal CONTENUTO (finfo), quindi un
  .zip vero passa e un file rinominato no; il `mime_type` salvato resta quello del client (`getClientMimeType()`, invariato).
- Test: `AttachmentCrudTest` "201 for a real .zip archive" (zip reale via `ZipArchive`). FE invariato: `attachment-tile.tsx`
  ha gia' l'icona archivio per `application/zip`.
- Manuale: guide in-app nessun impatto (non elencano i formati allegati); manuale Claude Docs non accessibile dalla
  sessione — verificare se elenca i formati ammessi.

## SPEC 0192 REDESIGN STATISTICHE GESTIONE RICHIESTE — VERDE, NON COMMITTATO (2026-10-05)

- Spec `docs/specs/0192-request-statistics-redesign.xml` (D-1..D-9 utente). Solo FE, endpoint/numeri/export invariati.
- `/request-statistics`: toolbar con preset periodo (`dashboard-period-presets.ts`, preset DERIVATO dalle date, "Personalizzato"
  apre il pannello filtri), tab Panoramica + una per categoria (`use-request-dashboard-tab.ts`, localStorage
  `{module}.dashboard-tab`, `OVERVIEW_TAB`), KPI con contatore animato (`hooks/use-count-up.ts`, off senza matchMedia o
  con reduced-motion) e delta vs periodo precedente (`useRequestDashboardPrevious`: seconda chiamata allo STESSO endpoint,
  solo con periodo chiuso, niente placeholder; `dashboard-comparison.ts`, `unhandled_*` invertiti), ripartizione per
  categoria per RANGO (top 4 colori + "Altre", `dashboard-overview.ts::categoryShares`), heatmap normalizzata per colonna,
  classifica operatori = pivot dei grafici `operator` (`dashboard-leaderboard.ts`; "Non assegnato" riconosciuto dalla
  label dell'operatore `UNASSIGNED_OPERATOR_KEY`, sempre ultimo, mai sul podio). Query principale `keepPreviousData`.
- Eliminati `request-dashboard-section.tsx` e `use-request-dashboard-collapse.ts` (espandi/comprimi non esiste piu').
  Chiavi i18n rimosse: `expandAll`, `collapseAll`, `tilesTitle`, `chartsTitle`.
- Scroller orizzontali con `w-0 min-w-full` (tab, heatmap, classifica): senza, 19 categorie allargavano la pagina.
- Richieste utente successive (stesso giorno): (1) hover classifica uniforme — la cella sticky "Operatore" ha fondo
  opaco, su hover prende `color-mix(in oklab, --muted 40%, --card)` (oklab: in oklch la card acromatica dava una
  tinta rosa); (2) valori colorati per indicatore: `INDICATOR_COLORS`/`indicatorTint` in `dashboard-indicator-meta.ts`
  (pastiglia tinta 18% + testo foreground, mai testo nel colore serie; zeri grigi; barre piene); (3) tab "come in
  Gestione Richieste": `request-dashboard-category-strip.tsx` riusa `useCategoryTabFit` (tipo allargato a `{ id:
  number }`, id = POSIZIONE della categoria) e `CategoryMoreButton` — "Altre (N)" con ricerca, categoria aperta sempre
  in riga. Niente preferiti: l'endpoint `category-tab-preferences` richiede `request-management.viewAny`.
- Test: `dashboard-*.test.ts` (logica), `request-dashboard-tabs.test.tsx` (redesign), `request-dashboard.test.tsx` e
  `-filter-bar.test.tsx` adeguati (requisito cambiato: niente collapse; il periodo chiuso fa 2 chiamate), fixture in
  `request-dashboard-fixtures.ts`. Suite FE completa 6702 verde, tsc pulito, ESLint pulito sui file toccati (2 errori
  preesistenti altrove: `quotes/column-renderers.tsx`, `registries/registry-form-metadata.test.tsx`).
- Verificato a schermo (Playwright, 1440 chiaro/scuro e 375): nessuno scroll orizzontale di pagina.
- Guida in-app `request-statistics` IT/EN aggiornata. Da fare: manuale Claude Docs (doc non condiviso con la sessione).

## CATALOGO: "ORIENTAMENTO SPECIALISTICO" (CAMPI OFFERTA + STATI PRATICA) + SEZIONI SEED BIANCHE — VERDE, COMMITTATO (2026-10-05)

- Fonte: PDF "Campi Misure APL" (scheda Orientamento/SFL GOL). Decisione utente 2026-10-05: torna la scheda del
  2026-10-01 (mai committata, era stata sostituita dai Tirocini), ACCANTO a Tirocini e Apprendistato. Stati: "se tra le
  sottocategorie APL sono gli stessi unisci, altrimenti ognuno il proprio" -> liste diverse, quindi ognuna la sua.
- Registro unico `QualificaCatalog/AplPracticeCatalogue` (QUOTE_ATTRIBUTES, FORMS, STATUS_SECTIONS, WORKFLOWS) per le 3
  pratiche APL: lo leggono `QualificaCatalogSeeder`, `QualificaQuoteLayoutSeeder::OWN_FORMS`, `WorkflowStatusCatalogue`.
  Nuova pratica APL = una riga per mappa qui + barriera in `CategoryInheritanceRules` + nodo in `CATALOG`. Ha liberato
  righe: `WorkflowStatusCatalogue` 497, `QualificaCatalogSeeder` 499 (ancora al limite: prossima aggiunta -> split).
- Campi (`AplOrientationAttributeCatalogue`, categoria esistente "Orientamento Specialistico", barriera
  `inherits_quote_attributes=false`): `sfl_renewal_status` (Da rinnovare/Rinnovato), `decree_status`, `deliverable_policies`
  (integer 1-4), `last_active_policy_date`, `orientation_measure` (Presa in carico/Orientamento/Accompagnamento),
  `sfl_months_received` (integer 0-12), `practice_end_date` (ora costante condivisa
  `AplInternshipAttributeCatalogue::PRACTICE_END_DATE`), `reporting_id`, `decree_id`, `orientation_convocation_date`,
  `orientation_intake_date`, `orientation_session_date`, `orientation_job_support_{1,2,3}_date`. Sezioni Testata / Dati
  pratica / Percorso.
- NON campi: Utente = anagrafica; Operatore/Commerciale/Segnalatore = campi offerta; SGA/Commessa/Valore = commessa.
  "Ultima politica" (testata) e "Ultima politica attiva" = un solo campo data. Automazioni del PDF (data fine calcolata,
  rinnovo SFL mensile) NON implementate: valori manuali. Da proporre come follow-up.
- Stati (`AplOrientationWorkflowStatusCatalogue`, sezione `apl_orientation`, categoria esatta, vince sul ramo APL): Da
  convocare (open) / Convocato, Presa in carico, Monitoraggio SFL (pending) / Fine pratica (won) / Perso (lost). Nessun
  "Non risponde". IMPATTO: la lista ramo "APL" (Nuovo Contatto ... Assegnato) non si applica piu' a nessuna categoria
  seedata; per l'orientamento le colonne report APL (nuovi contatti, invio presa in carico) restano senza dati.
- Sezioni seed tutte BIANCHE (decisione utente 2026-10-05): Tirocini "Stato pratica" e Apprendistato "Dati pratica" da
  `Highlighted` a `Default`. `QualificaQuoteLayoutSeeder::PREVIOUSLY_HIGHLIGHTED` riconosce il blob odierno con quelle
  sezioni grigie e lo ricompone; un layout modificato a mano in altro modo resta intatto. `ECampusAttributeCatalogue::
  PREVIOUS_SECTIONS` resta Highlighted (storico, serve al riconoscimento).
- Test: nuovo `Products/QualificaAplOrientationCatalogueTest` (4, incluso grigio->bianco e layout a mano preservato);
  requisito cambiato: rimosso "Orientamento senza campi propri" (InternshipTest), varianti `default`, layout quote 11->12,
  `QUOTE_LAYOUT_OWN_CATEGORIES` +Orientamento, "Non risponde" esclude `AplPracticeCatalogue::WORKFLOWS`. Suite completa
  verde (9249 passed, 1 skipped), Pint pulito. Seed applicato al DB locale `qnet2` (verificato).
- Da fare: rieseguire `QualificaProductionDataSeeder` su staging/prod. Su DB importato "Orientamento Specialistico" puo'
  avere un layout legacy: il seeder non lo tocca (dato utente) -> i nuovi campi finirebbero in "altre informazioni".
- Manuale: nessun impatto (dati di seed; guide in-app e Manuale QNet non elencano campi/stati per categoria).

## SPEC 0191 PREZZO/COSTO PRODOTTO PER UTILIZZO — VERDE, NON COMMITTATO (2026-10-05)

- Spec `docs/specs/0191-product-pricing-by-usage.xml` (approved, D-1..D-9 utente). Prezzo obbligatorio/visibile sse
  `SALE`, Costo sse `COST`, margine sse entrambi. Togliere un utilizzo NON cancella il valore (nascosto, non validato).
- BE: regola unica nel trait `Http/Requests/Concerns/RequiresPricingForUsages` (Store+Update, `withValidator`), sui
  valori EFFETTIVI (usages inviati ?? salvati ?? `[SALE]`; valore inviato ?? salvato) -> 422 `price`/`cost`. Regole base
  `nullable|numeric`. `CreateProductData` `?float cost/price`. Sorgenti migrazione legacy invariate.
- FE: unica regola in `features/products/product-pricing-visibility.ts::productPricingVisibility` (schema, sezione
  prezzi, riepilogo, dettaglio). `CreateProductPayload.cost/price: number | null`; update a diff. Griglia invariata
  (mostra il valore salvato anche se non pertinente, D-7). Nessuna bonifica dati, nessuna migrazione.
- Test: `ProductPricingByUsageTest` (AC-001..006, 008), `ProductCrudTest` POST vuoto senza errore `cost` (requisito
  cambiato); FE `product-form-pricing.test.tsx`, `product-pricing-visibility.test.ts`, schema/payload/detail aggiornati;
  test form che compilavano il Costo su prodotto solo Vendibile adeguati (D-1). Suite completa BE/FE verde, tsc pulito.
- Guida in-app products IT/EN aggiornata. Da fare: manuale Claude Docs (doc non condiviso con la sessione) — sezione
  Prodotti > Prezzi e fornitura, Dettaglio, flusso "voce di costo".

## SEED OPERATORI: 4 SUPERVISOR APL + MARTINA MOSCA — VERDE, COMMITTATO (2026-10-05)

- `OperatorRoster`: Giovanna Gervasio, Raffaele Distico, Gessica Crispo, Emanuele Ascione = riga di Rosa Falzarano
  (`Supervisor Commerciale`, `SUPERVISOR_ROLE`, sede fisica Frattamaggiore, nessuna sede remota) ma con competenza
  `['APL']` (copre tutto il ramo, INV-2). Martina Mosca = riga di Michela Fabozzi (`COORDINATOR_ROLE`, nessuna competenza).
- Rimossi da `StaffRoster` (i due roster restano disgiunti, conteggio condiviso 71 -> 76). Gli account gia' esistenti in
  prod con ruolo base convergono al prossimo `QualificaOperatorSeeder` (ruolo/sedi/competenza; password intatta).
- `ReportsToRoster` (decisione utente 2026-10-05): i 4 non rispondono a nessuno; Mosca risponde a Fabozzi. I 4 NON sono
  in `COMMERCIAL_SUPERVISORS` (non sono "Risponde a" dei commerciali).
- Test: `QualificaOperatorSeederTest` conteggio 67 -> 72 (requisito cambiato) + test dedicato; asserzioni in
  `QualificaReportsToSeederTest`.

## CATALOGO: "FORMAZIONE APPRENDISTATO" (CAMPI OFFERTA + STATI PRATICA) — VERDE, COMMITTATO (2026-10-05)

- Foglio "Apprendistato - Campi Operatore", sezioni DATI PRATICA, FORMAZIONE, FORMAZIONE - UNITA' FORMATIVE. Stesso
  schema dei Tirocini APL: `QualificaCatalog/ApprenticeshipAttributeCatalogue.php` (CATEGORY "Formazione Apprendistato",
  sotto "APL", selezionabile, barriera `inherits_quote_attributes=false`) + `ApprenticeshipWorkflowStatusCatalogue.php`.
- Campi: `decree_status`/`decree_id`/`reporting_id` RIUSATI dai Tirocini (ora costanti `AplInternshipAttributeCatalogue::
  DECREE_STATUS|DECREE_ID|REPORTING_ID`), `teaching_tutor` (relation users), `hiring_date`, `contract_duration_months`,
  `contract_end_date`, `company_training_capacity` (enum 3); per ognuna delle 11 UF `apprenticeship_<yN_ufN|yN_stage>_
  {done_by_us (boolean), reason (enum 3 suggerimenti), sessions (table: day date + hours decimal)}`. Ore previste = descrizione sezione.
- NON campi: Operatore = `operator_id`, Commerciale/Segnalatore = `commercial_id`/`reporter_id` dell'offerta, Percorso =
  storico stati, annualita' = prodotto (1°/2°/3° Anno). Allegati per UF: nessun tipo file. Somma ore vs previste: non automatica.
- Stati (prima classificazione, da affinare): "Attesa Abilitazione CPI" open (pinned), 4 pending, "Pratica conclusa"
  closed_won (pinned); closed_lost resta "Chiusa negativa" (il foglio non ha perdite). No "Non risponde".
- `QualificaQuoteLayoutSeeder`: nuovo `OWN_FORMS` (categoria => sezioni proprie) per Tirocini e Apprendistato, perche'
  condividono codici e da una lista comune filtrata ognuna avrebbe reso le sezioni dell'altra.
- ATTENZIONE limiti: `QualificaCatalogSeeder.php` e `WorkflowStatusCatalogue.php` a 499 righe: la prossima aggiunta richiede uno split.
- DB dev importato: la categoria (old_id 115) ha gia' 20 campi offerta legacy Ricerca & Selezione e un layout legacy;
  il seeder non li tocca (layout = dato utente) -> i nuovi campi finirebbero in "altre informazioni". Da decidere con l'utente.
- Test: `tests/Feature/Products/QualificaApprenticeshipCatalogueTest.php` (3); aggiornati elenchi categorie in
  QualificaCatalogSeederTest, QualificaQuoteLayoutSeederTest, QualificaContactProcessingSeederTest, QualificaWorkflowSeederTest.
- Manuale: guide in-app nessun impatto (non descrivono form per categoria); manuale Claude Docs non accessibile dalla sessione.

## FIX TABELLE RIGHE CHE SFORANO SU MOBILE — VERDE, NON COMMITTATO (2026-10-05)

- Bug: su schermo stretto le righe offerta (Gestione Richieste, Offerte tab offerta/costi) uscivano dalla card. Causa:
  `FormItem` e' `grid gap-2`, la sua colonna `auto` prendeva il min-content della tabella (`min-w-[994px]`), quindi
  la cella si allargava e l'`overflow-x-auto` interno non scorreva mai.
- Fix: costante `LINE_TABLE_SCROLL_CLASS` in `components/record-form/layout.ts` (`... contain-inline-size`, stesso
  rimedio di `table-field-desktop-view.tsx`), usata da `QuoteLinesField`, `QuoteLinesReadOnlyList`,
  `WorkOrderCostsLinesField`, `ContractProgramLinesTable`. Nuove tabelle larghe a righe: usare questa costante.
- Test: `quotes/quote-lines-field-layout.test.tsx` (2), `quote-lines-read-only.test.tsx` (+1). Manuale: nessun impatto.

## FIX DEMO SEED: OFFERTE/COMMESSE/TASK VUOTI — VERDE, NON COMMITTATO (2026-10-02)

- `DemoDataSeeder` si interrompeva in `DemoQuoteSeeder` (422 su attributi offerta `relation`/enum multiselect/`table`,
  arrivati col catalogo e-Campus): niente offerte, commesse, contratti, task, timesheet, allegati, notifiche.
  Fix: `database/seeders/Support/DemoAttributeValueFaker.php` (relation = primo id reale del target via
  `CustomFieldEntityRegistry`, `many`/multiselect = array, table = [], time/email/url/color validi), usato da
  `DemoQuoteSeeder` al posto del vecchio `fakeAttributeValue`.
- Secondo blocco emerso subito dopo: `DemoTaskSeeder::buildRootData` collegava commessa E opportunita' insieme (422
  spec 0154 D-11). Ora sono esclusivi e con commessa l'anagrafica resta vuota (la riempie il server dalla catena).
- Test: `tests/Feature/Seeding/DemoAttributeValueFakerTest.php` (3), `DemoTaskSeederTest` (+1). DB dev riseminato:
  20 offerte, 10 commesse, 2 contratti, 60 task, 114 time entries.

## SPEC 0190 COSTI COMMESSA (PREVENTIVATO VS EFFETTIVO) — VERDE, NON COMMITTATO (2026-10-02)

- Spec `docs/specs/0190-work-order-costs.xml` (approved; D-1..D-4 utente, D-5..D-9 approvate col piano). Il legacy
  (`manageorder/{id}` tab amministrazione, `AnalisiPreventiva`) modificava in place i costi dell'offerta: qui i costi
  reali sono un'entita' della commessa e `quote_lines` resta in sola lettura.
- Dati: tabella `work_order_costs` (migrazione `2026_10_02_130000`), model `WorkOrderCost` (`LogsModelActivity`, morph
  `work_order_cost` in `AppServiceProvider`), `WorkOrder::costs()`. FK: `quote_line_id` = riga ricavo della commessa
  (riferimento), `supplier_id` = registry `is_supplier`, `incurred_on` obbligatoria.
- API: `GET|PUT /api/work-orders/{workOrder}/costs` (`routes/api/work-order-costs.php`, `WorkOrderCostController`,
  `SyncWorkOrderCostsRequest` con authz in `authorize()` = 403 prima della 422, `WorkOrderCostWriter` set completo,
  `WorkOrderCostOverviewBuilder` confronto in centesimi). Preventivato = COST con `offer_line_id` tra le righe della
  commessa; COST generiche = `unallocated_lines` fuori dai totali; effettivo include i non attribuiti.
- Permessi: `work-orders.viewCosts` / `manageCosts` (policy + visibilita'), flag `permissions.actions.view_costs` /
  `manage_costs`. Applicati sul DB dev (`migrate` + `permissions:sync`).
- Frontend: `features/work-order-costs/` (sezione `WorkOrderCostsSection` in `work-order-detail.tsx`, tab Confronto e
  Costi effettivi; riga propria che compone `QuoteProductSelect`/`computeLineAmounts`, `QuoteLineRow` invariato).
  i18n sotto `workOrders.costs.*` (`{it,en}-work-order-costs.ts`, `en.ts` a 499 righe). Guida in-app `work-orders`
  IT/EN con sezione `costs`; parity test adeguato (la nota `in-development` resta singola).
- Test: `WorkOrderCostsTest` (AC-001..010), `work-order-costs-*.test.*` + `work-order-detail.test.tsx` (AC-011..014).
  `QuoteWorkflowMigrationTest` rollback step 124 -> 125. `WorkOrderSecurityTest` conteggio permessi 12 -> 14.
  `lines.*.id` e' `distinct` (id duplicato nel PUT = 422). Prefill da `products.cost`: `cost-row-product.test.ts`.
- Da fare: manuale Claude Docs (doc non condiviso con la sessione) — sezione Commesse > Costi. Fuori scope: provvigioni
  nel margine, ODA/fatture passive, costi orari personale, migrazione costi legacy.

## SPEC 0189 IMPORT LEGACY ANAGRAFICHE / OPPORTUNITA' / OFFERTE / COMMESSE — VERDE, NON COMMITTATO (2026-10-02)

- Spec `docs/specs/0189-legacy-registries-opportunities-quotes-work-orders-migration.xml` (decisioni G-1..G-11 del lead,
  da confermare dall'utente; contratto delle 4 API congelato li').
- LEGACY `/Users/Repository/qnet` (non committato): NUOVI `Api/V2/{Registry,Opportunity,Quote,WorkOrder}MigrationController`,
  `Support/Migration/{MigrationFieldFormatter,LegacyReferenceLookup}`; route `GET /api/v2/migration/{registries,
  opportunities,quotes,work-orders}`. Esportano solo record importabili (G-1: opportunita' con cliente, offerte di
  opportunita' esportate, commesse di offerte esportate). `AppServiceProvider.php`/`config/database.php` legacy erano
  gia' modificati e NON fanno parte di questa feature.
- qnet-2: migrazione `2026_10_02_120000_add_old_id_to_operational_records_tables` (old_id su registries, opportunities,
  quotes, quote_lines, work_orders; applicata al DB locale; `QuoteWorkflowMigrationTest` step 123 -> 124). Sorgenti
  `RegistriesSource`, `OpportunitiesSource`, `QuotesSource`, `WorkOrdersSource` (+ concern `MapsLegacy*`, support
  `LegacyQuoteStatusApplier`, `PersonNameSplitter` = nome/cognome privati ricostruiti dal codice fiscale), registrate in
  `config/migrations.php` e `MigrationOrder` fasi 7-10. `OpportunityService::import()` (niente guard "una opportunita'
  aperta", niente notifiche/lead). `AbstractMigrationSource`: report bufferizzato (flush ogni 200 voci + a fine run).
  Nessuna notifica (manager/supervisori scritti dopo la create), activity log disattivato (`withoutLogs`), commesse
  senza task template.
- Verifica: Pest completo 9216 pass / 0 fail / 1 skip; Pint, `tsc -b --force`, Vitest help/migrations 128 verdi.
  E2E reale su DB temporaneo `qnet2_e2e` (copia di qnet2 + users/referents): registries 20696/0 falliti (286 s),
  opportunities 19561/0 (217 s), quotes 17281/0 (608 s; 39837 righe, 11107 contratti), work-orders 16024/0 (176 s);
  0 notifiche, 0 task, 0 activity_log.
- Decisione utente 2026-10-02 (opzione C, sostituisce la A data poco prima): una riga offerta legacy gia' collegata a
  un'altra commessa viene DUPLICATA sull'offerta per ogni commessa successiva (`Migrations\Support\QuoteLineDuplicator`:
  stesso prodotto/importi/provvigioni, senza old_id, totali e margine ricalcolati) e la copia collegata alla commessa.
  Il vincolo una-riga-una-commessa resta. Test `WorkOrdersSourceImportTest` aggiornato (requisito cambiato).
- Aperti: 1341 righe offerta senza prodotto (sconti/detrazioni non migrati o
  servizio vuoto); fuori scope: note, documenti, valori campi flessibili, fasi/task commesse, tag/ATECO anagrafiche,
  richieste GOL senza cliente. `OpportunityService.php` a 498 righe: va splittato. Manuale Claude Docs NON aggiornato
  (documento non accessibile da questa sessione): aggiungere la sezione import operativo in "Migrazioni".
  `docs/HANDOFF.md` supera di molto i 50 KB: va archiviato.

## PRODOTTI: DESCRIZIONE NEI SELECT + AREA E-CAMPUS — VERDE, NON COMMITTATO (2026-10-02)

- Decisione utente 2026-10-02: ogni prodotto e-Campus ha in descrizione la sua area (fonte: PDF "Schema Corsi
  E-Campus" = raggruppamento del foglio CORSI E-CAMPUS). `ECampusCourseCatalogue::DEGREES[*]['courses']` ora e'
  `corso => area`. `CatalogProducts::seedProduct(..., description:)` la scrive alla creazione e riempie SOLO una
  descrizione vuota su un prodotto gia' esistente (una descrizione manuale resta).
- `GET /api/products/for-select`: `subtitle` = "Categoria · Descrizione" (descrizione `Str::squish`, omessa se vuota;
  `ProductForSelectResource::SUBTITLE_SEPARATOR`); `search` cerca su `name` OR `description`
  (`ProductService::forSelect`, `FOR_SELECT_COLUMNS` + `description`). Nessun cambio frontend: tutti i picker
  prodotto (Gestione Richieste, Offerte, prodotti di interesse, provvigioni, import) rendono gia' il subtitle.
- Test: `ProductForSelectTest` (+2; fixture con `description => null` dove si asserisce il subtitle, la factory la
  genera a caso), `QualificaECampusCatalogueTest` (+1). Guide in-app `products.ts` IT/EN e manuale Claude Docs
  (riga Descrizione) aggiornati. Seed applicato al DB locale.

## SPEC 0188 OVERRIDE "SEMPLIFICAZIONE RIGA OFFERTA" PER NODO — VERDE, NON COMMITTATO (2026-10-02)

- Decisione utente 2026-10-02: "Corsi E-Campus" NON semplificata (imponibile modificabile in Gestione Richieste),
  "Formazione" resta semplificata. Rivede spec 0114 D-1 solo per `simplified_offer_line`.
- Contratto (spec 0188): colonna nullable `product_categories.simplified_offer_line_override` (null = eredita,
  sempre null su una radice; migrazione `2026_10_02_100000_*`). `simplified_offer_line` resta l'EFFETTIVO
  denormalizzato: i lettori non cambiano. `simplified_offer_line_source_category` = antenato dichiarante piu' vicino.
  Campo in Resource, nodo tree, permessi di campo; 422 "A root category sets the simplified offer-line rule
  directly." / "This category inherits the simplified offer-line rule; set its override instead."
- Backend: `SimplifiedOfferLineInheritance` non estende piu' `RootOwnedCategorySetting` (nearest-ancestor, resync
  idempotente che rispetta gli override); `RootOwnedSettingsWriter` guardie/create/reparent (D-5). Test:
  `ProductCategories/SimplifiedOfferLineOverrideTest` (17), `RequestManagement/SimplifiedOfferLineOverrideFreezeTest`.
  `QuoteWorkflowMigrationTest` rollback 122 -> 123 step (nuova migrazione).
- Seed: `CatalogRootRules::NODE_OVERRIDES` (NON `CategoryInheritanceRules`, che ha modifiche APL non committate e
  gestisce barriere non ereditate) -> override false su "Corsi E-Campus". `QualificaCatalogRootRulesTest` aggiornato.
- Frontend: tile semplificazione modificabile anche sul figlio (modello `ReportableRule`), hook
  `use-simplified-offer-line-inheritance.ts`, `simplified-offer-line-inheritance.ts` nearest-ancestor, i18n
  `simplifiedOfferLineForced*`; fixture tree aggiornate con `simplified_offer_line_override: null`.
- Manuale: guide in-app IT/EN `product-categories.ts` aggiornate (righe sostituite, file a 485/500); manuale Claude Docs
  aggiornato (tabelle regole + elenco "Imposta cio' che vale solo qui" + descrizione).
- Verifier: VERDE, AC-001..016 PASS; Pest 9146 passed/1 skipped, Vitest 6622/6622, tsc -b e ESLint puliti.
  Migrazione e seed applicati al DB locale `qnet2`.

## CORSI E-CAMPUS: ALBERO APPIATTITO + CAMPI OFFERTA RIVISTI — VERDE, COMMITTATO (2026-10-02)

- Decisione utente 2026-10-02: niente piu' livelli/aree. "Corsi E-Campus" e' una sottocategoria SELEZIONABILE di
  Formazione (`QualificaCatalogSeeder::CATALOG` + `SELECTABLE_SUBCATEGORIES`), i 140 prodotti stanno direttamente
  su di essa. Sostituisce l'albero a tre livelli del 2026-10-01 (sezione "CATALOGO CORSI E-CAMPUS" sotto).
- `ECampusCourseCatalogue::DEGREES` = livello => `fees` + `courses` (lista piatta); `PARENT` rimosso.
  `ECampusCategoryTree` CANCELLATO, sostituito da `QualificaCatalog/ECampusTreeFlattening::apply()` (in
  `seedCatalog()`, prima dei prodotti): sui DB che hanno ancora i vecchi nodi sposta prodotti, righe
  opportunita'/progetto/campagna/competenza e commission_configurations sul ramo, poi cancella aree e livelli.
  In transazione; se due righe dello stesso owner+funzione collasserebbero sul ramo lancia `RuntimeException`
  (da unire a mano). No-op su installazione nuova.
- Stati e-Campus invariati (ora risolti sul nodo stesso). Fold verificato sul DB locale `qnet2` (140 prodotti e 18
  righe offerta accorpati); poi l'utente ha fatto `migrate:fresh --seed` e il catalogo e' stato riseedato.
- Test: `QualificaECampusCatalogueTest` riscritto (5 scenari: seed piatto, fold, fold rifiutato, form/stati,
  ricomposizione del form 2026-10-01);
  lista selezionabili e conteggio in `QualificaCatalogSeederTest` (requisito cambiato). Suite completa verde.
- `QualificaCatalogSeeder.php` e' a ridosso delle 500 righe (hard limit; 500 con le modifiche APL): la prossima
  aggiunta richiede uno split.
- Campi offerta e-Campus (stessa data, decisione utente): RITIRATI `degree_course` ("Corso di Laurea (CdS)", il
  corso e' il prodotto) e i flag di pagamento `financing`, `fee_regulation`, `bank_transfer` -> in
  `ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES`. NUOVI: `ecampus_receipt` "Contabile e-Campus",
  `qualifica_receipt` "Contabile Qualifica", `data_collection_form` "Modulo raccolta dati" (boolean, sezione
  Documenti) e `payment_type` "Tipo di pagamento" (enum `single_payment`/`two_installments`/`three_installments`/
  `financing`), UNICO campo della sezione Pagamento (1 colonna). Manuale: nessun impatto (non cita e-Campus).
  Valori gia' salvati nei campi ritirati restano sulle offerte, non letti (nessuna conversione a `payment_type`).
- Layout: `ECampusAttributeCatalogue::PREVIOUS_SECTIONS` = form del 2026-10-01;
  `QualificaQuoteLayoutSeeder::isRetiredECampusComposition()` lo riconosce dopo lo strip dei codici ritirati (le
  larghezze sopravvivono allo strip, quindi il match byte-per-byte normale non basta) e lo ricompone. Un form
  modificato a mano resta intatto.
- Dopo il commit `42e78925` (NON COMMITTATO, suite verde 9128): sezione "Corso di Laurea" da `Highlighted` a
  `Default` (bianca). Quota "PROGETTO FORM" non e' piu' un prodotto (`ECampusCourseCatalogue::RETIRED_FEES`):
  115 prodotti e-Campus, totale catalogo 418; `CatalogProducts::retireECampusFees()` (step 5) cancella i vecchi
  prodotti non referenziati (quote_lines, opportunity_product, lead_product, commission_configurations), quelli
  referenziati restano con `Log::warning`. Nuovo campo `form_course` "Corso Form" (boolean) nella sezione Corso di
  Laurea. Il form di `42e78925` non e' fra le revisioni riconosciute (mai pushato): sul DB locale la riga layout
  va cancellata e riseedata.

## TIROCINI APL: CAMPI OFFERTA, LAYOUT, STATI — VERDE, NON COMMITTATO (2026-10-02)

- Fonte: PDF "Campi Misure APL (1).pdf" (scheda Tirocini). Decisione utente 2026-10-02: SOSTITUISCE la scheda
  Orientamento/SFL GOL del 2026-10-01, non si aggiunge. "Orientamento Specialistico" torna com'era prima del
  2026-10-01: nessun campo proprio, nessuna barriera, nessun layout, stati del ramo APL. I cataloghi
  `AplMeasure*` non esistono piu' (mai committati): sostituiti da `AplInternship*`.
- Categoria `Tirocini extracurriculari privati` sotto APL (seed `QualificaCatalogSeeder::CATALOG`, selezionabile;
  sul DB importato l'import legacy la ADOTTA per nome). Barriera `inherits_quote_attributes=false`.
- Campi (`QualificaCatalog/AplInternshipAttributeCatalogue`): `practice_start_date`, `practice_end_date`,
  `registers_status` (Inserito/Non inserito), `decree_status` (Persa/Inviata/Accolta/Pagata), `practice_number`,
  `reporting_id`, `decree_id`. Layout: Stato pratica (highlighted: registri, decreto, date) / Dati pratica.
- NON campi: Utente = anagrafica; Commerciale/Segnalatore = campi dell'offerta; Stato pratica = workflow.
  Esclusi per decisione utente: Tutor, Note, tutto il soggetto ospitante (anche il menu in testata), tutor aziendale,
  destinatario, automazione registri mensili.
- Stati (`QualificaCatalog/AplInternshipWorkflowStatusCatalogue`, sezione `apl_internships`), criterio categoria
  ESATTA (vince sul ramo APL). Decisione 2026-10-02: Produzione documentale (open) / Vacancy, Candidatura, Assenso,
  Attivazione politica attiva, Attivo, Prorogato, Concluso (pending) / Attestazione finale (won) / Interrotto (lost,
  riga fissa perche' primo negativo) e Perso (lost, custom). Nessun "Non risponde" (scelta utente, test escluso).
- Test: `QualificaAplInternshipCatalogueTest` (3, incluso "Orientamento senza campi propri"); aggiornati per requisito
  cambiato `QualificaCatalogSeederTest`, `QualificaContactProcessingSeederTest` (layout 9->10),
  `QualificaQuoteLayoutSeederTest`, `QualificaWorkflowSeederTest`. Suite completa verde (9124 passed, 1 skipped), Pint pulito.
- Manuale: nessun impatto (dati di seed; guide in-app e Manuale QNet non elencano campi/stati per categoria).
- Da fare: rieseguire `QualificaProductionDataSeeder`/`QualificaCatalogSeeder` su staging/prod. Un DB locale gia'
  seedato con la versione del 2026-10-01 conserva i campi/stati di Orientamento (seed additivi, workflow esistente
  saltato per nome): va resettato. Follow-up possibili: anagrafica soggetti ospitanti, registri mensili.

## TOAST IN ALTO AL CENTRO — VERDE, COMMITTATO (2026-10-01)

- Decisione utente 2026-10-01: gli alert (sonner) e l'avviso "Nuova versione disponibile" compaiono in alto al centro,
  non piu' in basso a destra. Unica modifica: `App.tsx` `<Toaster position="top-center" />` (un solo Toaster globale;
  `VersionUpdateBanner` usa lo stesso `toast()`). Nessun toast forza una posizione propria: non aggiungerne.
- Verifica: `tsc -b --force` pulito, ESLint pulito, `version-update-banner.test.tsx` 5/5. Manuale: nessun impatto
  (ne' guide in-app ne' Claude Docs citano la posizione degli avvisi).

## SCADENZA TOKEN API 30 GIORNI — VERDE, NON COMMITTATO (2026-10-01)

- Decisione utente 2026-10-01: i token Sanctum scadono dopo un mese. `config/sanctum.php`:
  `'expiration' => (int) env('SANCTUM_EXPIRATION', 60 * 24 * 30)` (minuti); `.env.example` documenta `SANCTUM_EXPIRATION=43200`.
  Effetto: check Health "Scadenza token" (`SecurityHealthCheck::sanctumExpirationDetail`) passa da Degradato a OK.
- Scadenza FISSA dal login: il frontend non chiama mai `/api/auth/refresh`. Al 401 l'interceptor di `api/client.ts`
  pulisce il token e l'AuthProvider riporta al login. Vale anche per i token di impersonation.
- CORS lasciato invariato per decisione utente (resta Degradato: nessun `config/cors.php`, default framework `*`).
- Test: `tests/Feature/Auth/AuthTest.php` (+3: valore config, token valido a 30gg-1min, 401 a 30gg+1min). Suite completa verde (9121 passed, 1 skipped).
- Manuale: guida in-app `general` sezione `sign-in-and-password` +nota 30 giorni (IT+EN); manuale Claude Docs
  aggiornato (stessa nota sotto "Accesso"). PDF derivato NON rigenerato.

## STAGING: EMAIL BLOCCATE SENZA MAIL_ALWAYS_TO — VERDE, NON COMMITTATO (2026-10-01)

- Requisito utente: in staging tutte le email vanno a `MAIL_ALWAYS_TO`. Il redirect esisteva gia'
  (`App\Mail\StagingMailRedirector`, `alwaysTo()` su mailer di default + `outbound_emails.mailer`), ma con
  `APP_ENV=staging` e variabile vuota le email partivano ai destinatari reali (fail-open).
- Decisione utente 2026-10-01: fail-closed. `StagingMailRedirector::handle()` ora: production -> nulla;
  indirizzo valorizzato -> redirect (invariato, anche in local); `APP_ENV=staging` senza indirizzo ->
  listener `MessageSending` che lancia `App\Exceptions\Mail\MailBlockedInStagingException` (copre ogni
  mailer, anche quelli nominati). Effetti: job notifiche mail falliscono (failed_jobs), `SendOutboundEmailJob`
  marca l'email `failed` (messaggio generico), mai `sent`. Lo staging e' riconosciuto SOLO da `APP_ENV=staging`.
- Rev. mittente (decisione utente 2026-10-01): con il redirect attivo anche il MITTENTE diventa `MAIL_ALWAYS_TO`
  (nome visualizzato originale mantenuto), via listener `MessageSending` (`alwaysFrom()` non basta: il `from` del
  messaggio lo sovrascrive). Motivo: Graph invia DALLA casella `from`; in staging gli utenti copiati/seed
  (es. `demo@app.com`) non sono caselle M365 -> `ErrorInvalidUser`. Il record OutboundEmail conserva il
  `from_address` reale. Test cambiato per requisito cambiato: `StagingRedirectCouplingTest` (Graph ora chiamato
  su `/users/staging-inbox@.../messages`).
- Test: `tests/Feature/Mail/StagingMailRedirectTest.php` (+3), `tests/Feature/OutboundEmails/StagingRedirectCouplingTest.php` (+1, 1 aggiornato).
- Verificato: `composer test` 9118 passed / 1 skipped; Pint pulito. Manuale: nessun impatto (config di deploy).
- Staging, prerequisiti Azure: app Graph con permessi Application `Mail.ReadWrite` + `Mail.Send` + admin consent
  (il flusso bozza->send richiede ReadWrite, a differenza di classroom-rental che usa `/sendMail`); dopo il
  consenso `php artisan cache:clear` (token in cache). `test-it@qualificagroup.it` deve essere casella M365.
- Aperto (decisione utente): in PRODUZIONE un utente con email non M365 non puo' inviare dalle commesse
  (mittente = `users.email`, D-6 spec 0175).
- Deploy staging: verificare che il `.env` abbia `APP_ENV=staging` e `MAIL_ALWAYS_TO` valorizzata.

## E-CAMPUS: CAMPI OFFERTA, LAYOUT E STATI DI LAVORAZIONE — VERDE, COMMITTATO (2026-10-01)

- Fonte: PDF "BOZZA gestionale_Progetto Università" (colonne FACOLTA' -> Finanziamento + colonna STATO).
- Campi Offerta (`QualificaCatalog/ECampusAttributeCatalogue::ATTRIBUTES`) assegnati su "Corsi E-Campus":
  `faculty` (enum), `degree_course` (text), `degree_level` (enum bachelor/master), 7 boolean (`identification_documents`,
  `enrollment_form`, `diploma_or_self_certification`, `annex_a`, `fee_regulation`, `bank_transfer`, `financing`).
- Barriera `inherits_quote_attributes = false` su "Corsi E-Campus" (`CategoryInheritanceRules`, come DIL): niente
  campi GOL/CPI/Dati Aula di Formazione. "Form" e i prezzi annuali del PDF NON sono campi (fuori dall'elenco utente).
- Layout: `ECampusAttributeCatalogue::SECTIONS` (Corso di Laurea highlighted / Documenti di iscrizione / Pagamento a
  3 colonne) aggiunte a `QualificaQuoteLayoutSeeder::SECTIONS`. `SeedsAttributeLayouts::layoutSection()` ha un
  `$style` opzionale (variant, columns, description, larghezze che riempiono la riga): senza style blob invariato.
  Il layout sta solo sul nodo "Corsi E-Campus", le aree lo ereditano (spec 0115).
- Stati: `QualificaCatalog/ECampusWorkflowStatusCatalogue` (sezione `e_campus` di `WorkflowStatusCatalogue`, file
  separato perche' quello e' a 495 righe), workflow "Corsi E-Campus" con criterio di ramo. Pinned: Nuovo Contatto
  (open, aggiunto come in tutte le liste Formazione), ISCRITTO (closed_won), Non attinente (closed_lost). "NR"
  foldato in "Non risponde" (open). Legende OPEN/PENDING/... di `WorkflowStatusCatalogue` ora pubbliche.
- Test: `QualificaECampusCatalogueTest` (2 scenari); conteggi layout +1 in `QualificaQuoteLayoutSeederTest` e
  `QualificaContactProcessingSeederTest` (requisito cambiato). Seed applicato al DB locale `qnet2`.

## CATALOGO CORSI E-CAMPUS NEL SEED DI PRODUZIONE — VERDE, COMMITTATO (2026-10-01)

- Fonte: PDF "CORSI E-CAMPUS" dell'utente. Albero `Formazione > Corsi E-Campus > {Corsi di Laurea Triennali,
  Corsi di Laurea Magistrali} > {Ingegneria, Letteratura, Psicologia, Economia, Giurisprudenza (solo triennali)}`:
  i primi due livelli nuovi sono contenitori, le aree sono selezionabili.
- Le aree si chiamano `"<area> - <livello>"` (es. `Ingegneria - Corsi di Laurea Triennali`, decisione utente
  2026-10-01) per distinguerle nei select; chiave comunque (name, parent_id). Il ramo non sta in
  `QualificaCatalogSeeder::CATALOG` ma in `QualificaCatalog/ECampusCategoryTree` (create-only, chiamato in
  `seedCatalog()` prima di `CatalogRootRules::apply()`); dati in `QualificaCatalog/ECampusCourseCatalogue`.
- Prodotti (`CatalogProducts` step 4): uno per quota del livello, nome `"<corso> <quota>"` (es. `Ingegneria Civile e
  Ambientale [L-7] PROGETTO FORM`, nome fermato prima dei ":"), prezzo = quota, SERVICE, nessuna IVA. Triennali
  6 quote (1500/500/2856x3/300), magistrali 5 (1500/500/3056x2/300): 15x6 + 10x5 = 140 prodotti (totale 443).
- Escluso: "Magistrali a ciclo unico" (Giurisprudenza [LMG/01]), il PDF non riporta le quote.
- Test: `QualificaECampusCatalogueTest` (nuovo); totali/lista selezionabili aggiornati in `QualificaCatalogSeederTest`
  e `QualificaProductionDataSeederTest` (requisito cambiato). Manuale: nessun impatto (dati di seed).

## NOTIFICHE ASSEGNAZIONE OPPORTUNITA' vs OFFERTA + LINK TRASFERIMENTO — VERDE, COMMITTATO (2026-10-01)

- Due commit: `39893f32` (separazione delle notifiche) e il successivo (ereditati + link del trasferimento).
- Decisione utente 2026-10-01 (rev. spec 0081 + spec 0186 D-7): chi e' nell'Opportunita' ma in nessuna sua
  Offerta riceve la notifica dell'Opportunita'; chi viene inserito nell'Offerta riceve SOLO quella dell'Offerta.
- `RecordLinkResolver::pathFor(User, AssignmentTargetEnum, int, RequestModule = Requests)`: Opportunity =
  `/opportunities/{id}` o null (mai piu' `/request-management`); Quote = `/quotes/{id}` -> `/{modulo}/{id}` -> null.
  Il trasferimento usa il ramo Quote col modulo dell'attore: eliminati `transferPath()` e `opportunityId`
  (RequestTransferNotice, RequestTransferService, RequestTransferredNotification).
- `AssignmentNotifier`: per target Opportunity esclude, al commit, i GA di ogni Offerta (`offerManagerIds`).
  `QuoteService::create()` notifica anche i GA ereditati (D-4 annullata): copre creazione a mano, conversione
  lead e Gestione Richieste. `OpportunityService::propagateManagersToQuote()` notifica l'Offerta.
- Test: `OpportunityOfferAssignmentNotificationTest` (nuovo), AC-014 di `RecordAssignmentLinkTest` e AC-006 di
  `QuoteAssignmentNotificationTest` riscritti (requisito cambiato), 2 test nuovi sul link del trasferimento.
- Manuale: guide `opportunities`, `quotes`, `request-management` IT/EN + Manuale Claude Docs; PDF da rigenerare.

## SPEC 0187 STATO DEL SISTEMA + UTENTI ONLINE — VERDE, COMMITTATO (2026-10-01)

- Spec `docs/specs/0187-system-health.xml` (porting di classroom-rental spec 0038, senza Stripe, + Code + presenza).
- BE: `GET /api/system-health` (middleware `super-admin`, 403 standard come Migrazioni, niente envelope) e
  `POST /api/presence/heartbeat` (204 no-op: la Guard Sanctum aggiorna `personal_access_tokens.last_used_at`).
  `App\Services\System\{SystemHealthService, OnlineUsersService, Checks/*}`, `HealthStatusEnum::worst()`,
  config `config/system-health.php` (`online_window_minutes`=2, `queue_pending_threshold`=100), indice su
  `last_used_at` (migrazione 2026_10_01_000000 -> `QuoteWorkflowMigrationTest` rollback step 121 -> 122).
  Online = persona `COALESCE(impersonated_by, tokenable_id)`, is_active esclusi, una volta per persona.
  Detail keys: database(connection,name) email(mailer,transport,credentials) queue(connection,pending,failed)
  security(app_debug,app_env,https,sanctum_expiration,cors,app_key,mail_secrets,activitylog).
- FE: `features/system-health/` (pagina `/admin/system-health` dietro `MigrationRouteGuard`, refetch 30 s,
  `usePresenceHeartbeat` montato in `AppLayout`, ping 60 s), namespace i18n `systemHealth`, icona `activity`,
  nav `navigation.systemHealth`, guida help `system-health` it/en (HELP_GUIDE_KEYS 55).
- Manuale Claude Docs: aggiunta sezione "Stato del sistema" in Parte 1 dopo Migrazioni + voce nell'intro.
- Da sapere: `sanctum.expiration` e' null -> il check Sicurezza risulta "degradato" in ogni ambiente finche'
  non si imposta una scadenza token (decisione aperta, non toccata).
- Verifiche: Pest suite completa 9106 passati / 1 skipped (System 14/14), Pint ok; Vitest mirati 334/334, ESLint ok, `tsc -b --force` ok.

## COMMESSE: "ANAGRAFICA CLIENTE" NEL DETTAGLIO — VERDE, COMMITTATO (2026-10-01)

- Dettaglio commessa, sezione "Contratto e righe prodotto": nuovo primo campo "Anagrafica cliente" /
  "Client registry" (`workOrders.detail.registry`), valore = `RecordLink domain="registries"` su
  `workOrder.registry` (gia' esposto da `WorkOrderResource::summarizeRegistry()`, catena
  `quote.opportunity.registry`, spec 0173), `DetailEmpty` se null. Solo frontend, nessun cambio di contratto.
- Test: 2 casi in `work-order-detail.test.tsx` (link `/registries/7`, placeholder senza registry).
- Manuale: guida in-app Commesse ancora "in fase di sviluppo" -> nessun impatto.

## FIX ACTIVITY LOG CONTRATTI / STATI CONTRATTO (404) — VERDE, NON COMMITTATO (2026-10-01)

- Bug: `GET /api/activity-log/contracts/{id}` (e `contract-statuses/{id}`) -> 404 "Risorsa non trovata": le due
  risorse non erano registrate in `backend/config/activity-log.php`, quindi `ActivityLogRegistry` lanciava
  `ModelNotFoundException`. Il frontend (`contract-detail`, `contracts-table`, `contract-status-detail`,
  `contract-statuses-table`) le chiamava gia'. Fix: registrate `contracts` (Contract) e `contract-statuses`
  (ContractStatus), gate di default `PolicyActivityLogAuthorizer`, nessuna relazione aggregata.
- Test: dataset di `tests/Feature/ActivityLog/ActivityLogModulesTest.php` esteso con le due risorse (AC-001/002/003).
- Audit: tutte le altre risorse usate dal frontend sono registrate; le Policy con `viewActivity` non registrate
  restanti (addresses, attachments, contacts, personal_data, rewarded-referents) non hanno una tab Attivita'.
- In produzione serve `php artisan config:cache` dopo il deploy se la config e' cachata.

## SPEC 0186 NOTIFICA "GESTORE ACCOUNT" DI OFFERTA — VERDE, NON COMMITTATO (2026-10-01)

- Spec `docs/specs/0186-quote-assignment-notifications.xml`. Nuovo `AssignmentTargetEnum::Quote`; link per
  destinatario in `RecordLinkResolver::quotePath()` = stesso ordine di `resolveViewableDomain('quotes')`:
  `/quotes/{id}` (quotes.view) -> `/request-management/{id}` (request-management.view) -> null.
- `RecordDetails::for/targetFor/labelFor` gestiscono Quote (scheda: titolo, cliente, sede, stato, fonte,
  supervisore, operatore dell'Offerta). Rimossi `requestManagementRecordId`/`requestManagementQuote` da
  AssignmentNotifier/RecordAssignmentNotification/RecordDetails (restano solo nel transfer, `pathFor` Opportunity).
- Gestione Richieste (RequestAttributionWriter, RequestAssignmentService) notifica ora sul `$quote` (solo GA2,
  regola 0097 D-6 invariata). `QuoteManagerWriter::sync()` restituisce gli attached; `QuoteService::create/update`
  notifica ogni nuovo gestore a fine transazione; in create solo se `manager_slots` inviato (ereditati = no).
- Test `tests/Feature/Notifications/QuoteAssignmentNotificationTest.php` (AC-001..007); suite completa verde
  (9085 passed). Guide in-app quotes/request-management IT+EN + manuale Claude Docs aggiornati.

## SPEC 0185 MODULO "STATISTICHE GESTIONE RICHIESTE" — VERDE, NON COMMITTATO (2026-10-01)

- Spec `docs/specs/0185-request-statistics-module.xml` (D-1 un solo modulo, statistiche di Gestione Iscritti
  eliminate; D-2 pannello tolto dalla tabella; D-3 permesso dedicato `request-statistics.view` che SOSTITUISCE
  `{module}.report`). Numeri, filtri, cache e path API invariati (`/api/request-management/report/*`).
- BACKEND: voce menu `request-statistics` (gruppo opportunities, dopo Iscritti, icona `chart-column`, rotta
  `/request-statistics`); `RequestStatisticsAuthorization` (nessun campo) registrata in `config/authorization.php`
  per renderla assegnabile dal form Ruoli; `RequestModule::STATISTICS_PERMISSION` + `hasStatistics()` (solo Requests):
  le rotte report sono registrate solo per `request-management` (Iscritti 404/405); controller dashboard/report
  autorizzano con la costante; ability `report` rimossa da `RequestManagementPolicy` (e quindi Iscritti).
  Migrazione `2026_10_01_100000_move_request_report_permissions_to_request_statistics` (ruoli/utenti con
  `request-management.report` o `enrollee-management.report` ricevono il nuovo permesso, i vecchi sono eliminati;
  **da eseguire `php artisan migrate`**). Seeder produzione: blocco `REQUEST_STATISTICS` a Supervisore e Coordinatore
  commerciale (`admin` lo ha con l'intero catalogo). `OperatorRoleCatalogue.php` a 494 righe (vicino al limite 500).
- FRONTEND: pagina `pages/request-statistics-page.tsx` (`REQUEST_STATISTICS_PERMISSION` in `request-management/types.ts`);
  `request-dashboard-panel.tsx` -> `request-dashboard.tsx` (`RequestDashboard`, sempre visibile); toggle e pannello
  tolti da `RequestManagementTable` (Richieste e Iscritti); `reportPermission` tolto dalla filter bar; label permesso
  `report` rimossa da `*-permissions.ts`. Guida in-app nuova `request-statistics` IT/EN; `request-management` ->
  sezione puntatore `statistics-moved`; `enrollee-management` -> `statistics-differences` dice che non ci sono piu'.
  Manuale Claude Docs aggiornato (sezione "Statistiche Gestione Richieste", tolta "Statistiche in Gestione Iscritti").
- Test con requisito cambiato (dichiarati): helper report -> pseudo-ability `statistics` (`requestReportPermission()` in
  `tests/Helpers/RequestManagementHelpers.php`); `EnrolleeReportTest` riscritto (rotte assenti); conteggi abilita'
  Iscritti 16->15; `QuoteWorkflowMigrationTest` step 119->121 (mancava anche la migrazione della 0184).
  Nuovo `RequestStatisticsModuleTest` (AC-001..008). Toggle/permission test FE cancellati; nuovo test pagina.
- Verifica: Pest completo 9074 pass (1 flaky `DemoOpportunitySeederTest`, verde 3/3 da solo); Pint pulito; Vitest
  completo 863 file/6603 test verdi; ESLint pulito; `tsc -b --force` EXIT 0. Non verificato a schermo nel browser.
- Nota: le cancellazioni dei 3 file FE del toggle sono gia' nell'indice git (`git rm` del teammate), nessun commit.
  HANDOFF e' ~340 KB, ben oltre il limite di 50 KB: va archiviato in `docs/handoff-archive/`.

## SPEC 0184 CATEGORIE PREFERITE NELLO STRIP (GESTIONE RICHIESTE/ISCRITTI) — VERDE, NON COMMITTATO (2026-09-30)

- Spec `docs/specs/0184-request-category-tab-favorites.xml` (D-1 salvataggio sull'account; D-2 preferite in cima +
  interruttore "Mostra solo preferite"). Contratto: GET/PUT `/api/{module}/category-tab-preferences` →
  `data.preferences = { favorite_category_ids: int[], show_only_favorites: bool }`; PUT = sostituzione completa.
- BACKEND: migrazione `2026_09_30_120000_create_user_category_tab_preferences_table` (unique user_id+module, FK cascade;
  **da eseguire `php artisan migrate` sul DB locale**), model `UserCategoryTabPreference` (no activitylog/Policy come
  ADR-0004), `UpdateCategoryTabPreferencesRequest` (max 200, distinct, exists), `CategoryTabPreferencesResource`,
  `CategoryTabPreferencesController` (authz `{module}.viewAny`, preferite di categorie eliminate scartate in lettura),
  rotte nel loop di `routes/api/request-management.php`. Test `CategoryTabPreferencesTest` (AC-001..007).
- FRONTEND: `use-category-tab-preferences.ts` (query + mutation ottimistica, rollback + toast), `arrangeCategoryTabs`
  in `category-tab-fit.ts`, menu con stella per riga + switch (`request-management-category-picker.tsx`; gruppi
  fotografati all'apertura, la riga non salta sotto il mouse), pulsante menu sempre visibile (stella compatta senza
  overflow, `MEASURE_MANAGE`). Misura larghezze su tutte le categorie (stabile), fit sullo strip ordinato: evita il
  loop di render che si avrebbe dipendendo da un array derivato. Stella piena gialla: token `--favorite` (riempimento) e
  `--favorite-border` (contorno, 3.3:1 sulla card) in `index.css` light/dark, classi `fill-favorite text-favorite-border`. `request-management-table.tsx` a 496 righe (vicino
  al limite 500: prossima modifica lì va preceduta da uno split).
- Verifica: Pest RequestManagement 816 + nuovi 16 + route test 26 verdi; Vitest request-management+help 588 verdi;
  eslint/pint puliti; `tsc -b --force` EXIT 0; controllo visivo Playwright su harness temporaneo (rimosso).
  Guida `request-management` IT/EN e manuale Claude Docs aggiornati.

## GESTIONE RICHIESTE — SCHEDE CATEGORIA PRIORITY+ "ALTRE (N)" — VERDE, NON COMMITTATO (2026-09-30)

- Richiesta utente: con tante categorie lo strip ripiegava su una `Select` inutilizzabile. Ora layout priority+ (stile
  CRM): le schede che entrano restano inline, le altre in **Altre (N)** (popover con ricerca case/accent-insensitive,
  frecce/Invio/Esc, conteggio per categoria); la categoria selezionata e' sempre inline (prende il posto delle ultime).
- Nuovi in `features/request-management/`: `category-tab-fit.ts` (`fitCategoryTabs` puro + `filterCategoriesByName`),
  `use-category-tab-fit.ts` (misura via layer nascosto `data-tab-measure`, `useLayoutEffect` + ResizeObserver),
  `request-management-category-picker.tsx` (`RequestManagementCategoryPicker`, `CategoryMoreButton`).
  Riscritto `request-management-category-tabs.tsx` (props invariate; non usa piu' `FormTabStrip`, che resta per quote).
- i18n `requestManagement.categoryTabs.{more,pickerLabel,searchPlaceholder,noMatch}` IT/EN. Guida `request-management`
  IT/EN (sezione `overview`) e manuale Claude Docs (paragrafo dopo "scheda per ogni categoria") aggiornati.
- Verifica: Vitest `features/request-management` + `features/help` + `form-tab-strip` 78 file/578 test verdi; eslint
  pulito; `tsc -b --force` EXIT 0; controllo visivo Playwright su harness temporaneo (rimosso) a 1280/768/375: nessuno
  scroll orizzontale, pinning della selezionata ok.

## SPEC 0183 CAMPI FLESSIBILI OFFERTA DALLE OPPORTUNITA' LEGACY — VERDE, NON COMMITTATO (2026-09-30)

- Spec `docs/specs/0183-quote-attributes-from-legacy-opportunities.xml` (F-1 note libere NON importate; F-2 rev. 2 gruppi
  ISO per norma: generiche 1/70/74/80 tutti i gruppi, norme fuori gruppo nessun gruppo; F-3 card 16 Formazione GOL
  senza campi Offerta; F-4 seeder manuali intoccati; F-5 R&S `res_degree` al posto di `degree`; F-6 adozione per nome:
  figlie manuali con barriere quote/work_order false; F-7 file/anagrafica/Formalab fuori scope; F-8 tabella Sedi ISO
  con colonne indirizzo testo; F-9 categorie legacy auto-padre (2, 7, 8) importate come radici).
- LEGACY (non committato): nuovi `Support/Migration/Attributes/QuoteAttributes.php`, `Support/Migration/IsoStandardGroups.php`
  (`GENERIC_CATEGORY_IDS=[1,70,74,80]`), `Support/Migration/MigrationQuoteLayoutSections.php`; modificati
  `AttributeDictionary`, `MigrationAttributeCards` (306 righe, >300 soft), `MigrationAttributeLayouts`,
  `AttributeLayoutMigrationController` (voci `context:quote`, id `<cat>-quote-all`), `ProductCategoryMigrationController`.
  353 attributi (nuovi 339-353: pre_info_iso_addresses, descrizione_azienda, res_degree, ps_*, responsabile_progetto,
  note_stato, pre_info_progetti_ruoli); 194 voci layout (109 work_order invariate + 85 quote).
- qnet-2: `ProductCategoriesSource::cutManualChildrenFromLegacyFields()` (F-6) e auto-parent = radice (F-9); test in
  `ProductCategoriesSourceImportTest`. Guida `migrations` IT/EN e manuale Claude Docs aggiornati.
- Verifica: scenario production seeder (seeder manuali poi import HTTP reale, 2 passate idempotenti) su SQLite
  temporaneo: 37 attributi e 24 categorie manuali identici allo snapshot, Orientamento Specialistico invariato (vuoto);
  17 categorie Offerta = contratto, 0 "Altre informazioni"; Commessa invariata rispetto alla 0182. Pest 9049 pass / 0 fail;
  Vitest help/migrations/i18n 305; `tsc -b --force`, Pint, `php -l` puliti. Non verificati riga per riga tutti i nomi/
  opzioni del contratto (campione + diff HTTP del teammate legacy).
- F-10 (ordine sezioni come nel legacy su piu' categorie): legacy esporta `sort_order` canonici (card*100+pos*10,
  ISO Offerta 200..280, "Dati contrattuali" 9990); `AttributeLayoutsSource` salva i layout con `canonical_order: true`
  (`AttributeLayoutService::upsert(..., canonicalOrder: true)`, costante `CANONICAL_ORDER_KEY`); il PUT da API/editor lo
  rimuove; `AttributeLayoutMerger` ordina per sort_order (stabile) SOLO se tutti i layout contribuenti hanno il flag,
  altrimenti concatena (layout manuali Formazione invariati: 62/62 risoluzioni identiche a HEAD). Guida
  `product-categories` IT/EN + manuale con la nota sull'ordine. Pest completo 9053 pass / 0 fail.
  I layout gia' importati nel DB di sviluppo non hanno il flag: serve reimport da zero dei layout per vedere l'ordine.
- DB di sviluppo `qnet2`: la categoria 186 "SOA" ha ancora parent_id=186 (ciclo creato dall'import precedente): F-9
  vale per i nuovi import; va corretta a mano o con reimport da zero.
- Aperti: valori opportunita'/offerte (`pre_info_*`); regola "contenitore con una sola card" (categorie SOA vuote) non
  applicata, in attesa di decisione utente.

## SPEC 0182 CAMPI FLESSIBILI COMMESSA DI TUTTE LE CARD DAL LEGACY — VERDE, NON COMMITTATO (2026-09-30)

- Spec `docs/specs/0182-all-cards-work-order-attributes-migration.xml` (estende 0181; E-1 stato per radice
  `stato_consulenza_<R>` (69 resta `stato_consulenza`), anche ISO radice 1; E-2 Azienda->registries, Commerciale/
  Segnalatore->users; E-3 PAL misura = due boolean; E-4 quote solo card proprie; E-5 refusi corretti; E-6 prefissi
  `avv_`/`gare_`/`gol_`; E-7 fuori scope allegati Istruzione, campi classe/edizione GOL; E-8 multi-card = sezioni di tutte
  + un solo "Dati contrattuali"; E-9 ramo Formazione qnet-2 intatto; E-10 categorie con card proprie hanno barriere
  quote/work_order false).
- LEGACY (non committato): nuovi `Support/Migration/Attributes/{AttributeBuilder,AttributeDictionary,CardAttributes,
  TenderAttributes,WorkflowStates}.php`, `Support/Migration/{MigrationCategoryContext,MigrationLayoutSections}.php`;
  modificati `AttributeMigrationController` (1196->1173 righe), `ProductCategoryMigrationController` (card proprie,
  flag `inherits_quote_attributes`/`inherits_work_order_attributes`, `disableCache()`), `AttributeLayoutMigrationController`,
  `MigrationAttributeCards`, `MigrationAttributeLayouts`, `CompanySite`/`OperationalSiteMigrationController`
  (`disableCache()`: il trait Cachable faceva esportare 179 categorie su 183). 338 attributi (nuovi id 235-338 in coda).
- qnet-2: `ProductCategoriesSource` usa i flag per contesto se presenti (`inheritanceFlags()`); test regressione
  `AttributesSourceImportTest` (relation registries/users, tabelle, enum 79 opzioni) e `ProductCategoriesSourceImportTest`.
  Guida `migrations` IT/EN e manuale Claude Docs aggiornati.
- Verifica: import reale HTTP su SQLite temporaneo, 2 passate idempotenti (337 attributi, 183 categorie, 109 layout,
  unico fallimento `provinces`); 17 categorie rappresentative = contratto, 0 "Altre informazioni", 0 categorie con piu'
  stati. Pest completo 9047 pass / 0 fail; Vitest help/migrations/i18n 305; `tsc -b --force` e `php -l` puliti.
- Aperti: valori commesse; radici senza stati (4.0, Fondi, Progetti, Software, HR, ALTRO) = solo campi comuni e nessun layout.

## SPEC 0181 CAMPI FLESSIBILI COMMESSA ISO DAL LEGACY (+ LAYOUT A SEZIONI) — VERDE, NON COMMITTATO (2026-09-30)

- Spec `docs/specs/0181-iso-work-order-attributes-migration.xml` (D-1 solo i campi della pagina `manageorder`;
  D-2 stato_consulenza/standard enum con opzioni legacy; D-3 valori commesse fuori scope; D-4 etichette della pagina;
  D-5 stato_consulenza solo card ISO; D-6 comunicazione_email comune; D-7 codici/chiavi legacy per le tabelle;
  D-8 layout esistente non sovrascritto; D-9 in work_order solo le card dei PROPRI prodotti: la categoria contenitore
  69 non porta piu' ISO+SOA ai figli).
- LEGACY `/Users/Repository/qnet` (non committato, autorizzato dall'utente): `Api/V2/AttributeMigrationController`
  (234 attributi, nuovi in coda id 231-234: data_urgenza_emissione, comunicazione_email, orderiso_verificas table,
  orderiso_details table; nessun id esistente spostato), `Support/Migration/MigrationAttributeCards`
  (`linksForCards(quoteCards, workOrderCards)`), `Api/V2/ProductCategoryMigrationController` (passa le card proprie),
  NUOVI `Api/V2/AttributeLayoutMigrationController` + `Support/Migration/MigrationAttributeLayouts`, route
  `GET /api/v2/migration/attribute-layouts` (gruppo auth:api + v2.access). `AppServiceProvider.php` e
  `config/database.php` legacy erano gia' modificati e NON fanno parte di questa feature.
- qnet-2: NUOVA `Migrations\Sources\AttributeLayoutsSource` (key `attribute-layouts`, fase 6, in
  `QualificaLegacyImportSeeder::SOURCES` dopo `product-category-attributes`); `AttributesSource`: adozione per `code`,
  validazione config `table` (`TableFieldConfigValidator`), `entity_type` `_`->`-`. Etichette `/migrations` IT/EN,
  guida in-app `migrations`, manuale Claude Docs (sorgente Layout attributi).
- `EXTERNAL_MIGRATION_TOKEN` in `backend/.env` sostituito con il nuovo token fornito dall'utente (il vecchio dava 401).
- Verifica: import reale via HTTP da qnet.test su SQLite temporaneo (2 passate, idempotente): Ente_Iso = 26 campi del
  contratto in 5 sezioni senza "Altre informazioni"; 56 layout; unico fallimento atteso `province_id` (provinces senza
  destinazione). Pest completo 9043 pass / 0 fail / 1 skip; Pint, `tsc -b --force`, Vitest migrations/i18n/help 305.
  `QualificaWorkflowSeederTest` e' fallito una volta in un giro completo precedente (flaky, fuori scope).
- Aperti: valori commesse (orderisos, verifiche, standard -> `work_orders.attribute_values`) in spec successiva;
  `product-category-attributes` resta additivo (su DB gia' importato i vecchi link ISO extra vanno tolti a mano o reset);
  il contesto quote usa ancora le card dei discendenti (possibile stessa contaminazione, non toccato).

## SEED PRODUZIONE: STATO "AUTOFINANZIATO" TOLTO DALLE LISTE GOL — VERDE, NON COMMITTATO (2026-09-30)

- `WorkflowStatusCatalogue`: rimosso `'Autofinanziato'` dalle colonne `GOL - <Regione>` (Lombardia, Campania,
  Lazio, Sicilia, Umbria e `GOL_BASE_STATUSES` di Molise/Puglia/Calabria/Basilicata/Abruzzo). Resta definito nella
  sezione GOL perche' `DIL` (che la prende in prestito) e `APL` lo tengono. Conseguenza: il `closed_lost` fissato
  di GOL - Lombardia (e delle regioni base/Lazio/Sicilia/Umbria) diventa "Associato NO _ Altro Ente".
- `QualificaWorkflowSeeder` salta i workflow gia' esistenti: su un DB gia' seminato lo stato NON sparisce da solo
  (va tolto dal configuratore, o serve una migrazione dati se richiesto).
- Test: `QualificaWorkflowSeederTest` aggiornato (pinned closed_lost Lombardia) + nuovo caso di trascrizione.
  Seeding+Products 299, catalog/report/migration 289 passati; Pint ok. Manuale: nessun impatto (guide e Claude Docs
  citano "Autofinanziato" solo come categoria).

## IMPORT LEAD: FONTE SEMPRE OBBLIGATORIA + AUTO-MAPPING "NOME"/"INDIRIZZO E-MAIL" — VERDE, NON COMMITTATO (2026-09-30)

- Delta spec 0176 (D-7/D-8, AC-010/011). `LeadImportFieldCatalog`: `source_id` `required => true` (anche con
  `campaign_code` mappato); 422 su `global_config.source_id` dal flusso generico di `ConfigureImportRequest`.
  Fallback per riga alla Fonte della campagna invariato (difesa per run gia' configurati).
- Auto-mapping: `config/imports.php` alias `nome` -> `full_name`, `indirizzo e mail` -> `email`; nuova chiave
  `contextual_aliases` (`first_name` <- `nome` quando `last_name` e' matchato), applicata da
  `ColumnMapper::applyContextualAliases()` (Step 2 di `suggest`).
- FE: rimossa la regola dinamica D-5 (`dynamicRequiredFieldIds` in `ImportConfigFields`, blocco in
  `import-mapping-schema.ts`, `sourceRequiredRunLevel` in `import-step-mapping.tsx`): il required arriva dal catalogo.
  Prefill Fonte dalla campagna invariato.
- Test adeguati (requisito cambiato): payload configure con `source_id` in LeadsImportWizardFlowTest,
  LeadImportProductInterestTest, LeadImportCampaignFromFileTest (AC-003 + nuovo 422); nuovi casi in ColumnMapperTest;
  FE import-step-mapping-source.test / import-config-fields.test.
- Verifica: `composer test` 9033 passed; Vitest imports+help 361 passed; `tsc -b --force` pulito; ESLint 0 errori;
  Pint ok. Manuale: guide in-app imports IT/EN + Claude Docs (passaggio Mappatura) aggiornati.

## SPEC 0180 CAMPO FLESSIBILE `table` (TABELLA RIPETIBILE) — VERDE, NON COMMITTATO (2026-09-30)

- Spec `docs/specs/0180-table-field-type.xml` (D-1 Attributi + Campi personalizzati; D-2 solo colonne scalari;
  D-3 promemoria email e D-4 migrazione `orderiso_verificas` in spec successive; D-5 colonne modificabili con dati).
- BE: nucleo `App\CustomFields\Table\TableFieldSchema` (`fromConfig(config, required)`, `rules()` chiavi relative,
  `normalize()`, `resolve()`, costanti COLUMN_TYPES/SUMMARY_COLUMN_TYPES/SUMMARY_STRATEGIES/RESERVED_KEYS) +
  `TableFieldConfigValidator`; handler `Types\TableFieldType` (columnType `table`, filterType `text`, sort/filtro su
  `<key>->summary`) + interfaccia opzionale `Types\ProvidesNestedValidationRules`; `CustomFieldValidator` unisce le
  regole annidate e confronta i permessi DOPO `normalizeForStore`. Attributi: rami `table` in
  `AttributeValueValidator`/`AttributeValueNormalizer`, `AttributeColumnBuilder` (sola lettura), cell-edit escluso in
  `WritesAttributeCells`; `DynamicFieldResolver` stampa il summary. `config/custom-fields.php`: `table.max_columns`=20,
  `table.max_rows`=200. `AttributesSource` sample: record `table` (14 tipi).
- Storage/contratto: scrittura `{rows:[{id?,<col>..}]}`, persistito/letto `{rows:[{id uuid,..}], summary}`; errori
  `custom_fields|attribute_values.<key>.rows.N.<col>`. Sugli attributi non c'e' read-resolve centrale: una colonna
  rimossa resta nello stored finche' il record non e' riscritto (invisibile in UI, il FE rende per config).
- FE: `SCALAR_FIELD_COMPONENT_REGISTRY` (nuovo) + `table: TableFieldControl` (desktop tabella con
  `contain-inline-size`, mobile card), `buildTableFieldSchema` (riusato in `attribute-values-schema.ts`),
  `applyTableServerErrors` chiamato centralmente da `applyServerValidationErrors` (`features/auth/form-errors.ts`);
  editor admin `DefinitionTableColumnsEditor` nei form Campo personalizzato e Attributo; `TableSummaryCell` in
  `column-defaults.tsx`; `ColumnType` + `'table'`, `CELL_EDITOR_REGISTRY` ora `Partial` (table senza editor);
  `readonly-table-value.tsx` nel dettaglio commessa. i18n `customFields.tableField.*`/`tableEditor.*`.
- Verifica: Pest completo 9028 pass / 0 fail / 1 skip; Vitest completo 862 file / 6576 pass; `tsc -b --force`,
  ESLint, Pint puliti; Playwright sul dev (admin Campo personalizzato + Attributo, nessun salvataggio) a 375/768/1024
  senza scroll di pagina, scroll interno al campo, radio a selezione singola. Test aggiornati per requisito cambiato:
  `AttributeTableTest` badge 13->14, `FieldTypeRegistryTest` + `table`.
- Manuale: guide in-app `custom-fields`/`attributes` IT+EN (sezioni `table-field`, `table-field-fill`); manuale Claude
  Docs aggiornato (tipi, Tabella, Attributi, compilazione); **PDF derivato da rigenerare**.
- Rev. 2 (decisione utente 2026-09-30, AC-022 rivisto + AC-025): le colonne griglia type=table portano
  `table` = config della definizione (`CustomFieldColumnBuilder`/`AttributeColumnBuilder::resolved()`); la cella
  (`TableSummaryCell` + `format-table-row-inline.ts`) mostra in linea la riga selezionata se `selectable` e riga
  selezionata, altrimenti summary + conteggio; tooltip Radix (hover + focus) con `ReadonlyTableValue` in entrambi i
  casi; senza `column.table` degrada a summary senza tooltip. Verifica: Pest CustomFields 131 / RequestManagement
  806 / Attributes 54; Vitest data-table+custom-fields+request-management+table+work-orders 1265, help 106;
  `tsc -b --force` pulito. Rev. 2 NON verificata nel browser (servono dati demo con un attributo Tabella).
- Aperti/fuori scope: promemoria email (D-3), migrazione verifiche legacy (D-4; bug legacy `setAttivo` non scoped);
  nessuna UI per `color` opzioni/`config` per-colonna (conservati nel round-trip); `min-w-0` sul `<main>` del layout
  e' un fix generale possibile ma non fatto (fuori scope).

## STRESS TEST 100 UTENTI: INTERVENTI S1/S2/S4/S5/S6 — COMMITTATO (2026-09-30)

- Fonte: report "QNet stress test 100 utenti" del 2026-09-29. S3 (buffer pool, FPM, OPcache, `php artisan
  optimize`) resta a cura dell'utente (server/produzione).
- S1, spec 0179 (decisione utente: semantica "parola che inizia con"): migrazione
  `2026_09_30_100000_add_fulltext_search_indexes_to_personal_data_and_contacts` (FULLTEXT, no-op su SQLite;
  gia' applicata al DB locale qnet2, ~29 s). Nuovo hook generico `TableDefinition::applyGroupedSearch($query,
  $columnIds, $term): array` (default `[]`, delegato da `DelegatesUnaugmentedTableMethods`; il motore salta le
  colonne restituite). `App\Tables\RequestManagement\RequestClientSearch`: parole >= 3 caratteri senza
  stopword InnoDB, passo 1 id anagrafiche (scheda + contatti principali, tetto `config/table-search.php`
  `request_client_match_cap` = 5000), passo 2 `quotes.opportunity_id IN (opportunities where registry_id IN
  ...)`. Rimossi `RequestClientColumns::applySearch` e `RequestManagementTableDefinition::applyDerivedSearch`;
  le costanti `CARD_COLUMNS`/`CONTACT_COLUMNS` ora pubbliche. GET columns espone `searchMinLength` = 3 (solo
  request-management/enrollee-management); FE `appliedSearchTerm()` in `use-table-toolbar-state.ts` + suffisso
  placeholder `table.searchMinLength`. Misura 1M a cache calda (richiesta HTTP completa): "rossi" 121 ms,
  "mario" 201 ms, "mar" 447 ms (prima 30-120 s). Una sottoquery unica senza passo 1 e' stata misurata: > 2 min.
- S2: `App\Http\Middleware\LimitStatementDuration` (gruppo api) + `App\Support\Database\StatementTimeout`
  (MariaDB `max_statement_time`, MySQL `max_execution_time`), `DB_STATEMENT_TIMEOUT_SECONDS` default 25 (sotto
  il `request_terminate_timeout` FPM), percorsi esclusi in `config/database.php` `statement_timeout.excluded_paths`
  (import, export, migrazioni, report CSV, download). Errore 1969/3024 -> 503 `{success:false, message}`.
- S4: tab categoria `RequestManagementScopedTableDefinition::baseQuery` = `whereIn(opportunity_id, select
  opportunity_id from opportunity_product_lines where product_category_id)`. Conteggio 2,7 s -> 0,17 s; pagina
  righe ordinata per data 0,07 -> ~0,5 s (compromesso accettato dal report: il motore non distingue conteggio e
  righe). Non toccato: filtro colonna `product_categories` (whereHas) e report, da verificare con EXPLAIN.
- S5: `AggregateCache` lock 120 s, voce trattenuta `retain_seconds` 3600, a timeout del lock serve l'ultimo
  valore (D-4 invariata). Spec 0178 D-3b.
- S6 (decisione utente: "per sede"): widget statistiche opportunita' `by_operational_site` via
  `Aggregates::topRelatedLabelledBy()` + `OperationalSiteLabel::compose`; tolto `by_registry`. Spec 0040/0152.
- Test cambiati per requisito: `QuoteWorkflowMigrationTest` rollback 119; `RequestManagementDefaultColumnsTest`
  ricerca "mario@example" (la punteggiatura separa le parole); `AggregateCacheTest` (AC-014); test stats
  opportunita'.
- Manuale: guide in-app IT/EN request-management + enrollee-management aggiornate; Claude Docs aggiornato
  (Gestione Richieste: paragrafo ricerca; Gestione Iscritti: "ricerca" tra le funzioni uguali).
- Nota server per l'utente: con `innodb_ft_enable_stopword=OFF` o `innodb_ft_min_token_size` diversi da 3
  riallineare le costanti di `RequestClientSearch`.
- Prossimi passi: rilanciare lo stress test con gli stessi script; valutare split di
  `RequestManagementTableDefinition.php` (499 righe, al limite dei 500).

## RIMOSSA L'AZIONE DI RIGA "MODIFICA" DALLE TABELLE CON DETTAGLIO — VERDE (2026-09-30)

- Direttiva utente 2026-09-30: ogni modulo ha la sua pagina/scheda di dettaglio (tutti i `*-screens.tsx` hanno
  `detailOwnsEditAction: true` e inoltrano `onEdit`), quindi `edit` non e' piu' una row action. Stesso schema gia'
  usato da Opportunita'/Offerte/Commesse (2026-08-05/09-02).
- Backend: tolto `edit` dal catalogo `actions()` (40 `*ColumnCatalog`/`RolesTableDefinition`) e da `actionsFor()`
  (+ `TaskRowActionResolver`, mappa di `CommissionConfigurationsTableDefinition`). Docblock aggiornati.
- Frontend: tolti i `case 'edit'` dalle 38 tabelle + Configurazioni provvigioni; `'edit'` fuori da
  `ACTIONS_OPENING_DETAIL` (contratti) e dal `decorateRow` dei ruoli. `useModuleOpener` non espone piu'
  `openEdit` (dead code): l'edit in Sheet si raggiunge solo da `DetailScreen.onEdit`; in page mode dal pulsante
  Modifica della pagina. Le route `:id/edit` restano.
- Test cambiati per requisito (non tampering): array azioni attese senza `edit` (24 file Pest); Vitest su opener,
  sectors/campaigns/leads/projects (edit via Visualizza -> Modifica), products/referents/registries (edit di riga
  ignorato), reward-types open-mode (ora sulla view).
- Guide in-app IT+EN aggiornate (general, sectors, tags, sources, payment-methods, business-functions,
  commission-configurations, products, product-categories, roles, users, custom-fields, reward-types, task-*).
  Manuale Claude Docs: NON accessibile dalla sessione -> da aggiornare a mano (vedi riepilogo in chat).
- Verifica: Pest parallelo 8930 pass/1 skip; Vitest 6525/6526 (unico rosso: timeout 5 s su
  `role-field-permissions-personal-data`, non toccato, verde in isolamento); `tsc -b --force` ok; ESLint e Pint ok.

## FIX AZIONI DI RIGA TASK (icone + label) — VERDE, NON COMMITTATO (2026-09-30)

- Causa: `TaskColumnCatalog::actions()` puntava a `tasks.actions.<x>` (oggetti nei locale FE, non stringhe) e a
  `tasks.actions.request_update` (inesistente: il FE usa `requestUpdate`); `TasksTable` non passava `iconMap`,
  quindi ogni azione di dominio ricadeva sull'icona neutra "...".
- Fix: label backend -> `tasks.actions.<x>.label` / `tasks.actions.requestUpdate.label`; `TASK_ACTION_ICONS` in
  `task-table.tsx` (stessi glifi di `TaskActionsBar`: check->CheckCircle2, badge-x->XOctagon,
  message-circle-question->BellRing, ecc.). Nomi icona backend invariati.
- Verifica: Pest `tests/Feature/Tasks` 626 verdi (nuovo test sulle label in `TaskTableTest`), Vitest tasks+table
  753 verdi, ESLint/Pint/`tsc -b --force` puliti, Playwright su `/tasks`: icona Completa visibile, menu tradotto.
  Manuale: nessun impatto.

## REDESIGN POPUP EMAIL COMMESSE (stile CRM) — VERDE, NON COMMITTATO (2026-09-30)

- Solo FE, `features/work-order-emails/`. Nuovi: `work-order-email-dialog-chrome.tsx` (`WorkOrderEmailDialogHeader`
  icona+titolo+sottotitolo+slot badge / `WorkOrderEmailDialogFooter`, bande `bg-surface`), `work-order-email-attachment-tile.tsx`
  (tile file con icona per MIME, estensione, peso; usata da composer e dettaglio).
- Composer: header/footer fissi, scroll solo sul body interno (`max-h-[70vh]`, `DialogContent` NON clippa: popup
  AsyncPaginatedSelect portalato li'); campi a "busta" (Mittente con avatar, A, Oggetto) con CC/CCN nascosti dietro
  due pulsanti finche' vuoti (sempre visibili se hanno indirizzi); corpo senza label visibile (sr-only); allegati su
  `bg-surface` con barra `Progress` di utilizzo, sorgenti con icone, tile in griglia. Dettaglio: pannello di lettura
  (oggetto, card mittente+data, destinatari a chip, corpo in card, allegati a tile), footer "Chiudi" (`common.close`).
  Picker documenti: stesse bande, righe in card. Badge stato con icona per stato, "Inviata" in tono `success`.
- Dialog: `grid-cols-1` obbligatorio su `DialogContent` con `p-0` (la colonna implicita `auto` sbordava a 375px).
- i18n nuove (IT+EN): `composer.subtitle|addCc|addBcc|subjectPlaceholder`, `detail.noSubject`,
  `composer.attachments.documentsDialogSubtitle`.
- Verifica: Vitest work-order-emails+recipients+help 143 verdi, ESLint e `tsc -b --force` puliti, screenshot
  Playwright headless su `/work-orders/104` (light, dark, 375px). Manuale: guida in-app e manuale Claude Docs non
  descrivono la tab Email (lacuna preesistente, nessuna sezione da aggiornare).
- Noti, fuori scope: `formatBytes(0)` mostra "1 KB" (utilizzo allegati a vuoto); in dark `text-destructive`
  (L 30.6%) ha contrasto basso ("Elimina bozza", asterisco obbligatorio).

## FIX EMAIL COMMESSE: SUGGERIMENTI A/CC/CCN — VERDE, NON COMMITTATO (2026-09-30)

- `components/ui/email-recipients-input.tsx`: la lista suggerimenti si richiudeva ~10 ms dopo il clic. Il campo e'
  l'Anchor (non il Trigger) del Popover Radix: il `focusin` che apre la lista arriva al `document` dopo che il
  `DismissableLayer` ha appena agganciato il suo listener -> "focus outside" -> dismiss. Fix: `onInteractOutside`
  ignora focus/pointerdown dentro l'anchor (`anchorRef`). Test di regressione in `email-recipients-input.test.tsx`;
  verificato anche in Chrome headless su `/work-orders/104`. Manuale: nessun impatto.
- Invio email in locale: `backend/.env` ha ora `OUTBOUND_EMAIL_MAILER=smtp` (Mailpit :1025/:8025). Senza la variabile
  il default e' `microsoft-graph` e senza `MAIL_MSGRAPH_*` fallisce ("Configurazione Microsoft Graph non valida").
  I commenti in `config/outbound_emails.php`/`.env.example` dicono "log in dev" ma il fallback reale e' Graph.

## SPEC 0178 CONTEGGIO UNICO + CACHE AGGREGATI (100 UTENTI, 1M RECORD, NO REDIS) — NON COMMITTATO (2026-09-29)

- Decisioni utente: niente Redis, store `database`; ritardo ammesso sui numeri aggregati (fresco 10 s, max
  5 min); chi scrive vede numeri esatti; CSV report sempre live (nota in spec 0107 D-2); tabelle di
  appoggio NON in cache (D-8, misurato: <1 ms/query e con store database la cache costa quanto la query).
- (A) Griglie: `knownTotal` su POST tables/{domain}/rows: con startRow>0 il server salta count()+aggregates()
  e rimanda il totale; startRow=0 conta sempre. FE `ssrm-datasource.ts` memorizza il totale per firma
  (filtri/search/scope, non sort), `resetKnownTotal()` chiamato da `refreshGrid()`. Limite noto: le due
  `refreshServerSide({purge:false})` dirette (dettaglio premi, notifiche) non azzerano.
- (B) `App\Support\Cache\AggregateCache::remember(key, compute, actor)`: lock sul primo calcolo, un solo
  refresh differito (`defer`), ricalcolo sincrono se l'attore ha scritto dopo il calcolo, errori dello store
  assorbiti, solo array/scalari (lezione CustomFieldProvider). Middleware `RecordActorWrite` (api, terminate):
  scritture 2xx tranne rows/values/form-context. `cache:prune-expired` orario a blocchi. Config
  `config/aggregate-cache.php`. Chiavi: tab/dashboard/report per `RequestManagementScope::scopeFingerprint()`
  (sedi hashate; DEVE cambiare con scopeToActor), stats `global` o `user:{id}` (`StatsDefinition::cacheScope`),
  values per utente; lingua nella chiave dove ci sono etichette (report, stats, values).
- (C) Tendine for-select operative (15 resource D-7): `include_total=0` -> limit+1, `total: null` +
  `pagination.has_more`; `ForSelectQuery::page()`, `paginatedResponse(?int $total, ..., ?bool $hasMore)`.
  FE `use-for-select.ts` invia include_total=0, fallback sul totale per i resource non migrati.
- Test preesistenti adattati per cambio requisito (cache): `RequestManagementReportOperatorsEndpointTest` e
  `ProductUsageTest` (`Cache::flush()` dopo dati creati via factory), for-select FE (includeTotal).
- Verifica: Pest completo 8895 passati / 1 skipped (8896), Pint pulito; Vitest 6525/6525 (852 file),
  `tsc -b --force` pulito. ESLint: 2 errori PREESISTENTI in file non toccati (`quotes/column-renderers.tsx:11`,
  `registries/registry-form-metadata.test.tsx:271`). Flaky preesistente: `DemoOpportunitySeederTest` INV-1/INV-2
  (seeder casuale a volte senza opportunita' multi-riga).
- Misura 1M (store database, super-admin), fredda -> cache: tab 2,4 s -> 7 ms, dashboard 3,8 s -> 17 ms,
  filtri report ~1 s -> 3-7 ms, stats opportunita' 4,8 s -> 7 ms, values anagrafica 5,6 s -> 10 ms,
  tendina offerte con ricerca 1,58 -> 0,83 s, pagina 2 griglia senza count.
- Guide in-app IT/EN request-management + enrollee-management aggiornate (contatori entro pochi secondi, CSV
  live). Manuale Claude Docs: da aggiornare stesse frasi (Gestione Richieste: schede categoria e statistiche;
  Gestione Iscritti: statistiche).
- Prossimi passi: spec ricerca full-text (ricerca rapida Gestione Richieste ancora 27-31 s anche senza count),
  ordinamenti su colonne collegate, configurazione server (buffer pool, OPcache, FPM).

## STRESS TEST 1M RICHIESTE: API LENTE — COMMITTATO (2026-09-29)

- Misurato con harness fuori repo (kernel HTTP + DB::listen, super-admin) sul DB locale con ~1M
  richieste/opportunita'/anagrafiche. Lead, commesse, contratti, task, segnatempo: tutti < 400 ms (tabelle
  piccole). Lento solo cio' che tocca quotes/opportunities/registries.
- Dashboard report (`RequestManagementDashboardBuilder`): una sola `rows(All)` invece di TotalOnly +
  OperatorsOnly (ricalcolava tutto due volte); con un solo ramo il riepilogo riusa la sua riga TOTALE.
  `QuoteCountAggregator::partitioned()`: totale = somma dei gruppi per gli indicatori partizionati per
  operatore (spec 0106 D-10 rev. 2026-09-29; "Aziende inserite" tiene la query propria, AC-015).
  `ReportBranchRowsBuilder` calcola una volta le colonne con la stessa formula. 13,9 s -> 2,4 s (1 categoria).
- Tab categoria (`RequestCategoryTabsResolver`): conta da `quotes` raggruppando per id, nomi letti dopo.
  Admin 4,5 s -> 2,5 s, commerciale 0,34 -> 0,20 s. Poi in cache con la spec 0178 (7 ms a cache calda).
- Statistiche (`Aggregates::topRelated`, 17 widget): top N raggruppato sulla FK, etichette lette dopo
  (opportunita' 11,5 s -> ~3 s). Valori filtro (`OpportunityRelationColumns`/`QuoteRelationColumns`):
  GROUP BY label al posto di DISTINCT (MariaDB materializzava il semi-join), nomi manager via EXISTS sul
  pivot, "(Vuoti)" manager controllato solo sul pivot (user_id cascade).
- Migrazione `2026_09_29_130000_add_created_at_index_to_opportunities_and_registries` (sort di default delle
  griglie senza indice); rollback count di `QuoteWorkflowMigrationTest` -> 118. Applicata al DB locale
  (insieme alla `..._120000_prune_receive_transfer_notifications_permissions`, non ancora eseguita li').
- Verifica: Pest completo 8789 verdi (1 skipped), Pint pulito. Manuale: nessun impatto (stessi valori).
- Ancora lenti (non toccati): ricerca rapida Gestione Richieste 56-62 s (LIKE %x% su 6 campi via 3
  tabelle, serve spec), ordinamento per cognome 6,7 s, pagine profonde (OFFSET), controllo "(Vuoti)" sulle
  relazioni sempre valorizzate (~3,7 s, scansione anti-join completa). Ambiente: `innodb_buffer_pool_size`
  128 MB in locale (my.cnf vuoto): con 2 GB i report scendono ancora ~40%.

## CATEGORIA PRODOTTO IN CELLA: SOSTITUZIONE DIRETTA SU CATEGORIA A RIGA SINGOLA — NON COMMITTATO (2026-09-29)

- Segnalazione utente: dalla griglia Gestione Richieste (ruolo commerciale) non si cambiava la categoria, dal form si'.
  Root cause: NON permessi (backend verificato: `product_lines` editabile, PATCH riuscito su 39/40 richieste del
  commerciale id 12, l'unico 422 e' la coerenza prodotti di interesse, voluta). Era `ProductLinesCellEditor`: con una
  coppia che risolve a `single` (es. Formazione) disabilitava ricerca e opzioni finche' non si rimuoveva la coppia.
- Decisione utente: sostituzione diretta. In `single` la scelta sostituisce la coppia (`[next]`), in `multiple` si
  aggiunge come prima; la categoria gia' presente resta non selezionabile. Messaggio `singleModeReached` riscritto
  IT/EN e mostrato SOTTO il testo del passo. Spec 0077 nuovo AC-046. Backend invariato.
- Test: `product-lines-cell-editor.test.tsx` (test INV-3 aggiornato: requisito cambiato; + duplicato in single;
  multiple ora verifica l'append). Vitest product-lines/help/request-management/table/data-table verdi, ESLint e
  `tsc -b --force` puliti.
- Seconda direttiva (stesso giorno, spec 0077 AC-047), tabella E form: con un'altra riga gia' classificata le RADICI
  `single` sono elencate ma disabilitate (nota `productLines.singleRootBlocked`), perche' renderebbero `single` una
  scheda con piu' righe. Helper `isSingleRootBlocked()` in `category-tree-scope.ts`; form: `otherRowsFilled(index)`
  / `singleRootsBlocked` in `useProductLinesField` -> prop `otherRowsFilled` di `ProductCategoryRootSelect`; griglia:
  `addingBesidePairs` nell'editor. Vale per tutti i form con `ProductLinesField` (opportunita', richieste, progetti,
  campagne). Due test di `product-lines-field-management-mode.test.tsx` sceglievano una radice single sulla riga 2
  (requisito cambiato): ora usano una seconda radice multipla aggiunta al fixture. 1507 test verdi.
- Manuale: guida in-app request-management IT/EN (paragrafo sulla cella Categoria prodotto nella sezione overview)
  e product-categories IT/EN (riga "Modalita' di gestione: Singola"); Manuale Utente QNet Claude Docs aggiornato
  (paragrafo dopo la tabella colonne di Gestione Richieste + riga "a riga singola" della tabella modalita').
  PDF derivato non rigenerato.

## DETTAGLIO RICHIESTA INESISTENTE / NON VISIBILE: STATO "RECORD NON DISPONIBILE" — NON COMMITTATO (2026-09-29)

- Segnalazione utente: `/request-management/203001` (404/403) mostrava "Impossibile caricare il record." + Riprova.
  Direttiva: gestire come q-net (`q-net/src/app/pages/errors/*`): logo brand in maschera `bg-primary`, titolo,
  descrizione, nessun Riprova. Backend invariato (gia' 404 envelope / 403 da `assertInScope`).
- Nuovi: `lib/record-unavailable-reason.ts` (`recordUnavailableReason(error)` -> `'notFound' | 'forbidden' | null`,
  solo axios 404/403) e `components/detail/record-unavailable.tsx` (`<RecordUnavailable reason title description />`,
  card `bg-card`, badge icona sul logo: `SearchX` 404 / `Lock` 403; testi passati dal chiamante). Verificato sul
  DB locale: 203001 esiste -> 403 per utente senza viewAll (id 12), 200 per id 1; id inesistente -> 404. `RequestWorkPanelScreen` lo usa prima del ramo errore generico (vale anche per
  Gestione Iscritti). i18n: `requestManagement.workPanel.unavailable.{notFound,forbidden}.{title,description}`
  (non in `common`: `en.ts` e' a 499 righe, limite 500).
- `app/query-client.ts`: niente retry automatico sulle query in 404/403 (prima 1 retry = ~1s di skeleton inutile).
- Guida in-app IT/EN `request-management` > "Lavorare una richiesta": paragrafo sui due stati. Manuale Claude Docs
  NON aggiornato (connettore senza accesso al doc): sezione Gestione Richieste da allineare.
- Prossimo passo proposto: estendere `RecordUnavailable` alle altre schermate di dettaglio (`useEntityDetail` +
  `DetailError`, ~30 moduli) e alla pagina Gestione Iscritti nella guida.

## GESTIONE RICHIESTE: RITORNO ALLA TABELLA DOPO "TRASFERISCI CONTATTO" DAL PANNELLO — NON COMMITTATO (2026-09-29)

- Bug utente: trasferendo il contatto dal pannello "Lavora" si restava sul pannello (ricaricato), che l'operatore
  non puo' piu' gestire (fuori dal suo perimetro D-3 senza `viewAll`).
- Fix FE: `useRequestTransfer(panel, onTransferred?)` al successo chiama l'uscita dell'host (lo stesso `onSaved`
  del salvataggio) invece di invalidare `requestManagementKeys.panel`: in modale chiude lo Sheet + `refreshGrid`
  (righe + tab categoria), in pagina `navigate(LIST_PATH)`. Vale anche per Gestione Iscritti (stesso pannello).
  Doc aggiornata di `ModuleDetailScreenProps.onSaved` e `RequestWorkPanelScreenProps.onSaved` (save o transfer).
- Test: `request-work-panel-transfer.test.tsx` (requisito cambiato: prima "refreshes the panel", ora "leaves the
  panel", niente refetch; su errore `onSaved` non chiamato). Vitest request-management/modules/pages 539 verdi,
  help 106 verdi, ESLint e `tsc -b --force` puliti.
- Manuale: guida in-app IT/EN `request-management` (sezione lavorazione) + manuale Claude Docs (dopo il passo "Salva").

## SEED BULK DI RICHIESTE (1M PER TEST DI VOLUME) + FIX CODICI OLTRE 9999 — NON COMMITTATO (2026-09-29)

- Direttiva utente: >1M richieste nella griglia Gestione Richieste per test di volume; scelta la modalita' bulk.
  `php artisan qualifica:seed-sample --bulk --requests=1000000` (default 1000). Con `--bulk` qualsiasi altro flag di
  dimensione -> INVALID. Seeder `QualificaSampleBulkRequestSeeder`: crea fino a 150 MODELLI dal write path reale
  (`Support/SampleRequestTemplates`: Lead seeder + Request seeder in transazione poi ROLLBACK, righe lette prima),
  poi copia con INSERT multi-riga, 500 richieste per transazione, id assegnati da MAX(id) (DB senza altri scrittori).
  Ogni copia: anagrafica propria (`Support/BulkRegistryRows`: registry + personal_data + email/phone primari),
  codice QUO sequenziale, titolo/nome via `RevenueProductTitleBuilder::compose()`, team/Fonte/Sede ruotati,
  created_at negli ultimi 365 giorni in orario 08-19 (evita il buco DST: app UTC, MySQL locale in Europe/Rome).
  Niente activity log ne' notifiche. Misurato su MySQL locale: ~1.800 richieste/s, 75 MB costanti (1M ~10-15 min).
- BUG REALE CORRETTO (decisione utente): `GeneratesSequentialCode` usava MAX(code) su stringa -> oltre 9999
  ("QUO-9999" > "QUO-10000") ogni nuovo codice collideva (500). Ora ordina per LENGTH poi valore
  (`lastCodeQuery()`), vale per Offerte/Commesse/Progetti/Campagne/Prodotti. Costo: scansione dell'indice del
  prefisso a ogni creazione. Nuovo `formatSequentialCode()`; `QuoteService::CODE_PREFIX` ora public.
- `PicksFreeRegistries::freeRegistries($guard, $limit)`: a chunk e si ferma a $limit (prima caricava TUTTE le
  anagrafiche: OOM con 1M). Request seeder passa `$requests`, Opportunity seeder batch + categorie incomplete.
- `RevenueProductTitleBuilder`: `compose()`, `opportunityCode()`, `quoteRevenueProductNames()` pubblici.
- Test: nuovo `QualificaSampleBulkRequestSeederTest` (12), 2 nuovi in `ProjectCodeGenerationTest` (PRJ-10000/10001).
  Suite completa 8789 verdi (1 skipped), Pint pulito. 1M NON ancora eseguito sul DB locale (lo lancia l'utente).
- Manuale: nessun impatto (comando di sviluppo; il fix dei codici non cambia nulla di visibile sotto 10.000).

## NOTIFICHE DI TRASFERIMENTO SOLO A COINVOLTI + "VISUALIZZA TUTTI" — NON COMMITTATO (2026-09-29)

- Decisione utente: la notifica di trasferimento arriva a chi aveva il contatto, a chi lo riceve e a chi ha
  `{module}.viewAll`; nessun altro. Causa del "arriva a tutti": il blocco `OWN_REQUESTS` del catalogo ruoli dava
  l'intero modulo meno le negate, quindi anche `receiveTransferNotifications` a ogni operatore.
- BE: `RequestTransferService::supervisors()` usa `SUPERVISORY_ABILITY = 'viewAll'`; rimossi
  `RequestManagementPolicy::receiveTransferNotifications()` e l'ability (Enrollees ora 16 abilita');
  migrazione `2026_09_29_120000_prune_receive_transfer_notifications_permissions` (cancella `%.receiveTransferNotifications`,
  down no-op). Rollback count di `QuoteWorkflowMigrationTest` -> 117. Spec 0081 rev. 2026-09-29 (AC-020/021).
- Guide in-app IT/EN: nuova sezione `transfer-notifications` in request-management, riga in enrollee-management.
  Manuale Claude Docs aggiornato (Gestione Richieste > azioni sulle righe, Gestione Iscritti).
- Verifica: Pest suite completa verde (unico rosso era il rollback count, corretto e rieseguito), Pint pulito;
  Vitest help 106 verdi, ESLint e `tsc -b --force` puliti.

## TAB CATEGORIA GESTIONE RICHIESTE SEMPRE AGGIORNATE — NON COMMITTATO (2026-09-29)

- Bug utente: cambiando la categoria di una richiesta dal pannello "Lavora", al ritorno in tabella la barra delle
  tab categoria (elenco + contatori `requests_count`) restava vecchia fino a F5. Le righe SSRM erano gia' fresche
  (verificato con Pest e con Playwright headless sul dev): la causa era `useRequestManagementCategories` con
  `staleTime` 5 min e nessuna invalidazione.
- Fix FE: tolto lo `staleTime` (rilettura a ogni mount della tabella, cache mostrata nel frattempo; il cambio tab non
  rimonta l'owner, quindi niente refetch li'); nuovo `useInvalidateRequestManagementCategories` chiamato dentro
  `refreshGrid` di `RequestManagementTable` (salvataggio modale, delete, assegnazioni, trasferimento). Vale anche per
  Gestione Iscritti (stesso componente, chiave `module.key`).
- Verifica: Vitest request-management 451 verdi (+2 test in `request-management-table.test.tsx`), ESLint e
  `tsc -b --force` puliti. Manuale: nessun impatto.

## SESSO ANAGRAFICA SENZA DEFAULT — NON COMMITTATO (2026-09-29)

- Decisione utente: il sesso di una scheda persona fisica NON e' piu' preimpostato a maschio; se non compilato resta
  vuoto (null). Tolti tutti i fallback `'male'` in FE (`drafts.ts`, `personal-data-card-form.tsx`,
  `request-create-payload.ts`, `use-request-work-form.ts`, `request-work-payload.ts`); select con placeholder
  `personalData.form.genderPlaceholder` (IT/EN). BE: tolto `#[IsDefault]` da `GenderEnum::Male`; l'import legacy
  `ReferentsSource` lascia null un sesso vuoto/sconosciuto (warning "left blank"). Colonna gia' nullable, nessuna migrazione.
- I dati gia' salvati come maschio per effetto del vecchio default restano tali (non distinguibili da una scelta reale).
- Verifica: Pest 224 + 294 verdi, Pint pulito; Vitest 861 verdi, ESLint e `tsc -b --force` puliti. Manuale Claude Docs aggiornato.

## SEED SENZA EMAIL, CON NOTIFICHE IN-APP — NON COMMITTATO (2026-09-29)

- Direttiva utente: nei seeder nessuna email deve partire; le notifiche in-app vanno bene.
- `Concerns/SeedsWithoutNotifications` (faceva `Notification::fake()`: bloccava ANCHE le in-app) rinominato in
  `Concerns/SeedsWithoutMail::withoutMail(callable)`. Durante il callback il dispatcher e'
  `Database\Seeders\Support\MailSuppressingChannelManager`: `send()` -> `sendNow()` (ignora `ShouldQueue`, altrimenti
  il job mail finirebbe a un worker col dispatcher reale) e canale `mail` no-op. Il dispatcher precedente e' ripristinato.
- Applicato agli orchestratori `DemoDataSeeder`, `QualificaSampleDataSeeder` (quindi anche `qualifica:seed-sample`),
  `QualificaProductionDataSeeder`, e ai leaf che gia' lo usavano (`DemoTaskSeeder` — rimossa la sua copia privata —,
  `QualificaSampleTaskSeeder`, `QualificaSampleWorkOrderSeeder`). Un leaf lanciato da solo con `--class` fuori da
  questi (es. `DemoOpportunitySeeder`) NON e' coperto: se serve, aggiungere il trait anche li'.
- Test: `tests/Feature/Seeding/SeedsWithoutMailTest.php` (nuovo); `DemoTaskSeederTest` aggiornato (requisito cambiato:
  ora in-app > 0 e zero `MessageSending`). Suite completa verde (8771 pass, 1 skip). Manuale: nessun impatto.

## SPEC 0177 PRIMO ACCESSO UTENTE + EMAIL DI BENVENUTO — REV. 1 COMMITTATA (70f4b270), REV. 2 NON COMMITTATA (2026-09-29)

- Spec `docs/specs/0177-user-first-access.xml` (approvata). Decisioni utente: modalita' "Entrambi" (invito con
  link di default, password provvisoria facoltativa), reinvio dal dettaglio utente. REV. 2 (UX, decisione utente
  dopo prova): il cambio al primo accesso e' CONSIGLIATO e RIMANDABILE, niente piu' blocco server-side ne' pagina.
- Stato = `users.must_set_password` (bool, default false, NON fillable, solo forceFill). Nessun backfill: utenti
  esistenti, `UsersSource` (migrazione legacy), seeder e factory restano false e senza email. Il flag si accende
  SOLO da `POST /api/users` (`UserController::store` -> `App\Services\Users\UserOnboardingService::start`, dopo
  il commit; un dispatch fallito non fa fallire la 201) e da `PATCH /api/users/{user}` con password su un ALTRO
  utente (`UserService::update`). Si spegne con set-password, `PUT /auth/me/password`, reset password dimenticata.
- Token di invito: broker `users_setup` su tabella dedicata `password_setup_tokens` (72h), mai mescolato con
  `password_reset_tokens`. Notifica `WelcomeUserNotification` (token null = variante password provvisoria, link
  al login, password MAI nel contenuto). Viste `emails.welcome-user(-plain)`.
- Rotte: `POST /api/auth/set-password` (pubblica, throttle:6,1), `POST /api/users/{user}/resend-welcome`
  (UserPolicy::update, 422 `user` se gia' attivato), `PUT /api/auth/me/first-password` (rev. 2, throttle:6,1, solo
  nuova+conferma; `SetFirstPasswordRequest`: 403 con envelope via `failedAuthorization()` se niente flag o token
  di impersonificazione, 422 se uguale all'attuale; riusa `AuthService::changePassword`).
  `UpdatePasswordRequest`: nuova password `different:current_password`. Middleware `password.set` RIMOSSO (rev. 2).
- FE: `/set-password` (pubblica, `ResetPasswordForm` parametrizzato). Rev. 2: `features/auth/first-access-dialog.tsx`
  montato in `layouts/app-layout.tsx` ("Benvenuto in QNet, {nome}!", Nuova/Ripeti password, "Piu' tardi" =
  stato locale -> ricompare a ogni accesso/ricarica; nascosta in impersonificazione). Pagina `/first-access` e
  redirect in `ProtectedRoute` RIMOSSI. Dettaglio utente: badge "In attesa di primo accesso" + "Reinvia email di benvenuto"
  (`use-resend-welcome-email.ts`). Password facoltativa in creazione (`user-schema.ts`, `user-form-payload.ts`).
- Manuale: guide in-app IT/EN `users.ts` (`create-user`, `reset-password`, nuova `welcome-email`) e `general.ts`;
  Claude Docs "Manuale Utente QNet" aggiornato (Primi passi > Primo accesso; Utenti > campo password,
  reimpostazione, Email di benvenuto e primo accesso).
- Verificato (verifier indipendente): `composer test` 8763 passed / 1 skipped; Spec0177 17/17; vitest 6490/6490;
  eslint e `tsc -b --force` puliti; Pint pulito. Test esistenti aggiornati per requisito cambiato:
  `MetaEndpointTest` (password non piu' required in create), `QuoteWorkflowMigrationTest` (rollback step +2).
- Rev. production seed (decisione utente 2026-09-29): chi riceve la password condivisa del seed viene marcato
  `must_set_password` tramite il concern `Database\Seeders\Concerns\AssignsSeedPassword`, senza email.
  Riguarda `TestUsersSeeder` (a ogni run, perche' ogni run ripristina la password), `QualificaOperatorSeeder`
  (solo alla creazione) e `QualificaStaffSeeder` (solo creazione).
  Toggle `config('seeding.force_password_change')` / env `SEED_FORCE_PASSWORD_CHANGE`: il default e' true, false
  solo se `APP_ENV=staging` (produzione e locale si', staging no). Se lo staging usa un altro `APP_ENV`, impostare
  la env a false. Gli ambienti gia' seedati non vengono sistemati (decisione utente). AC-022 in
  `tests/Feature/Spec0177/SeedPasswordChangeTest.php`; la suite completa e' verde (8768 passed).
- Rev. 2 verificata: `composer test` 8768 passed / 1 skipped; Spec0177 21/21; vitest 6491/6491; eslint e
  `tsc -b --force` puliti. Manuale Claude Docs e guide in-app riallineati (consigliato, "Piu' tardi").
- Aperti: `frontend/src/i18n/locales/en.ts` a 499 righe (hard limit 500) -> split al prossimo intervento;
  colonna/filtro "In attesa di primo accesso" nella tabella Utenti fuori scope (evoluzione possibile).

## SEED: ACCOUNT PRIVILEGIATI NEL PRODUCTION — NON COMMITTATO (2026-09-29)

- `TestUsersSeeder::TEST_USERS`: super-admin = Ciro Cacciapuoti + Nicola Eliseo; ruolo `admin` = Mario Esposito +
  Enrico Ferrante. Tolti da `StaffRoster` (create-only, non competono sul ruolo). `TestUsersSeeder` ora chiama
  `QualificaRoleSeeder` (gira prima degli step 7-8 nella catena).
- Ruolo `admin` (`OperatorRoleCatalogue::ADMIN_ROLE`/`ADMIN_DESCRIPTION`): TUTTO il catalogo permessi, nessuna
  restrizione di campo, sincronizzato a parte in `QualificaRoleSeeder` (step 4) — NON e' in `ROLES` (non e' una
  mansione: i test delle mansioni ne verificano i limiti). Resta fuori da cio' che e' solo super-admin
  (assegnare super-admin, Migrazioni, impersonare un super-admin).
- DB esistente: rilanciare il production seed promuove i 4 account (upsert per email) e RIPRISTINA la loro
  password condivisa (`config('seeding.password')`), come gia' per Ciro.

## SEED: PRODOTTI NEL PRODUCTION + COPERTURA CATEGORIE NEL SAMPLE — COMMITTATO (2026-09-29)

- Production: `QualificaLegacyImportSeeder::SOURCES` include ora `products` (fase 5, dopo
  `product-category-attributes`); `QualificaProductionDataSeeder` chiama `ProductTypologySeeder` prima
  dell'import (la tipologia si risolve per NOME da `folder`).
- Sample (`qualifica:seed-sample`), decisioni utente: copre ogni categoria VENDIBILE (selezionabile + BF
  effettiva + almeno un prodotto proprio SALE), IN AGGIUNTA al batch (le size contano le righe oltre la
  copertura); livello piu' profondo = Commessa se `generates_contract`, altrimenti Offerta (Formazione).
  Classe `Database\Seeders\Support\SampleCategoryCoverage` (incomplete/unquoted/uncontracted/unprogrammed +
  `pickCovering`). La catena aggiunge 1 lead per categoria da coprire; lo step Opportunita' apre i deal di
  copertura SOLO se riceve `sinceOpportunityId` (dalla catena); Offerte di copertura mai perse, Contratti di
  copertura sempre "validato"; `PicksDemoOffers::pickOfferFor()`.
- Commesse: ognuna con un Modello di Task attivo, il meno usato prima (tutti usati prima di ripetere); il
  generatore gira come l'attore del seed (`Auth::setUser` ripristinato) con notifiche soppresse.
- Test: nuovo `QualificaSampleCategoryCoverageTest`; conteggi aggiornati (requisito cambiato: +1 lead/
  contratto/commessa per la categoria del fixture) in `QualificaSampleDataSeederTest`,
  `SeedSampleDataCommandTest`, `QualificaSampleDealFlowSeederTest`; import `products` in
  `QualificaLegacyImportSeederTest`, tipologie in `QualificaProductionDataSeederTest`.
- Da sapere: `QualificaSampleQuoteSeeder` e' a 347 righe (>300 soft). Non ancora eseguito sul DB locale reale.

## FIX MIGRAZIONE PRODOTTI: TIPOLOGIA DA `folder` LEGACY — NON COMMITTATO (2026-09-29)

- `ProductsSource` ignorava `folder` del legacy (`"Ente"`/`"Consulenza"`): ogni prodotto finiva sulla tipologia
  di default. Ora `folder` -> `product_typology_id` risolto per NOME della tipologia, case-insensitive (niente
  stringhe hardcoded, spec 0099 AC-054); assente -> default silenzioso; senza match -> default + warning.
  Colonna preview `folder` aggiunta. Test in `ProductsSourceImportTest` (2 nuovi).
- Da sapere: i prodotti gia' migrati vengono SALTATI al re-import (idempotenza su old_source/old_id), quindi
  la tipologia errata va corretta cancellandoli e rimigrando. Aperti (segnalati, non fatti): `product_type`
  legacy arriva `"service"` minuscolo -> warning spurio su ogni riga; il campo custom `folder` del template
  Qualifica (opzioni `ente`/`consulenza`) riceverebbe il valore grezzo `"Consulenza"` via auto-persist.

## SPEC 0176 FONTE OBBLIGATORIA SUL LEAD, EREDITATA DALLA CAMPAGNA — NON COMMITTATO (2026-09-28)

- Spec `docs/specs/0176-lead-source-required.xml`. Decisioni utente: Fonte facoltativa sulla Campagna; sul Lead
  precompilata dalla campagna ma modificabile; nessun backfill; import = precompilata dalla campagna, modificabile.
- BE: migrazione `2026_09_28_160000_add_source_id_to_campaigns_table` (FK restrictOnDelete, `SourceService::delete`
  blocca Fonti usate da campagne); `Campaign::source()`, `Source::campaigns()`; campaign request/DTO/resource
  (`source_id`, `source {id,name}`), for-select `meta.source`, colonna tabella `source` (set, ordinabile).
  `App\Services\Leads\LeadSourceResolver::resolve(?sourceId, ?campaignId)` = unica regola "propria, altrimenti
  della campagna": usata da Store/UpdateLeadRequest (`Rule::requiredIf`), `LeadService::create/update`,
  `LeadRowValidator` (errore di riga "The source is missing"). `ImportRowContext` ha ora `globalConfig`
  (passato da `StagedRowBuilder`). Ceiling permessi Lead: `source_id` mandatory + required; inline `source` non
  nullable. PATCH senza `source_id` resta valido (lead legacy senza Fonte).
- Test: nuovi `LeadSourceRequiredTest`, `CampaignSourceTest`, `LeadImportSourceTest`; fixture esistenti aggiornate
  (requisito cambiato). `QuoteWorkflowMigrationTest` rollback `--step 114` (coordinato con la sessione 0175).
  Suite BE completa verde.
- FE (teammate): form Campagna (select Fonte), form Lead (Fonte obbligatoria + prefill da `meta.source`), wizard
  import (prefill Fonte dalla campagna), guide in-app IT/EN. Manuale Claude Docs aggiornato (campi Lead,
  creazione Campagna, configurazione import).

## SEED PRODUZIONE: VIA "TRATTATIVE IN CORSO", FUNZIONE "CONSULENZA" — NON COMMITTATO (2026-09-28)

- `QualificaCatalogSeeder::CATALOG`: "Consulenza" ha una sola foglia, "Presa Appuntamenti";
  `ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES = ['Presa Appuntamenti']`.
  Il seeder NON cancella la riga "Trattative in Corso" dove esiste gia' (firstOrCreate): va eliminata a mano.
- `QualificaBusinessFunctionLinkSeeder`: `OWN_FUNCTIONS = ['Consulenza']` (creata se nessuna funzione con quel nome
  esiste, altrimenti adottata), link `'Presa Appuntamenti' => 'Consulenza'`; `link()` ora salta anche lo slot gia'
  coperto da un antenato (invariante spec 0023, una funzione per catena).
- Test aggiornati (requisito cambiato, dichiarato): Catalog/ContactProcessing/QuoteLayout/Workflow/LegacyImport/
  BusinessFunctionLink seeder test. `composer test` verde (8681 pass). Manuale: nessun impatto.
- Aperto: "Questa categoria ha sottocategorie o prodotti" all'eliminazione. `product-categories-table.tsx` mappa ogni
  409/422 su `deleteInUse`, ma il 409 arriva anche per opportunita' collegate; le FK restrict (progetti, campagne,
  provvigioni, `employment_product_lines`) danno 500. In locale "Trattative in Corso" si elimina (204): serve sapere
  quale categoria/ambiente.

## SPEC 0175 EMAIL DALLA COMMESSA — VERDE, NON COMMITTATO (2026-09-28)

- Spec `docs/specs/0175-work-order-emails.xml` (implemented). Verifier finale VERDE: ambito 0175 416/416 Pest,
  vitest/tsc senza errori nei file 0175, Pint pulito, AC-001..AC-023 PASS. I rossi del run completo sono della
  spec 0176 (campaigns.source_id) di un'altra sessione sullo stesso working tree.
- BE: tabelle `email_templates`, `document_bundles`, `outbound_emails`; moduli CRUD SSRM `email-templates`,
  `document-bundles` (TableDefinition + for-select ADR 0011, `GET /email-templates/variables`); segnaposto
  `WorkOrderEmailVariableCatalog`/`WorkOrderEmailVariableResolver` (valori e()-escaped nel body), `EmailHtmlSanitizer`
  (RichTextSanitizer + strip img); API `routes/api/work-order-emails.php` (bozze private dell'autore = 404 per gli altri,
  409 fuori da draft, send draft|failed -> queued 202, allegati upload/import documents|document_bundle|quote_pdf con
  limite `outbound_emails.max_total_attachments_kb`, download annidato); invio `SendOutboundEmailJob` (tries=1) via
  mailer `microsoft-graph` (`App\Mail\Transport\MicrosoftGraphTransport` + `App\Services\Graph\GraphMailClient`,
  env `MAIL_MSGRAPH_*`, `OUTBOUND_EMAIL_MAILER`); `StagingMailRedirector` copre anche il mailer outbound.
  Permessi `email-templates.*`, `document-bundles.*`, `work-orders.viewEmails|sendEmail`.
- Migrazione: sorgenti `email-templates`, `document-bundles` (fase 1 + `QualificaLegacyImportSeeder`); legacy
  `/Users/Repository/qnet` controller `EmailTemplateMigrationController`, `DocumentBundleMigrationController`
  (NON committati, come 0174).
- FE: `features/{email-templates,document-bundles,work-order-emails}`, tab Email in `work-order-detail.tsx`,
  `RichTextEditorHandle`/`allowImages`, `EmailRecipientsInput`, hook condiviso `src/hooks/use-caret-insertion.ts`.
- Manuale: guide in-app IT/EN email-templates, document-bundles, migrations; guida work-orders lasciata "in sviluppo"
  (bozza sezione Email pronta nello scratchpad della sessione); manuale Claude Docs: sezioni Modelli email/Modelli
  documenti + nota migrazioni.
- Da verificare: invio reale Graph solo con credenziali M365 in un ambiente di test (i test usano Http::fake).
  Fuori scope (spec successive): reinvio ricorrente, email da offerta/opportunita', immagini nel corpo.
- Migrazioni: dopo 0175+0176 `QuoteWorkflowMigrationTest` conta 114 step di rollback.

## GESTIONE RICHIESTE: RITORNO ALLA TABELLA DOPO IL SALVATAGGIO — NON COMMITTATO (2026-09-28)

- Direttiva utente 2026-09-28: dopo creazione o modifica di una richiesta il sistema torna alla tabella, sul tab
  categoria di partenza (persistito in localStorage da `useRequestManagementCategoryPreference`, nessun cambio li').
- `ModuleDetailScreenProps.onSaved?` (nuovo, `features/modules/types.ts`): un DetailScreen che salva da se'
  (solo il work panel) lo chiama; `useModuleOpener` lo cabla nel ramo `view` -> chiude lo Sheet + `onSaved` del caller.
- `ModuleRegistryEntry.returnToListOnSave?` (nuovo): `ModuleFormPage` naviga a `basePath` invece che al dettaglio;
  attivo solo su `request-management` (create in page mode). In modal il create chiudeva gia' lo Sheet.
- `RequestWorkPanelScreen`/`useRequestWorkForm(panel, onSaved?)`: `onSaved` dopo il PATCH riuscito (non su 422/errore).
  Le pagine dettaglio `request-management/:id` e `enrollee-management/:id` passano `navigate(LIST_PATH)`
  (Gestione Iscritti condivide il pannello: stesso comportamento).
- Guide in-app IT/EN `request-management` + manuale Claude Docs (passi "Crea richiesta" e "Salva") aggiornati.
- Test: `request-work-panel-submit` (+2), `module-form-page` (+2), `use-module-opener` (+1). Vitest
  request-management/pages/modules/help verdi (534 + 104), ESLint pulito, `tsc -b --force` pulito.

## SPEC 0174 MIGRAZIONE COSTI LEGACY -> PRODOTTI SOLO COSTO — NON COMMITTATO (2026-09-28)

- Spec `docs/specs/0174-legacy-cost-products-migration.xml` (decisioni utente D-1/D-2/D-3).
- Legacy (`/Users/Repository/qnet`, non committato): `Api/V2/CostProductMigrationController` +
  `GET /api/v2/migration/cost-products` (UNION ALL di `products`/`vehicles`/`equipment`/`expense_reports`,
  id riga `<source>:<source_id>`; 20 righe sul DB locale). Articoli: cost = `purchase`, price = `price`.
- qnet-2: migrazione `2026_09_28_140000_add_old_source_to_products_table` (`old_source`, unique
  (old_source, old_id), backfill `services`); `ProductsSource::OLD_SOURCE = 'services'` (idempotenza sulla coppia);
  helper condivisi in `Sources/Concerns/MapsExternalProductRecord` (`resolveVatRate`, `unresolvableReference`,
  `trimmedText`, sostituisce `mapDescription`/`mapCode` di ProductsSource).
- Nuova sorgente `cost-products` (`Sources/CostProductsSource`, fase 5 di `MigrationOrder`): usages ["COST"],
  ramo `Costi` > `Articoli` (+ sotto-categoria legacy) / `Veicoli` / `Attrezzature` / `Note spese` via
  `Support/CostCategoryResolver` (firstOrCreate nome+padre, niente old_id); attributi text `cost_*` da
  `Support/CostProductCatalogue`. Rev.2 (richiesta utente): `cost-products` e' in
  `QualificaLegacyImportSeeder::SOURCES` (fase 5, ultima); la radice `Costi` non ha old_id e non viene annidata
  sotto "Consulenza". `QualificaLegacyImportSeederTest` copre import + idempotenza.
- `QuoteWorkflowMigrationTest` rollback a 110 step; `Unit/Migrations/MigrationRegistryTest` con la nuova sorgente
  (requisito cambiato). Test `Migration/CostProductsSourceImportTest` (10, sanity-break verificato).
- Verifica (dopo rev.2): `composer test` 8591 passati + 1 skip, Pint pulito; Vitest help 104/104, `tsc -b --force` e ESLint puliti.
  Endpoint legacy provato via tinker sul DB locale (non via HTTP: `route:list` legacy rotto da errore preesistente).
- Guide IT/EN `migrations` + manuale Claude Docs (rev 111, sezione Migrazioni) aggiornati.
- Da fare in locale: `php artisan migrate`, poi Migrazioni: `vat-rates` prima di `cost-products`.

## SEED PRODUZIONE — "TRATTATIVE IN CORSO" E "PRESA APPUNTAMENTI" SELEZIONABILI — VERDE, NON COMMITTATO (2026-09-28)

- Richiesta: "aggiungere due categorie sotto Consulenza". Esistevano gia' in `QualificaCatalogSeeder::CATALOG` (dal
  2026-07-28) ma come contenitori; decisione utente: renderle SELEZIONABILI (nome invariato, nessun prodotto seedato).
- `QualificaCatalogSeeder::SELECTABLE_SUBCATEGORIES` = `CatalogProducts::SINGLE_OFFER_CATEGORIES` +
  `ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES`. Il realign delle sottocategorie a ogni run porta a
  `is_selectable = true` anche i DB gia' seedati (nuovo test dedicato).
- Test: `QualificaCatalogSeederTest` aggiornato (requisito cambiato) + test di riallineamento. Suite completa parallela:
  8592 verdi, 1 skipped. Pint pulito. Manuale: nessun impatto (le guide non elencano queste categorie).

## SEED PRODUZIONE — "NON RISPONDE" RICLASSIFICATO APERTO — VERDE, NON COMMITTATO (2026-09-28)

- Richiesta: "Non risponde" era `closed_lost` ma deve essere APERTO. Sostituisce la classificazione della voce sotto
  (stesso giorno). `WorkflowStatusCatalogue`: legend `OPEN` (slate) in TUTTE le sezioni, anche AUTOFINANZIATO e
  CONSULENZA dove il foglio lo coloriva pesca (override esplicito). Posizione invariata (prima di "Irreperibile").
- Effetto collaterale: in `Autofinanziato` la riga fissa `closed_lost` passa da "Non risponde" a "Irreperibile".
- `QualificaWorkflowSeeder::DEFAULT_SET_NO_ANSWER`: group `open`, color `slate`.
- LIMITE: su un DB gia' seedato il seeder NON riclassifica (salta workflow esistenti e il set di default se la riga
  esiste gia'): correggere dal configuratore o con un backfill.
- Test aggiornati (requisito cambiato): `QualificaWorkflowSeederTest`, `QualificaDefaultSetStatusSeederTest`;
  `tests/Feature/Seeding` + `QualificaLegacyImportSeederTest`: 112 verdi. Pint pulito. Manuale: nessun impatto.

## SEED PRODUZIONE — STATO "NON RISPONDE" IN TUTTI GLI STATI DI LAVORAZIONE — VERDE, NON COMMITTATO (2026-09-28)

- Richiesta: aggiungere "Non risponde" (chiuso con esito negativo = `closed_lost`) a tutti gli stati di configurazione
  del seed di produzione, anche al set di DEFAULT globale (solo produzione, non demo).
- `WorkflowStatusCatalogue`: riga `Non risponde` (NEGATIVE, rossa) nelle sezioni GOL, AUTOIMPIEGO/YISU, APL (gia'
  presente in AUTOFINANZIATO e CONSULENZA) e in ogni lista regionale GOL + `GOL_BASE_STATUSES` + DIL, sempre subito
  prima di "Irreperibile": nessuna riga fissa `closed_lost` cambia etichetta.
- `QualificaWorkflowSeeder::seedDefaultSetStatus()`: accoda `Non risponde` al set globale (`quote_workflow_id` null)
  se manca; ripresenta le custom esistenti cosi' come sono (il sync del writer e' autoritativo). Idempotente.
- LIMITE NOTO: i workflow gia' esistenti su un DB gia' seedato NON ricevono la riga (il seeder salta i workflow
  esistenti per non toccare le modifiche del configuratore); il set di default invece si'. Serve un backfill se voluto.
- Test: `QualificaWorkflowSeederTest` aggiornato (lista APL, conteggio closed_lost 13->14, trascrizione DIL) + nuovo test
  "in ogni lista"; nuovo `QualificaDefaultSetStatusSeederTest` (sanity-break verificato). Pint pulito.
  Suite completa: 8579 verdi, 2 rossi in `MigrationRegistryTest` dovuti al lavoro parallelo spec 0174 (`cost-products`,
  file non tracciati altrui), estranei a questa modifica. Manuale: nessun impatto (non elenca gli stati del cliente).

## ANAGRAFICHE — GESTIONE DOCUMENTI COME OPPORTUNITA' (spec 0173) — VERDE, NON COMMITTATO (2026-09-28)

- Richiesta: "Anagrafica bisogna inserire la gestione documenti come fatto in opportunita'". Ricalca spec 0134 (Commessa).
- Backend: `HasAttachments` su `Registry`, alias `registry` in `config/attachments.php` (gia' nel morph map); nuova
  ability `registries.viewDocuments` (`RegistryPolicy`) esposta come azione `view_documents`
  (`RegistriesAuthorization`). Tabella: azione `documents` (paperclip, `count_field` `documents_count`, dopo `edit`
  -> inline), `withCount` solo collection `documents`. Allegati autorizzati da `attachments.*` (nessun gate per record).
  SERVE `php artisan permissions:sync`; i ruoli Qualifica col blocco Catalogo e Anagrafiche la ricevono da soli.
- Frontend: `REGISTRY_ATTACHABLE_ALIAS` in `registries/api.ts`; `registry-detail.tsx` con `useCollaborationTabs`
  (Documenti | Cronologia); `registries-table.tsx` con `DocumentsDialog` + `REGISTRIES_ACTION_ICONS`, refresh griglia
  alla chiusura.
- Test: `RegistryDocumentsTest` (7); `registry-detail.test.tsx` (+2), `registries-table.test.tsx` (+2), sanity-break
  verificato. Pest suite completa 8578 verdi (1 skipped), Pint pulito; Vitest registries/help/attachments 176 verdi;
  `tsc -b --force` 0 errori; ESLint pulito sui file toccati.
- Guide in-app IT/EN: `registries` nuova sezione `registry-documents`, `general` (elenco moduli con Documenti).
  Manuale Claude Docs aggiornato (rev 109): nuova sottosezione "Documenti dell'anagrafica" + elenco in "Documenti".
- Preesistente, non toccato: ESLint `registry-form-metadata.test.tsx:271` (`_omit` inutilizzato).
- Da verificare a mano: upload dal dettaglio (pagina e Sheet) e dall'azione di riga, badge conteggio.
- Rev.2 (stessa spec): scheda "Documenti anagrafica" in SOLA LETTURA nei dettagli Opportunita', Offerte e Commesse,
  come "Documenti opportunita'" in Offerte/Contratti. Hook condiviso `registries/use-registry-documents-tab.tsx`
  (`useRegistryDocumentsTab(registryId)`: `null` senza anagrafica o senza `registries.viewDocuments`). Commessa:
  nuovo `registry: {id, name} | null` in `WorkOrderResource` (via `quote.opportunity.registry`, aggiunto a
  `WorkOrderService::DETAIL_RELATIONS`); `WorkOrderDetail.registry` opzionale lato FE (come `QuoteDetail.registry`).
  i18n `registries.detail.registryDocumentsTab`. Test: `WorkOrderDocumentsTest` (+1), hook (3), dettagli
  opportunita' (+1), offerta (+2), commessa (+2); sanity-break verificato. Pest WorkOrders+Contracts 304 verdi, Pint
  pulito; Vitest quotes/opportunities/work-orders/registries/contracts/help/i18n 141 file 1237 verdi; tsc 0 errori.
  Guide IT/EN `registries` + manuale Claude Docs (rev 110) aggiornati.
  Nota: il gate `registries.viewDocuments` e' solo UI; `/api/attachments` non ha controllo per record (come ovunque).

## TEAM OPPORTUNITA' PERSO ALLA SCELTA DELL'ANAGRAFICA + TEAM OFFERTA STALE — VERDE, NON COMMITTATO (2026-09-28)

- Bug 1 (causa): la sezione Team (e Attribuzione) sta SOPRA Cliente; scegliere l'anagrafica azzerava Commerciale,
  Segnalatore, Supervisore e G.A. sostituendoli con quelli dell'anagrafica (spesso vuoti) -> team perso al salvataggio.
  Backend corretto (nessuna modifica). Decisione utente 2026-09-28: chiedere conferma, su tutti e 4 i campi.
- Nuovo `features/opportunities/use-registry-role-inheritance.ts` (sostituisce `applyRegistrySelection` inline in
  `opportunity-registry-field.tsx`): valori inseriti dall'utente (pieni e diversi da quanto ereditato dall'anagrafica
  precedente) che cambierebbero -> dialog `useConfirm` (`opportunities.form.registryRolesConfirm.*`, "Sostituisci" /
  "Mantieni i miei"); valori ereditati dall'anagrafica precedente si sostituiscono senza chiedere. G.A. sempre
  paddati a `DEFAULT_MANAGER_SLOTS` (4 card visibili anche per anagrafica senza gestori). Risposte fuori ordine
  ignorate (`requestRef`). `getValues` passato da `OpportunityFormBody` -> `OpportunityClientSection` -> registry field.
- Bug 2 (causa): il form Offerta copia ruoli/team dal `meta` di `opportunities/for-select`, cache labels con
  `staleTime` 5 min e applicata una sola volta. `useOpportunityFormSubmit` ora fa `resetQueries` (non invalidate:
  il dato stale verrebbe servito e applicato prima del refetch) su `forSelectKeys.resource('opportunities')` dopo
  create e update.
- Test: blocco anagrafica spostato da `opportunity-form-body.test.tsx` (474 righe) al nuovo
  `opportunity-registry-role-inheritance.test.tsx`; requisito cambiato su 2 test (conferma "Replace" prima del
  re-inherit; 4 card G.A. vuote invece di nessuna) + 2 nuovi (Keep mine / Replace). Nuovi
  `use-registry-role-inheritance.test.ts` (9) e `use-opportunity-form-submit-cache.test.tsx` (2, sanity-break
  verificato con invalidateQueries). Suite opportunities/quotes/help verde, eslint e `tsc -b --force` puliti.
- Manuale: guida in-app `opportunities` IT/EN (tip nella sezione creazione) + Manuale Utente QNet (paragrafo
  "Anagrafica e ruoli" dopo "Titolo.").
- Fuori scope (segnalato): la sync G.A. offerta -> opportunita' (spec 0087 D-7) non resetta la cache for-select.

## LINK DELLE SCHEDE DETTAGLIO FILTRATI PER PERMESSI (OFFERTE -> GESTIONE RICHIESTE) — VERDE, NON COMMITTATO (2026-09-28)

- Richiesta utente: nelle schede dettaglio nessun link a un record che l'utente non puo' vedere; l'Offerta apre
  in Offerte oppure in Gestione Richieste se l'utente vede solo quella (come gia' fatto sui buoni, commit 8ad06c43).
- Nuovo `features/modules/use-viewable-domain.ts`: `resolveViewableDomain(domain, can)` + `useViewableDomain(domain)`.
  Candidati = il dominio + `VIEW_FALLBACK_DOMAINS` (`quotes -> request-management`), primo registrato con
  `<dominio>.view`, altrimenti `null` = testo semplice. Usato da `RecordLink` (tutti i campi-link delle schede),
  `RelatedRecordLink` dei contratti (prop `permission` rimossa: derivata dal dominio) e `reward-detail-renderer`
  (la sua mappa `RECORD_OPEN_TARGETS` sostituita da `RECORD_DOMAINS` alias -> dominio + resolver condiviso).
- Lead: "Vai all'opportunita'" ora dentro `<Can permission="opportunities.view">` (prima senza controllo).
- Persone: `UserDetailSheetContext` ha `canOpenUserDetail` (provider = `can('users.view')`; default `true` solo fuori
  provider, dove l'apertura e' comunque no-op). `UserProfileHoverCard`/`UserProfileHoverAction` senza permesso =
  avatar+nome non cliccabili (`title` col nome). Vale anche nelle celle persona delle tabelle (stesso componente).
- Test: `use-viewable-domain.test.ts` (5), `record-link.test.tsx` (+3 fallback Offerte), `lead-conversion-action.test.tsx`
  (+1), `user-cell.test.tsx` (+1); sanity-break verificato. Test adeguati al contratto: `canOpenUserDetail: true`
  nei Provider di `work-order-detail-team`/`opportunity-detail-sections`/`user-cell`, mock abilities in
  `user-detail-sheet.test.tsx`, `lead-detail.test.tsx` "exactly once" ora concede `opportunities.view` (requisito cambiato).
- Verifica: Vitest suite completa 822/823 file, l'unico rosso (lead-detail sopra) corretto e leads 170/170 verdi;
  `tsc -b --force` 0 errori, ESLint pulito. Guide in-app IT/EN `general` (paragrafo link) e `leads` aggiornate.
- Manuale Claude Docs NON aggiornato: documento non accessibile da questa sessione (accesso negato). Da aggiornare:
  sezione "Schede di dettaglio/Interfaccia generale" (link solo se permesso, persone con permesso utenti, Offerta
  -> Gestione Richieste) e sezione Lead ("Vai all'opportunita'" visibile con permesso opportunita').
- Limiti noti (segnalati, non fatti): il controllo e' per ability, non per singolo record (scope sede/visibilita'
  di Gestione Richieste resta solo server-side); fuori dalle schede dettaglio restano link non filtrati:
  notifiche ("Apri"), colonna task nei giorni di Consuntivazione, alert opportunita' esistente nel form, card
  task della board Commessa (gate `tasks.viewAny`, non `tasks.view`). HANDOFF e' ~270 KB: va archiviato.

## SPEC 0172 SOTTO-TASK NEI MODELLI DI TASK + MIGRAZIONE LEGACY — VERDE, NON COMMITTATO (2026-09-28)

- Spec `docs/specs/0172-task-template-subtasks-and-legacy-migration.xml` (decisioni utente D-1/D-5/D-8/D-9/D-10).
- Schema: `2026_09_28_100000_add_parent_id_to_task_template_items_table` (FK self, cascadeOnDelete come rete) e
  `2026_09_28_100100_add_old_id_to_task_templates_table`. Il working tree contiene anche la spec 0171 (altra sessione):
  `QuoteWorkflowMigrationTest` ora a 109 step (105 + 2 di 0172 + 2 di 0171).
- Contratto `task-templates`: `items[]` piatto, `items.*.key`/`items.*.parent_key` (key di una riga PRECEDENTE),
  max 3 livelli sotto la radice, sotto-item senza fase (`stage_key` vietato), `due_offset_days` <= padre,
  `ITEMS_MAX` 500; risposta con `parent_id`. Validazione in `Concerns\ValidatesTaskTemplateItemTree`; cancellazione
  sempre dal basso via `Services\TaskTemplates\TaskTemplateItemTree::deepestFirst()` (hook allegati per ogni riga).
- Generatore: sotto-item -> sotto-task (`parent_task_id`, fase null, `stage_position` 0, `subtask_position` per
  fratelli) in `WorkOrderTaskGenerator` + `WorkOrders\GeneratedTaskPositioner`.
- Migrazione: sorgente `task-templates` (`Migrations\Sources\TaskTemplatesSource` + `Support\TaskTemplateTreeFlattener`,
  345 righe, sopra soft limit), fase 1 di `MigrationOrder`, idempotenza su `task_templates.old_id`; duplicati
  "<nome> (old_id N)", `is_active` false se il titolo contiene "non attivo", sotto-azioni anomale promosse a radice
  con warning, descrizioni via `RichTextConverter::plainTextToHtml`. `task-templates` e' anche in
  `QualificaLegacyImportSeeder::SOURCES` (fase 1, dopo `payment-methods`): il seed di produzione importa i modelli
  legacy nella stessa mass run (`QualificaLegacyImportSeederTest` copre import e idempotenza).
- Legacy (`/Users/Repository/qnet`, non committato): `Api/V2/TaskTemplateMigrationController` +
  `GET /api/v2/migration/task-templates` (proiezione grezza, 39 modelli / 136 fasi / 1297 azioni sul DB locale).
  `route:list` nel legacy fallisce per un errore preesistente (`App\Http\Controllers\email`).
- FE: `features/task-templates/task-template-item-tree.ts` (logica albero pura), `task-template-sub-item-row.tsx`,
  "Aggiungi sotto-task" fino al 3 livello; guide IT/EN `task-templates` (sezione `sub-tasks`) e `migrations`;
  manuale Claude Docs aggiornato (Modelli di Task, Migrazioni).
- Verifica (verifier): `composer test` 8571 passati + 1 skip, Pint pulito, Vitest 6296/6296, `tsc -b --force` e
  ESLint puliti, AC-001..AC-021 PASS (AC-020 completato con il limite 500 nelle guide).
- Prossimo passo: import reale da `/migrations` contro il legacy configurato (`EXTERNAL_MIGRATION_BASE_URL`), poi
  revisione dei warning nel report.

## SPEC 0171 TITOLO OPPORTUNITA' E OFFERTA MODIFICABILE — VERDE, NON COMMITTATO (2026-09-28)

- Rev.2 (richiesta utente): titolo automatico = "codice - prodotti" (`OPP_12 - ISO 9001 + SOA`, `QUO-0042 - ...`,
  solo codice senza righe di ricavo) e stesso flusso per l'OFFERTA. `OpportunityTitleBuilder` CANCELLATO, sostituito
  da `Services/Quotes/RevenueProductTitleBuilder` (`forOpportunity`/`forQuote`); nuovo `Services/Quotes/QuoteTitleWriter`
  (write/recalculate, chiamato da `QuoteService` create Step 5a e update dopo le righe). Migrazione
  `2026_09_28_130000_add_title_is_manual_to_quotes_table`: regola mista (`OPP_<n>` = automatico, altri = manuali).
  `title` offerta facoltativo (Store/Update, FieldDefinition non piu' mandatory), `CreateQuoteData::$title` nullable e
  fuori da `attributes()`, `UpdateQuoteData` title fuori da `submittedAttributes()`; `LeadConversionOfferCreator` e
  `RequestCreationService` passano `title: null`. Resource `title_is_manual`. Comando `php artisan titles:recalculate`
  (ricalcola i titoli automatici esistenti: DA ESEGUIRE dopo il deploy, non eseguito sul DB di sviluppo).
  Frontend: `blankToNull` in `lib/utils.ts` (usato dai payload opportunita' e offerta), titolo offerta facoltativo con
  placeholder/hint, i18n `quotes.form.{titlePlaceholder,titleHint}` (rimosso `titleRequired`). Guide IT/EN quotes +
  opportunities, manuale Claude Docs aggiornati. `QuoteWorkflowMigrationTest` step 109. Test: `QuoteEditableTitleTest`
  (AC-010..013), aspettative "OPP_{id} - ..." in derivazione/titolo opportunita' (requisito cambiato); helper
  `nameDerivationCreateQuoteData` ora `title: null`.
- Verifica rev.2: backend suite completa 8571 passati / 0 falliti (1 skipped), Pint pulito; Vitest 197 file / 1690
  verdi (quotes, opportunities, request-management, contracts, work-orders, help, lib, leads); `tsc -b --force` 0
  errori fuori da `task-templates` (errori li' = lavoro in corso spec 0172 di altra sessione). ESLint: 1 errore
  preesistente in `quotes/column-renderers.tsx` (non toccato). `QuoteService.php` 456 righe (gia' oltre soft limit).

- Richiesta utente: campo Titolo nel form di creazione/modifica opportunita', precompilato. Titolo automatico SOLO
  dai prodotti delle righe di ricavo delle offerte (`OpportunityTitleBuilder`, invariato), mai dai prodotti di
  interesse; titolo scritto a mano = vince il manuale. Supera spec 0057 D-5/AC-008 e 0077 AC-037.
- Backend: migrazione `2026_09_28_120000_add_name_is_manual_to_opportunities_table` (bool default false, fuori da
  `$fillable`). Nuovo `Services/Opportunities/OpportunityNameWriter`: `write()` (null o uguale all'automatico =
  automatico, altrimenti manuale) e `recalculate()` (salta se manuale), usato da `OpportunityService` create/update
  (in update DOPO le letture `wasChanged()`) e da `QuoteService` (sostituisce il privato `recalculateOpportunityName`).
  `name` in Store/Update request (`max:191`, `StoreOpportunityRequest::NAME_MAX_LENGTH`), DTO (`name`/`nameSubmitted`,
  in coda), `FieldDefinition('name','text')`, resource `name_is_manual`. Griglia: `name` resta NON editabile inline.
- Frontend: `opportunity-title-section.tsx` (prima card del form), `name` nello schema (`NAME_MAX_LENGTH`), default
  edit = titolo attuale / create = '' (nessuna offerta ancora), payload: create invia `name` solo se scritto, update
  invia il valore trimmato se cambiato, svuotato = `null`. i18n `opportunities.form.{name,namePlaceholder,nameHint,nameMax}`.
  Guide in-app IT/EN `opportunities` aggiornate (riga Titolo + tip).
- Test per requisito cambiato: `OpportunityCrudTest` (nome inviato ora tenuto), `OpportunityMetaTest` (16 campi),
  `QuoteOpportunityNameDerivationTest` AC-037, `QuoteWorkflowMigrationTest` step; helper `nameDerivation*` spostati in
  `tests/Helpers/QuoteHelpers.php` (riusati da `OpportunityEditableTitleTest`, AC-001..007 + 403).
- Verifica: backend suite completa 8534/8537 + i 2 rossi (step rollback, `DemoOpportunitySeederTest` flaky noto) verdi
  dopo fix/da soli; Pint pulito. Vitest mirato 167 file/1360 verdi; suite completa 15 timeout sotto carico in moduli
  non toccati, 12 file rilanciati da soli 94/94 verdi. `tsc -b --force` 0 errori, ESLint 0 errori.
- Manuale Claude Docs aggiornato (sezione Opportunita': riga Titolo in tabella + paragrafo). Prossimo passo: chiedere se committare
  (per percorsi espliciti: il working tree contiene lavoro della spec 0172 e dei filtri tabella di altre sessioni).

## TABELLE — FILTRI MANTENUTI AL RELOAD (AVANZATI FIX 422 + RICERCA/PERSONALIZZATO IN BROWSER) — VERDE, COMMITTATO (2026-09-28)

- Richiesta utente: tutte le tabelle AG Grid mantengono i filtri attivi dopo il reload. Filtri di colonna e avanzati
  erano gia' persistiti lato server (`user_table_filters`); si perdevano ricerca rapida e filtro personalizzato (spec
  0158, "in memoria"). Attivare un filtro personalizzato azzera i filtri colonna e salva `{}`: al reload la tabella
  tornava senza alcun filtro. Decisioni utente: persistenza in **localStorage** (non server), ricerca inclusa.
  Cambia la decisione "non persistito" della spec 0158 per il filtro personalizzato.
- FE: `features/table/table-local-filters-storage.ts` (chiave `table-filters:<userId>:<domain>:<productCategoryId>:
  <opportunityId>:<quoteId>`, parse Zod difensivo, entry rimossa quando vuota) + `use-table-local-filters.ts` (legge
  `AuthContext` direttamente: senza provider nessuna persistenza, i test esistenti non cambiano). Seed iniziale in
  `useTableToolbarState({ initialSearch })` e `useCustomFilterState(initial)`; effetto di scrittura in
  `use-table-view-grid-state.ts`. La prima richiesta SSRM e' gia' filtrata. Il cambio tab categoria rimonta la
  tabella (`key`), quindi la lettura al mount basta.
- Test: `table-local-filters-storage.test.ts`, `table-view-local-filters.test.tsx` (sanity-break verificato);
  features/table + components/data-table 468/468, ESLint e `tsc -b --force` puliti. Guida `general` IT/EN e manuale
  Claude Docs aggiornati.
- BUG trovato in browser (Playwright): i FILTRI AVANZATI non si salvavano mai. Il pannello invia solo
  `{advancedFilters}`, ma `TableFilterStateRequest` esigeva `filterModel` (`present`) -> 422 silenzioso. Ora
  `filterModel` e' `sometimes` come `advancedFilters` (assente = resta il salvato, `{}` = svuota); senza nessuna
  delle due chiavi -> 422. `TableFilterStateService::save(?array $filterModel, ?array $advancedFilters)`.
  Test: `tests/Feature/Table/TableFilterStatePartialSaveTest.php` (4, falliti col 422 prima del fix); Table 278/278,
  Leads/Tasks/Quotes/Opportunities/RequestManagement 211/211, Pint pulito. Verificato in browser su Lead
  (Fonte + Stato lead restano al reload) e Utenti (ricerca + Ruoli).
- Da sapere: un filtro personalizzato salvato che referenzia una colonna non piu' filtrabile fa fallire la richiesta
  righe finche' l'utente non toglie la chip; il logout non svuota le chiavi (sono per utente).
- Tab categoria di Gestione Richieste (VERDE, NON COMMITTATO): i filtri avanzati salvano con la tab
  (`productCategoryId` fino a `useSaveTableFilters`); dopo un salvataggio/azzeramento filtri le config delle ALTRE
  tab vengono RIMOSSE dalla cache (`removeOtherScopeConfigs` in `use-table-config.ts`), non solo invalidate: una
  voce stale viene servita al mount e la griglia applica i filtri solo alla creazione, quindi interrogava con i
  filtri vecchi mentre le chip mostravano quelli nuovi (visto in browser). Un salvataggio filtri rifiutato ora
  mostra il toast `table.filtersSaveError`. `invalidateDomainConfigs` spostato in `use-table-config.ts`.
  Test: `use-table-filters-cache.test.tsx`, `use-advanced-filters.test.ts` (sanity-break ok); table +
  data-table + request-management 921/921, i18n+help 274/274, ESLint, `tsc -b` puliti. Verificato in browser:
  Tutte -> GOL Molise, tolgo Referente, torno su Tutte: righe e chip senza Referente.

## TEMPI DEI TEST — SUITE BACKEND IN PARALLELO, VITEST NODE/JSDOM — VERDE, NON COMMITTATO (2026-09-28)

- Causa: `composer test` = `php artisan test` seriale sotto l'Xdebug di Herd (3.4.0alpha2, mode=debug,develop),
  che puo' anche andare in segfault (signal 11). Ora `composer test` = `XDEBUG_MODE=off` + `--parallel`
  (`test:coverage` = `XDEBUG_MODE=coverage`, senza timeout Composer). Suite completa: 8632 test in ~1:56 (10 worker).
  Misura sul sottoinsieme `tests/Feature/Tasks` (625 test): 84.8s seriale+Xdebug -> 31.3s Xdebug off -> 7.9s parallelo.
- Il parallelo ha fatto emergere 68 fallimenti `Call to undefined function`: helper dichiarati in un file di test e
  usati da altri. 25 helper (98 copie locali, tutte identiche) spostati in `tests/Helpers/*Helpers.php` e caricati da
  `tests/Pest.php`; nuovi file: Registry, RewardedReferent, Opportunity, Lead, Contract, Import, User, WorkOrder,
  Stats. Regola nuova in `rules/backend.md §6`: helper condiviso tra file -> `tests/Helpers`, mai locale.
- Frontend: `test.projects` in `vite.config.ts`, `*.test.ts` in node, `*.test.tsx` in jsdom; `src/test/setup.ts`
  carica jest-dom/cleanup/stub solo se esiste `window`; 30 `.test.ts` che usano il DOM hanno
  `// @vitest-environment jsdom` in prima riga. 6245/6245 verdi; 192s -> ~178s (guadagno modesto: il costo e'
  nei 578 `.test.tsx`). `pool: 'threads'` e `deps.optimizer` misurati: nessun guadagno sull'intera suite, scartati.
- Agenti/comandi aggiornati (verifier, backend, frontend, tester-debug, build-feature): test mirati con
  `XDEBUG_MODE=off ./vendor/bin/pest <path>` durante lo sviluppo, suite completa `composer test` al verifier,
  `tsc -b --force` al posto di `tsc --noEmit`.
- Seed once (NON COMMITTATO): i 15 file di test seeder piu' lenti (Qualifica* + DemoTask/EmploymentProfile) ora
  eseguono il seeder una volta per scenario: i test con lo stesso setup (DB pulito + stessa sequenza di seed, nessuna
  mutazione) sono fusi in un `it` con tutte le asserzioni (seed -> asserzioni a run singolo -> re-seed -> asserzioni
  di idempotenza); restano separati i test con pre-stato proprio o che mutano i dati seminati. Suite 8632 -> 8528 test,
  asserzioni invariate per file (conteggio Pest), sanity-break verificato per ogni file. Totale
  asserzioni 36628 (prima 36615), ~1:56 -> ~1:31. Nuovi test seeder: stesso pattern, il seeder `QualificaCatalogSeeder` costa ~1.1s.

## REPORT — ASSOCIATI SU STATO ATTUALE, PRESA APPUNTAMENTI CALCOLATA (SPEC 0170) — VERDE, NON COMMITTATO (2026-09-28)

- Decisioni utente: Associati/Trattative Concluse/Invio Presa in carico = stato ATTUALE `closed_won`, periodo sulla
  data di creazione richiesta (`quotes.created_at`), nessuna lettura di `activity_log` (D-1/D-2). Presa Appuntamenti =
  passaggio DIRETTO (old -> new in `activity_log`) "OK App. Fissato..." -> "Assegnato" nel periodo, stesso workflow,
  per ogni categoria che ha la colonna (D-3/D-4). `potenziali` (nel periodo) resta sullo storico (D-5).
- BE: `CurrentStatusGroupIndicator` ha il flag `createdWithinRange`; nuovo `AppointmentTransitionIndicator`; sottoquery
  condivisa `activity_log` nel trait `Indicators/QueriesLoggedStatusTransitions` (usato anche da
  `WorkflowTransitionIndicator`, ora solo `potenziali`). Nomi stato in `config/request-management-report.php`
  `appointment_transition` (from_status_prefix / to_status), confronto case-insensitive. Stub rimasti: solo aule_*.
- Test: nuovo `RequestManagementReportCurrentOutcomeTest` (AC-001..005); AC-019 riscritto (requisito cambiato), AC-020
  ora prova l'attribuzione D-3 su `potenziali`; `EnrolleeReportTest` CSV: 3 righe (la riga operatore compare perche'
  Invio Presa in carico ora conta). Guide help IT/EN (request-management, product-categories) e manuale Claude Docs
  aggiornati.
- Da sapere: con periodo "oggi" Associati e' 0 per richieste create nei giorni prima (effetto di D-2). La colonna
  Presa Appuntamenti oggi e' attiva solo su Consulenza, il cui workflow non ha la coppia di stati (vale 0): per APL va
  spuntata in Categorie prodotto > Colonne report.

## SMISTAMENTO EQUO — LISTA OPERATORI SELEZIONABILE PER SEDE (SPEC 0168) — VERDE, COMMITTATO (2026-09-25)

- Decisioni utente: gruppi = Sede (D-1), selezione per gruppo (D-2), nessuna persistenza (D-3).
- BE: `POST /api/assignment/selection-scope` restituisce anche `balanced_groups` [{operational_site_id,
  operational_site_label, record_count, operators[{id,label,avatar_url,load}]}] + `balanced_unassignable_count`
  (`Services/Assignment/BalancedPoolGroups`, carichi quotes in `QuoteOperatorLoads`, estratto da
  `RequestAssignmentService`). I 3 endpoint di assegnazione accettano `operators_by_site` [{operational_site_id,
  operator_ids}] solo con mode=balanced (regole in `Http/Requests/Concerns/HasOperatorsBySiteRules`, restrizione
  in `Services/Assignment/OperatorsBySitePoolRestriction`): pool = candidati ∩ lista, Sede non elencata => skipped,
  un operatore non candidato viene ignorato (mai 422). Senza il campo il comportamento e' invariato.
- FE: `features/assignment/{balanced-operators-picker.tsx,balanced-operator-selection.ts,
  use-balanced-operator-selection.ts}`; `AssignOperatorsDialog` diviso in `assign-operators-dialog-body.tsx`, prop
  opzionale `balancedScope {groups, unassignableCount, isResolving, isError}` (i dialog di trasferimento non la
  passano). Cablati: leads-table, request-management-table (solo bulk-assign), review-bulk-assign-bar.
- Test: Pest 1514/1514 (35 nuovi, AC-001..010), Vitest 991/991 sulle cartelle toccate, ESLint, Pint, `tsc -b` puliti;
  verifier indipendente VERDE. 3 fallimenti in `attribute-values-schema.test.ts` appartengono a un'altra sessione.
- Da sapere: con i dati demo locali la lista e' spesso vuota, ed e' corretto. Import run 1: campagna senza Sede +
  categoria "GOL - Molise" con una sola operatrice competente (Sede 31). Se la Sede non si risolve o nessuno e'
  competente, il pool e' vuoto (regole 0110/0111/0113 invariate). Proposta aperta all'utente: fallback "tutti gli
  operatori della Sede" quando nessuno e' competente (richiederebbe una nuova spec).
- Manuale: guide in-app IT/EN (leads, request-management, general, imports) + manuale Claude Docs (rev 93).

## "CATEGORIE ME.PA." MULTISELECT + ETICHETTE ENUM NEI DOCUMENTI — VERDE, NON COMMITTATO (2026-09-25)

- Seguito del fix sotto (domanda utente "ci sono altri campi cosi'?").
- `categorie_mepa` arrivava da q-crm come `{"display":"select","multiple":true}`: `multiple` non lo legge nessuno, era
  scelta singola. Migrazione `2026_09_25_140000_promote_legacy_multiple_enum_attributes` (enum con `multiple: true` →
  `display: multiselect`, valori scalari avvolti in lista in quotes/work_orders/products; `multiple` resta come
  marcatore per la `down()`, lossy: tiene il primo codice). `AttributesSource::mapConfig()` fa la stessa traduzione
  per gli import futuri. Applicata al DB locale.
- `DynamicFieldResolver::quoteAttribute()`: un attributo enum stampa le etichette (lista → "A, B"); codice senza
  opzione → stampato com'e'. Guide IT/EN `document-layouts` (sezione variables) + manuale Claude Docs rev 94.
- Test: `tests/Feature/Attributes/PromoteLegacyMultipleEnumAttributesTest.php` (3), `AttributesSourceImportTest` (+1),
  `VariableResolverTest` (+1), rossi prima del fix. `QuoteWorkflowMigrationTest` rollback 104 -> 105 step (nuova
  migrazione, convenzione del test). Pest completo 8630/8632 (1 skipped, 1 = quel rollback, poi verde 2/2), Pint pulito.
- APERTI (segnalati all'utente, non fatti): (1) l'export di Gestione Richieste non congela la categoria del tab
  (`CreateExportRequest`/`ExportService` non chiamano `scopeToProductCategory`) → colonne `attr.*` vuote e righe
  non filtrate per categoria; inoltre il tipo sentinella `attribute` esporta una lista come JSON. (2) campi
  personalizzati enum nei documenti stampano ancora il codice (`customField()`). (3) il pannello richiesta rimanda
  tutta la mappa attributi: un valore storico fuori dalle opzioni blocca il salvataggio finche' non si corregge.

## FIX: ATTRIBUTI ENUM MULTISELECT NON SALVABILI (es. "Titolo di Studio") — VERDE, COMMITTATO (2026-09-25)

- Bug utente: modificare `degree` (enum `display: multiselect`) dava "Invalid input" sul form e
  "attribute_values.degree contains an invalid value" in griglia. Colpiva OGNI attributo enum multiselect.
- Causa form: gli schemi Zod degli attributi validavano ogni `enum` come `z.string()`. Ora c'e' un unico
  `buildAttributeEnumSchema` (esportato da `features/request-management/attribute-values-schema.ts`): lista di
  codici nullable per la multiselect (`seedAttributeValues` semina `null`), stringa per la singola; lo usa anche
  `products/product-schema.ts` (copia prima duplicata).
- Causa griglia: `resolveCellPatchValue` (`features/table/use-table-cell-edit.tsx`) faceva `Number(entry)` su ogni
  elemento non-oggetto di un array, quindi i codici diventavano `[null, ...]`. Ora gli scalari passano invariati; il
  tipo `value` di `CellPatchArgs`/`updateTableCell` ammette `string[]`. Backend invariato (gia' corretto).
- Test: `attribute-values-schema.test.ts` (nuovo, 4), `product-schema.test.ts` (+1), `use-table-cell-edit.test.tsx`
  (+1), rossi prima del fix. Vitest request-management/products/table/quotes/work-orders 1358/1358, `tsc -b --force`
  pulito, ESLint pulito; Pest `QualificaDegreeMultiselectTest` + `RequestManagementAttributeColumnsTest` 31/31.
  Manuale: nessun impatto.

## SEGNATEMPO: LA FASE SEGUE IL TASK — SPEC 0167 — VERDE, COMMITTATO (2026-09-25)

- Richiesta utente 2026-09-25. Ritira 0163 D-2 (fase = istantanea), il suo fuori scope e AC-004 (annotato in 0163).
  Segnatempo obbligatorio al completamento: invariato (regola 0162).
- Regola unica `App\Services\Tasks\TaskEffectiveStage::forTask()`: fase della RADICE dell'albero (risale
  `parent_task_id`). Usata da `TimeEntryLinkResolver::fromTask()` (voci dei sottotask ora prendono la fase della radice)
  e da `TaskTimeEntryStageRealigner` (`realignIfMoved()` = gate `wasChanged(work_order_stage_id|parent_task_id)` dopo il save; `realign()` incondizionato; sottoalbero BFS,
  UN solo UPDATE query builder su `time_entries`, niente activity log per voce).
- Cablato: `TaskService::update()` una riga `realignIfMoved()` dopo `save()` (file 491 righe, split sempre piu' urgente);
  `TaskStagePositioner::move()` chiama `realign()` solo nel ramo cross-fase (scrive via query builder, niente save). Eliminazione fase: FK nullOnDelete, invariata. Nessun backfill.
- FE: `useMoveBoardTask` invalida `taskBoardKeys.board` dopo il successo (totali "Minuti registrati" aggiornati).
  Guide IT/EN `time-entries` aggiornate (nota fase).
- Test: `tests/Feature/TimeEntries/TimeEntryStageFollowsTaskTest.php` (15, AC-001..010), test 0163 AC-004 rimosso
  (requisito cambiato), `use-task-board-mutations.test.tsx` (+1). Verifier: Pest completo 8580/8581 (1 skipped),
  dopo il fix TaskService rieseguiti TimeEntries/TaskStages/WorkOrders/Tasks 997/997, Pint pulito; Vitest 0167 verdi,
  ESLint pulito. `tsc -b` e 8 test Vitest ROSSI in `features/leads/`, `request-management/request-report-*`,
  `imports/wizard/review-grid` = lavoro di altre sessioni in corso, estraneo alla 0167.

## REPORT: PERIODO DAL/AL APERTO, DEFAULT OGGI — VERDE, COMMITTATO (2026-09-25)

- Spec `0169-report-open-date-range.xml`. Default Dal = Al = oggi (`todayReportRange`, sostituisce
  `currentWeekReportRange`, rimosso con `mondayOf`). I filtri gia' memorizzati in localStorage restano (D-1).
- Contratto: `date_from`/`date_to` OPZIONALI su POST `/{module}/report` e GET `/report/dashboard`; il FE li omette
  se vuoti (`toRequestReportFilterPayload`), `applied.date_*` = null se aperti. Regole condivise nel trait
  `Http/Requests/RequestManagement/Concerns/ValidatesReportDateRange` (`dateFrom()`/`dateTo()`; `after_or_equal`
  solo se entrambi presenti).
- `ReportDateRange`: `start`/`endExclusive` nullable + `constrain($query, $column)`, unico punto usato dai 5
  indicatori a periodo. Nome file: `-{dal}_{al}` / `-from-{dal}` / `-to-{al}` / nessun suffisso.
- FE: niente asterisco su Dal/Al, hint "Vuoto: dall'inizio/senza fine", chip Periodo "dal X"/"fino al Y"/"Tutto il
  periodo" (neutro solo senza date). Chiavi i18n `dateFromRequired`/`dateToRequired` rimosse.
- Test: `RequestManagementReportOpenRangeTest` (nuovo), dataset open-bound in Create/DashboardRequest test (sostituiti
  i due "422 se manca la data": requisito cambiato); Vitest schema/dialog/filter-bar aggiornati.
- Guide in-app IT/EN aggiornate (request-management, enrollee-management). HANDOFF e' a ~250 KB: va archiviato.

## ETICHETTE GA SENZA PERMESSI SUL CATALOGO — VERDE, COMMITTATO (2026-09-25)

- Bug (account reale Sara Armerini, ruolo `commerciale`, NON l'impersonificazione): `GET /api/product-categories/{id}/
  effective-manager-labels` dava 403 in Gestione Richieste/Opportunita'/Preventivi: la guardia richiedeva
  `product-categories.view` o `products.view|create|update`, il commerciale ha solo `product-categories.viewAny`.
- Fix (stessa linea del fix buoni: nessun cambio permessi/seeder): `ProductCategoryController::
  authorizeEffectiveManagerLabels()` = `product-categories.viewAny` OPPURE la guardia di `effectiveAttributes`
  (invariata). Spec 0080 endpoint authz + AC-014 rettificati. Test `ProductCategoryManagerLabelsTest` (viewAny -> 200).
- Nota: `ProductCategoryController.php` e' a 338 righe (soft limit 300): valutare lo split in un task dedicato.
- Manuale: nessun impatto.

## ABBINAMENTO BUONO SENZA PERMESSI SUL CATALOGO — VERDE, COMMITTATO (2026-09-25)

- Bug: i commerciali (e ogni ruolo di Gestione Richieste senza il blocco REWARDS) ricevevano 403 scegliendo un buono:
  `RewardAssignmentField.handlePick` chiamava `GET /api/reward-types/{id}` (gated `reward-types.view`) solo per il colore.
- Fix (decisione utente: NESSUN cambio permessi/seeder): `GET /api/reward-types/for-select` ora proietta
  `meta: { color }` (`RewardTypeForSelectResource`; `RewardTypeService::FOR_SELECT_COLUMNS` include `color`), FE
  `RewardTypeForSelectItem` in `features/reward-types/for-select-api.ts`; il chip si costruisce dall'opzione, niente
  fetch del dettaglio (rimossi `pendingId`/spinner per-opzione). Spec 0058 AC-009 + endpoint rettificati.
- Test: `RewardTypeForSelectTest` (shape con meta + attore senza permessi riceve il colore), FE
  `reward-assignment-field.test.tsx` e `request-attribution-rewards.test.tsx` asseriscono `fetchRewardType` mai chiamato.
- Manuale: nessun impatto (nessuna guida documentava il requisito di permesso).

## CORSI AUTOFINANZIATI PER REGIONE (SEED DI PRODUZIONE) — VERDE, COMMITTATO (2026-09-25)

- Fonte: CSV "Repertorio Corsi_Autofinanziati" (fogli Campania/Lazio/Lombardia/Sicilia), direttiva utente 2026-09-25.
  Trascritti in `QualificaCatalog/SelfFundedCourseCatalogue::COURSES` (leaf regionale => corsi `{name, price, plus_vat}`).
- "Autofinanziato" ora e' un CONTENITORE (fuori da `SELECTABLE_SUBCATEGORIES`, realign automatico) con 4 foglie
  selezionabili `Autofinanziato - Campania|Lazio|Lombardia|Sicilia`. Stesso corso ripetuto per sede = UN prodotto per
  regione; prezzi diversi tra sedi -> il piu' basso (ASO Sicilia 1200, ASACOM Sicilia 1200 da "1.200/1.300").
- "+ iva": prezzo netto + aliquota 22% (`VatRate::firstOrCreate(['rate' => 22], ['name' => 'IVA 22%'])`, riusa una 22%
  esistente). EIPASS, PEKIT Expert, ABA Lazio. Gli altri senza aliquota.
- `CatalogProducts::moveOffContainer()`: i corsi gia' seedati su "Autofinanziato" vengono SPOSTATI sulla regione (non
  duplicati); IVA compilata solo se vuota. Workflow "Autofinanziato" -> `BRANCH_CRITERION_FIELD` (realign come DIL).
- Test: nuovo `tests/Feature/Products/QualificaSelfFundedCatalogueTest.php` (test self-funded spostati li' da
  `QualificaCatalogSeederTest`, che era 585 righe); totale prodotti 294 -> 303 (anche `QualificaProductionDataSeederTest`).
  Suite completa parallela: 0 failed; 75 errori preesistenti "Call to undefined function <helper>()" (helper di test
  definiti in altri file, non caricati in parallelo/isolamento) — da sistemare a parte.
- Manuale: guida in-app `product-categories` IT/EN + manuale Claude Docs (schema categorie e passo "Autofinanziato").

## GESTIONE RICHIESTE — COLONNE `attr.*` MULTISELECT E RELAZIONE — VERDE, COMMITTATO (2026-09-25)

- Bug utente: "Titolo di Studio" (enum multiselect) apriva un menu vuoto; "Sede corso" (relation one) mostrava l'ID.
- Cause (solo FE, contratto spec 0064 invariato: `options` delle colonne attributo = oggetti `{value,label}`, valore
  riga relation = id nudo): `scalarColumnOptions` scartava le opzioni oggetto -> rich select `tags` senza valori;
  nessuna cella risolveva l'id della relation e `RelationCellEditor` leggeva solo `value.id`.
- Fix: `features/table/column-options.ts` (opzioni oggetto ridotte a `value`); nuova
  `components/data-table/relation-id-cell.tsx` (`RelationIdCell`, etichetta via `useForSelectLabels`), agganciata in
  `column-defaults.tsx::resolveCellRenderer` per colonne dinamiche con `editor: 'relation'`; `RelationCellEditor`
  accetta anche l'id nudo (`currentIdOf`). Copre anche "Docente" (`teacher`, relation -> referents).
- Verifica su tutti i tipi attributo reali (probe con lo shape di `AttributeColumnBuilder::resolved()`): text, textarea,
  integer, decimal, date, boolean, enum single/radio, multiselect, relation -> editor e visualizzazione corretti.
- Aperti (segnalati, non implementati): textarea edita con editor a riga singola (a capo persi); set filter delle
  colonne relation mostra gli id; `categorie_mepa` ha `config.multiple: true` ignorato ovunque (trattato come singolo).
- Test: `relation-id-cell.test.tsx` (nuovo), casi aggiunti in `cell-editor-registry`, `column-defaults`,
  `relation-cell-editor`. Manuale: nessun impatto (ripristina il comportamento gia' documentato).

## SEED RESPONSABILI ("RISPONDE A") DAL MANSIONARIO — VERDE, COMMITTATO (2026-09-25)

- Fonte: CSV "Mansionario Operatori_Abilitazioni 1 (Supervisione)" (direttiva utente 2026-09-25), trascritto in
  `database/seeders/QualificaCatalog/ReportsToRoster.php` (`MANAGERS`: email operatore => email responsabili).
  Fabozzi nessuno; Falzarano/Aliberti/Santamaria -> Fabozzi; Chiacchio -> Falzarano+Aliberti+Santamaria;
  Figurelli -> Santamaria; tutti gli altri (incluso lo staff Jessica Virgolini) -> Falzarano+Aliberti.
- Esclusi: Del Giudice, Vitale, Finizio, Pascale (gia' fuori dal roster), Miriam Cantatore (in nessun roster).
  "Jessica Faiettini" del CSV = `jessica.faettini@`.
- `QualificaReportsToSeeder` = step 9 di `QualificaProductionDataSeeder` (dopo operatori e staff). Convergente:
  `reportsTo()->sync()` per ogni account elencato; non tocca gli account non elencati; crea il profilo employment
  se manca (staff); salta con warning account/responsabile mancante e profilo `is_manager` (spec 0166 D-5).
- Test: `tests/Feature/Seeding/QualificaReportsToSeederTest.php` + asserzione step 9 in
  `QualificaProductionDataSeederTest`. Produzione: `php artisan db:seed --class=QualificaReportsToSeeder`.
- Manuale: nessun impatto (dati di seed, nessuna modifica a UI/regole).

## TASK — CAMPO "EVIDENZE" ELIMINATO — VERDE, COMMITTATO (2026-09-25)

- Decisione utente 2026-09-25: eliminare il campo Evidenze dal task (ritira spec 0154 D-3 / AC-004, annotato in spec).
- BE: migrazione `2026_09_25_130000_drop_evidence_from_tasks_table` (drop `tasks.evidence` + delete righe
  `role_field_permissions` tasks.evidence; distruttiva, `down()` ripristina solo la struttura). Rimossi da Model
  fillable, Store/UpdateTaskRequest, Create/UpdateTaskData, TaskService, TaskResource, TasksAuthorization; cancellato
  `TaskEvidenceWriter`. Una chiave `evidence` inviata da client vecchi e' ignorata (non validata).
- FE: rimossi campo dal form (`task-identity-section.tsx`), schema, tipi, payload, default `use-task-form`, fixtures,
  errori server, i18n `tasks.form.evidence*`, guide in-app IT/EN (tasks.ts).
- Test: `TaskMetaTest` 29 -> 28 campi, `QuoteWorkflowMigrationTest` rollback 104, AC-004 sostituito da test che la
  chiave e' ignorata. Verificati: Pest Tasks+migrazioni 714 verdi, Vitest tasks+help 543 verdi, tsc -b, ESLint, Pint.
- Manuale Claude Docs aggiornato (tabella campi form Task, rev 76). PDF derivato da rigenerare.

## PUNTI RINVIATI TASK — SPEC 0160-0164 — VERDE, COMMITTATO (2026-09-25)

- Decisioni utente 2026-09-25: festivi spostati (non saltati), sottotask 3 livelli, segnatempo facoltativo per
  tipologia, fase commessa automatica + manuale, kanban per colonna; tipi di nota ESCLUSI ("le note restano cosi'").
- 0160: `TaskRecurrenceCalculator` usa `App\Services\TimeEntries\WorkCalendar` (unica fonte festivi): con
  workdays_only il candidato in giorno non lavorativo si sposta al primo lavorativo, dedup se coincide, "dopo N"
  conta le occorrenze create, fine a data confrontata con la data spostata.
- 0161: `subtasks.*.subtasks` fino a profondita' 3 (4 livello prohibited), max 50 nodi sull'albero
  (`StoreTaskRequest::MAX_SUBTASK_NODES`); `TaskSubtaskBatchCreator` ricorsivo, `TaskSubtaskTreeLoader` (eager BFS),
  copia ricorsiva in `TaskOccurrenceFactory`, potatura `TaskRecurrenceService::subtreeUntouched()`. FE tipi in
  `features/tasks/task-subtask-types.ts` (3 livelli concreti, niente z.lazy per non rompere Path di RHF).
- 0162: `task_types.requires_time_entry` (default true), regola unica `TaskTimeEntryRequirement::isRequired()` (senza
  tipologia = true) usata da complete, bulk, TaskResource, TaskRowMapper; bulk senza tempo con un task che lo
  richiede -> 422 incompatible_tasks. FE interruttore "Registra il tempo" (`task-complete-time-entry-toggle.tsx`).
- 0163: `time_entries.work_order_stage_id` (FK nullOnDelete, backfill dai task); `TimeEntryLinkResolver`: con task
  fase = quella del task (input ignorato), senza task fase aperta della commessa (chiusa ammessa solo se invariata),
  senza commessa 422. Totali: `GET /work-orders/{id}/task-board` -> `stages[].logged_minutes`,
  `unstaged_logged_minutes` (withSum, niente N+1). FE: campo Fase nel form, "Minuti registrati" nella board.
- 0164: `kanbanGroup` {by: status|due, key} su POST /tables/{domain}/rows (hook `supportsKanbanGroups()` /
  `applyKanbanGroupScope()` + pass-through in DelegatesUnaugmentedTableMethods; `app/Tables/Tasks/TaskKanbanGroupScope`
  con la semantica di `task-kanban-due-buckets.ts`). FE `useInfiniteQuery` per colonna a blocchi di 50, niente
  limite 500. Nota: il filtro Stato di default "Aperti" lascia vuota la colonna Completati (come prima).
- `QuoteWorkflowMigrationTest` a `--step=103` (include le 3 migrazioni employment della 0166).
- Verifier: Pest 8557/8558 (1 skipped), Pint pulito; Vitest 6204/6204 (dopo fix del test AC-007 0163 che cercava un
  `<dt>` inesistente), tsc -b EXIT 0, ESLint pulito. Guide IT/EN tasks, task-types, time-entries aggiornate
  (work-orders resta "in fase di sviluppo"); manuale Claude Docs aggiornato.
- Debito: `TasksTableDefinition.php` 491, `task-schema.ts` 485, `features/tasks/types.ts` 482,
  `task-form-subtasks-section.tsx` 427, `table-view.tsx` 494: split al prossimo intervento. Test PHP con helper
  globali `function_exists()` duplicati (TaskConfig, WorkOrders) falliscono se eseguiti in sottoinsiemi.

## UTENTE CON PIU' RESPONSABILI ("RISPONDE A" MULTIPLO) — SPEC 0166 — VERDE, NON COMMITTATO (2026-09-25)

- `employment_profiles.reports_to_id` eliminata -> pivot `employment_profile_manager` (employment_profile_id,
  user_id=responsabile). Migrazioni `2026_09_25_100000/100100/100200` (tabella, spostamento dati + drop con down()
  lossy, rinomina permesso campo). `EmploymentProfile::reportsTo()` ora BelongsToMany ordinata per nome, accessor
  `reportsToIds`.
- Contratto: write `employment.reports_to_ids: int[]` (assente = invariato, [] = svuota; is_manager => svuota);
  read `reports_to_ids` + `reports_to: [{id,label}]`; permesso campo `employment.reports_to_ids` (multiselect);
  griglia utenti `reports_to: [{id,name}]` (`UserReportsToColumn`: filtro "almeno uno", sort MIN nome);
  team pulse Ore `manager_ids` (ascendente) al posto di `manager_id`.
- `EmploymentWriter::write($user, $employment, $actor)`: cambio dell'insieme => voce activity esplicita su
  EmploymentProfile (`reports_to_ids` old/attributes); la scheda Storico non cambia (D-6).
- Ore: `TimeEntrySubordinateResolver` segue tutti i responsabili; FE `team-tree.ts` chiavi nodo per PERCORSO
  (`12/40/57`), un collaboratore compare sotto ogni responsabile in lista, guardia cicli.
- Import legacy: sorgente esterna ancora a un solo `reports_to_id`; re-import aggancia solo se il profilo non ha
  responsabili.
- Guide IT/EN (users, time-entries) e manuale Claude Docs aggiornati; il PDF derivato va rigenerato.
- Verifica: suite completa BE 8538/8541 e FE 6202/6203; i 3 rossi (QuoteWorkflowMigrationTest,
  RequestManagementReportTransitionsTest, note-composer) passano rilanciati da soli, non toccano la 0166.
  `tsc -b --force` rosso SOLO su file della spec 0163 in corso (work_order_stage), non della 0166.
- Nota: i test PHP locali richiedono `XDEBUG_MODE=off` (segfault Xdebug).

## RUOLI MANSIONARIO — CHI VEDE GLI UTENTI APRE TUTTO IL FORM UTENTE — VERDE, COMMITTATO (2026-09-25)

- Bug: impersonando Rosa Falzarano (`supervisore-commerciale`), "Modifica utente" dava 403 su
  `GET /api/personal-data?personable_type=user&personable_id=…`. Il ruolo aveva `users.*` ma nessun
  `personal_data.*`/`contacts.*`/`addresses.*`; la matrice campi `users → personal_data.*` mostra le sezioni
  anagrafiche ma NON apre le API (permesso di modulo separato).
- Regola (direttiva utente): `QualificaRoleSeeder::permissionsOf` aggiunge a ogni ruolo che ha
  `Catalogue::USER_FORM_GATE` (`users.view`) le abilities `USER_FORM_ABILITIES` (viewAny/view/create/update/delete)
  su `USER_FORM_MODULES` (`personal_data`, `contacts`, `addresses`). Niente export/import/viewActivity.
  Derivata dai permessi, non da un blocco: vale per qualunque blocco apra gli Utenti.
- Test: `tests/Feature/Users/QualificaUserFormPermissionTest.php` (lettura scheda + CRUD contatti/indirizzi da
  supervisore; nessun altro ruolo riceve lo stack).
- Produzione: rieseguire `php artisan db:seed --class=QualificaRoleSeeder` (o `QualificaProductionDataSeeder`).
- ROSSO PREESISTENTE, non causato da questa modifica (fallisce anche con la regola disattivata):
  `QualificaRoleMatrixTest` "blocks the supervisor server-side…" → `POST /api/tables/users/rows` 500,
  `UserEmploymentColumns::userSummary()` riceve una Collection da `$employment->reportsTo`: il lavoro in corso di
  spec 0166 (non committato) ha reso `reportsTo` BelongsToMany e la colonna `reports_to` non e' ancora adeguata.
- Manuale: nessun impatto (le guide non descrivono le matrici dei ruoli seedati).

## GESTIONE ISCRITTI — VISIBILITA' SEDE FISICA (SPEC 0165) — VERDE, COMMITTATO (2026-09-25)

- Nuova ability `viewPrimarySite` SOLO su `enrollee-management` (`RequestModule::PRIMARY_SITE_ABILITY`,
  `hasPrimarySiteTier()`); 17 ability iscritti. Label IT "Visualizza sede fisica", EN "View physical site".
- `RequestManagementScope::visibleSiteIds()` e' l'unica fonte delle sedi aperte: viewSite = tutte le membership,
  altrimenti viewPrimarySite = sola sede fisica (pivot `is_primary`), altrimenti nessuna. Usata da
  `isInActorSites()` e `scopeToActor()`. `RequestManagementNotable` aggiunge il tier menzioni via
  `employment.primaryOperationalSite` (helper `existingPermission()`).
- Seed: blocco `PRIMARY_SITE_ENROLLEES` sul ruolo `commerciale`; `commerciale-iscritti` in RETIRED_ROLES, i suoi 9
  utenti del roster passano a `commerciale` (vedono ora solo la sede fisica, non piu' tutte le sedi).
  `supervisore-didattica` resta con viewSite.
- Test: `EnrolleePrimarySiteVisibilityTest` (AC-001..005); aggiornati per requisito PermissionsSync (16->17) e
  QualificaOperatorSeederTest (commerciali, ruolo ritirato). Guide IT/EN enrollee-management e manuale aggiornati.

## REPORT GESTIONE RICHIESTE — COLONNE SENZA PERIODO (SPEC 0159 rev-1) — VERDE, COMMITTATO (2026-09-25)

- Prima versione (82884ffc) annullata con a7808c55; questa e' la rev-1 dal brief utente definitivo.
- Catalogo `indicator_columns` a 14 chiavi: telefonate, `unhandled_callbacks`, `unhandled_new_contacts`,
  `current_potentials`, richiami, nuovi_contatti, potenziali, poi invariato. Indici CSV con catalogo completo
  (factory `reportable()`): +3 per tutto cio' che segue telefonate; i test posizionali sono stati rimappati.
- Regole: `unhandled_callbacks` = vecchia regola di `richiami` (non chiuse, richiamo <= oggi). `richiami` ora e'
  legata al periodo (richiamo dentro [date_from, date_to], futuri inclusi, scelta utente). `unhandled_new_contacts` =
  stato `open` a prescindere dalla creazione. `current_potentials` = stato ATTUALE nel gruppo pending/validated
  (`Indicators/CurrentStatusGroupIndicator`). Callback/NewContacts parametrizzati con `withinRange`.
- Label: SOLO richiami/nuovi_contatti/potenziali con " (nel periodo selezionato)" / " (selected period)".
- Seed produzione `ReportColumnsCatalogue`: mappa spec 0141 con le tre colonne SOSTITUITE dalle versioni senza
  periodo, il resto invariato. Solo categorie con `report_columns` null (installazioni esistenti invariate, D-8).
- Guide IT/EN (request-management, product-categories, enrollee-management) e manuale Claude Docs aggiornati.

## FILTRI PERSONALIZZATI E/O, PREFERITI, CHIP — SPEC 0158 — VERDE, COMMITTATO (2026-09-25)

- Contratto: blocco "DETTAGLIO CONGELATO" nella spec (Rule {field, operator, value}, gruppi and/or, mappa
  tipo-regola e operatori identica BE/FE). Chiude la serie di allineamento Task a q-net 0153-0158.
- Backend: `Services/Table/CustomFilterRuleValidator` (resolveType statico, validate, costanti 20/500/255/1..366) e
  `CustomFilterRuleApplier` (ogni regola -> payload del filtro di colonna AG Grid via
  `TableQueryBuilder::applyColumnFilter()`, date su colonna reale con whereDate, negativi includono i vuoti).
  `customFilterRules` su POST rows ed export: se non null, filterModel e advancedFilters (anche required) ignorati.
  /values NON riceve le regole (chiarimento nella spec). Viste: `rules` (con rules -> filters/advanced salvati {}),
  `is_favorite`; preferiti POST/DELETE `tables/{domain}/filter-views/{id}/favorite` (404 altro dominio/privata
  altrui); rotte viste estratte in `routes/api/filter-views.php` (api.php era oltre 500 righe).
  `TableFilterViewPolicy` ora BasePolicy con `abilities(): ['publish']` -> permesso `table-filter-views.publish`
  (permission-only resource, area "Trasversali"); 403 su POST/PUT shared senza permesso. Migrazioni
  2026_09_25_100000/_100100 (QuoteWorkflowMigrationTest aggiornato).
- Frontend: `features/table/custom-filters/` (rule-types, rule-schema, costruttore, stato filtro attivo, chip);
  `createSsrmDatasource` ora prende un oggetto di opzioni; `advancedFilters.clearField()`; guide general/roles IT/EN.
- Verifier: Pest 8458/8459 (1 skipped), Pint pulito; Vitest 6152/6152, tsc -b pulito, ESLint 0 errori. AC-001..007 PASS.
- Manuale Claude Docs aggiornato (Filtri salvati/filtro personalizzato/chip, permesso in Ruoli; sezione Task
  riscritta per 0153-0157).
- Debito: `frontend/src/features/table/table-view.tsx` 494 righe (split obbligatorio al prossimo intervento),
  `filter-views-control.tsx` 353. Ramo `filterType: 'multi'` dormiente e disallineato (BE `type date`, FE
  `datetime`): nessuna colonna lo dichiara oggi, allinearlo se diventa reale.

## REFERENTI CON BUONI — LINK DELLA CARD GATED SUI PERMESSI — VERDE, NON COMMITTATO (2026-09-25)

- Decisione utente 2026-09-25: un record collegato (opportunita'/offerta) sulla card buono e' cliccabile solo se
  l'utente puo' vederlo. Opportunita' -> `opportunities.view`; Offerta -> `quotes.view` (modulo Offerte), altrimenti
  `request-management.view` (Gestione Richieste, righe = quotes per spec 0086, stesso id), altrimenti testo semplice.
- `RECORD_OPEN_TARGETS` + `resolveOpenDomain` in `reward-detail-renderer.tsx` (fail closed mentre le abilities
  caricano); nuova prop `canOpenRecord` su `RewardCard`. Solo frontend: il backend autorizza gia' il dettaglio.
- Limite noto: con `request-management.view` ma offerta fuori dal proprio scope (operatore/sede, senza `viewAll`)
  il link e' cliccabile ma il dettaglio risponde 403. Il client non puo' valutarlo senza un flag per-record dal server.
- Guide in-app IT/EN e manuale Claude Docs aggiornati.

## ALLINEAMENTO TASK A Q-NET — SPEC 0157 SINTETICA + KANBAN (+ integrazioni 0156) — VERDE, NON COMMITTATO (2026-09-24)

- Backend: POST /rows accetta `tree`/`treeParentId` (TableRowsRequest, exists -> 422; `authorizeTreeParent` in
  TableController -> 403 via Gate view); hook generici `supportsTree()`/`applyTreeScope()`/`maxRowsLimit()` su
  TableDefinition (pass-through in `DelegatesUnaugmentedTableMethods`), scope in `app/Tables/Tasks/TaskTreeScope.php`
  applicato DOPO filtri/ricerca/avanzati (D-1). Limite righe: 100 di default, tasks 500 (`MAX_ROWS_LIMIT`).
  Test `TaskTableTreeTest.php`, `TaskKanbanPatchTest.php`.
- Frontend: `treeData` opt-in in TableView/DataTable (`components/data-table/tree-data-grid-options.ts`,
  ssrm-datasource invia tree/treeParentId da groupKeys). /tasks: selettore Analitica/Sintetica/Kanban
  (`use-task-list-view-mode.ts`, `use-task-kanban-mode.ts`, localStorage con try/catch). Kanban in
  `features/tasks/task-kanban/` su shell condivise `components/kanban/{kanban-column-shell,kanban-card-shell}.tsx`,
  riusate dalla board commessa (D-6, board invariata, suoi test non toccati). Stato: riusa
  `task-status-intercept-decision.ts` della cella 0156.
- Decisione utente 2026-09-24 (spec D-2 aggiornata): colonna "Piu' avanti" (scadenza dopo fine mese, drop = primo
  del mese successivo); "Questo mese" = mese corrente + senza scadenza.
- Integrazioni 0156 nello stesso working tree: 5 test backend (notifiche+rollback bulk, anti-N+1 griglia, 422 cella),
  8 file di test FE, 2 fix FE (refresh griglia alla chiusura note; toast `incompatible_tasks` nei dialog bulk
  data/priorita', prima ingoiato da `applyServerValidationErrors` che ritorna true per ogni 422).
- Verifica (verifier): Pest 8424/8425 (1 skipped), Pint pulito; Vitest 6079/6079, tsc -b pulito, ESLint pulito.
- Nota: `createSsrmDatasource()` ha 8 parametri posizionali: al prossimo parametro convertirlo in oggetto opts.
- Manuale Claude Docs: sezione Task da aggiornare (selettore viste, Sintetica, Kanban per stato/scadenza con
  "Piu' avanti", regole di trascinamento, limite 500, "+" di colonna). Prossima: spec 0158.

## ALLINEAMENTO TASK A Q-NET — SPEC 0156 LISTA — VERDE, COMMITTATO dall'utente in 8a67e7ae (2026-09-24)

- Contratto congelato nella spec (blocco "DETTAGLIO CONGELATO" in <contract>). Serie: prossima 0157, poi 0158.
- Motore generico: hook `aggregates(Builder)` su `TableDefinition` (default `[]`, pass-through anche in
  `CustomFields/DelegatesUnaugmentedTableMethods`: chi aggiunge un metodo all'interfaccia deve aggiungerlo li'),
  esposto come `meta.aggregates` di POST /rows solo se non vuoto. `RelationValueScopeChecker` estesa a
  task-statuses/types/priorities/importances e work-orders; `CellValueValidator` ha l'editor `work_order_stage`
  (valore = id, appartenenza verificata da TaskStageGuard). FE: `TableView` ha `renderFooter`, `pinnedRowSlot`
  (slot sotto la griglia, non vero pinned row AG Grid), `interceptCellCommit`, `disableBuiltinDelete`.
- Task: `TaskRowMapper`, `TaskAggregateColumns` (actual_minutes/parent_title via subquery bound), `TaskCellWriter`
  (cella -> `TaskService::update`, tutte le guardie), `editable` di riga = update E non `TaskWriteLock::isLocked`
  (super-admin escluso; la cascata antenati resta verificata alla scrittura). Ricerca per ID esatto su termine numerico.
- Bulk: POST /api/tasks/bulk (`TaskBulkService`, savepoint per task dentro una transazione esterna: un
  incompatibile -> rollback totale + 422 `incompatible_tasks`; notifiche sicure perche' `DB::afterCommit`).
  Per-riga condivisa con la board commessa in `TaskBulkActionExecutor` (board invariata).
- FE: stato in cella verso chiusura apre sempre "Completa"; da chiuso ad aperto chiama uncomplete. Clona =
  `TaskFormMode` `duplicate`. "Elimina" generico disattivato su tasks (c'e' il bulk delete tutto-o-niente).
- Test backend: esegui con `php -d xdebug.mode=off vendor/bin/pest` (con `artisan test` xdebug sembra bloccare).
- Verifica: Pest 8405/8406 (1 skipped), Pint pulito; Vitest 5975/5975, tsc -b pulito, ESLint pulito. Verifier: VERDE.
- APERTO: test aggiuntivi (dialog bulk, riga rapida, note, anti-N+1, notifiche+rollback) in corso, non committati.
  Manuale Claude Docs: sezione Task da aggiornare (filtri Anagrafica/Commessa/Questo mese/ricerca per ID, colonne
  nuove, modifica in cella, azioni Duplica/Note, azioni massive tutto-o-niente, riga rapida, totale minuti).
  Debito: `TasksTableDefinition` 471 e `TaskColumnCatalog` 464 righe.

## STAFF NEL PRODUCTION SEED: RUOLO `operatore-base` — VERDE, COMMITTATO (2026-09-24)

- Direttiva utente 2026-09-24: gli utenti di `utenti_nome_cognome.csv` (195) entrano nel production seed. I 68
  gia' seminati (OperatorRoster + super-admin di TestUsersSeeder) sono esclusi dai dati; i 127 nuovi stanno in
  `database/seeders/QualificaCatalog/StaffRoster.php` (CSV ISO-8859-1 ricodificato: Nicolò, Miccichè, ...).
- Nuovo ruolo `OperatorRoleCatalogue::BASE_ROLE = 'operatore-base'` ("Operatore base (Task e Segnatempo)"),
  `blocks: []` -> solo `EVERY_ROLE_BLOCKS` (task propri + segnatempo propri). Menu: dashboard, /tasks, /time-entries.
- `QualificaStaffSeeder` = step 8 di `QualificaProductionDataSeeder`, ultimo. CREATE-ONLY: email gia' presente nel
  DB -> saltata del tutto (ruolo, nome, password mai toccati). Stesso flusso account degli operatori (nome,
  locale it, email verificata, `seeding.password`, anagrafica via `SyncsPersonName`); niente employment profile.
- Test: `tests/Feature/Seeding/QualificaStaffSeederTest.php` (nuovo); i due test Task/Segnatempo "ogni ruolo"
  spostati da `QualificaRoleMatrixTest` (oltre 500 righe) in `tests/Feature/Users/QualificaTaskGrantsTest.php`,
  che ora semina anche lo staff (il ruolo base ha i suoi utenti li'); helper `visibleRoutes()` spostato in
  `tests/Pest.php`. Manuale: nessun impatto (non elenca i ruoli predefiniti).

## SEED DI ESEMPIO: OFFERTE, CONTRATTI, COMMESSE — VERDE, NON COMMITTATO (2026-09-24)

- Direttiva utente 2026-09-24: `qualifica:seed-sample` semina anche Offerte, Contratti e Commesse. Tre nuovi step in
  coda a `QualificaSampleDataSeeder` (flag `--quotes` 15, `--contracts` 8, `--work-orders` 4):
  - `QualificaSampleQuoteSeeder`: un'Offerta per ogni opportunita' del batch SENZA offerta (rispetta
    `single_quote_per_opportunity` per costruzione), righe REVENUE su prodotti SALE delle categorie gia' della card,
    ramo `single` = 1 riga qty 1, riga COST imputata a meta' (se esistono prodotti COST), GA1+GA2 promossi.
    Esito rotato sul set risolto: `open` / stato custom open-pending / `closed_lost`. Mai `closed_won`.
  - `QualificaSampleContractSeeder`: chiude vinte (QuoteService::update) solo offerte aperte, con righe REVENUE e
    `ContractEligibility::allowsContract` -> il contratto nasce da `ContractLifecycleManager`. Forme ruotate: meta'
    Validato (closed_won), da validare, lavorazione in scadenza, disdetto, sospeso.
  - `QualificaSampleWorkOrderSeeder`: "Programma" sui contratti `mayProgram()` = una commessa con tutte le righe
    libere (D-4 spec 0095) via `CreateWorkOrderData::forContractGeneration` + `WorkOrderService::create`.
- Confinamento: l'orchestratore prende il watermark `max(opportunities.id)` prima dello step 1 e lo passa come
  `sinceOpportunityId` agli step 4-6 -> mai toccate offerte/contratti reali.
- `Concerns/ResolvesSeedActor` (attore privilegiato, fallback primo utente).
- Step 7-8 (stessa direttiva): `QualificaSampleTaskSeeder` (`--tasks` 30) e `QualificaSampleTimeEntrySeeder`
  (`--time-entries` 60). Task su commessa XOR opportunita' (spec 0154 D-11), anagrafica del record, team = supervisore
  commessa / GA opportunita'; esiti ruotati: stato derivato, pending/chiuso negativo via PATCH (creatore), completati
  via `TaskCompletionService::complete()` dall'assegnatario -> segnatempo della chiusura (spec 0127). Mail silenziate
  (`Concerns/SeedsWithoutNotifications`). Segnatempo: 2/3 su task aperti (owner = assegnatario, titolo/link dal task,
  D-5 spec 0122), 1/3 su commessa o opportunita'; giorni feriali ultime 2 settimane.
  DIPENDENZA: il Task seeder usa `CreateTaskData`/`TaskService` nello stato ATTUALE dell'albero, che include la spec
  0154 non ancora committata (altra sessione). Usa solo argomenti gia' presenti prima della 0154.
- Comando unico senza flag = tutto coi default: `php artisan qualifica:seed-sample`.
- `--size=N` (direttiva utente 2026-09-24): N di ogni dominio in un colpo; lead = 3N (N convertiti + N anagrafiche
  libere per le opportunita' + N per le richieste), convertiti = N. Un flag di dominio esplicito vince su `--size`.
- LIMITE DA REGOLA sul catalogo reale: solo "APL" ha categorie vendibili che generano contratti (Formazione no,
  Consulenza senza prodotti) -> pochi contratti/commesse; il seeder avvisa quando ne produce meno dei richiesti.
- Test: nuovi `QualificaSampleDealFlowSeederTest` (8) e `QualificaSampleActivitySeederTest` (6); aggiornati i conteggi in `QualificaSampleDataSeederTest` e
  `SeedSampleDataCommandTest` (requisito cambiato). Dry-run sul DB locale in transazione annullata: ok.
- Manuale: nessun impatto (comando di sviluppo).

## "RESIDUO ORE" SU DIL — VERDE, NON COMMITTATO (2026-09-24)

- `ContactProcessingAttributeCatalogue`: nuovo attributo `remaining_hours` ("Residuo Ore", `integer`, costante
  `DIL_REMAINING_HOURS`) nel set proprio di DIL (Offerta + Commessa), dopo le due date Dote. Codice nuovo e non
  `dote_remaining_hours` ("Residuo Ore Dote" del ramo Formazione): una riga attributo = una sola etichetta.
  In `ROWS` ha una riga tutta sua dopo `['dote_activation_date', 'dote_expiry_date']`.
- `PREVIOUS_OWN_ATTRIBUTES` ora e' `array<string, list<list<string>>>` (una lista per revisione);
  `SeedsAttributeLayouts::effectiveCodeRevisions()` le scorre tutte. DIL ha due revisioni (prima del 09-16 e prima di
  "Residuo Ore"), cosi' i layout gia' seedati vengono ricomposti invece di lasciare il campo in "Altre informazioni".
- Test: nuovo `QualificaDilRemainingHoursTest` (tipo/assegnazione, posizione, convergenza); liste DIL aggiornate in
  `QualificaContactProcessingSeederTest`, `QualificaQuoteLayoutSeederTest`, `QualificaDilCourseFieldsTest` (il rewind
  alla revisione "Corso Scelto" ora toglie anche `remaining_hours`, che quella revisione non aveva).
  Products 219/219, ProductCategories 275/275, Unit/RequestManagement 22/22. Seeding 102/107: i 5 rossi sono
  `QualificaSampleDataSeederTest`/`SeedSampleDataCommandTest` (lavoro Task/sample non committato, estraneo). Pint pulito.
- Manuale: nessun impatto (guide in-app e manuale Claude Docs non elencano i campi di DIL).

## GESTIONE RICHIESTE: OPERATORE E TUTOR ORDINABILI/FILTRABILI — VERDE, COMMITTATO (2026-09-24)

- Direttiva utente 2026-09-24, supera spec 0086 AC-011. `RequestManagerColumns`: `operator_ga2` e `manager_ga1` ora
  `sortable: true`, `filterable: true`, `filterType: set` (filtro sul `users.name` dell'occupante, "(Vuoti)" = slot vuoto).
- Operatore (GA2): voce in `RequestRelationColumns::QUOTE_RELATIONS` (`operator` / `users` / `quotes.operator_id`).
- Tutor (GA1): nuova `RequestManagerSlotColumn` (posizione `quote_user` = `ManagerPositions::GA1`; sort con subquery
  correlata, filtro `whereHas('managers')` sulla posizione, valori distinti), delegata da `RequestRelationColumns` quando
  la colonna non e' una sua relazione. Iscritti eredita la stessa definizione.
- Test: nuovo `RequestManagementManagerColumnsSortFilterTest` (8); aggiornate le asserzioni `sortable/filterable` in
  InlineEditors/OperatorPickerScope/ManagerGa1Column (requisito cambiato). RequestManagement + EnrolleeManagement +
  Table + Exports: 1104/1104. Pint pulito. Frontend invariato (la griglia legge i flag dalla config).
- Manuale: nessun impatto sulle guide in-app (non dichiaravano le colonne non ordinabili).

## DASHBOARD GESTIONE RICHIESTE: "ESPANDI / COMPRIMI TUTTO" — VERDE, COMMITTATO (2026-09-24)

- `use-request-dashboard-collapse.ts`: nuovi `areAllOpen(targets)` / `setAllOpen(targets, open)` + helper puro
  `dashboardCollapseTargets(data)` (solo i blocchi realmente renderizzati: overall `section`; categoria `section` +
  `tiles`/`charts` se non vuoti). Espandi = apre tutti i blocchi (grafici compresi); comprimi = chiude solo le `section`
  (riaprendone una a mano si vede tutto). Stessa entry localStorage `${module}.dashboard-collapse`.
- `request-dashboard-panel.tsx`: il hook di collasso e' sollevato in `RequestDashboardPanelBody` e passato a
  `DashboardResults` (prop `collapse`), cosi' barra e sezioni condividono lo stato.
- `request-dashboard-filter-bar.tsx`: nuove prop `allExpanded`, `canToggleExpanded`, `onToggleExpanded`; pulsante icona
  (`ChevronsUpDown`/`ChevronsDownUp`) prima di Filtri, label `requestManagement.dashboard.expandAll|collapseAll`,
  disabilitato senza sezioni. Vale anche per Gestione Iscritti (stesso pannello).
- Test: filter-bar (+2), panel (+3: expand-all persistito, collapse-all, Gestione Iscritti con storage separato). request-management + stats 530/530, help parity
  verde, ESLint e `tsc -b --force` puliti. `request-dashboard-panel.tsx` a 314 righe (soft limit superato di poco).
- Manuale: guida in-app IT/EN `statistics-panel` aggiornata; manuale Claude Docs aggiornato (paragrafo sul pannello statistiche).

## SEED PRODUZIONE: TASK E SEGNATEMPO PROPRI A OGNI MANSIONE — VERDE, NON COMMITTATO (2026-09-24)

- `OperatorRoleCatalogue`: nuovi blocchi `OWN_TASKS` (`tasks.*` meno `OWN_TASKS_DENIED_ABILITIES` = `viewAll`,
  `viewSite`, `manageAll`; + `COLLABORATION_PERMISSIONS` = `notes.create` + `attachments.*`) e `OWN_TIME_ENTRIES`
  (`time-entries.*` meno `manageAll`, `viewAll`, `exportMonthly`). `EVERY_ROLE_BLOCKS` li aggiunge a tutti i ruoli in
  `QualificaRoleSeeder::run()`. `REQUEST_EXTRA_PERMISSIONS` ora = `referents.create` + `COLLABORATION_PERMISSIONS`.
- Configuratori task (stati/tipi/categorie/priorita'/importanze/modelli) restano chiusi; i for-select non hanno gate.
- Test: `QualificaRoleMatrixTest` (menu marketing/commerciale con `/tasks` e `/time-entries`, supervisore non piu'
  chiuso su `tasks`: requisito cambiato; nuovi test per tutti i ruoli + scoping task altrui 403). Users + RequestManagement
  + Seeding + SeederFlow + Tasks + TimeEntries: 1646/1646. Pint pulito.
- Manuale: guide in-app nessun impatto (non descrivono i permessi per mansione); manuale Claude Docs non accessibile,
  da verificare se elenca i moduli visibili per mansione.

## "TITOLO DI STUDIO" MULTISELECT, "QUALIFICA PROFESSIONALE" RITIRATA — VERDE, NON COMMITTATO (2026-09-24)

- `ContactProcessingAttributeCatalogue`: `degree` ("Titolo di Studio") ora `config = DEGREE_CONFIG`
  (`display: multiselect`) con nuova opzione `professional_qualification` / "Qualifica Professionale" in coda a
  `DEGREE_OPTIONS`. L'attributo separato `professional_qualification` su "Autofinanziato" e' tolto da `ATTRIBUTES`/`ROWS`
  e aggiunto a `RETIRED_ATTRIBUTES` (disassegnato + rimosso dai layout al re-seed; la riga attributo e gli eventuali
  valori salvati restano, non letti). Rimossi `PROFESSIONAL_QUALIFICATION` e `PREVIOUS_OWN_ATTRIBUTES['Autofinanziato']`.
- `QualificaContactProcessingSeeder::promoteDegree()`: su installazioni gia' seedate porta `degree` a enum multiselect e
  avvolge in lista i valori scalari gia' salvati in `quotes`/`work_orders.attribute_values` (via `DB::table`, niente
  activity log).
- Test: `tests/Feature/Products/QualificaDegreeMultiselectTest.php` (sostituisce `QualificaProfessionalQualificationTest`),
  opzioni aggiornate in `QualificaContactProcessingSeederTest`. Products + Seeding + SeederFlow: 319/326, i 7 rossi sono
  `DemoTaskSeederTest` ("task already closed", lavoro Tasks non committato, estraneo). Pint pulito.
- Manuale: nessun impatto (i campi "Dati Lavorazione Contatto" non sono elencati nelle guide).

## BADGE NOTIFICHE SIDEBAR ROSSO — VERDE, NON COMMITTATO (2026-09-24)

- `app-sidebar.tsx`: il contatore non lette sul link **Notifiche** ora è un pallino rosso (`UNREAD_BADGE_CLASS`:
  `rounded-full bg-destructive text-white`, con override `peer-*` per hover/active). Primitivo `SidebarMenuBadge` invariato.
- Guide in-app `notifications.ts` IT/EN aggiornate ("pallino rosso"/"red dot"). Manuale Claude Docs non accessibile
  dalla sessione: sezione Notifiche da allineare.

## BARRA COMPLETAMENTO UNIFICATA — VERDE, NON COMMITTATO (2026-09-24)

- Unico componente `components/completion-bar.tsx` (`CompletionBar`: `value|null`, `label`, `size`, `barClassName`,
  `valueClassName`) colorato da `components/completion-tone.ts` (spostato da `work-orders/task-board/`, test incluso).
  Cella griglia `features/table/completion-cell.tsx` (`CompletionCell`, prop `label`).
- Usato in: griglia task e commesse, header dettaglio task e commessa, sotto-task, readout form task, board
  (righe, kanban, riepilogo fase). KPI tile della board resta `completionTone` diretto (layout a tile).
- Eliminati `task-percentage-cell.tsx`, `work-order-completion-bar.tsx`, `TaskBoardCompletion`.
- Fuori scope (non completamento): barra probabilita' opportunita' e budget progetto hanno scala propria.
- Vitest tasks/work-orders/components/table/help 1217/1217, `tsc -b --force` ed ESLint puliti.
  Manuale Claude Docs (sezione Attivita') da allineare sui colori del completamento.

## ALLINEAMENTO TASK A Q-NET — SPEC 0155 CICLO DI VITA — VERDE, NON COMMITTATO (2026-09-24)

- Migrazioni 2026_09_24_110000 (task_recurrences: month_mode, ordinal, ordinal_weekday, year_month,
  workdays_only) e _110100 (tasks.subtask_position); `QuoteWorkflowMigrationTest` ora `--step=96`.
- Ricorrenza: validazione unica in `app/Http/Requests/Tasks/TaskRecurrenceRules.php` (Store/Update la usano);
  frequenze yearly/custom (custom = ogni N giorni), ordinale 1..5 (mese senza 5° -> saltato), workdays_only
  (solo lun-ven, nessun calendario festivita'). `month_mode` FACOLTATIVO: null = fixed (compatibilita').
  Ogni occorrenza copia i sottotask diretti (un livello); `isVirgin` ignora le copie intatte e il potatore le
  elimina prima dell'occorrenza.
- Sottotask: `subtasks[]` in POST /api/tasks (max 50, ereditarieta' D-3, transazione unica); riordino
  POST /api/tasks/{task}/subtasks/reorder sui SOLI figli visibili all'attore (gli invisibili restano al loro
  posto e non sono mai restituiti); `TaskSubtaskResource` con position + permissions.actions (incluso delete).
- Percentuale: calcolata su `Task::completionSubtasks()` (tutti i figli, mai filtrati per visibilita'),
  precaricata via `TaskStatusResolver::EAGER_LOADS` (2 livelli) in griglia e dettaglio. Il conteggio dei
  sottotask aperti si precarica con `TaskActionAvailability::withOpenSubtasksCount()`. Test anti-N+1
  (conteggio query 1 vs 5) in TaskSubtaskPermissionsTest e TaskCompletionPercentageTest.
- Completamento `for_all_assignees` (FE: true da dettaglio, false dal pannello sottotask). 403 del dettaglio
  con `errors.access_contacts` (richiedente + creatore), senza log/Teams.
- Verifica: Pest completo 8389/8390 (1 skipped), Pint pulito; Vitest 5949/5949, tsc -b pulito.
- DEBITO: `app/Services/TaskService.php` e' a 500 righe (limite): la prossima modifica richiede uno split.
  `task-recurrence-section.tsx` 450, `task-schema.ts` 417, FE `types.ts` 478 righe.

## ALLINEAMENTO TASK A Q-NET — SPEC 0154 FORM E CAMPI — VERDE, COMMITTATO dall'utente in 63fa14b2 (2026-09-24)

- Migrazioni 2026_09_24_100000 (task_categories.parent_id, unique(parent_id,name)), _100100 (is_default su
  task_types/priorities/importances), _100200 (tasks.is_private, evidence, lead_id). `QuoteWorkflowMigrationTest`
  AC-004 usa `--step=94`: ogni nuova migrazione va aggiunta li' (numero + elenco nel commento).
- Categorie annidate: for-select in ordine depth-first con `meta.parent_id`/`meta.depth`; DELETE con figli 409.
  Lookup: `DefaultRowManager` (una sola riga predefinita, mai inattiva); for-select `meta.is_default`.
- Task: `is_private` ristretto ai membri in `TaskVisibilityScope` (super-admin escluso dalla regola); `evidence`
  sanificato da `TaskEvidenceWriter` (niente immagini incorporate); `lead_id` coerente con l'anagrafica
  (`TaskLeadRegistryGuard`); commessa/opportunita' esclusive e anagrafica derivata dalla commessa
  (`TaskWorkOrderOpportunityGuard`, `TaskRecordLinkCoherence`); `is_completed` in creazione chiude con segnatempo
  dell'attore (min 1 minuto: le regole segnatempo rifiutano 0); `notify_assigned_users` / `notify_new_assigned_users`;
  stato iniziale a mano ammesso solo nei gruppi di `TaskManualStatusGuard::MANUAL_GROUPS`.
- Super-admin: lista che parte da "Assegnati a me" come per tutti (decisione utente: resta cosi').
- Verifica: Pest 8344/8345 (1 skipped), Vitest 5915/5915, tsc -b e ESLint puliti. Prossima: spec 0155.

## ALLINEAMENTO TASK A Q-NET — SPEC 0153 REGOLE E NOTIFICHE — VERDE, COMMITTATO dall'utente (2026-09-24)

- Serie approvata 0153-0158 (`docs/specs/`), da eseguire in ordine; 0153 fatta, prossima 0154 (form e campi).
- Lista: `assignment` e' `enum` multiplo richiesto, default `['assigned_to_me']`, OR; `all` = task dove ho un ruolo
  (anche con viewAll); `visible` = nessuna restrizione di ruolo (decisione utente: "Tutti i visibili"), nascosto via
  descriptor `excludedValues` a chi non ha tasks.viewAll/viewSite. `AdvancedFilterApplier`: `enum` + `multiple`
  accetta liste. Ordinamento predefinito updated_at desc (colonna nascosta ordinabile in `TaskColumnCatalog`).
  Test di griglia/visibilita' passano `assignment: ['visible']`. Dashboard "Tutti" linka `assignment=visible`.
- Stato iniziale open solo se unico assegnatario = richiedente. Eliminazione: `canUpdate` + task aperto non
  bloccato, cascata in `TaskDeleteCascade` (422 se un discendente non e' eliminabile), DELETE resta 204.
  Reject -> `assigned` + feedback azzerato. Blocco solo su task aperti; complete/uncomplete/reject lo tolgono.
  Super-admin modifica task chiusi (`TaskWriteLock::assertStructuralWriteAllowed($actor)`). Segnatempo: chi puo'
  modificare il task gestisce tutte le voci. Percentuale: closed_positive 100, closed_negative configurabile,
  aperto con sottotask = media ricorsiva max 99.
- Notifiche: `TaskNotifier::closed/feedbackInserted(task, actor, includeAssignees)`; assegnazione esclude il
  creatore; richiesta aggiornamento `{ target: assignees|observers|all, message 3..2000 }`, osservatori in copia
  con `is_cc` (ora in `NotificationData`, esposto dall'API). Blocked -> 422 (prevale su 0126 D-6).
- Verifica: Pest completo 8236/8238 (1 fallimento preesistente `QuoteWorkflowMigrationTest` AC-004, presente
  anche senza queste modifiche), Pint pulito; Vitest completo 5875/5875, `tsc -b --force` pulito, ESLint pulito
  sui file toccati. Guide in-app IT/EN tasks e dashboard aggiornate.
- APERTO: manuale Claude Docs non accessibile (doc non condiviso). Nel working tree ci sono modifiche NON di
  questa serie (test `Qualifica*`, refactor `components/completion-bar.tsx`): non toccarle, fuori dai commit.

## EXPORT CON FILTRI AVANZATI (confronto q-net, punto 1.1) — VERDE, NON COMMITTATO (2026-09-24)

- Bug: l'export generico ignorava `advancedFilters` (spec 0032) e i loro default `required` (tasks: `status=open`),
  quindi il file poteva contenere righe diverse dalla griglia. Ora `CreateExportRequest` accetta e valida
  `advancedFilters` (stesso `AdvancedFilterApplier::validate` di `TableRowsRequest`), `ExportController` lo congela
  nello state, `TableQueryBuilder::build()` chiama `applyAdvancedFilters`. FE: `ExportDialog` prop `advancedFilters`
  (da `advancedFilters.activeValues` in `table-view.tsx`), inviato nel payload e contato nel riepilogo "Filtri attivi".
- Test: `tests/Feature/Exports/ExportAdvancedFiltersTest.php` (4), `export-dialog.test.tsx` (+1). Pest Exports/Tasks/
  export 519/519, Pint pulito; Vitest exports/table/help verdi, `tsc -b --force` ed ESLint puliti.
- Guida in-app `general` IT/EN aggiornata (nota export). APERTO fuori scope: l'export non inoltra `productCategoryId`
  (request-management, colonne `attr.*`).
- Resto del PDF `confronto-task-qnet-qnet2-v2` (17 funzioni mancanti + 18 differenze): in attesa di decisioni utente.

## GRAFICI STATISTICHE DIFFERENZIATI (spec 0152) — VERDE, NON COMMITTATO (2026-09-23)

- Widget stats: `distribution.chart` (bars|columns|donut|stacked) e `trend.chart` (area|columns|line) + `tone` 1..5,
  enum `App\Stats\Widgets\{DistributionChart,TrendChart}`, parametri opzionali di `AbstractStatsDefinition::
  distribution()/trend()`; mappa per modulo D-5 applicata nelle 16 definizioni e fissata in `StatsEndpointTest`.
- FE: `normalize-chart-variant.ts` (assente/sconosciuto -> bars/area/1), `distribution-series.ts` (colori token o
  `--chart-1..5` in ordine fisso, max 6 + "Altri" `statsPanel.others`), `components/ui/{stat-column-chart,
  stat-donut-chart,stat-stacked-bar,stat-distribution-legend,stat-chart-tokens}`; `stat-chart` con `variant`/`tone`.
  Recharts: le rese devono essere il FIGLIO DIRETTO di `ResponsiveContainer` (un wrapper component => 0x0).
  Dashboard invariata: riusa `ModuleStatsPanel`, quindi stessi grafici dei moduli.
- Verifica: Pest Stats/Dashboard/Unit 1244/1244, Pint pulito; Vitest 5868/5868, `tsc -b --force` e ESLint puliti.
- APERTO: la palette `--chart-1..5` di `index.css` NON passa il validatore dataviz (light: chart-3 quasi grigio,
  chart-4/chart-5 indistinguibili; dark: contrasto basso chart-1/chart-4). Non toccata: decisione utente.

## DASHBOARD HOME DA Q-NET (spec 0151) — VERDE, NON COMMITTATO (2026-09-23)

- `/dashboard` non e' piu' un placeholder: card "Attivita' da completare" (5 contatori task come q-net + chip
  "Da validare"), sezione Segnatempo (riusa `TimeEntriesStatsPanels` + preset, default day), blocchi
  `ModuleStatsPanel` tasks/opportunities/quotes/leads/registries (vedi revisione sotto).
  Ogni blocco gated (`{domain}.viewAny`, `time-entries.viewAny`, `request-management.report`) e NON montato se
  nascosto. Grafico ad area q-net escluso (dati finti). Gestione Iscritti esclusa (non richiesta).
- Backend: `GET /api/dashboard/tasks` (`routes/api/dashboard.php`, `Dashboard\DashboardTaskCountersController`,
  `Services\Tasks\TaskDashboardCounters`) riusa `TaskVisibilityScope` + `TaskAdvancedFilterApplier` (parita'
  contatore = righe lista, sottotask compresi). Nuovi enum: `TaskListStatus::InValidation`,
  `TaskAssignmentScope::{AssignedByMe, CreatedByMe, ObservedByMe}` (semantica esclusiva q-net, D-3;
  `requested_by_me` invariato). `Stats\Quotes\QuotesStatsDefinition` (total, revenue_net, margin_net, won,
  by_status, trend) — il toggle Statistiche nella lista Offerte NON e' stato aggiunto (fuori scope).
- Frontend: `features/dashboard/**`; `TableView` ha `advancedFiltersOverride` + `onAdvancedFiltersOverrideCleared`
  (filtri dall'URL non persistiti, Applica/Reimposta li salvano e puliscono l'URL); `TableViewProps` estratto in
  `table-view-props.ts`; `features/tasks/use-task-list-url-filters.ts` (`/tasks?status=&assignment=`).
  i18n `{it,en}-dashboard.ts`, `moduleStats.quotes`, enum nuovi; guide in-app `dashboard` (riscritta) e `tasks`.
- Verifica (verifier): Pest Unit 1077/1077, Feature 7078/7080 (1 skip + `QuoteWorkflowMigrationTest:154` rosso
  PREESISTENTE, file non toccato), Vitest 5809/5809, `tsc -b --force` pulito, ESLint e Pint puliti.
- Debito: `use-advanced-filters.ts` 324 righe (soft limit). AC-012: 5 colonne da `xl`, a 1024px sono 3.
- REVISIONE UTENTE (stesso giorno, D-11): statistiche `tasks` DENTRO la sezione "Attivita' da completare"
  (sotto le card, nessun blocco Task separato); Gestione Richieste TOLTA dalla dashboard
  (`dashboard-request-management-section.tsx` eliminato, chiavi i18n `requestManagementSection` e
  `moduleSections.tasks` rimosse); card con colori/icone q-net (`DASHBOARD_TASK_CARD_TONES` in
  `dashboard-task-card-config.ts`, palette Tailwind con dark come `status-badge-classes.ts`). Vitest 5810/5810,
  tsc -b --force e ESLint puliti. Guide in-app e manuale Claude Docs aggiornati. Poi (utente): nel blocco
  Task della dashboard solo i 4 KPI, niente grafici — nuova prop `ModuleStatsPanel showCharts` (default true,
  la lista Task li mantiene).
- Prossimi passi: commit su richiesta.

## DETTAGLI RECORD ALLINEATI ALLO STILE OPPORTUNITA' — NON COMMITTATO (2026-09-23)

- Richiesta utente: tutte le viste dettaglio come Opportunita' — Modifica nell'header della card principale,
  Note/Documenti/Attivita' (+ extra) nella colonna laterale destra.
- Kit condiviso nuovo (usarlo per ogni nuovo dettaglio): `components/detail/record-body.tsx` (`RecordBody side`),
  `record-collaboration-card.tsx` (`RecordCollaborationCard tabs`, i tab li filtra il chiamante coi SUOI gate),
  `record-edit-button.tsx` (`RecordEditButton`), `features/activity-log/activity-log-tab.tsx` (`activityLogTab`).
  Rimossi `DetailHero`/`DetailMeta` da `detail-panel.tsx` (orfani); `DetailPanel/Section/Grid/Field` restano solo
  per `imports/lead-import-detail.tsx`.
- Migrati (6 agenti frontend): i 16 dettagli record + le ~26 anagrafiche lookup (ex `DetailPanel`, ora
  `RecordCanvas` + Edit in header + `detailOwnsEditAction: true`: prima in modale non avevano alcun Modifica).
  Work-orders: rimossi `work-order-collaboration-section.tsx` + gates hook (tab costruiti inline);
  tasks: `task-collaboration-section.tsx` e' ora l'hook `useTaskCollaborationTabs`, card spostata di lato.
  Pagine dedicate registries/referents/products: Edit tolto dal PageHeader (era doppio), ora `onEdit` alla View.
- Eccezioni volute: contracts tiene Modifica nella `ContractActionsBar` (direttiva utente 2026-08-31);
  field-change-requests ha Approva/Rifiuta nell'header e nessun lato (la resource non espone view_activity);
  users mostra Edit senza gate `resource.update` (`UserDetail` non ha `permissions`, invariato);
  request-management/enrollee (work panel) e lead-import detail non toccati. Note/documenti NON aggiunti a
  moduli che non li hanno (servirebbe backend).
- Verifica: Vitest intero 758 file / 5780 verdi, `tsc -b --force` pulito, ESLint pulito sui file toccati (2 errori
  preesistenti in `quotes/column-renderers.tsx` e `registry-form-metadata.test.tsx`, non toccati).
- Guide in-app: paragrafo sul layout comune in `general` (IT/EN, sezione `opening-records-and-quick-create`).
- Fix post-review (`/task-statuses/1`): 7 lookup avevano `RecordSection` direttamente nella `RecordCard` (senza
  `RecordSectionsGrid` = senza padding `p-4`); ora dentro la griglia con `full`. Tolto il cast `onEdit as () => void`
  (17 file): gate `canEdit && onEdit`. Regola: una `RecordSection` sta SEMPRE in un `RecordSectionsGrid`.

## NOTIFICHE: AZIONI CON ETICHETTA + "APRI" IN MODALE (spec 0150 D-3 rivista, D-8) — NON COMMITTATO (2026-09-23)

- Azioni riga letto/non letto = pulsanti con icona + testo (richiesta utente). Opzione generica OPT-IN
  `labeledActions` su `TableView`/`RowActionsOptions` (`features/table/row-actions.tsx`, costante
  `LABELED_ACTIONS_COLUMN_WIDTH = 190`); larghezza colonna Azioni via nuova prop `actionsColumnWidth`
  (`DataTable` -> `buildColDefs`, vince sul default 100/120). Gli altri domini restano a icone.
  Icone catalogo BE `mail-open` (mark-read) / `mail` (mark-unread) in `NotificationColumnCatalog`.
- "Apri" (`features/notifications/column-renderers.tsx`) e' un vero `<Link>`: se `findModuleRecordByPath` risolve
  un modulo registrato apre la MODALE (`useRecordModalLink`, Sheet nella cella), altrimenti naviga (es.
  `/imports/:id`). Non letta -> markAsRead + `api.refreshServerSide({ purge: false })` (non-purge: la cella e la
  modale sopravvivono, verificato in browser). La campanella resta invariata (naviga).
- Test: `column-renderers.test.tsx` (AC-012 rivisto), `row-actions.test.tsx` (AC-017), nuovo
  `components/data-table/column-def-builder.test.ts`, Pest `NotificationsTableTest` (icone). Vitest intero 5751 verdi,
  Pest Table+Notifications verdi, tsc -b / ESLint / Pint puliti. Guide in-app IT/EN aggiornate.
- Manuale Claude Docs NON aggiornato (documento non condiviso con la sessione): sezione Notifiche da allineare.
- Dev DB: 4 notifiche "[TEST] ..." create per demo@app.com (verifica visiva), cancellabili.

## NOTIFICHE: PAGINA DEDICATA (spec 0150) — NON COMMITTATO (2026-09-23)

Spec `docs/specs/0150-notifications-page.xml` (D-4 e AC-008 rivisti, AC-016 aggiunto su richiesta utente).
- BE: `NotificationPolicy` (viewAny=true per ogni autenticato, view/update = proprietario; niente permesso Spatie).
  `NotificationsTableDefinition` + `app/Tables/Notifications/{NotificationColumnCatalog,NotificationRowMapper,
  NotificationDerivedColumns}` registrata come `notifications` in `config/tables.php`; `baseQuery()` limitata
  all'attore (fail-closed senza attore); colonne status/title/message/level derivate dal JSON `data` (query builder,
  niente *Raw); azioni `mark-read`/`mark-unread`; nessuna colonna editabile, niente delete.
  Nuovi endpoint: `PATCH /api/notifications/{id}/unread`, `POST /api/notifications/bulk-read` {ids: uuid[] max 500}
  (route in `routes/api/notifications.php`, doc `docs/api/0004-notifications.md`).
  Fix generico: `TableController::updateRow(string $row)` + `TableCellUpdateService::update(int|string $rowId)`
  (prima un id non intero dava 500 TypeError; ora 403/404 con envelope). `TableDefinitionContractTest` ha
  l'allow-list `PERMISSIONLESS_VIEW_ANY_DOMAINS = ['notifications']`.
- FE: `TableRowId = number | string` (`features/table/types.ts`); ~35 tabelle usano `Number(row.id)` dove l'id e'
  numerico; bulk-delete resta number[]. Pagina `/notifications` (`pages/notifications-page.tsx`,
  `features/notifications/{notifications-table,column-renderers,use-unread-badge}`), "Apri" via `safeInternalPath`
  (segna letta se non letta). Link "Notifiche" + contatore non lette FISSO nel footer della sidebar sopra
  Impostazioni (`components/app-sidebar.tsx`), NON e' una voce di `config/navigation`. "Vedi tutte" nella campanella.
- Guida in-app IT/EN `notifications` + HELP_GUIDE_KEYS; manuale Claude Docs aggiornato (menu laterale, campanella,
  sottosezione "Pagina Notifiche").
- Verifica: Pest Notifications+Table+Tables+Navigation+Authorization+RoleMatrix 459/460 (unico rosso
  `MetaEndpointTest` users.export = flaky preesistente sotto parallelo, isolato 7/7); Pint pulito; tsc -b verde;
  Vitest intero 757 file / 5746 test verdi.
- Aperti: (1) con sidebar collassata il badge e' nascosto (default `SidebarMenuBadge`), il numero resta nel tooltip;
  (2) la guida `notifications` non compare nell'indice help ne' si apre da /notifications perche' l'help deriva
  dalla navigazione backend (stesso limite di Impostazioni); (3) colonna `id` di default `type: number` su uuid,
  nascosta e non filtrabile: follow-up opzionale.

## IMPOSTAZIONI: COLORE TEMA (PRESET COLORI DA q-net) — NON COMMITTATO (2026-09-23)

Richiesta utente: "ricopia da /Users/Repository/q-net l'impostazione di cambio colore del sistema". Portati gli 8 preset
di q-net (`src/theme/palette.ts`: default, forest, amber, rose, ocean, plum, graphite, terracotta), adattati a qnet-2.
- BE: colonna `users.color_preset` (string 16, nullable; migrazione `2026_09_23_120000`), `ColorPresetEnum` (fonte dei
  valori), fillable su `User`, regola `sometimes|required|in` in `UpdateProfileRequest` + `accountAttributes()`,
  `UserResource` serializza `'default'` se null. Nessun endpoint nuovo: `PATCH /auth/me`. Test `ColorPresetPreferenceTest`.
- FE: colori SOLO in CSS (`src/color-presets.css`, importato da `index.css`), chiave `[data-color-preset]`; toccano solo
  gli accenti (primary, ring, sidebar, sidebar-primary/accent/border/ring), mai la scala superfici. Il preset `default` e'
  il token set di `index.css` (selettori `:root, [data-color-preset='default']` e `.dark, .dark [data-color-preset='default']`).
  `ColorPresetProvider` (App.tsx, dentro UiScaleProvider) mette l'attributo su `<body>` (non `<html>`: batte i token
  ereditati senza lotta di specificita'). `ColorPresetForm` in Impostazioni › Impostazioni sistema › Colore tema: card
  radio con `data-color-preset` che mostrano i token reali del preset nella modalita' corrente. Id sincronizzati in 3 posti:
  `COLOR_PRESETS` (`features/appearance/color-preset.ts`), `color-presets.css`, `ColorPresetEnum`.
- Fixture `User` dei test FE: aggiunto `color_preset: 'default'` (12 file).
- Guida in-app IT/EN `general`: riga tabella Impostazioni + paragrafo "Colore tema". Manuale Claude Docs NON aggiornato (doc non condiviso con la sessione):
  da aggiungere in Impostazioni › Impostazioni sistema il paragrafo "Colore tema" (8 preset, anteprima, chiaro/scuro).
- Verifica: Pest Auth+Users 316/316; Vitest appearance 25/25, help+i18n+pages 297/297, fixture toccate 71/71;
  ESLint, Pint, `tsc -b --force` puliti. Contrasto primary-foreground su primary >= 5.2:1 in tutti i preset (light/dark).

## ATTRIBUTI: AZIONE RIGA "DUPLICA" — NON COMMITTATO (2026-09-23)

Richiesta utente: "tasto clona attributo in /attributes". Riusata l'azione generica `duplicate` (etichetta "Duplica",
come categorie/progetti/campagne) e l'infrastruttura `useModuleOpener.openDuplicate` + rotta `/attributes/:id/duplicate`.
- BE: `AttributeColumnCatalog::actions()` + `AttributesTableDefinition::actionsFor()` -> `duplicate` gated su `create`
  Attribute. Nessun endpoint nuovo: la copia passa da `POST /attributes`.
- FE: `AttributeFormMode` + `{ type: 'duplicate'; source }`; `useAttributeForm` precompila dal sorgente
  (`code` + `_copy` troncato a `CODE_MAX_LENGTH`, ora esportata da `attribute-schema.ts`; `name` + `common.copySuffix`;
  tipo/opzioni/config/custom fields); submit via create. Screen `AttributeLoadedFormScreen` (variant edit|duplicate).
  Meta create per duplicate. Tabella: `openDuplicate`.
- Test: dataset Pest in `AttributeTableTest.php`; Vitest `attribute-form.test.tsx` (3 casi duplicate) e
  `attributes-table.test.tsx`. Guida in-app IT/EN: sezione `duplicate-attribute`. Manuale Claude Docs: paragrafo
  "Duplicare un attributo" nella sezione Attributi.
- Verifica: Pest Attributes+Table 267/267, Vitest attributes 69/69 + help 103/103, ESLint e Pint puliti.
  `tsc -b --force` ROSSO per modifiche parallele non di questa sessione (`TableRowId = number | string` in
  `table/types.ts`, `use-bulk-actions-slot.tsx`): nessun errore nelle righe toccate qui.

## CATEGORIE PRODOTTO: AZIONE RIGA "DUPLICA" (+ COPIA LAYOUT ATTRIBUTI) — NON COMMITTATO (2026-09-23)

Decisioni utente: etichetta "Duplica" (chiave azione `duplicate`, come progetti/campagne); copia la categoria
+ il suo layout attributi; NON copia sottocategorie, prodotti, storico.
- BE: `ProductCategoryColumnCatalog::actions()` + `ProductCategoriesTableDefinition::actionsFor()` -> `duplicate`
  gated su `create` ProductCategory. `POST /product-categories` accetta `layout_source_id` (nullable, exists);
  il controller autorizza `view` sulla sorgente (403), `ProductCategoryService::create($data, ?$layoutSource)`
  chiama `AttributeLayoutCopier::copy()` nella stessa transazione dopo il sync attributi: copia le righe
  `attribute_layouts` PROPRIE della sorgente (ogni context/scope), potando gli item non piu' negli attributi
  effettivi della copia (righe/sezioni vuote eliminate, layout vuoto non scritto), poi `AttributeLayoutService::upsert`.
- FE: `ProductCategoryFormMode` + `{ type: 'duplicate'; source }`; `mapCategoryToFormValues` condiviso edit/duplicate
  (nome + `common.copySuffix`); submit via create con `layout_source_id`. Screen `ProductCategoryLoadedFormScreen`
  (variant edit|duplicate). Tabella: `openDuplicate`. `UpdateProductCategoryPayload` esclude `layout_source_id`.
- Test: `ProductCategoryDuplicateTest.php` (Pest), `product-category-form-duplicate.test.tsx`, caso in
  `product-categories-table.test.tsx`. Guida in-app IT/EN: sezione `duplicate-category` + riga azione.
- Manuale Claude Docs aggiornato (riga "Duplica" nella tabella azioni + sottosezione "Duplicare una categoria").
- Verifica: Pest ProductCategories+Products+Tables 495/495, Vitest product-categories/modules/help verdi,
  Pint/ESLint puliti, `tsc -b --force` pulito.

## AUTOFINANZIATO: "QUALIFICA PROFESSIONALE" (MULTISELECT) — NON COMMITTATO (2026-09-23)

- `ContactProcessingAttributeCatalogue`: nuovo attributo `professional_qualification` ("Qualifica Professionale"),
  `enum` + `config.display = multiselect`, stesse opzioni di `degree` ("Titolo di Studio", estratte in
  `DEGREE_OPTIONS`), assegnato solo ad "Autofinanziato" (Offerta + Commessa). Riga layout propria sotto `degree`.
- Convergenza installazioni gia' seedate: `PREVIOUS_OWN_ATTRIBUTES['Autofinanziato']` + nuova voce in
  `QualificaQuoteLayoutSeeder::PREVIOUS_SECTIONS` (sezioni odierne). `SeedsCategoryAttributes` ora persiste `config`.
- Test: `tests/Feature/Products/QualificaProfessionalQualificationTest.php`; Products + Seeding + SeederFlow verdi (325).
- Manuale: nessun impatto (guide in-app e manuale Claude Docs non elencano i campi "Dati Lavorazione Contatto").

## MODALE FILTRI CONDIVISA + "AZZERA FILTRI" IN GESTIONE RICHIESTE — NON COMMITTATO (2026-09-23)

- Nuovo `components/ui/filters-sheet.tsx`: `FiltersSheet` (Sheet + header band con icona) + `FiltersSheetFooter`
  (Azzera filtri / Annulla / Applica, `formId`, `labels`, `onReset`, `disabled`) + `FILTERS_SHEET_BODY_CLASS`.
  Usato da `TaskBoardFiltersSheet` (commessa) e `RequestReportFiltersDialog` (Gestione Richieste).
- Reset richieste: `form.reset(requestReportDefaultValues(tutte categorie/operatori/sedi), { keepDefaultValues: true })`
  sulla BOZZA; si applica solo con "Applica". i18n `requestManagement.report.buttons.reset` (IT/EN).
- Guida in-app `request-management` (IT/EN) aggiornata. Test: vitest request-management/work-orders/ui/help verdi, tsc -b pulito.

## COMMESSA: STATO CALCOLATO DAI TASK + BARRA COMPLETAMENTO — NON COMMITTATO (2026-09-23)

Spec 0149 (`docs/specs/0149-work-order-computed-status.xml`). Manuale: guida in-app Commesse resta "in fase di
sviluppo" (nessun impatto); manuale Claude Docs da aggiornare (Commesse: stati/barra; board: KPI senza annullati).
- Stati `WorkOrderStatus`: `open` / `in_progress` / `completed` / `closed` (colori blue/amber/green/slate).
  `is_force_closed` -> `closed`; altrimenti sui task RADICE della commessa, tutti (niente TaskVisibilityScope),
  annullati (`closed_negative`) esclusi: 0 completati (`closed_positive`) -> open; completati + almeno uno non
  chiuso -> in_progress; completati e nessuno non chiuso -> completed.
- `completion_percentage` = media arrotondata di `task_statuses.completion_percentage` sulle radici non annullate
  (0 se nessuna). Stessa esclusione in `task-board-metrics.ts` (KPI globale + per fase).
- Unica fonte: `WorkOrderStatusResolver` — `withProgress()` (3 aggregati per subquery, in `baseQuery()`),
  `progress()` -> `WorkOrderProgress{status, completionPercentage}` (fallback: 1 query se aggregati assenti),
  `applyFilter()` (set sui 4 valori), `applyCompletionSort()`. Il vecchio `resolve()`/`EAGER_LOADS` non esiste piu'.
- Contratto: dettaglio `completion_percentage` top-level; riga tabella `completion_percentage`; nuova colonna
  `completion_percentage` (number, sortable, NON filtrabile) subito dopo `status`.
- FE: `work-order-completion-bar.tsx` (`WorkOrderCompletionBar` + `WorkOrderCompletionCell`, tono `completionTone`),
  badge a 4 stati nell'header (label da `enums.work_order_status.*`; chiavi `detail.statusOpen/statusClosed` rimosse),
  `useInvalidateTaskBoard` invalida board + `workOrderDetailQueryKey` (usato da fasi, bulk, salvataggio task).
  Rimossa (richiesta utente) la stat "Righe prodotto" dalla strip KPI dell'header; la chiave `detail.lines` resta
  usata dalla sezione del dettaglio.
- Test: `WorkOrderComputedStatusTest.php` (AC-001..013), `WorkOrderTableTest` aggiornato (4 valori, 13 colonne).

## TASK: VISIBILITA' PER SEDE (`tasks.viewSite`) — NON COMMITTATO (2026-09-23)

Spec 0148 (`docs/specs/0148-task-site-visibility.xml`). Manuale: guide in-app IT/EN `tasks` (sezione overview) +
Claude Docs (Parte 3 › Task, "Chi vede un task") aggiornati.
- Tre livelli: `tasks.viewAll` (tutti) · `tasks.viewSite` (propri + task con almeno un assegnatario che condivide
  una sede, fisica o remota, con l'utente) · default (propri: creatore/richiedente/assegnatario/osservatore).
  Nessun ruolo riceve `viewSite` di default.
- Tutto in `TaskVisibilityScope` (query + record, in unione); `TasksTableDefinition` eager-load
  `assignees.employment.operationalSites` per la forma in memoria. `TaskNotable` menziona i `viewSite` della sede.
- Solo lettura: la modifica resta a `TaskAbilityResolver`/`manageAll`. Il PATCH di un non-membro risponde 422
  (field ceiling prima della policy), come gia' per `viewAll`.
- Test: `TaskSiteVisibilityTest.php`. Cambiati per requisito: conteggio `tasks.*` 15 -> 16 in
  `TaskPermissionsTest` e `TaskRequestUpdateTest`. Dopo il deploy: `php artisan permissions:sync`.

## BOARD COMMESSA: CARD/RIGA DEL TASK INTERAMENTE CLICCABILE — NON COMMITTATO (2026-09-23)

Stato: VERDE. Vitest `features/work-orders` 28 file / 205 test, ESLint pulito, `tsc -b --force` EXIT 0. Manuale: nessun impatto.
- Helper `task-board-card-click.ts` (`openTaskOnCardClick`): onClick sul contenitore di card kanban
  (`task-board-kanban-card.tsx`), riga lista (`task-board-task-row.tsx`) e sotto-task (`task-board-subtask-row.tsx`).
  Apre il task salvo click su un controllo (`button, a, input, [role=checkbox|button]`: drag handle, checkbox,
  espandi, persone) e ignora i click che arrivano via portal React (`currentTarget.contains`).
- Scartato lo stretched link CSS (`::after`): la Lista (vista di default) non ne beneficiava e copriva i tooltip.
- Test: `task-board-card-click.test.tsx` (lista + kanban: click ovunque, titolo una sola volta, controlli esclusi).

## FIX: MENZIONI NON CLICCABILI NEI DIALOG MODALI — NON COMMITTATO (2026-09-23)

Stato: VERDE. Vitest `features/notes` 39/39, ESLint pulito, `tsc -b --force` EXIT 0. Manuale: nessun impatto.
- Causa: il popup `@menzione` (`use-note-mention-extension.ts`) e' montato su `<body>`; un Dialog/Sheet
  Radix modale (es. `NotesDialog` di Gestione richieste) mette `pointer-events: none` sul body, il popup
  lo ereditava e i click lo attraversavano (e spostavano il focus fuori dall'editor).
- Fix: `pointerEvents: 'auto'` sullo stile del popup. Radix lo considera comunque interno al dialog
  perche' e' un portal React del sottoalbero dell'editor (nessuna chiusura del dialog al click).
- Test: `note-composer.test.tsx` › "mention popup inside a modal dialog" (click + Invio).

## TASK: FILTRI DELLA BOARD + STATISTICHE NELLA TABELLA — NON COMMITTATO (2026-09-22)

Spec 0147 (`docs/specs/0147-task-table-filters-and-stats.xml`, implemented). Stato: VERDE.
- Pest seriale: 8059 test, 8058 passati + 1 skipped. Vitest: 749 file / 5686 test.
- `tsc -b --force` EXIT 0, ESLint e Pint puliti. Manuale Claude Docs: nuova sezione "Filtri e statistiche" (Parte 3 › Task).

**Filtri** (filtri avanzati del dominio `tasks`, nessun endpoint nuovo)
- Catalogo `App\Tables\Tasks\TaskAdvancedFilterCatalog`; derivati in `TaskAdvancedFilterApplier`
  (`status`, `due`, `assignment`), il resto e' `relation` generico (whereHas per id).
- `status` e' `required` con `defaultValue: open`: il FE non invia un valore uguale al default e il
  motore applica il default. Quindi la tabella (ed export) parte sui soli task aperti; `status: all` = tutti.
- Scadenza su `DATE(COALESCE(end_date, start_date))` (`DUE_REFERENCE_SQL`), come la board.
- Enum pubblici nuovi in `config/config.php` `form_enums`: `task_list_status`, `task_due_window`,
  `task_assignment_scope` (solo etichette, non sensibili; il file chiede review Security per ogni aggiunta).

**Statistiche** (`GET /api/stats/tasks`, `TasksStatsDefinition`)
- 4 stat: `overdue`, `due_today`, `estimated_minutes`, `actual_minutes`; poi `by_status`, `by_priority`, `trend`.
- Solo task radice, sempre ristretti da `TaskVisibilityScope`, indipendenti dai filtri tabella.
- Nuovo `StatFormat::Duration` (`duration`, minuti) reso con `formatMinutesLabel`; icone nuove
  nell'allow-list: `alert-triangle`, `calendar-clock`, `timer`, `clock`.
- FE: `StatsToggleButton` + `ModuleStatsPanel` in `task-table.tsx`; mutazione -> refresh griglia + invalidate stats.

**Test cambiati per requisito (D-2):** `TaskCompletionPercentageTest` AC-023 ora invia `status: all`.
`StatsEndpointTest` include `tasks`.

**Da sapere:** `php artisan test --parallel` da' falsi rossi ("Call to undefined function" su helper
di test definiti in altri file) e con Xdebug attivo il worker va in segfault: usare la suite seriale
con `XDEBUG_MODE=off`.

## COMMESSE: TASK BOARD CON FASI — NON COMMITTATO (2026-09-22)

Spec 0146 (`docs/specs/0146-work-order-task-stages.xml`, implemented). Verifier: VERDE.
- Pest: suite completa 8036 test, l'unico rosso (`TaskAbilityResolverTest`) e' stato corretto e rieseguito.
- Vitest: 746 file / 5664 test.
- `tsc -b --force` EXIT 0; eslint pulito sui file toccati; Pint pulito.

**Naming.** La "fase" si chiama `stage` negli identificatori, perche' "phase" e' gia' il `group` di `TaskStatus`; in UI resta "Fase".

**DB**
- `task_template_stages`.
- `task_template_items.task_template_stage_id` (nullOnDelete).
- `work_order_stages` (`closed_at`, `closed_by_id` scritti solo dal service).
- `tasks.work_order_stage_id` (nullOnDelete) + `tasks.stage_position` (NON fillable).
- `QuoteWorkflowMigrationTest` porta il conteggio a 90 passi.

**Regole**
- Solo i task radice hanno fase e posizione; su un sottotask la fase da' 422.
- La fase deve appartenere alla commessa del task. Se cambia la commessa o il task diventa sottotask, la fase si azzera (`TaskStageGuard`).
- Una fase si chiude solo senza task aperti (radici + sottotask), altrimenti 409 con `data.open_tasks_count`. `BaseApiController::fail()` ha ora un 4° parametro opzionale `$data`.
- Su una fase chiusa: create/move/update danno 409.
- Commessa chiusa: board in sola lettura, mutazioni 409.
- Chiusura forzata della commessa (D-8, `WorkOrderTaskForceCloser`): i task non chiusi passano a ClosedNegative.
  - Nella stessa transazione, senza visibilita', senza notifiche ne' segnatempo.
  - `closure_feedback` = motivo, se il task non ne aveva gia' uno.
  - Riaprire la commessa non riapre i task.
- `WorkOrderResource.open_tasks_count`.

**API**
- Route in `routes/api/work-order-task-board.php`, richiesto da `routes/api/work-orders.php` perche' `api.php` e' a 499 righe.
- Endpoint:
  - `GET work-orders/{id}/task-board`;
  - `GET|POST stages`, `POST stages/reorder`, `PATCH|DELETE stages/{stage}`, `POST stages/{stage}/close|reopen` (con `scopeBindings`);
  - `POST task-board/move`, `POST task-board/bulk` (assign/complete/uncomplete/block/priority/dates; esito per singolo task, max 200 id).
- Template:
  - `stages[{id?,key,name}]` + `items.*.stage_key`, SEMPRE nella stessa richiesta (422 altrimenti);
  - `WorkOrderTaskGenerator` copia le fasi.
- `work_order_stage_id` e' nel catalogo `TasksAuthorization` e in `TaskAbilityResolver::PROTECTED_FIELDS`.

**Frontend**
- `features/work-orders/task-board/`:
  - vista Lista raggruppata + Board kanban (`@dnd-kit`);
  - KPI, filtri client-side (predefinito "Aperti"), barra azioni massive;
  - menu "..." nell'header con "Nuova fase" (dialog con il nome).
- Drag: `position` e' calcolata sui gruppi NON filtrati (`allGroups`), mai su quelli renderizzati.
- Campo "Fase" nel form task (solo con commessa e senza padre, solo fasi aperte), prefill da `ModuleCreateParams.work_order_stage_id`.
- Editor delle fasi nel Modello di Task: multi-container drag, piu' un select "Fase" per riga come alternativa da tastiera.
- Avviso inline `openTasksWarning` nella chiusura della commessa.

**Rifiniture UI (richieste dell'utente, 2026-09-22)**
- Filtri alla "Gestione richieste": barra `bg-surface` con ricerca, "Modifica filtri" e Lista/Board.
  - Chip dei filtri applicati su una riga orizzontale (`task-board-applied-filters.tsx`).
  - Pannello laterale con bozza applicata solo con "Applica" (`task-board-filters-sheet.tsx`, `task-board-segmented-field.tsx`).
  - Rimosso `task-board-toolbar-filters-popover.tsx`.
- Riga task con campi etichettati (`task-board-row-content.tsx`, `task-board-task-meta.tsx`).
  - Persone via `UserProfileHoverCard` + `UserAvatar`.
  - Completamento; ore lavorate/stimate con barra rossa oltre la stima.
- Fase:
  - pannello `bg-surface` (non piu' `bg-card` su card);
  - completamento = MEDIA di `task_status.completion_percentage` dei task radice, stessa regola per il KPI globale;
  - ore complessive (`TaskBoardStageSummary`);
  - bottone "Task" con sola icona +.
- Menu "..." nell'header del board con "Nuova fase" (dialog col nome). Le fasi senza task restano visibili.
- Gerarchia visiva ("troppo piatto"):
  - canvas `bg-surface` sotto l'header del board;
  - toolbar e KPI come card bianche rialzate;
  - fase = titolo sul canvas (pallino colore da `task-board-stage-accent.ts`, conteggio, riepilogo, menu in `task-board-stage-menu.tsx`) + UNA card bianca di task con striscia colore e righe `divide-y` (niente box per riga);
  - sottotask con linea guida;
  - kanban: colonne con intestazione sul canvas e corpo `bg-muted/40`.
  - Rimossi `task-board-due-badge.tsx` e `task-board-avatar-stack.tsx`.
- "Scadenza" rinominata "Data fine" (riga e filtro). `TaskBoardEndDate`: chip rosso + triangolo + tooltip "Scaduto" se passata, tooltip "Oggi" se odierna.
- Filtri Stato (per singolo stato) e Importanza, entrambi multi-select (`taskStatusIds`, `taskImportanceIds`); il segmentato Aperti/Completati/Bloccati/Tutti ora si chiama "Mostra". Contratto board esteso con `BoardTask.task_importance`. "Modifica filtri" solo icona, anche in Gestione richieste.
- Completamento colorato (task, fase, KPI commessa) con una sola regola in `task-board-completion-tone.ts`: 0-33 rosso, 34-66 ambra, 67-99 blu, 100 verde. Nuovo token `--warning` / `--color-warning` in `index.css` (light 38, dark 55), accanto a `--success`.
- Contratto board esteso con `BoardTask.description_excerpt` (`RichTextPlainText::excerpt`, 160 caratteri, null se vuota), mostrata sotto il titolo; spec 0146 aggiornata.

**D-10.** Rimosso lo scope `workOrderId` della griglia Task (spec 0133), sia BE (`WorkOrderScopedTableDefinition`) sia FE (`TableRowScope.workOrderId`); lo scope `quoteId` e' invariato. Eliminato `work-order-tasks-section.tsx`.

**Manuale**
- Guide in-app `task-templates` (nuova sezione `stages`) e `tasks` aggiornate IT+EN.
- `work-orders` resta marcata "in fase di sviluppo".
- Manuale Claude Docs NON aggiornato: il documento non e' accessibile da questa sessione. Vanno aggiornate le sezioni Modelli di Task (fasi), Task (campo Fase) e Commesse (task board, chiusura forzata dei task).

**Prossimi passi / da verificare**
- Test manuale in browser su `/work-orders/:id`: drag lista/kanban, menu fase, azioni massive, chiusura commessa.
- Soft-limit >300 righe: `use-task-form.ts` (326), `task-board-stage-group.tsx` (311), `WorkOrderService.php` (338).
- Questo file supera di molto i 50 KB: archiviare le voci vecchie in `docs/handoff-archive/`.

## OFFERTE: COMMISSIONI SUL MARGINE + MARGINE ATTESO NETTO — NON COMMITTATO (2026-09-22)

Spec 0145 (`docs/specs/0145-quote-commissions-on-margin.xml`, implemented), sopra la 0144. Verifier: VERDE (Pest
sequenziale 7977 pass / 1 skip / 1 flaky estraneo `DemoOpportunitySeederTest` che passa da solo; Vitest 728/5534;
eslint pulito; `tsc -b --force` EXIT 0).
- Base commissioni PERCENTAGE = max(0, net riga REVENUE - costi imputati via `offer_line_id`); costi generici esclusi;
  FIXED invariato. BE: unico punto `App\Services\Commissions\QuoteLineCommissionBaseResolver`
  (`resolve()`/`resolveForLines()`); `CommissionCalculationInput::lineNetAmount` rinominato `baseAmount`.
- Ricalcolo: `QuoteLineCommissionWriter::recalculateQuoteMargins()` chiamato da `QuoteService::create()/update()` dopo
  `writeSubmitted()` e prima degli aggregates. Tutti i canali (Gestione Richieste, inline-edit, conversione Lead)
  passano da QuoteService. Nessun backfill: offerte esistenti si aggiornano al prossimo salvataggio.
- `margin_net` PERSISTITO = revenue_net - cost_net - totale commissioni (griglia, Contratti, layout documento);
  `summary.margin.net` visibile anche senza permesso commissioni. API: shape invariata.
- FE: base in `commission-calculator.ts` (`calculateCommissionBaseNet`, `allocatedCostNetByOfferLineKey`,
  `sumCommissionTotals`), riusata da riepilogo live, dialog commissioni (`allocatedCostNet`) e margini per prodotto.
  Riepilogo: Ricavi, Costi, Commissioni, Margine atteso, Tipologia. "Margine per prodotto" con colonna Commissioni;
  NASCOSTO senza permesso `commissions` (decisione utente).
- Limite noto: nel FORM, un utente senza permesso commissioni non riceve le commissioni delle righe, quindi il Margine
  atteso live e' al lordo commissioni fino al salvataggio (poi il dettaglio mostra il netto dal server).
- Da ottimizzare (bassa): `QuoteLineWriter::sync()` calcola la base per riga (1 query/riga REVENUE) poi sovrascritta dal
  ricalcolo batch.
- Manuale: guida in-app `quotes` IT/EN + manuale Claude Docs aggiornati.
- Prossimo passo: commit (0144 + 0145) su ordine esplicito dell'utente.

## OFFERTE: COSTO IMPUTATO A RIGA PRODOTTO — NON COMMITTATO (2026-09-22)

Spec 0144 (`docs/specs/0144-quote-cost-line-allocation.xml`, status implemented). Verifier: VERDE (Pest sequenziale
7970 pass / 1 skip; Vitest 728 file / 5517 test; eslint pulito; `tsc -b --force` EXIT 0).
- DB: `quote_lines.offer_line_id` nullable, FK self su `quote_lines.id` `nullOnDelete` (riga prodotto eliminata da
  qualsiasi canale -> costo generico). Solo righe COST; REVENUE sempre NULL. Migrazione `2026_09_22_100000_*`
  (`QuoteWorkflowMigrationTest` step 85 -> 86).
- Contratto `cost_lines[i]`: `offer_line_id` (riga REVENUE persistita della stessa offerta) XOR `offer_line_index`
  (indice in `offer_lines` della STESSA richiesta, per righe nuove); nessuna = generico. 422 su
  `cost_lines.{i}.offer_line_id|offer_line_index`; le due chiavi sono `prohibited` su `offer_lines`/inline-edit
  (`QuoteLineRules::fieldRules()` deriva dal `$field`). Resource: `offer_line_id` su ogni riga.
- BE: `QuoteLineWriter::sync(..., ?array $revenueLines = null): array` restituisce le righe salvate per indice;
  risoluzione in `App\Services\Quotes\CostLineAllocationResolver`; `QuoteLineCoverageWriter` passa le REVENUE
  salvate (null se `offer_lines` non inviato). `DemoQuoteSeeder`: meta' delle offerte demo imputano il costo.
- FE: righe form con `client_key` (client-only, `line-<id>` in edit) e `offer_line_key` sui costi; mapping nel payload
  in `quote-line-values.ts` (indice calcolato DOPO lo scarto delle righe pristine). Select "Prodotto associato" =
  `SearchableSelect` stile select IVA (nuove prop opzionali `onClear`/`labels.clearLabel`). Blocco "Margine per
  prodotto" (`quote-product-margins-calc.ts` + `.tsx`), calcolato client-side, form live + dettaglio; totali header
  invariati. Tipi riga spostati in `quote-line-types.ts` (split di `types.ts`, riesportati).
- Manuale: guida in-app `quotes` IT/EN (sezione `offer-and-cost-lines`) + manuale Claude Docs aggiornati.
- Fuori scope (non fare senza richiesta): Gestione Richieste, layout PDF/DOCX, griglia/statistiche, provvigioni.
- Da verificare a mano: UI tab Costi a 375/768/1024px. Nota verifier (bassa): l'indice esplicito su `offer_line_id`
  e' ridondante su MySQL (la FK lo crea) — lasciato per coerenza con `product_id` sulla stessa tabella.
- Prossimo passo: commit su ordine esplicito dell'utente.

## FIX NOTIFICHE RICHIESTE DI MODIFICA IN ITALIANO — NON COMMITTATO (2026-09-22)

Bug: la notifica "New change request" (e quella di esito) arrivava in inglese e mostrava la chiave i18n frontend
`requestManagement.columns.source` al posto dell'etichetta del campo. Causa: stringhe assenti da `lang/it.json` e
`config/field-change-requests.php` `label` e' una chiave del FRONTEND, passata grezza come `:field`.
- Nuovo `lang/{en,it}/field-change-requests.php` (`fields.<field>` => etichetta). Nuovo campo protetto → aggiungere qui la voce.
- `FieldChangeRequest::fieldLabel()` traduce nella locale CORRENTE (quella del destinatario, `HasLocalePreference`),
  fallback al nome campo. Rimosso il parametro `$fieldLabel` dai costruttori di `FieldChangeRequestedNotification` e
  `FieldChangeRequestResolvedNotification` (e dai caller Creator/ResolutionNotifier).
- Esito: `__('approved')`/`__('rejected')` generici sostituiti da due frasi complete (`:handler approved|rejected your request
  on :field of :subject`). Traduzioni IT aggiunte in `lang/it.json`.
- Test: 2 nuovi in `tests/Feature/FieldChangeRequests/FieldChangeRequestNotificationTest.php`; suite backend completa verde
  (7953 pass, sequenziale; `--parallel` da' falsi rossi per helper di test condivisi tra file).

## GUIDA IN-APP PER MODULO + REGOLA MANUALE — NON COMMITTATO (2026-09-22)

Spec 0143 (`docs/specs/0143-in-app-module-help.xml`), solo frontend, nessuna modifica backend/dipendenze. Verifier: VERDE
(tsc -b --force EXIT 0, vitest 722 file / 5442 test pass, eslint pulito, vite build con chunk separati per guida).
- Header: `HelpButton` (lucide CircleHelp, `SheetTrigger asChild` per il ritorno focus su Esc) prima di `NotificationBell`
  in `layouts/app-layout.tsx`. Pannello `components/help-panel.tsx` (Sheet, `storageKey="sheet-width:help"`).
- Contratto congelato `features/help/types.ts` (`HelpGuide`/`HelpSection`/`HelpBlock`: paragraph, steps, list, table, tip,
  warning, note; inline solo `**grassetto**`, renderizzato come nodi React, mai innerHTML).
- Contenuti statici `features/help/content/{it,en}/<key>.ts` (50 chiavi: `general` + 49 chiavi navigation con route), elenco in
  `help-guide-keys.ts` (`HELP_GUIDE_KEYS`, `GENERAL_HELP_KEY`). Loader lazy `help-content-loader.ts` (`import.meta.glob` non eager).
- Visibilita' = voci di `useNavigation()` (gia' filtrate per permesso lato server); guida corrente = match pathname su route
  (esatto o prefisso `route + '/'`), altrimenti `general`. Ricerca client-side accent-insensitive solo sulle guide visibili.
- Test: `content/help-content-parity.test.ts` (IT/EN stessi id sezione, `work-orders` = sola nota "in fase di sviluppo"),
  `i18n/locales/help-i18n-parity.test.ts`, test componenti in `features/help/components/*.test.tsx`, `layouts/app-layout.test.tsx`.
- `CLAUDE.md` §4-bis (decisione utente): ogni modifica aggiorna il manuale (guide in-app IT+EN + manuale Claude Docs
  https://claude.ai/artifact/KwvSrXafsGqT9qzZULxJhh) o dichiara "manuale: nessun impatto"; aggiunto a DoD §5 e checklist.
- Fonte dei testi: manuale Claude Docs (export md in scratchpad di sessione, non versionato). PDF manuale consegnato all'utente
  fuori repo (Desktop). Commesse = "in fase di sviluppo" sia nel manuale sia nella guida.
- Rev 2 (AC-014..018, verifier VERDE: vitest 725 file / 5457 test x2, tsc/eslint puliti): schede Radix "Questa pagina" /
  "Tutte le guide" in `help-panel.tsx`; indice `help-guide-index.tsx` con icone da `resolveIcon` (menu) e `help-icons.ts`
  (`resolveHelpGuideIcon`, BookOpen per `general`), gruppi `Collapsible` con contatore, aperto il gruppo corrente, badge
  "Sei qui" + `aria-current="page"`; `help-guide-view.tsx` con icona e indice sezioni. `HelpVisibleGuide` ha ora `icon`/`groupIcon`.
  Chiave i18n `allGuides` rimossa (sostituita da `tabCurrentPage`/`tabAllGuides`/`youAreHere`/`sectionsIndexLabel`).
  `features/navigation/icon-map.ts`: aggiunte `shapes` e `layout-template` (prima fallback Circle anche nella sidebar).
- Da verificare a mano: resa a 375px e leggibilita' contenuti nel pannello reale. Prossimo passo: commit su richiesta utente.
- Nota: questo file supera i ~50 KB indicati; l'archiviazione delle voci vecchie in `docs/handoff-archive/` e' da fare.

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
- Griglia prodotti (D-9): colonna `usages` in `App\Tables\Products\ProductUsageColumn` (tags + enumKey `product_usage`, filtro set
  `whereJsonContains` OR, sort `CAST(products.usages AS CHAR)` con direzione allow-listed, values = casi presenti). `Product::booted()`
  salva `usages` in ordine canonico. FE `column-filters.ts`: valueFormatter del Set Filter anche per `tags` (etichette enum).
  `ProductTableTest` aggiornato (lista colonne).
- Fuori scope (D-8): filtro nel picker "prodotti di interesse".
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

