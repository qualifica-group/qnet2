# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

## PROVINCE COME DESTINAZIONE DEI CAMPI RELAZIONE (spec 0225, 2026-10-12) — VERDE, COMMITTATO

- Problema utente: in /migrations "Attributi" falliva la riga legacy 101 `province_id` "Provincia" (relation verso
  `provinces`, non custom-fieldable). Decisione utente: collegarlo a una select sulle province.
- `CustomFieldEntityRegistry::RELATION_ONLY_TARGETS` (`provinces` => Province): destinazioni valide solo per una
  relazione, mai host di campi personalizzati. `isRelationTarget()` (usato da `ValidatesFieldTypeDefinition` e
  `AttributesSource`), `relationTargets()`; `modelClassFor()` risolve anche queste (tutti i chiamanti sono relazioni).
  `entities()` / `isCustomFieldable()` invariati: un campo personalizzato SULLE province resta 422.
- Nuovi endpoint: `GET /provinces/for-select` (`routes/api/geo.php`, `ProvinceForSelectController`, solo
  auth:sanctum, NationalScope, `ids[]` fuori filtro) e `GET /custom-fields/relation-targets` (custom-fields.viewAny).
- Frontend: `useCustomFieldRelationTargets()` (in `use-custom-field-entities.ts`) alimenta
  `DefinitionRelationTargetEditor` nei form attributo e campo personalizzato; i18n `customFields.entities.provinces`.
  I mock di `use-custom-field-entities` nei 5 test dei form espongono anche il nuovo hook.
- DB locale: import "attributes" rilanciato (run 31: 1 creato = attributo 460 province_id, 0 falliti). Nessuna
  categoria/layout legacy usa `province_id` (verificato sull'API).
- Test: Pest Geo 47, CustomFields 138, Attributes 54, Migration 375, RequestManagement 851, QuoteWorkflows 71+43,
  Unit/CustomFields 112; Vitest attributes+custom-fields 176, help 115; ESLint, Pint, `tsc -b` puliti.
- Manuale: guide in-app attributes/custom-fields (it/en) e manuale Claude Docs (due frasi sulla Relazione).

## PUNTO 2 DOPO L'ONDA 1 (2026-10-12) — VERDE, COMMITTATO

- HANDOFF archiviato: le voci dalla "SEEDER — CAMPI OBBLIGATORI" indietro sono in `docs/handoff-archive/2026-10.md`
  (il file era 577 KB).
- Test che fallivano gia' prima (requisito cambiato, non bug): `QualificaProductionDataSeederTest` 418 -> 395 prodotti
  (refactor e-Campus a801a10d aveva aggiornato solo `TOTAL_SEEDED_PRODUCTS`); `SectorTest` fillable con `code` e
  `is_active` (spec 0212/0213). Suite backend completa: 9933/9937, restano solo i 3 `QuoteDocumentPdfTest` (soffice
  assente sulla macchina).
- PDF del manuale NON rigenerato: lo store di Claude Docs rifiuta l'export lato file (non writer del documento).
  Va esportato dall'interfaccia del documento (Esporta -> PDF).

## MIGRAZIONI: REGOLE COMMISSIONE SENZA DESTINATARIO = REGOLE DI RUOLO (2026-10-12) — VERDE, COMMITTATO (QNet 2; legacy no)

- Dopo spec 0223 l'import commissioni falliva su 30 righe "no recipient"/"no role". Analisi sul DB legacy locale:
  21 regole commerciali (4, 34-40, 74-78, 147-153, 436) hanno un `archive_id` di contatti CANCELLATI (l'API li
  emetteva come null): non sono regole "per tutti". Davvero senza destinatario solo 12, 619, 623, 624, 626-629.
- Decisioni utente: la pulizia sta nel LEGACY, non nella logica d'import (niente euristiche in QNet 2).
  - Legacy `/Users/Repository/qnet`, `CommissionConfigurationMigrationController::applyMigratableScope()` (in SQL,
    total/paginazione coerenti): ruolo noto + valore; destinatario esistente oppure nessuno (`archive_id` null/0);
    delle regole senza destinatario solo la piu' recente per ruolo + categoria/servizio. API locale: 615 -> 591
    righe, restano 12, 619, 626, 627, 628, 629. **Modifica NON committata nel repo legacy** (che ha gia' altro in
    stage di altri); va portata anche sul legacy di produzione.
  - QNet 2 `CommissionConfigurationsSource`: `recipient_id` vuoto -> regola di RUOLO (recipient null), nome senza
    destinatario; destinatario presente ma senza tipo -> riga fallita.
- Test: `CommissionConfigurationsSourceImportTest` (+2 casi, dataset "no recipient" -> "recipient without kind",
  requisito cambiato). Migration 377, CommissionConfigurations 40 verdi.
- Manuale: testo "cosa serve" di Configuratore commissioni (it/en); manuale Claude Docs: nessun impatto (non
  dettaglia l'import commissioni).
- Prodotti senza categoria: invariato ("No categoria" di QNet 2), decisione utente.

## CONFIGURATORE COMMISSIONI: REGOLA DI CATEGORIA VALE PER IL RAMO (spec 0223, 2026-10-12) — VERDE, COMMITTATO

- Motivo: import `commission-configurations` 501 falliti su 615, di cui 471 su categorie madre non selezionabili
  (ISO, SOA-, SICUREZZA N/N, GDPR, GARE-, AVVALIMENTI-, ENTE DI CERTIFICAZIONE - SOA). Decisioni utente: "Regola
  vale per il ramo" (D-1), "vince la categoria piu' vicina" (D-2). Spec `docs/specs/0223-...xml` approvata.
- `CommissionRuleResolver`: gradino categoria = `product_category_id IN` (categoria del prodotto + antenati, catena
  strutturale via `CategoryHierarchy::ancestors`, calcolata UNA volta per `resolve()`); candidati ordinati
  priority/valid_from/id, vince il primo della categoria piu' vicina (`closestCategoryRule`). Catena 0089 invariata
  (personali 1-3 prima dei gradini di ruolo, prodotto prima di categoria).
- Validazione regole: `ActiveProductCategory` al posto di `SelectableProductCategory` (contenitori ammessi, inattive
  no). `ProductCategorySelectableTest` AC-012 (solo il caso commissioni) aggiornato: requisito cambiato.
- Nuovo `GET /api/product-category-nodes/for-select` (`ProductCategoryNodeForSelectController`,
  `ProductCategoryForSelectResolver::resolveNodes`, request/resource dei rami riusate, `resolvePlain` condiviso con
  `resolveBranches`): tutte le categorie attive, contenitori compresi; `product-categories/for-select` invariato.
- Frontend: form Configuratore usa `PRODUCT_CATEGORY_NODES_FOR_SELECT_RESOURCE` + hint `categoryBranchHint`.
- Import: tolto il rifiuto "not selectable" da `CommissionConfigurationsSource`.
- Verifiche: CommissionConfigurations 40, ProductCategories 324, Migration 374 verdi; Vitest commission+categories
  280, help+migrations 145 verdi; Pint/ESLint/`tsc -b` puliti. Quotes: 1 fallito `QuoteDocumentPdfTest` "real PDF"
  per `DocumentConversionException` (conversione PDF dell'ambiente, non legato alle commissioni).
- Manuale: guide in-app commission-configurations e product-categories (it/en), testo migrazione commissioni,
  manuale Claude Docs (elenco "come si sceglie la regola" + riga Selezionabile). PDF da rigenerare.
- Da fare: rilanciare l'import "Configuratore commissioni" (attese ~585 create, 30 fallite).

## MIGRAZIONI: PRODOTTI SENZA CATEGORIA IN "No categoria" (2026-10-12) — VERDE, COMMITTATO

- Problema utente: 24 prodotti legacy falliti con `category_id is required` (sconti, prove, voci di servizio:
  legacy id 425, 499, 505, 516, 517, 524, 527, 637, 666, 681, 726, 1354, 1413-1416, 1425-1429, 1508, 1509, 1533).
- Decisione utente: categoria "No categoria" creata dal seeder di produzione e prodotti messi li' dall'import.
- `ProductsSource::UNCATEGORIZED_CATEGORY = 'No categoria'`: radice (container, non selezionabile) nel `CATALOG` di
  `QualificaCatalogSeeder` (step 2 del production seeder, prima dell'import: non viene annidata sotto
  Consulenza). `resolveCategory()`: `category_id` vuoto -> quella categoria + warning; assente -> riga fallita
  ("run QualificaCatalogSeeder first"); `category_id` non migrato -> riga fallita come prima.
- Test: 2 nuovi in `ProductsSourceImportTest`. Verdi: Migration 374, Products 201, ProductCategories 320.
  **Preesistente, NON causato da questa modifica** (fallisce anche togliendo la riga dal catalogo):
  `QualificaProductionDataSeederTest` (2 test) si aspetta 418 prodotti e ne trova 395.
- Manuale: testo "cosa serve" di Prodotti (it/en, corretto anche il fornitore: ora lo imposta product-suppliers),
  nota nelle guide in-app, paragrafo nel manuale Claude Docs.

## MIGRAZIONI: UTENTI GIA' PRESENTI AGGANCIATI PER EMAIL O NOME (2026-10-12) — VERDE, COMMITTATO

- Problema utente: gli utenti reali sono creati da `QualificaOperatorSeeder` senza `old_id`, quindi le sorgenti
  successive non li collegano e l'import "Utenti" falliva sull'email gia' usata.
- Decisioni utente: (1) l'utente non riconosciuto si crea come prima; (2) quello riconosciuto riceve SOLO l'`old_id`
  + i riferimenti di impiego vuoti (societa', sede, responsabile via `reconcileEmployment`): ruolo, password,
  email, nome, contatti e campi custom restano di QNet (outcome `created` senza model); (3) omonimi = riga FALLITA
  con le email trovate, mai indovinare.
- `App\Migrations\Support\ExistingUserMatcher::match()`: email case-insensitive (se l'utente ha gia' un altro
  `old_id` -> RuntimeException), poi nome+cognome sulla scheda `personalData` (case-insensitive) tra i soli utenti
  con `old_id` NULL: 1 = adotta, >1 = RuntimeException, 0 = crea. Iniettato in `UsersSource`, metodo `adopt()`.
- Test: nuovo `UsersSourceAdoptionTest` (6 casi). `RunMigrationJobTest` AC-012: l'errore di riga ora e' provocato da
  un'email di un utente gia' collegato a un altro legacy id (prima: email gia' usata, che ora viene adottata) —
  requisito cambiato, dichiarato. `tests/Feature/Migration` 372 verdi.
- Manuale: testo "cosa serve" di Utenti (it/en), nota nelle guide in-app (overview), paragrafo nel manuale Claude Docs.

## MIGRAZIONI: STATO PER SORGENTE + "COSA SERVE PER MIGRARE BENE" (2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: per ogni sorgente una descrizione di cosa serve per migrarla bene, e vedere quali sono gia'
  migrate per capire cosa manca.
- **Sorgenti operative RIMESSE** (chiarimento utente: "rimettili ma non metterli nel seeder legacy import
  intendevo"): `referents`, `registries`, `opportunities`, `quotes`, `work-orders`, `work-order-line-payments`,
  `product-suppliers`, `commission-configurations` di nuovo in `config/migrations.php` e `MigrationOrder` (30
  sorgenti, 12 fasi), con test, guide e manuale come prima del commit `bf7b773c` (annullato nel working tree,
  nessun revert committato). `QualificaLegacyImportSeeder::SOURCES` non le ha mai incluse: nuovo test che lo
  blocca. Testi "cosa serve" anche per queste 8.
- Contratto (additivo): `GET /api/migrations` -> ogni sorgente ha `phase` (1-based, `MigrationOrder::phaseOf`),
  `last_run` (`MigrationRunResource` dell'ultima run di QUALSIASI attore, o null) e `migrated_at` (fine dell'ultima
  run completed, o null; resta anche se una run successiva fallisce). `GET /api/migrations/{source}/columns` ->
  `dependencies: [{source, required}]`.
- `MigrationSource::dependencies()` (default `[]` in `AbstractMigrationSource`, value object `MigrationDependency`
  required/optional) su tutte le 30 sorgenti. required = senza, la riga fallisce o viene saltata
  (o il linker non fa nulla); optional = collegamento vuoto + warning. Mappa verificata sul codice da un agente
  (file:line); `MigrationDependenciesTest` impone: dipendenza registrata e in fase precedente. Corretti 2 commenti
  di `MigrationOrder` (business-functions -> users non risolvibile in fase 1; product-categories -> business-functions).
- Stato lettura: `MigrationService::latestRunsBySource()` / `migratedAtBySource()`.
- Frontend: `MigrationStatusOverview` (card "Stato migrazione", sorgenti per fase, contatore "N di M migrate",
  clic = seleziona), `MigrationRequirementsCard` (testo per sorgente + "Da importare prima" con badge
  Obbligatoria/Consigliata e stato, alert rosso per obbligatorie mancanti), `MigrationSourceStatus` (badge),
  `resolveMigrationSourceState` (notMigrated/running/failed/partial/migrated). Testi per sorgente in
  `i18n/locales/{it,en}-migration-requirements.ts` (parity test). `useMigrationSources` ripolla ogni 3s finche'
  una sorgente e' running; invalidato all'avvio import e alla chiusura delle finestre.
- Verifiche: backend `tests/Feature/Migration` 366 + `tests/Unit/Migrations` 30 verdi, Pint pulito; frontend
  migrations+help 145 verdi, ESLint pulito, `tsc -b --force` pulito.
- Manuale: guide in-app it/en sezione `status-and-requirements` (+ ripristinata `operational-records`); manuale
  Claude Docs: due paragrafi sullo stato, ripristinati riepilogo e i due collegamenti, tolto "non vengono
  importati". Il PDF va rigenerato.
- Segnalati, NON corretti: `business-function-members` sovrascrive il responsabile gia' impostato; `sectors` non
  adotta per nome (duplicati con QualificaSectorSeeder); `work-order-line-payments` riscrive sempre i dati di
  pagamento delle righe.

## MIGRAZIONI LEGACY: SGANCIATI I MODULI OPERATIVI (2026-10-12) — COMMITTATO, POI ANNULLATO (vedi voce sopra)

- Decisione utente: anagrafiche, opportunita', offerte, commesse e referenti "troppo grandi per essere importati";
  restano tutte le tabelle di appoggio e i collegamenti. Tolte dal registro anche le sorgenti che dipendono da
  quelle (scelta utente): `work-order-line-payments`, `product-suppliers`, `commission-configurations`.
- **Solo dall'elenco** (scelta utente, NON cancellare il codice): rimosse da `config/migrations.php` (commento
  "Detached on 2026-10-12") e da `MigrationOrder::PHASES`, ora 6 fasi / 22 sorgenti. Le classi `*Source`, i concern
  `MapsLegacy*` e i support (`LegacyQuoteStatusApplier`, `QuoteLineDuplicator`, `PersonNameSplitter`) restano;
  per riattivarle: una riga in config + la fase in `MigrationOrder`. Il piano "Importa tutto" scarta da solo le
  chiavi non registrate (`MigrationPlanService::reconcile`).
- Test: gli 8 `*SourceImportTest` registrano la propria sorgente in `beforeEach` (`config(['migrations.definitions.<key>'
  => ...])`) cosi' il codice tenuto resta verificato; i due test "registrata e ordinata" (WorkOrderLinePayments AC-014,
  CommissionConfigurations AC-007) ora controllano solo le colonne; nuovo guard in `MigrationRegistryTest` che le 8
  chiavi non siano ne' registrate ne' in ordine. `tests/Feature/Migration` 362 verdi, `tests/Unit/Migrations` 28 verdi.
- Manuale: guide in-app it/en `migrations.ts` (sezione `operational-records` sostituita da `not-imported`), manuale
  Claude Docs aggiornato (riepilogo Migrazioni, nuovo paragrafo, tolti i due collegamenti). Il PDF va rigenerato.

## UTENTI MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0222, 2026-10-12) — VERDE, COMMITTATO (8ce533d9)

- Richiesta utente: "committa e procedi" (commit 1aa6a1ad = onda 1 fino a Campagne; poi Utenti, ultimo dell'onda 1).
  Solo frontend: `updateUser` restituisce `UserDetailWithPermissions`; nuovo `userDetailQueryKey`.
- Niente pagina di modifica: `generateEditRoute: false`; `UserDetailView({userId, onChanged})` (fa ancora la sua fetch:
  lo usa anche il pannello condiviso del dettaglio utente, che ora e' modificabile secondo i permessi). Eliminati
  `use-user-form-meta`, `user-form-account-tabs`, `user-form-employment-tabs`, `user-form-contract-data-tab`,
  `user-form-assignment-section`, `user-form-summary`; `useUserFormHydration` tolto da `user-form-defaults`
  (ora `editDefaults` + `useUserFormDefaults`); `UserFormMode` spostato in `types.ts`.
- Struttura: `use-user-form` (solo form), `use-user-form-submit` (gate della scheda in creazione, avatar differito,
  422 indicizzati competenze/riporti + `personalDataServerErrorMessage`), `use-user-inline-edit` (buffer scheda come
  Referenti), `user-record.ts` (`UserRecordValues` con `assignment` e `password_state`, cascate
  `MANAGER_CASCADE_FIELDS`/`COVERS_ALL_CASCADE_FIELDS`, `toFieldRefs`), `user-account-fields`,
  `user-employment-fields`, `user-record-sections` (Credenziali e accesso, Profilo, Rapporto contrattuale, Dati
  contrattuali), `user-assignment-record-section`, `user-avatar-field` (upload/rimozione immediati, poi invalidate: la
  risposta degli endpoint avatar non ha l'albero del profilo), `use-user-draft-values`, `user-create-sections`,
  `user-form-header` (RecordCardHeader + `UserDetailStats`), `user-form-body`, `user-form` (solo creazione).
  `useUserSectionVisibility` in `user-assignment.ts`. `PersonalDataIdentityRecordSection` ha `leading?` (foto profilo).
- PATCH sparso (D-3): `employment` intero solo se un suo campo cambia (prima sempre), `personal_data` solo se la scheda
  cambia (prima sempre), password solo se digitata. Sezioni del rapporto di lavoro sempre presenti (prima assenti senza
  profilo): il primo salvataggio crea il profilo.
- Cascate: Responsabile svuota Riporta a, Tutte le categorie azzera le Competenze; le due righe si aprono solo se anche il
  campo svuotato e' modificabile. Il verdetto di assegnazione sta nella striscia KPI (creazione: live) e nel callout.
- i18n: `users.form.sections.*` solo titoli (credentials = "Credenziali e accesso", tolta `access`), tolti
  `editTitle/editSubtitle`, `assignment.description/rule/summary`; nuovi `users.detail.password.*`,
  `users.form.leaveConfirm`; `detail.subtitle` cambiato.
- Test: nuovi `user-test-fixtures.tsx`/`user-test-stubs.tsx`, `user-detail-inline-edit.test.tsx` (6); riscritti
  form/employment/sites/competence/competence-flag/metadata/custom-fields/assignment-surfaces (REQUIREMENT CHANGED
  dove serve: D-8 competenze "insieme vuoto accettato", employment sparso nel payload test, flag "tutte le categorie"
  nel dettaglio). Utenti + quick-create 137 verdi.
- Verifica: `tsc -b --force` 0 errori; ESLint pulito; Vitest completo 974 file: 7355 verdi + 6 falliti per timeout
  (load average 138 della macchina, durate 67-450 s, moduli non toccati: lead, opportunita', anagrafiche, task, import),
  ripassati da soli 6/6 (41 test). Browser: `/users/1` scheda anagrafica modificata e ripristinata (PATCH solo
  `personal_data`, 200), riga Password aperta, 375 px senza scroll orizzontale; `/users/new` righe chiuse, invito via
  email, verdetto "Non assegnabile"; `/users/1/edit` non trovata; nessun errore in console. Token eliminato.
- Manuale: guide in-app IT/EN `users` (creazione, assegnazione, modifica sul posto, password); manuale Claude Docs
  (passi di creazione, tabella, assegnazione, modifica/disattivazione, reimpostazione password). PDF da rigenerare.
- Onda 1 COMPLETATA: il piano si chiude qui (vedi PIANO sotto).

## CAMPAGNE MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0221, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "procedi" (onda 1, dopo Progetti). Solo frontend: `updateCampaign` ora restituisce
  `CampaignDetailWithPermissions` (la PATCH restituiva gia' i permessi).
- Niente pagina/finestra di modifica: `generateEditRoute: false`, `formOwnsHeader: true`; eliminati
  `use-campaign-form-meta`, `campaign-geo-section`, `campaign-planning-section`, `campaign-form-product-lines.test`.
- Struttura come Progetti: `use-campaign-form` (solo form, `editDefaults`, stato "Nuovo" e paese predefinito solo su
  creazione nuda, intatta e non collegata), `use-campaign-form-submit` (422 `product_lines` -> `serverError`, BR-3 sul
  budget), `use-campaign-inline-edit` (il toast "regola fuori vista" tiene conto dei campi mostrati dall'editor:
  `EDITOR_EXTRA_FIELDS`), `campaign-record.ts` (`CampaignRecordValues`, `CAMPAIGN_GEO_FIELDS`,
  `PROJECT_CASCADE_FIELDS`, `projectLabel`), `campaign-fields.tsx`, `campaign-project-editor.tsx`,
  `campaign-record-sections.tsx` (Identita', Collegamento al progetto, Classificazione, Geografia, Pianificazione),
  creazione `use-campaign-draft-values` + `campaign-create-sections` + `campaign-form-header` + `campaign-form-body`
  + `campaign-duplicate-loader`. `CampaignForm({source?, onSuccess, onCancel})`; quick-create aggiornato.
  `CampaignRelationField` ridotto (via `forceDisabled`/`required`/`params`/`onValueChange`, senza chiamanti);
  `CampaignProjectField.selected` ora e' un `ForSelectItem` (`{id,label}`).
- Riga Progetto = cascata (D-3): la scelta precompila Partner, Sede, Data inizio, Stato, Righe, livelli geo (bloccati) e
  il salvataggio della riga li porta con se'; si apre solo se tutti i campi della cascata sono modificabili.
  Scollegare (D-4): nello stesso riquadro compaiono Stato, Righe prodotto (da UNA riga vuota, prima `[]`) e Geografia.
  Collegata: Stato e Righe senza matita; Geografia senza matita se il progetto riempie tutti i livelli.
- Condiviso: `useCascadeEditable` spostato in `components/record-form/use-cascade-editable.ts` (era duplicato in
  `opportunity-record.ts` e `lead-record-sections.tsx`); lo usano Opportunita', Lead, Progetti (Area geografica) e
  Campagne.
- i18n: tolti `editTitle/editSubtitle`, descrizioni di sezione, `descriptionLinked`, `hints.project/geography/
  moreInfoLabel`; nuovi `unlinkedHint`, `leaveConfirm`; `detail.subtitle` cambiato.
- Test: nuovi `campaign-test-fixtures.tsx`/`campaign-test-stubs.tsx`, `campaign-detail-inline-edit.test.tsx` (5);
  riscritti `campaign-form-body.test` (11) e `campaign-project-link.test` (10) (REQUIREMENT CHANGED); aggiornati
  detail e table. Campagne 109 verdi.
- Verifica: Vitest completo 976 file / 7367 verdi; `tsc -b --force` 0 errori fuori da `features/migrations`; ESLint
  pulito. Browser (il DB e' stato riseminato con dati): `/campaigns/new` righe chiuse, stato Nuovo e paese Italia gia'
  impostati, 375 px senza scroll orizzontale, `/campaigns/1/edit` non trovata, nessun errore in console; salvataggio
  sul posto della Descrizione su `/campaigns/1` E su `/projects/1` (verifica del dettaglio Progetti rimasta in
  sospeso): PATCH del solo campo, 200; editor Geografia con paese bloccato dal progetto. Descrizioni ripristinate via
  API, token eliminato.
- Manuale: guide in-app IT/EN `campaigns` (creazione, Duplica, nuova sezione `edit-a-campaign`, collegamento);
  manuale Claude Docs (passi di creazione, collegamento, suggerimento, Duplica, modifica sul posto). PDF da rigenerare.

## PROGETTI MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0220, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "non committare, continua con la tua onda". Solo frontend: `updateProject` ora tipizza la risposta
  come `ProjectDetailWithPermissions` (la PATCH restituiva gia' i permessi).
- Niente pagina/finestra di modifica: `generateEditRoute: false`, `formOwnsHeader: true`; eliminati
  `project-edit-loader`, `project-geography-section`, `project-planning-section`, `use-project-form-meta`. La matita
  della scheda in griglia apre `ProjectDetailScreen` in uno Sheet (invalida schede e statistiche al cambio).
- Struttura (stesso schema di Lead/Prodotti): `use-project-form` (solo form, `editDefaults`, stato e PAESE
  predefiniti solo su creazione nuda e solo se il campo e' intatto), `use-project-form-submit` (422 su
  `product_lines` -> `serverError`), `use-project-inline-edit`, `project-record.ts` (`ProjectRecordValues`,
  `PROJECT_GEO_FIELDS`), `project-fields.tsx`, `project-record-sections.tsx` (Identita', Classificazione, Ambito
  geografico, Pianificazione), `project-detail-sections`, `project-detail-header` (`ProjectStatsStrip`),
  creazione `use-project-draft-values` + `project-create-sections` + `project-form-header` + `project-form-body`
  (`source?` = duplica). `ProjectForm({source?, onSuccess, onCancel})` senza `mode`; quick-create aggiornato.
- Ambito geografico = UNA riga `field="country_id"` `layout="block"`, modificabile solo se lo sono tutti e 4 i livelli;
  il salvataggio invia i soli livelli cambiati. Codice: modificabile solo in creazione.
- Paese predefinito: prima lo applicava `GeoSelect` al montaggio; con la riga chiusa l'editor non e' montato, quindi un
  progetto creato senza aprire la riga falliva con "Paese obbligatorio". Ora lo applica `useProjectForm`
  (`useDefaultCountryId`). Da ricordare per Campagne (stessa cascata geo).
- Test: nuovi `project-test-fixtures.tsx`/`project-test-stubs.tsx`, `project-detail-inline-edit.test.tsx` (5),
  riscritti form-body/product-lines/duplicate (REQUIREMENT CHANGED: righe chiuse, casi di modifica sul dettaglio),
  aggiornati detail/table/view/card-grid. Progetti 135 verdi.
- Verifica: Vitest completo 976 file / 7362 verdi (prima del fix paese; dopo il fix Progetti + help verdi);
  `tsc -b --force` 0 errori fuori da `features/migrations` (lavoro dell'altra sessione); ESLint pulito. Browser:
  `/projects/new` righe chiuse, Fatto su Denominazione, riga geo aperta, paese Italia gia' nella riga chiusa, 375 px
  senza scroll orizzontale, `/projects/1/edit` = non trovata, nessun errore in console. NON verificato nel browser il
  salvataggio sul dettaglio: il DB locale e' stato riseminato pulito durante la prova (0 progetti, 0 categorie) e non
  ho seminato dati demo per non sporcare il DB delle migrazioni. Verificato poi con la voce Campagne (PATCH 200).
- Manuale: guide in-app IT/EN `projects` (creazione, Duplica, nuova sezione `edit-a-project`); manuale Claude Docs
  (passi di creazione, nota Duplica, modifica sul posto, riga Ambito geografico). PDF da rigenerare.

## CONTRATTI — "MODIFICA DATI" DIVENTA MODIFICA SUL POSTO (spec 0219, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "non committare, continua con la tua onda". Il contratto non si crea a mano (nasce dall'offerta,
  spec 0072 D-6) e non aveva pagina di modifica ma la finestra "Modifica dati": rimossa (`contract-edit-dialog.tsx`
  eliminato, pulsante tolto da `ContractActionsBar`). Solo frontend, PATCH `/contracts/{id}` invariata.
- Dettaglio: Commenti = `NotesCalloutRow` (scritti dentro il callout, placeholder `detail.commentsPlaceholder`);
  Rinnovo e Scadenza = righe `RecordInlineField` in "Ciclo di vita"; Note di pagamento = riga in "Documento e
  pagamento". Campi in `contract-fields.tsx`, stato in `use-contract-inline-edit.ts` (usa `useUpdateContract`,
  restituisce il contratto con i permessi aggiornati all'host). Matita solo con `contracts.update`, permesso di campo
  e stato in lavorazione (`contractLifecycleActions().edit`, la regola che mostrava "Modifica dati").
- `buildEditPayload` -> `buildUpdatePayload(values, original)`: diff sparso (prima inviava sempre tutti e 4 i campi).
  Le date arrivano dall'API come timestamp ISO completi (`2026-09-20T00:00:00.000000Z`): il form le normalizza a
  `YYYY-MM-DD`, altrimenti il campo data restava vuoto (difetto gia' presente nella vecchia finestra).
- Condiviso: `NotesCalloutRow` ha `canEdit` facoltativo (default invariato).
- i18n: di `contracts.actions.edit.*` restano solo `success`/`genericError`.
- Test: `contract-detail.test` e `contract-actions-refresh.test` (REQUIREMENT CHANGED: niente "Edit data", matite al
  suo posto), nuovo `contract-detail-inline-edit.test.tsx` (5). Contratti 137 verdi.
- Verifica: Vitest completo 973 file / 7347 verdi (prima delle ultime modifiche dell'altra sessione); `tsc -b --force`
  0 errori nei miei file — ora ci sono errori in `features/migrations/*` dovuti al lavoro in corso dell'altra
  sessione (`types.ts` modificato, `migration-source-state*` nuovi), non toccati da me. ESLint pulito. Browser reale:
  `/contracts/92` (aperto) Rinnovo modificato e poi ripristinato a `2026-09-20` via API, editor Commenti aperto, nessun
  pulsante "Modifica dati", 375 px senza scroll orizzontale; unico messaggio in console il banner licenza AG Grid
  (preesistente). Il server rifiuta giustamente un Rinnovo successivo alla Scadenza: l'errore compare nell'editor.
- Manuale: guide in-app IT/EN `contracts`; manuale Claude Docs (tabella azioni, elenco, suggerimento). PDF da rigenerare.

## PRODOTTI MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0218, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "procedi con prossimo modulo" (onda 1). Solo frontend: la PATCH `/products/{id}` restituiva gia'
  `permissions`, `applicable_attributes`, `attribute_layout` (ora `updateProduct` li espone).
- Dettaglio (`ProductDetailView`: niente `onEdit`, nuovo `onChanged`): righe in `product-record-sections.tsx`
  (Anagrafica: Codice — mai modificabile dopo la creazione, soglia del permesso — Nome, Descrizione; Classificazione:
  Categoria, Funzione aziendale sola lettura, Tipologia, Unita', Tipo, Utilizzi; Prezzi e fornitura: Costo/Prezzo
  secondo gli utilizzi, IVA, Fornitore, sempre presenti), attributi di categoria `product-attributes-section.tsx`
  (un attributo = una riga, anche vuoti, layout `attribute_layout`, matita solo con `update`: autorizzati a livello
  di risorsa), `CustomFieldRecordSections`. KPI `ProductStatsStrip` (condiviso con la creazione) in
  `product-detail-header.tsx`. Campi in `product-fields.tsx` / `product-pricing-fields.tsx`.
- D-4: cambiare Categoria mostra nello stesso editor gli attributi nuovi ("Attributi della nuova categoria") e li
  invia nello stesso PATCH; attivare un Utilizzo chiede l'importo nell'editor (`ProductUsagesEditor`). Annullare
  riallinea categoria e attributi (`syncCategory` in `useProductForm`). Il dettaglio usa subito
  `applicable_attributes` del prodotto finche' la lookup degli attributi non risponde (prima una riga aperta subito
  non veniva inviata).
- Hook: `use-product-form.ts` (solo form, `values` + `keepDirtyValues`, `editDefaults`, `createLayout`),
  `use-product-form-submit.ts`, `use-product-inline-edit.ts`, `use-product-category-picker.ts` (opzioni albero,
  ex logica del form body), `use-product-draft-values.ts`.
- Creazione: `product-form-body.tsx` (RecordCanvas, righe chiuse, `syncCategory` su Ripristina),
  `product-form-header.tsx` (nome live, badge codice/categoria, KPI live), `product-create-sections.tsx` (attributi
  come righe dopo la scelta della categoria, layout di creazione), `ProductForm` solo creazione con codice suggerito,
  `GuardedProductForm` (conferma uscita). Rotta `products/:id/edit` rimossa, `generateEditRoute: false`.
- Rimossi: `product-identity-section`, `-classification-section`, `-pricing-section`, `-form-summary`,
  `-dynamic-fields`, `-attribute-values-section` (+ test), `use-product-form-meta`; i18n `form.editTitle/editSubtitle`,
  `form.summary`, descrizioni delle sezioni, `form.dynamicFields.empty`; nuove `form.leaveConfirm.*`,
  `detail.newAttributes`.
- Test: fixture `product-test-fixtures.tsx` + stub `product-test-stubs.tsx`; riscritti con REQUIREMENT CHANGED
  `product-form-{code,usages,pricing,decimals,relations,custom-fields,category-picker}`, `product-dynamic-fields`,
  `product-detail`; nuovo `product-detail-inline-edit.test.tsx`. Prodotti 94 verdi.
- Verifica: Vitest completo 972 file / 7343 verdi; `tsc -b --force` 0; ESLint pulito. Nota: con la macchina sotto
  carico (load ~9, altra sessione attiva) un giro della cartella prodotti ha avuto timeout da 5 s, rientrati al giro
  successivo. Browser reale: `/products/1` Descrizione modificata e poi ripristinata a `null` via API, editor
  Categoria aperto, `/products/new` con codice suggerito, `/products/1/edit` -> pagina non trovata, 1280 e 375 px
  senza scroll orizzontale, nessun errore console; token temporaneo eliminato.
- Manuale: guide in-app IT/EN `products` (creazione, dettaglio modificabile, attributi); manuale Claude Docs aggiornato
  (passi di creazione + paragrafo "Modificare un prodotto"). PDF derivato da rigenerare.

## LEAD MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0217, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "procedi direttamente al lead senza committare" (onda 1 del piano sotto). Solo frontend: la PATCH
  `/leads/{id}` restituiva gia' `permissions` (ora `updateLead` li espone).
- Dettaglio (`LeadDetailView`: niente `onEdit`, nuovo `onChanged`; `ResourcePermissionsProvider` attorno): righe
  `RecordInlineField` in `lead-record-sections.tsx` (Note callout `NotesCalloutRow`; Anagrafica e campagna;
  Dettagli: Sede, Operatore, Fonte; Prodotti di interesse e Dati importati come righe block a tutta larghezza,
  presenti anche vuote). Campi in `lead-fields.tsx`; stato in `use-lead-inline-edit.ts`, submit in
  `use-lead-form-submit.ts`, form in `use-lead-form.ts` (`values` + `keepDirtyValues`, `editDefaults`).
- Cascate (`use-lead-cascades.ts`, ex logica di `lead-form-body.tsx`, comportamento invariato): Campagna -> Sede/Fonte
  + conferma prodotti (`useLeadCampaignProductInterest`, nuovo `syncBaseline`), Sede azzera Operatore, Operatore
  compila Sede vuota. `syncBaseline` all'apertura di ogni riga (il "valore precedente" e' quello con cui la riga si
  apre). D-6c: Campagna non modificabile se Sede/Fonte/Prodotti non lo sono; Sede se Operatore non lo e';
  Operatore (con Sede vuota) se Sede non lo e'. Prodotti filtrati sulle categorie della Campagna lette dal
  for-select campagne (prima solo dalle categorie dei prodotti gia' scelti: un lead senza prodotti aveva il picker
  disabilitato in modifica).
- Creazione: `lead-form-body.tsx` (RecordCanvas, `useDraftInlineEdit` + `syncBaseline`), `lead-form-header.tsx`
  (Anagrafica come titolo, Campagna sottotitolo, `LeadStatsStrip` condiviso), `lead-create-sections.tsx` (riga
  "Converti automaticamente in Opportunita'" in testa solo con `opportunities.create`), `use-lead-draft-values.ts`.
  `LeadForm` solo creazione; leave guard in `LeadFormScreen`; registry `generateEditRoute: false`,
  `formOwnsHeader: true`. `ExtraFieldsEditor` senza FormSection (e' l'editor della riga). Rimossi
  `use-lead-form-meta.ts`, `lead-product-interest-section.tsx`, `LeadFormSkeleton`, i18n `form.editTitle/editSubtitle`,
  `form.sections.{notes,extraFields}`, le `description` delle sezioni; nuove `form.leaveConfirm.*`.
- Condiviso: `RecordInlineField` disabilita matita/clic sul valore mentre `inline.isSaving` (bug trovato nel browser:
  una matita cliccata durante un salvataggio resettava il form sotto il PATCH e il successo richiudeva l'editor;
  vale per tutti i moduli gia' migrati). Test di regressione in `lead-detail-inline-edit.test.tsx`.
- Test: fixture `lead-test-fixtures.tsx` + stub picker `lead-test-stubs.tsx` (`stubState.campaigns`); REQUIREMENT
  CHANGED dichiarati in `lead-form-body*`, `lead-detail`, `leads-table` (niente sheet di modifica; annullare la
  creazione chiede conferma), `lead-conversion-action` (fixture completata con `notes`/`extra_fields`); nuovo
  `lead-detail-inline-edit.test.tsx` (8). Lead 176 verdi.
- Verifica: Vitest completo 972 file / 7346 verdi; `tsc -b --force` 0; ESLint pulito sui file toccati. Browser reale:
  `/leads/1` Note modificata sul posto e poi ripristinata a `null` via API, editor Campagna aperto, `/leads/new` con
  editor Dati importati, `/leads/1/edit` -> pagina non trovata, 1280 e 375 px senza scroll orizzontale, nessun
  errore console; token temporaneo eliminato (resta la voce nel log attivita' e `updated_at` del lead 1).
- Manuale: guide in-app IT/EN `leads` (creazione a righe chiuse, nuova sezione `editing-a-lead`); manuale Claude Docs
  aggiornato (passi di creazione + paragrafo "Modificare un lead"). PDF derivato da rigenerare.

## PIANO "NESSUNA PAGINA DI MODIFICA" + REFERENTI MODIFICABILI SUL POSTO (spec 0216, 2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: "così come fatto con task, opportunità, offerte, voglio che si eliminino le pagine di edit anche
  per altri moduli e rendere tutto modificabile inline, anche la creazione". Scelte utente: partire dall'onda 1, un
  modulo alla volta (niente team/subagent), le tabelle di impostazione dopo con un KIT CONDIVISO.
- PIANO (fatti prima: Task 0195, Commesse 0196, Offerte 0197, Opportunità 0198, Anagrafiche 0200):
  onda 1 = Referenti (FATTO, 0216) -> Lead (FATTO, 0217) -> Prodotti (FATTO, 0218) -> Contratti (FATTO, 0219) -> Progetti (FATTO, 0220) -> Campagne (FATTO, 0221) -> Utenti (FATTO, 0222);
  PIANO CHIUSO ALL'ONDA 1 (utente 2026-10-12): onda 2 e onda 3 annullate, i moduli restanti tengono la pagina di
  modifica. Il tentativo su Fatture (spec 0224, 7b5edbb7) e' stato annullato da 8fec3019.
- Referenti (solo frontend, PATCH già con `permissions`): dettaglio a righe `RecordInlineField` (Dati anagrafici = una
  riga, Dettagli referente: Tipo, Ambito, Utente collegato, Note; campi personalizzati con `CustomFieldRecordSections`;
  Contatti/Indirizzi in persistenza immediata). Creazione = replica a righe chiuse (Fatto/Ripristina, telefono
  obbligatorio, avviso duplicati e Contatti/Indirizzi nella colonna laterale, conferma uscita
  `GuardedReferentForm`). Rotta `referents/:id/edit` rimossa, `generateEditRoute: false`, `ReferentForm` solo
  creazione `{onSuccess, onCancel}` (anche quick-create). File nuovi: `use-referent-form-submit`,
  `use-referent-inline-edit`, `referent-record(.ts|-details.tsx)`, `referent-fields`, `referent-create-sections`,
  `use-referent-draft-values`, `guarded-referent-form`, `referent-test-fixtures`. Rimossi: `referent-form-summary`,
  `referent-form-details-tab`, `use-referent-form-meta`, segnaposto "Settori attività", i18n
  `form.editTitle/editSubtitle/activitySectors*`, `detail.summary`, `form.sections.*.description`; nuove
  `form.leaveConfirm.*`. `ReferentDetailStats` -> `ReferentStatsStrip` (condiviso con la creazione).
- Condiviso Anagrafiche/Referenti spostato in `features/personal-data` (nessun cambio di comportamento):
  `PersonalDataIdentityRecordSection` + `PERSONAL_DATA_CARD_FIELD` + `PersonalDataCardBuffer` (ex
  `registry-record-identity.tsx`, `REGISTRY_CARD_FIELD`), `usePersonalDataDraftInlineEdit` (ex
  `use-registry-draft-inline-edit.ts`), `usePersonalDataFieldPermission` (ex in `use-registry-form.ts`),
  `personalDataServerErrorMessage` (copia rimasta in `company-sites/use-company-site-form.ts`: da unificare in onda 3).
- REQUIREMENT CHANGED nei test referenti (righe chiuse, niente modo edit, riga utente collegato governata dal permesso
  `user_id`); nuovo `referent-detail-inline-edit.test.tsx` (10).
- Verifica: Vitest completo 971 file / 7342 verdi; `tsc -b --force` 0; ESLint pulito sui file toccati. Browser reale
  (Playwright, Chromium in cache): `/referents/new` -> creazione -> `/referents/1` -> Note modificata sul posto, 1280 e
  375 px senza scroll orizzontale, nessun errore console; referente di prova e token Sanctum temporaneo eliminati.
- Manuale: guide in-app IT/EN `referents` (nuova sezione `editing-a-referent`, creazione riscritta); manuale Claude
  Docs aggiornato ("La scheda referente": creazione a righe chiuse + paragrafo "Modificare un referente"). Il PDF
  derivato va rigenerato.
- Nota: questo file è ~540 KB, ben oltre il limite di 50 KB: serve archiviare le voci vecchie in `docs/handoff-archive/`.

## MIGRAZIONI — NOMI SORGENTI IN ITALIANO, SORGENTI DI COLLEGAMENTO (2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: in /migrations tradurre in italiano le sorgenti ancora in inglese; ogni sorgente che fa da
  collegamento deve dirlo nel nome. Aggiunte in `it-/en-migrations.ts` le chiavi `sources.*` mancanti (task-templates,
  payment-methods, cost-products, email-templates, document-bundles, commission-configurations) e le sorgenti di
  collegamento con il suffisso "— collega …" / "— link …": `product-suppliers` (Prodotti — collega fornitori),
  `work-order-line-payments` (Commesse — collega dati di pagamento alle righe), `business-function-members`
  rinominata da "riconcilia" a "collega responsabile e operatori"; `product-category-attributes` lo era gia'.
  Testi IT: "source" -> "sorgente". Il `label()` backend resta inglese (solo fallback). Le etichette delle colonne
  del template/anteprima e i messaggi del report restano inglesi (vengono dal backend).
- Guida in-app migrations IT/EN aggiornata (elenco sorgenti + nota sulle sorgenti di collegamento); manuale Claude
  Docs aggiornato (nomi italiani + paragrafo collegamenti); PDF derivato da rigenerare.
- Verifica: Vitest migrations + help 135/135, ESLint pulito, `tsc -b --force` pulito.

## COMMESSA — SCADENZA CONTRATTO NEI KPI, CONTRATTO CON CODICE (2026-10-12) — VERDE, COMMITTATO

- Richiesta utente: nel dettaglio commessa la Scadenza contratto prende il posto di "Contratto n." nella striscia
  KPI (`WorkOrderStatsStrip`, prop `contractExpiryDate` da `workOrder.contract?.expiry_date`; in creazione `null`) e
  esce dalla sezione Contratto. Il campo Contratto mostra `codice — titolo` (stesso formato del link commessa nel task).
- Rimossa la chiave i18n `workOrders.detail.contractNumber` (non più usata). `contract_number` resta nel payload e
  nella griglia. Solo frontend, nessun cambio di contratto API.
- Guida in-app work-orders IT/EN aggiornata; manuale Claude Docs: nessun impatto (Commesse ancora "in fase di sviluppo").
- Verifica: Vitest work-orders + help 355/355, `tsc -b --force` pulito, ESLint pulito.

