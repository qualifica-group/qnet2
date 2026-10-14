# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

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

## MERGE main -> feature/amministrazione — VERDE, NON COMMITTATO (2026-10-13)

- Merge di main (fino a eb207f4f, spec 0240-0242) su feature/amministrazione (spec 0208-0212). 12 file in conflitto,
  tutti per aggiunte parallele: risolti tenendo entrambi i lati.
- Conteggi da tenere allineati: rollback `QuoteWorkflowMigrationTest` = 168 step, `SupplierCommissionDirectionTest`
  = 32 step (15 migrazioni del ramo + 9 di main); `HELP_GUIDE_KEYS` = 69 chiavi.
- `registry-form-body.tsx`: `isSupplierPreset` (ramo) + `usePersonalDataDraftInlineEdit` (rinomina di main);
  `async-paginated-select.tsx`: `min-w-60 max-w-[calc(100vw-2rem)]` (ramo) dentro `cn(..., contentClassName)` (main);
  `proforma-request-detail.tsx`: struttura `RecordBody header=` di main + campo "Cliente" del ramo;
  `icon-map.ts`: tolto l'import `BookOpen` duplicato dal merge automatico.
- Rotture semantiche (merge automatico pulito, typecheck rosso): `agent-fiscal-profile-detail.tsx` e
  `cost-type-detail.tsx` portati sulla nuova API di main `RecordBody header= collaborationTabs=` (niente piu' `side`);
  quick-create prodotti senza `mode` (`ProductForm` di main e' solo creazione); `PurchaseOrderMigrationTest` = 17 step.
- Verifica: `tsc -b --force` 0 errori, Vitest 7844/7844, Pest 10403 verdi; rossi solo i 3 `QuoteDocumentPdfTest` che
  richiedono LibreOffice (assente su questa macchina, non legati al merge).
- HANDOFF: tenuta la versione archiviata di main; le voci del ramo 0208-0212 restano qui sotto, le quattro voci
  del ramo del 2026-10-08 spostate in `docs/handoff-archive/2026-10.md`.

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

## "NO CATEGORIA" HA LA FUNZIONE AZIENDALE "NO FUNZIONE AZIENDALE" (2026-10-12) — VERDE, NON COMMITTATO

- Causa: run quotes #37 = 348 offerte fallite, tutte per `OpportunityProductLineCoverage::ensure()` ("has no business
  function"): righe ricavo su prodotti in "No categoria" (24 prodotti, soprattutto sconti), radice senza funzione.
- Decisione utente: "No categoria" ha la funzione aziendale "No funzione aziendale". `QualificaBusinessFunctionLinkSeeder`:
  nuova funzione propria (`OWN_FUNCTIONS`, costante `UNCATEGORIZED_FUNCTION`) + link
  `ProductsSource::UNCATEGORIZED_CATEGORY` => essa. Docblock `QualificaCatalogSeeder` allineato.
- Test (requisito cambiato): `QualificaBusinessFunctionLinkSeederTest` elenco funzioni/categorie collegate + nuovo caso
  "No categoria" (9/9). Suite Pest completa 9991/9995 (solo i 3 `QuoteDocumentPdfTest`, LibreOffice assente).
  Vitest help+migrations 145. `tsc -b` rosso SOLO su `task-priorities` (refactor in corso di un'altra sessione).
- DB locale: seeder eseguito (funzione #30). Dry-run delle 348 offerte (transazione annullata): 348/348 ok. Da fare:
  rilanciare l'import "quotes" da /migrations.
- Manuale: guida `migrations` IT/EN + paragrafo "No categoria" del manuale Claude Docs.

## CAMPI FLESSIBILI OFFERTE LEGACY = INFO PRELIMINARI DELL'OPPORTUNITA' (spec 0189 G-17, 2026-10-12) — VERDE, COMMITTATO

- Decisione utente: i valori `pre_info_*` (sull'opportunita' legacy) vanno sull'offerta, nella STESSA migrazione
  `quotes` (niente sorgente nuova). Ogni offerta della stessa opportunita' riceve gli stessi valori.
- Legacy (`/Users/Repository/qnet`, non committato): nuovo `App\Support\Migration\LegacyQuoteAttributeValues`
  (una query per tabella per pagina, prima riga per opportunita', colonne rinominate in `COLUMNS`, sedi ISO da
  `company_addresses` + `provincies`, ruoli progetto con "cognome nome"); `QuoteMigrationController` espone
  `attribute_values` per le sole card (`services.spec_service`) delle righe dell'offerta. Verificato su tutto il DB
  legacy: 17.281 offerte, 1.295 con valori. Solo `php -l` (il legacy non ha test ne' Pint).
- qnet-2: nuovo `App\Migrations\Support\LegacyQuoteAttributeValueApplier` (codici applicabili via
  `QuoteAttributeResolver`, validazione codice per codice con `AttributeValueValidator`, utenti rimappati via
  `old_id`, scrittura con `QuoteAttributeValueWriter`; persistita dal `finalizeLegacyRecord`). `QuotesSource`:
  dipendenza opzionale `product-category-attributes`. Helper delle offerte legacy spostati da
  `QuotesSourceImportTest` a `tests/Helpers/QuoteMigrationHelpers.php` (nessuna asserzione cambiata); nuovo
  `QuotesSourceAttributeValuesImportTest` (4 test). Migration 392/392, Unit/Migrations 30/30, Quotes verdi tranne
  `QuoteDocumentPdfTest` (LibreOffice assente in locale, non correlato). Pint ok.
- Spec 0189: G-17, campo `attribute_values` nella shape `migration/quotes`, AC-011c. Guida `migrations` IT/EN e
  manuale Claude Docs aggiornati (PDF derivato da rigenerare).
- Da verificare: e2e reale legacy -> qnet-2 (warning attesi su codici ISO dei gruppi non previsti dalla norma della
  categoria). HANDOFF oltre 50 KB: archiviare le voci vecchie in `docs/handoff-archive/`.

## ONDA 4: ATTRIBUTI, CONFIGURATORE COMMISSIONI, CONFIGURATORE STATI OFFERTA SENZA PAGINA DI MODIFICA (spec 0240-0242, 2026-10-13) — VERDE, COMMITTATO (3 commit, uno per spec)

- Richiesta utente: "ok procediamo allora con questi" (i moduli rimasti con la pagina di modifica, esclusi sempre
  Gestione richieste/iscritti, Attiva = Richieste Proforma, Gestione conti). Contratti era gia' sul posto (0219):
  restano solo commenti obsoleti su `ContractEditDialog` in `contract-screens.tsx`/`contracts-table.tsx` (segnalati).
- 3 agenti `frontend` a ownership disgiunta, kit 0233 + contratto 0233 D-2, backend invariato (PATCH parziali che
  rispondono gia' con `permissions`). Attributi (0240): gemello di custom-fields 0231, Codice/Tipo solo creazione,
  editor `Definition*` riusati; `TypeSection`/`RelationTargetEditor` ora ESPORTATI da
  `custom-field-definition-type-sections.tsx` (`RelationTargetEditor` generico `<T extends FieldDefinitionFormValues>`,
  comportamento invariato); `:id/duplicate` resta. Commissioni (0241): righe raggruppate Destinatario (ruolo+tipo+
  destinatario; cambio ruolo non ammesso azzera tipo/destinatario), Ambito (scope+categoria/prodotto, l'altra
  relazione azzerata nel PATCH), Validita' (dal+al); `buildUpdatePayload` diff; editor dei gruppi dentro
  `MetaFieldRowContext.Provider value={null}` (sotto-etichette visibili). Stati Offerta (0242): Nome/Attivo righe del kit,
  Criteri/Stati righe `block`, `statuses` ora valore RHF validato, PATCH solo dell'array cambiato con `id` esistenti;
  foglio "Stati predefiniti" invariato.
- Verifica lead: `tsc -b --force` 0; Vitest completo 982 file / 7556 test, 7555 verdi + 1 flaky NON toccato
  (`stat-chart.test.tsx`, chunk lazy sotto carico: 3/3 verde da solo, stessa famiglia di `stat-donut-chart`);
  ESLint pulito sui file toccati. Browser
  (Playwright): nessun "Modifica", `/edit` non trovata, 375 px senza overflow, duplica attributo ok, creazione e
  modifica reale di una configurazione commissioni (PATCH `{"priority":7}`), poi eliminata; token eliminato.
- Manuale: guide in-app IT/EN dei 3 moduli (agenti); Claude Docs: paragrafi "Creare e modificare un attributo",
  passi di creazione + "Modificare una configurazione" (Commissioni), "Creare e modificare un workflow". PDF da rigenerare.
- Segnalati, fuori scope: a 375 px l'editor Stati (selettore colore + badge) e la riga criterio si sovrappongono
  (layout preesistente di `WorkflowStatusesEditor`/`ROW_GRID_CLASS`); `RecordCardHeader` tronca titolo/sottotitolo
  della creazione a 375 px quando ci sono Annulla/Salva (vale per tutti i moduli).

## ONDA 3: TABELLE DI IMPOSTAZIONE SENZA PAGINA DI MODIFICA, KIT CONDIVISO + 25 MODULI (spec 0233-0239, 2026-10-12) — VERDE, COMMITTATO (7 commit, uno per spec)

- Richiesta utente: "ok procedi" (riapertura onda 3 dopo il riepilogo). Esclusi sempre Gestione richieste/iscritti,
  Attiva, Gestione conti. Backend invariato (tutte le PATCH restituivano gia' `permissions`).
- KIT (lead, spec 0233) in `components/record-form/`: `use-record-inline-edit.ts` (`useInlineEditingField` +
  `useRecordInlineEdit`), `inline-rows.tsx` (`TextInlineRow` anche multiline, `NumberInlineRow`, `ColorInlineRow`,
  `IconInlineRow`, `SwitchInlineRow`: valore chiuso letto dal form, stessa riga per dettaglio e bozza),
  `record-create-layout.tsx` (`RecordCreateLayout`); `components/icon-picker-control.tsx` unico (via le 6 copie).
  Pilota: Priorita' Task.
- 6 agenti `frontend` a ownership disgiunta: 0234 cataloghi task (stati, tipologie, categorie, importanza; i18n
  condiviso `*-task-lookups.ts`), 0235 stati (progetto, contratto, pagamento commessa, premio, tipi premio), 0236
  commerciale (tipologie prodotto, unita' di misura, IVA, modalita' di pagamento), 0237 anagrafiche (tipi
  referente, fonti, tag, settori, funzioni aziendali), 0238 organizzazione (societa', sedi aziendali, sedi
  operative), 0239 documenti (layout, modelli email, pacchetti documenti). Contratto per modulo: `update*` con
  `permissions` + `*DetailQueryKey`, form edit con `values`/`keepDirtyValues`/`clearServerError`, dettaglio con
  righe del kit, creazione `RecordCreateLayout` + `useDraftInlineEdit`, wrapper solo creazione (`useResourceMeta`,
  via i `use-*-form-meta.ts`), registry `generateEditRoute:false`/`formOwnsHeader:true`, `leaveConfirm`.
- Scelte notevoli: righe di sistema/bloccate senza matita sui campi non modificabili; Codice solo creazione;
  modalita' di pagamento con righe raggruppate (rate+giorni, fine mese+extra); tipologie prodotto commissione
  fornitore+verso in una riga; layout documenti `config` ora valore del form, Contenuto = riga `block` (anteprima
  A4 chiusa, editor aperto), modelli email Corpo = riga `block`; sedi operative cascata geo = una riga
  (`city_id`); sedi aziendali hook diviso, banche riga a bozza, contatti/indirizzo/logo/predefinita immediati,
  `personalDataServerErrorMessage` unificato; IVA ora mostra i campi personalizzati anche nel dettaglio.
- Lead: voci quick-create aggiornate (via `mode` per i form solo creazione; Categorie prodotto conserva `mode`
  con `parentId`); test `module-routes` (payment-methods senza `:id/edit`, requisito cambiato). FIX PRE-ESISTENTE
  (lead, segnalato dagli agenti): `build-custom-fields-schema.ts` ogni campo `.optional()` -> un campo
  personalizzato di testo mai toccato non blocca piu' la creazione (gli obbligatori restano rifiutati), test nuovi.
  L'agente task-lookups ha tolto 2 chiavi da `task-lookups-i18n-parity.test.ts` (chiavi rimosse).
- Verifica lead: `tsc -b --force` 0; Vitest completo 980 file / 7515 verdi (dopo l'allineamento di
  `module-routes`); ESLint: 1 errore in `features/quotes/column-renderers.tsx` = lavoro dell'altra sessione, non
  di questa voce. Browser (Playwright, solo lettura) su 25 moduli: nessun pulsante Modifica, matita apre/Annulla
  chiude, `/edit` non trovata, 375 px ok, creazione a righe chiuse (layout/email: editor aperti per scelta; sedi
  aziendali: aggiunte rapide contatti/indirizzo aperte come Anagrafiche); pilota e Priorita' creati/modificati/
  eliminati davvero; token eliminati.
- Manuale: guide in-app IT/EN di tutti i moduli (agenti); Claude Docs: paragrafo generale delle tabelle di
  configurazione riscritto (modifica sul posto, creazione a righe chiuse), corrette le frasi su pulsante Modifica
  della scheda, spostamento categorie e modifica prodotti. PDF da rigenerare.
- Aperti: riga select del "gruppo" duplicata in 3 moduli stati (candidata `SelectInlineRow` nel kit);
  `use-module-opener.tsx` ha ancora il ramo edit (`form.editTitle`) inutilizzato dai moduli migrati;
  `business-function-detail.test.tsx` 332 righe (>300 soft).

## ONDA 2 COMPLETATA IN PARALLELO: CATEGORIE PRODOTTO, RUOLI, CAMPI PERSONALIZZATI (spec 0229/0230/0231, 2026-10-12) — VERDE, COMMITTATO (a1c326f1, f7141cc9, 2667a0a5)

- Richiesta utente: "committa, pero' usa gli agenti e velocizza le modifiche in parallelo". Tre agenti `frontend`
  a ownership disgiunta (cartella feature + locale + guida + spec propri); HANDOFF, manuale e commit al lead.
  Spec 0228 saltata: usata dall'altra sessione (data offerta). Backend invariato per tutti e tre (PATCH parziali
  che restituiscono gia' `permissions`; gli `update*` frontend ora le tipizzano e popolano la cache del dettaglio).
- Categorie prodotto (0229): Dettagli (Nome, Padre, Descrizione, Funzione aziendale; ereditata = senza matita),
  Regole di gestione una riga per regola (le 4 regole decise dalla radice, su una sottocategoria, "Ereditata da"
  senza matita; Colonne report solo se visibile nei report), Attributi e Gestori Account righe `block`, campi
  personalizzati in place. Creazione/duplicazione = replica (tolti striscia KPI e Riepilogo laterale; duplicate con
  `layout_source_id`). Conferma azzeramento funzione aziendale delle discendenti ora al salvataggio della riga.
- Ruoli (0230): Nome e Membri righe, Permessi riga `block` (esploratore + matrice campi), campi personalizzati in
  place; super-admin / senza `update` nessuna matita. `RoleForm` solo creazione, usato anche dal quick-create
  (`quick-create-entries/advanced-entries.tsx`: tolta la prop `mode`). i18n ruoli in `locales/{it,en}.ts`.
  Aperti: `description` del ruolo esposto dal backend ma mai gestito dal frontend (fuori scope); caso teorico
  `permissions` non editabile ma `field_permissions` si' -> ora nessuna matita (nessun caso reale noto).
- Campi personalizzati (0231): Modulo/Tipo/Chiave solo creazione (senza matita); Etichetta, Gruppo, Ordine, Attivo,
  Indicizzato righe; Presentazione riga raggruppata; Impostazioni/Opzioni/Relazione/Colonne/Validazione righe
  `block` (editor condivisi con gli attributi NON modificati). Validazione per riga (un valore storico invalido
  altrove non blocca). Aperti: la guida cita "Scheda" ma nessun controllo la espone (pre-esistente); nei blocchi
  complessi aperti il titolo compare due volte (titolo sezione + card dell'editor condiviso).
- Verifica lead: `tsc -b --force` 0 errori sull'albero; ESLint 0 errori (1 warning pre-esistente in
  `table-field-control.test.tsx`); Vitest completo 983 file / 7433 verdi (un primo giro aveva 2 flaky di timing,
  `stat-donut-chart` e `attribute-form-table-editor`, verdi da soli e al secondo giro completo). Browser
  (Playwright): Categorie prodotto e Campi personalizzati dagli agenti, Ruoli dal lead (creazione a righe chiuse,
  Nome sul posto, riga Permessi, `/roles/:id/edit` non trovata, 375 px ok, console pulita); record e token di
  prova eliminati.
- Manuale: guide in-app IT/EN dei tre moduli aggiornate dagli agenti; manuale Claude Docs aggiornato per Ruoli
  ("Creare un ruolo" + "Modificare un ruolo"), Campi personalizzati (passo di creazione + punto "Modificare") e
  Categorie prodotto (creazione radice e sottocategoria a righe chiuse, nuova "Modificare una categoria", tolti i
  rimandi al pannello Riepilogo). PDF da rigenerare.
- Aperti Categorie prodotto: chiavi `productCategories.detail.title/subtitle` gia' orfane prima (lasciate); da
  confermare con l'utente se rivuole la striscia KPI nella creazione; un 422 sulla funzione aziendale ora compare
  sulla riga chiusa (prima il form azzerava il valore).
- Prossimo: commit separati per modulo su ordine esplicito. ONDA 2 CHIUSA (onda 3 resta annullata).

## GESTORI OFFERTE LEGACY = GESTORI OPPORTUNITA' + PROPRI (spec 0189 G-16, 2026-10-12) — VERDE, COMMITTATO

- Decisione utente: qnet-2 importa cio' che il legacy dice; i gestori dell'offerta li decide l'API legacy.
- Legacy (`/Users/Repository/qnet`, committato a parte): nuovo helper `App\Support\Migration\LegacyOpportunityManagers::
  forOpportunities()` (assigned_to..assigned4_to + `responsabile_opportunities`, utenti esistenti, distinti, max 12),
  usato da `OpportunityMigrationController` (output invariato, refactor DRY) e da `QuoteMigrationController`:
  `manager_user_ids` = gestori dell'opportunita' + `quotations.assigned_to`/`assigned2_to`, distinti, max 12.
- qnet-2: codice invariato (`QuotesSource` scrive i gestori con `promoteToOpportunity: true`, quindi un gestore
  dell'offerta assente sull'opportunita' viene aggiunto all'opportunita'). Nuovo test G-16 in
  `QuotesSourceImportTest` (21/21 verdi). Spec 0189: G-16, shape `migration/quotes`, AC-011b.
- Da verificare: il legacy non ha test ne' Pint; verificato solo `php -l`. Controllo sui dati legacy (offerte con
  gestori diversi da quelli dell'opportunita') non eseguito.

## OFFERTE LEGACY: COSTI, DATA OFFERTA, RICHIAMO, CLIENTE + CAMPO "DATA OFFERTA" (spec 0189 G-13/14/15, spec 0228, 2026-10-12) — VERDE, NON COMMITTATO

- Verifica campi offerte (richiesta utente) -> decisioni utente: importare i costi (fornitore NON scritto, sta sul
  prodotto: solo warning se diverso), data offerta = nuovo campo del form precompilato a oggi + /migrations, prossimo
  richiamo = data richiamo legacy, cliente offerta diverso dall'opportunita' = warning.
- G-8 era sbagliata: `quotationequipments` e' vuota; i costi veri sono `quotationexpensereports` (8.027 righe su
  1.604 offerte esportate, ~9,64 M EUR). Nessuna riga ricavo con costi imputati ha provvigioni a percentuale.
- Legacy (`/Users/Repository/qnet`, non committato): `QuoteMigrationController` emette `registry_id` (customer_id),
  `callback_date` (opportunities.recall_date) e `cost_lines[]` {id, product_id=expensereport_id, description=note,
  quantity, unit_price, vat_rate_id, supplier_id, offer_line_id = min quotationservices.id stesso service_id}.
- qnet-2 `QuotesSource` + nuovo concern `MapsLegacyQuoteCostLines`: righe COSTO (prodotto `old_source=
  CostProductCatalogue::EXPENSE_REPORTS`), imputate via `offerLineIndex` (riga ricavo scartata -> costo generico +
  warning), nessun `old_id` sulle righe costo (unique condiviso con le righe ricavo); warning fornitore diverso / prodotto
  senza fornitore; warning cliente diverso; `quote_date` = legacy ?? data di created_at legacy; `next_callback_at` =
  callback 00:00 (forceFill in `finalizeLegacyRecord`). Dipendenza opzionale `cost-products`. QuotesSource 344 righe
  (soft limit superato, sotto l'hard).
- Spec 0228 (la 0227 e' dei task template, altra sessione): `quotes.quote_date` date NOT NULL indicizzata (migrazione
  `2026_10_16_100000`, backfill DATE(created_at)); default oggi nel `creating` del model `Quote` (copre Gestione
  Richieste, conversione lead, factory); fillable+cast; `QuotesAuthorization` campo `date` mandatory; POST/PATCH
  `sometimes|date_format:Y-m-d` (null -> 422); `QuoteResource.quote_date`. Frontend: `QuoteDateField` nella sezione
  "Dati offerta" (create precompilato con la data locale, detail in place), schema/payload/types.
- Test aggiornati per requisito cambiato: contatori rollback `QuoteWorkflowMigrationTest` 153 e
  `SupplierCommissionDirectionTest` 17, elenco campi `QuoteAuthorizationTest`, fixture FE con `quote_date`.
- Verifiche: suite Pest completa 9985/9989 (falliscono solo i 3 `QuoteDocumentPdfTest`, LibreOffice assente); nuovo `QuoteDateTest` 8; Vitest quotes+RM+help+i18n
  1211, migrations 225; ESLint, Pint, `tsc -b --force` puliti. DB locale migrato (126 offerte con data = created_at). Import "quotes" NON eseguito in locale.
- Manuale: guide `quotes` e `migrations` IT/EN, requisiti `/migrations`; Claude Docs: paragrafo "Data offerta" dopo
  "Titolo" e paragrafo sorgente Offerte dopo Opportunita'.
- Fuori scope segnalati: colonna/filtro "Data offerta" nella griglia Offerte e nei documenti; `quotationemployees`
  (80 costi orari); 498 provvigioni legacy con valore ma senza destinatario (qnet-2 richiede recipient_id).

## MIGRAZIONE ANAGRAFICHE: ANCHE I TAG (spec 0189 AC-012, 2026-10-12) — VERDE, NON COMMITTATO

- Richiesta utente: "in anagrafiche su /migrations devi tirarti anche i tags".
- Legacy (`/Users/Repository/qnet`, non committato): `RegistryMigrationController` emette `tag_ids:int[]`
  (`company_tags.tag_id` distinti, solo tag esistenti, ordine `company_tags.id`). Provato sul DB legacy: 6480 righe
  `company_tags`, 4049 aziende con tag; pagina da 100 -> 5 aziende con tag.
- qnet-2 `RegistriesSource`: dipendenza opzionale `tags`, colonna anteprima `tag_ids`, Step 5 `writeTags()`: rimappa
  via `old_id` (`remapLegacyIds`, warning "Unresolved tag_ids (legacy id N).") e scrive `custom_fields.tags` con
  `CustomFieldWriter::write` (const `TAGS_CUSTOM_FIELD`). Campo `registries.tags` non definito (template
  `QualificaTemplateSeeder` non eseguito) -> anagrafica creata senza tag + warning. Chiave legacy `tag_ids`, NON
  `tags`: la seam generica `persistCustomFields` scriverebbe gli id legacy grezzi.
- Anagrafiche gia' importate: lo skip per `old_id` NON aggiunge i tag a posteriori (serve un re-import pulito o una
  sorgente "— collega" dedicata, non richiesta).
- Test: `RegistriesSourceImportTest` +2; Pest `tests/Feature/Migration` 387 pass; Pint ok. Guide in-app migrations
  IT/EN (Vitest help 115 pass, ESLint ok, `tsc -b --force` pulito). Manuale Claude Docs: nuovo paragrafo sulla
  sorgente Anagrafiche prima di quello sulle Opportunita'.

## MODELLI DI TASK MODIFICABILI SUL POSTO + CREAZIONE COME IL DETTAGLIO (spec 0227, 2026-10-12) — VERDE, COMMITTATO (999c63a8)

- Richiesta utente: "committa e passa avanti" (onda 2, dopo Segnatempo). Solo frontend: PATCH /task-templates/{id}
  parziale gia' esistente; ora `updateTaskTemplate` restituisce anche `permissions` (il backend le mandava gia').
- Dettaglio: "Dettagli" = Nome, Descrizione (rich text), Attivo, una riga ciascuno (PATCH del solo campo). "Fasi" =
  UNA riga `layout="block"` chiave `items` (stesso permesso di campo della board): la matita apre la board della
  creazione, Salva invia SOLO `items`+`stages` (full sync) e poi carica gli allegati in coda delle righe nuove
  (prima, in modifica, venivano persi). Nessuna matita senza `permissions.resource.update`.
- Creazione: replica del dettaglio (banda con nome live + Annulla/Salva, Dettagli a righe chiuse Fatto/Ripristina,
  board APERTA come le griglie offerta 0197, azioni ripetute in fondo, `useFormLeaveGuard`). Registry:
  `generateEditRoute: false`, `formOwnsHeader: true`, `onChanged`.
- File: nuovi `use-task-template-board` (stato board + validate + errori 422 + upload posizionale, `reset`),
  `use-task-template-inline-edit`, `task-template-fields`, `task-template-record-sections`,
  `task-template-items-overview` (lista read-only estratta dal dettaglio). `useTaskTemplateForm` solo creazione
  (spread della board); `buildUpdatePayload` -> `buildBoardUpdatePayload`; `useTaskTemplateStages` espone
  `setStageRows`; `taskTemplateDetailQueryKey` in `api.ts`. Rimossi `use-task-template-form-meta.ts`,
  `TaskTemplateFormMode`, i18n `form.editTitle/editSubtitle`, `detail.description/isActive/items.title`,
  `sections.identity.description`, `sections.stages.description`; nuove `form.leaveConfirm.*`; `detail.subtitle`
  "modificabile sul posto". NB: `sections.stages.title` ("Fasi") e' anche l'aria-label del select di spostamento.
- Test: REQUIREMENT CHANGED payload test (board), hook test (solo creazione: tolti i 2 casi edit, coperti dal nuovo
  `task-template-detail-inline-edit.test.tsx`, 7), form-body (+1, righe chiuse/board aperta), detail test (wrapper
  QueryClient). Vitest completo 980 file / 7398 verdi; ESLint pulito su task-templates; `tsc -b --force` pulito sui
  file di questo lavoro (errori `quote_date` nei test offerte = lavoro in corso di un'altra sessione).
  Browser (Playwright): `/task-templates/new` righe chiuse -> Nome + Riga 1 -> Salva -> dettaglio: Nome salvato sul
  posto, Fasi + Riga 2 salvate, `/task-templates/:id/edit` non trovata, 375 px senza scroll orizzontale, nessun
  errore console; modelli di prova (42, 43) e token temporaneo eliminati.
- Manuale: guida in-app IT/EN `task-templates` (creazione a righe chiuse, nuova sezione `editing-a-template`);
  manuale Claude Docs (passi di creazione + paragrafo modifica sul posto). PDF da rigenerare.
- Prossimo: commit su ordine esplicito (solo i file di questa voce); poi Categorie prodotto (onda 2).

## SEGNATEMPO MODIFICABILE SUL POSTO + CREAZIONE A RIGHE CHIUSE (spec 0226, 2026-10-12) — VERDE, COMMITTATO (8321dee8)

- Richiesta utente (ripetuta): "eliminare le pagine di edit anche per altri moduli, tutto inline, anche la creazione".
  Segnalato il conflitto con la chiusura del piano (22bd06ed); decisione utente: RIAPRIRE L'ONDA 2, senza toccare
  Gestione richieste, Gestione iscritti, Attiva e Gestione conti. Ordine onda 2: Segnatempo (FATTO, 0226) -> Modelli di
  task -> Categorie prodotto -> Ruoli -> Campi personalizzati. Un modulo alla volta, stop a ogni verde.
- Solo frontend (PUT /time-entries/{id} completo invariato: la riga salva il form intero, cambia solo quel campo).
  Righe: Titolo, Data, Tipo, Orario (Dalle/Alle/Tempo, UNA riga, chiave `minutes`), Note; Contesto segnatempo =
  UNA riga "Collegamenti" (chiave `registry_id`). Le righe di gruppo azzerano `MetaFieldRowContext` (`GroupEditor`)
  cosi' ogni campo tiene la sua etichetta. Titolo senza matita con task collegato; nessuna matita senza
  `permissions.update`; Elimina nel piede se `permissions.delete`. Errori su campi non visibili -> toast
  (`hiddenErrorMessage`, `time-entry-row-fields.ts`).
- File nuovi in `features/time-entries/form/`: `time-entry-fields` (controlli condivisi con `TimeEntryEditor`),
  `time-entry-type-chip`, `time-entry-record(.ts|-sections.tsx)` (`TimeEntryRecord` = RecordCanvas/RecordCard),
  `time-entry-row-fields`, `use-time-entry-inline-edit`, `time-entry-detail`, `time-entry-detail-sheet` (ex
  edit-sheet), `time-entry-create-form`, `time-entry-test-fixtures`. Rimossi `time-entry-form-body`,
  `time-entry-form-screen`, `time-entry-edit-sheet`, ramo edit di `TimeEntryFormActions`; `TimeEntryEditor` ora solo
  per gli editor incorporati nel task (senza titolo/contesto: rimosse le prop morte nei due chiamanti).
  `useTimeEntryForm`: `values`+`keepDirtyValues` in edit, espone `submit` grezzo e `clearServerError`.
  Pagina `/time-entries/:id` = `pages/time-entry-detail-page.tsx` (gate `time-entries.viewAny`). Menu riga
  "Modifica" -> "Apri" (`timeEntries.table.open`); i18n nuove `form.detailTitle/detailSubtitle/
  activitySectionTitle/scheduleRow/contextRow`, rimosse `form.editTitle/editSubtitle/update`.
  `time-entry-context-fields` usa `WORK_ORDERS_FOR_SELECT_RESOURCE` condiviso (`tasks/for-select-api`).
- Fuori scope (D-7): "Nuovo intervallo" nel task e sezione segnatempo della chiusura task, invariati.
- Test: REQUIREMENT CHANGED `time-entry-form-body.test.tsx` -> `time-entry-create-form.test.tsx` (8) +
  `time-entry-detail-inline-edit.test.tsx` (10); aggiornati dashboard (sheet di dettaglio) e day-entries-table
  ("Open"). Vitest time-entries+tasks+pages+routes 719 verdi, help+i18n 310; ESLint pulito; `tsc -b --force` 0.
  Browser reale (Playwright): `/time-entries/new` righe chiuse -> creazione (90 min) -> `/time-entries/:id` Note
  salvata sul posto, riga Collegamenti con etichette, 375 px senza scroll orizzontale, Elimina; nessun errore
  console; voci di prova (71, 72) e token temporaneo eliminati.
- Manuale: guida in-app IT/EN `time-entries` (creazione a righe chiuse, tabella campi, "Apri", paragrafo modifica
  sul posto); manuale Claude Docs (passi di creazione + paragrafo modifica nella sezione Segnatempo). PDF da
  rigenerare.
- NOTA: nello stesso albero c'e' lavoro non committato di un'altra sessione (badge OLD ID, `LegacyIdPolicy`, help
  `roles`, permessi): al commit includere SOLO i file di questa voce.
- Prossimo: commit su ordine esplicito; poi Modelli di task (onda 2).

## OLD ID: COLONNA NELLE GRIGLIE + BADGE NELLE SCHEDE, DIETRO PERMESSO (2026-10-12) — VERDE, COMMITTATO

- Richieste utente: in ogni tabella, oltre all'id, anche l'old_id (committato in 39ffecc6); poi nel dettaglio un badge
  evidenziato "OLD ID #123" accanto al titolo; visibile solo a super-admin o a chi ha il permesso.
- Decisioni utente: solo dove la tabella DB ha gia' `old_id` (31 griglie su 57, 25 schede; nessuna migrazione);
  colonna nascosta di default come l'id; badge formato `OLD ID #123`.
- Permesso `legacy-ids.view`: `LegacyIdPolicy` (solo catalogo, abilities ['view'], come TableFilterViewPolicy) +
  `config('authorization.permission_only_resources')` -> area "Trasversali" del form ruoli. Controllo unico
  `App\Authorization\LegacyIdVisibility::allows(?User)` (super-admin via Gate::before). Applicato ANCHE alla colonna
  delle griglie (scelta coerente: altrimenti il permesso non proteggerebbe nulla).
- Griglie: `InjectsDefaultIdColumn::columnsWithDefaultId()` inietta `old_id` dopo `id` se il table ha la colonna
  (`Schema::hasColumn`, memo statico) e `LegacyIdVisibility::allows(Auth::user())`; `TableService::mapRowWithMeta`
  lo mette nel payload riga solo per `$actor` autorizzato. Export invariato.
- Schede: 25 Resource con `'old_id' => $this->when(LegacyIdVisibility::allows($request->user()), $this->old_id)`
  (CompanySiteResource: `coreFields(Request $request)`). FE: `RecordCardHeader` prop `legacyId` ->
  `components/detail/legacy-id-badge.tsx` (Badge outline minimal, testo `text-[11px]` muted — richiesta utente "piu' minimal"; i18n `common.legacyIdBadge`); `old_id?: number | null` sui
  25 tipo `*Detail`. Nessun `can()` lato FE: l'assenza del campo nasconde il badge.
- i18n: `table.columns.old_id`, `common.legacyIdBadge`, `permissions.resources.legacy-ids`. Guide `general` e
  `roles` IT/EN + manuale Claude Docs aggiornati.
- Test: `tests/Feature/Table/TableLegacyIdColumnTest.php` (9: colonna, righe, sort, negativo senza permesso,
  super-admin, Resource dettaglio, catalogo ruoli); i 21 test "elenco colonne" tornati com'erano (attori senza
  permesso); `PermissionCatalogueEndpointTest` AC-006 aggiornato (area shared + legacy-ids). FE
  `legacy-id-badge.test.tsx`. Pest: verde tranne 3 `QuoteDocumentPdfTest` (LibreOffice assente). Vitest 979 file /
  7391 test, ESLint, `tsc -b` puliti sui miei file (errori `time-entries` = lavoro in corso di un'altra sessione).
- Prossimo passo: commit su ordine esplicito; ricordare all'utente di assegnare "ID legacy > Visualizza" ai ruoli
  (dopo `php artisan permissions:sync` in deploy).

## OPPORTUNITA' LEGACY: CATEGORIE DALLE RIGHE DELLE OFFERTE (spec 0189 emendata, G-12, 2026-10-12) — VERDE, NON COMMITTATO

- Problema: 7.711 opportunita' importate su 19.561 senza categorie prodotto (`opportunityservicecategories` vuota
  nel legacy) -> nessuna funzione aziendale. Richiesta utente: "inseriscilo nelle /migrations e esponilo
  nell'opportunity del legacy tramite righe prodotto dell'offerta".
- Legacy (`/Users/Repository/qnet`, non committato): `OpportunityMigrationController` espone
  `quote_product_category_ids` = `services.category_service` distinti dei prodotti in `quotationservices` delle
  offerte dell'opportunita' (join su `service_categories`: solo categorie esistenti). Sono categorie foglia
  (es. 77 Sic_Lavorazione), non la radice: e' quello che fa `OpportunityProductLineCoverage::ensure` in qnet-2.
- qnet-2 `OpportunitiesSource`: categorie proprie risolte vuote -> `quote_product_category_ids` (`productLines()`).
  Sul rilancio un'opportunita' gia' importata SENZA righe prodotto viene completata via `ProductLineWriter`
  (riga skipped + nota "Missing product categories back-filled."); con righe non si tocca; nulla da scrivere ->
  skip silenzioso. Precedente dello skip "self-healing": `UsersSource::reconcileEmployment`.
- Nota: il workflow delle offerte gia' importate non cambia (si risolve dalle righe dell'offerta; le righe
  prodotto dell'opportunita' sono solo il ripiego per offerte senza righe ricavo).
- Guida in-app `migrations` IT/EN e manuale Claude Docs (paragrafo "La sorgente Opportunita'...") aggiornati.
- Test: Pest Migration 379 (2 nuovi in `OpportunitiesSourceImportTest`), Vitest help 115, ESLint, Pint,
  `tsc -b` puliti; legacy `php -l` ok (nessuna suite legacy sui controller di migrazione).
- DB locale: import "opportunities" rilanciato (run 36: 19.561 skipped, 0 falliti, 6.435 completate con la nota);
  restano 1.276 opportunita' senza righe prodotto (ne' categorie proprie ne' offerte con prodotti categorizzati).
- Prossimo passo: commit separati nei due repo (qnet-2 e legacy), su ordine esplicito.

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
  ONDA 2 RIAPERTA (utente 2026-10-12, stesso giorno): Segnatempo (FATTO, 0226) -> Modelli di task (FATTO, 0227) -> Categorie prodotto (FATTO, 0229) ->
  Ruoli (FATTO, 0230) -> Campi personalizzati (FATTO, 0231): ONDA 2 CHIUSA; esclusi Gestione richieste, Gestione iscritti, Attiva, Gestione conti; onda 3 RIAPERTA e FATTA (spec 0233-0239).
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

