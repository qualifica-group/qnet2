# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

## SUITE INTERAMENTE VERDE: AZZERATI I 17 ROSSI STORICI (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: chiudere i rossi rimasti e ripulire. I 14 rossi backend + 3 frontend che da
giorni venivano etichettati "preesistenti" e rimbalzati fra teammate avevano **5 cause distinte**,
tutte identificate e corrette. Backend **4695 passed / 1 skipped / 0 failed**, frontend **3056
passed su 442 file / 0 failed**, Pint `passed`, `tsc -b --force` EXIT=0, ESLint pulito.

**1. Gli assert di navigazione erano inerti (11 rossi, la causa piu' grave).**
`NavigationService::filter()` (riga 73) scarta un gruppo senza route i cui figli sono tutti
nascosti. L'helper `navigationSectionKeys($data, 'sezione')` cercava i figli di UNA sezione: per
l'utente **senza** permesso quella sezione non esiste nella risposta, quindi la collection era
vuota e `not->toContain('x')` passava **a vuoto**. Tutti e 44 gli assert di gating della
navigazione — inclusi i 33 allora verdi — non verificavano nulla: sarebbero rimasti verdi anche
con il nodo che trapelava a un utente non autorizzato. Gli 11 rossi erano solo la punta emersa,
cioe' i file rimasti agganciati alle sezioni `management` e `configuration` dopo la
riorganizzazione dell'IA (oggi `registries-group`, `products-group`, `rewards-group`,
`administration`).
- Helper sostituito con **`navigationNodeKeys(array $data)`** in `tests/Pest.php`: ricorsivo su
  tutto l'albero, a qualsiasi profondita' (serve anche per `imports`, annidato a livello 3).
  Il ramo negativo diventa "assente dall'INTERO albero" — piu' forte del precedente e, soprattutto,
  non falsificabile da una riorganizzazione delle sezioni. `navigationSectionKeys` eliminato
  (nessun residuo: era dead code una volta convertiti i 44 call site).
- Convertiti 22 file + il lookup inline di `CompanySitePermissionsTest`, che puntava anch'esso a
  `management`.
- **Da sapere:** il nuovo helper non asserisce piu' in quale sezione sta il nodo. Era una
  precisione solo apparente — si e' rotta due volte e nel frattempo copriva zero. Chi vuole
  vincolare la posizione nell'IA scriva un test dedicato alla navigazione (esiste gia' il
  precedente: `RequestManagementNavigationTest`, `MigrationNavigationTest`).

**2. `CustomFieldWritePipelineTest` (422 vat_number).** La partita IVA e' ora validata davvero
(`App\Rules\VatNumber` -> `App\Support\Fiscal\ItalianVatNumber`, algoritmo a cifra di controllo).
Il test usava `IT999` come payload accessorio — non e' un numero valido. Allineato a
`IT99988877769`, lo stesso valore gia' usato da `CompanyCrudTest`. L'intento del test (merge
parziale dei custom field) e' invariato.

**3. `AbstractMigrationSourcePreviewTest`.** `RolesSource` dichiara 3 colonne native e
`mapNativeRow()` emette **sempre** `description`, null-fillata quando il record esterno la omette
(necessario: la griglia di preview mappa le righe per column id, una chiave assente sfaserebbe le
celle). L'aspettativa del test precedeva quella colonna. Aggiornata **coprendo entrambi i casi** —
un record con description e uno senza — invece di limitarsi ad aggiungere `null`.

**4. `RequestManagementTableSearchTest` over-length.** Non e' una regressione: il cap di
`TableRowsRequest::SEARCH_MAX_LENGTH` e' stato alzato **deliberatamente** da 100 a 255 (il
commento alla riga 33 documenta il perche': un cap corto rifiutava l'incolla di nomi prodotto
lunghi e la 422 compariva nella griglia come una riga di celle "ERR"). Il test era rimasto sul
vecchio limite con un magic value `101`; ora usa `TableRowsRequest::SEARCH_MAX_LENGTH + 1`, come
gia' facevano `TableRowsSearchTest` ed `ExportStoreTest`.

**5. I 3 rossi frontend erano un bug di codice, non test stantii.**
`quote-commissions-dialog.tsx` e `commission-configuration-detail.tsx` formattavano i numeri con
`toLocaleString(undefined, ...)` / `Intl.DateTimeFormat(undefined, ...)`: locale `undefined`
significa **locale ambientale del runtime**, quindi l'output cambiava da macchina a macchina
(qui `20,00`, in CI `20.00`). I test, che fissano `i18n.changeLanguage('en')`, avevano ragione.
Entrambi allineati alla convenzione gia' usata da 6 altri file: **`new Intl.NumberFormat(i18n.language, …)`**.
Nel dialog si riusa `formatQuoteAmount` (gia' esportato da `quote-summary.tsx`, stessa feature)
invece di un terzo formatter: l'importo passa da `calculateCommissionAmount` -> `roundCommission`,
gia' arrotondato a 2 decimali, quindi il `maximumFractionDigits: 2` non cambia il risultato.
**Regola da tenere:** in questo frontend il locale non si prende mai dall'ambiente, si prende da
`i18n.language`. Un `undefined` li' dentro e' un test che passa sul portatile e rompe in CI.

**Pulizia collaterale.** Pint falliva su 2 file che nessuno aveva formattato
(`app/Services/Commissions/QuoteCommissionPayloadRedactor.php`,
`tests/Feature/Migration/AttributesSourceImportTest.php`), entrambi non toccati da questo lavoro:
formattati, ora `pint --test` e' `passed`.

**Restano aperti** (non risolvibili qui, richiedono l'app reale): AC-140 responsive dell'editor
document-layouts + apertura in Word di un `.docx` generato; AC-035 responsive del pannello Offerte.

## FIX 500 SU GET /api/quotes/{id} IN PRODUZIONE (2026-07-30) — VERDE, NON COMMITTATO

Errore prod: `SQLSTATE[42000] 1055 ... ORDER BY clause is not in GROUP BY clause`
(`quote_lines.sort_order`) su `QuoteController@show`.

**Root cause.** `QuoteCommissionSummaryCalculator::totals()` costruiva l'aggregato
riusando la relazione `Quote::lines()`, che porta con se' `orderBy('sort_order')` per il
read path. L'`ORDER BY` finiva nella query con `GROUP BY recipient_role`: MySQL con
`only_full_group_by` (prod) la rifiuta, SQLite (dev/test) la tollera — per questo era
verde in locale e rossa solo in produzione.

**Fix.** `->reorder()` subito dopo `$quote->lines()`: l'ordinamento di riga non ha senso
in un aggregato. Nessun'altra query con `groupBy` nel backend parte da una relazione
ordinata (verificate: ImportService, LeadOperatorDistributor, RequestAssignmentService,
RequestCategoryTabsResolver — tutte da `query()`/`DB::table()` o ordinate su colonna
raggruppata).

**Regola generale da tenere:** un aggregato non si costruisce mai sopra una relazione che
definisce `orderBy` senza `reorder()`. Le suite girano su SQLite, che non segnala il
problema: la garanzia va asserita sull'SQL emesso.

**Test.** `QuoteSummaryHttpTest`: nuovo caso che cattura il query log della `show` e
asserisce che nessuna query con `group by` contenga `order by`. Verificato che fallisce
senza il fix (reproduce-first) e passa con il fix.

**Verifica eseguita:** Pest `tests/Feature/Quotes tests/Feature/CommissionConfigurations`
-> 170 verdi; Pint pulito sui due file toccati.

## OFFERTE: TAB "NOTE E PAGAMENTI" + METODO DI PAGAMENTO (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: nelle Offerte (modulo `quotes`) la sezione/tab "Note" diventa "Note e
pagamenti" e ospita un select con tutti i metodi di pagamento, salvato sull'offerta.

**Contratto congelato.** `quotes.payment_method_id` (FK nullable -> `payment_methods`,
`nullOnDelete`), scrivibile su `POST /api/quotes` e `PATCH /api/quotes/{id}`, esposto da
`QuoteResource` come `payment_method_id` + `payment_method: {id,name}|null`. Il select e'
alimentato da `GET /api/payment-methods/for-select` (gia' esistente, spec 0068), quindi
mostra solo i metodi attivi.

**Invarianti da non rompere**
- `payment_method_id` NON e' un default ne' un'ereditarieta': a differenza di `layout_id`
  non esiste un "default di modulo" sui `payment_methods` e le Opportunita' non hanno un
  campo da cui ereditarlo. Chiave assente = resta `null`. Percio' il DTO di create ha
  `paymentMethodId` **senza** flag `*Submitted` (basta il valore), mentre quello di update
  ha la coppia `paymentMethodId`/`paymentMethodIdSubmitted` come ogni altra FK opzionale.
- `quotes` e' il **primo consumatore** del lookup `payment-methods`: per questo
  `PaymentMethodService::delete()` non e' piu' una delete nuda ma ha il guard 409
  (`$paymentMethod->quotes()->exists()`), esattamente il punto di estensione che il file
  documentava da spec 0068. Il `nullOnDelete` a schema resta solo come rete di sicurezza.
- Nessuna validazione "metodo attivo" lato server (a differenza di `ValidatesQuoteLayout`):
  il for-select gia' offre solo gli attivi, e un'offerta con un metodo disattivato dopo la
  firma deve restare salvabile.

**Test toccati perche' il requisito e' cambiato (dichiarato, non aggirato)**
- `QuoteAuthorizationTest` AC-064 asseriva l'elenco ESATTO dei campi di `quotes`: ora
  include `payment_method_id` fra `layout_id` e `internal_notes`.
- `quote-form-body.test.tsx` asseriva il tab `'Notes'`: ora `'Notes and payments'`.
- Le fixture di 7 suite frontend hanno i due campi nuovi (additivo).

**Fuori scope, segnalato:** nessuna colonna `payment_method` nella tabella AG Grid delle
offerte e nessun token `payment_method` in `QuoteFieldResolver` (il resolver dei campi per
il `.docx`): se il layout Word deve stampare il metodo di pagamento, va aggiunto li'.

**Verifica eseguita:** Pest `tests/Feature/Quotes tests/Feature/PaymentMethods` -> 251
verdi (13 nuovi in `QuotePaymentMethodTest`); Pint pulito; Vitest `src/features/quotes` ->
149/150 (l'unico rosso, `quote-commissions-dialog`, e' **pre-esistente**: fallisce anche a
albero pulito), `src/features/payment-methods` + `src/i18n` -> 50 verdi; ESLint pulito;
`tsc -b --force` EXIT=0. Migrazione applicata al DB di sviluppo.

## AZIONE MASSIVA LEAD -> OPPORTUNITA' (2026-07-30) — VERDE, NON COMMITTATO

Spec `docs/specs/0071-lead-bulk-opportunity-conversion.xml` (approvata dall'utente),
implementata per intero. Toglie dallo scope-out di spec 0044 la voce "conversione massiva
da tabella".

**Decisioni utente (vincolanti, non reinterpretare):** D-1 **tutto-o-niente** (un solo lead
non convertibile rifiuta l'intero batch, nessuna conversione parziale); D-2 dialog di
conferma con riepilogo; D-3 esecuzione **sincrona con cap** in config, niente queue.

**Contratto congelato.** `POST /api/leads/convert-to-opportunities` `{lead_ids:int[]}` ->
`200 {converted, opportunity_ids}`. Rifiuto: `422 { errors: { reason:'not_convertible',
blockers:[{id, reason:'already_converted'|'not_derivable'}] } }` — stessa forma del 422 di
`bulk-move` categorie, reso inline nel dialog.

**File nuovi:** `config/leads.php` (`bulk_conversion_max`, default 200, env
`LEADS_BULK_CONVERSION_MAX`), `Http/Requests/Leads/BulkConvertLeadsRequest.php`,
`Exceptions/Leads/BulkConversionBlockedException.php` (possiede le costanti `REASON` e
`BLOCKER_*`), `Actions/Leads/ConvertLeadsToOpportunities.php`,
`tests/Feature/Leads/BulkLeadConversionTest.php`; FE `use-convert-leads.ts`,
`convert-leads-dialog.tsx` + le due suite.

**Invarianti da non rompere**
- La conversione del singolo lead resta SOLO in `ConvertLeadToOpportunity`: l'Action bulk
  possiede unicamente pre-check, atomicita' e ordine. Il predicato di convertibilita' e'
  `LeadOpportunityDefaultsResolver::campaignDerivesProductLine`, non una copia.
- `LeadOpportunityDefaultsResolver::REQUIRED_RELATIONS` e' passata da `private` a
  **`public`**: l'Action bulk fa l'eager load dell'intero batch con quella lista, cosi' la
  lista resta una sola. Se cambia, cambia per entrambi.
- Il pre-check precede ogni scrittura: e' l'unico modo di elencare TUTTI i blockers
  (provare-e-rollbackare mostrerebbe solo il primo).
- Authz doppia in `LeadController::convertToOpportunities`: `opportunities.create` +
  `leads.view` per ogni lead targetizzato.

**Scelte UI da conoscere**
- Semantica diversa dall'azione di riga singola, e dichiarata nel dialog: quella apre il
  form Opportunita' precompilato, la massiva deriva tutto server-side (N form non sono
  componibili). Nessun campo dell'Opportunita' e' impostabile in bulk.
- I lead gia' convertiti sono intercettati lato client dal `lead_status` gia' in griglia
  (conteggio + confirm disabilitato), NON rendendoli non selezionabili: la stessa
  selezione serve al bulk-delete, che deve continuare a raggiungerli (direttiva
  2026-07-21, `isRowSelectable` resta non usato).
- `getBulkActions` di `leads-table.tsx` ora compone due voci gated separatamente
  (`leads.update` -> assegna operatori, `opportunities.create` -> converti). Un test di
  `leads-table-assign.test.tsx` asseriva `getBulkActions === undefined` senza
  `leads.update`: **requisito cambiato**, ora asserisce che resti la sola voce di
  conversione, piu' un nuovo caso "nessuna delle due abilities -> slot non cablato".

**Verifica eseguita:** Pest `tests/Feature/Leads tests/Feature/Opportunities
tests/Feature/Imports` -> 437 verdi (di cui 14 nuovi); Vitest `src/features/leads` -> 125
verdi; Pint pulito; `tsc -b --force` EXIT=0. La suite completa ha 14 rossi backend
(attributes/companies/custom-fields/migrations/request-management/*-security navigation) e
3 rossi frontend (commission-configurations, quotes): **pre-esistenti**, falliscono anche
in isolamento, nessuno tocca i lead.

## IMPORT LEAD: AUTO-CONVERT DEFAULT ON (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: nello step finale del wizard di importazione lead il toggle "Converti
automaticamente in Opportunita'" deve partire **acceso**. Da opt-in a opt-out.

- `frontend/src/features/imports/wizard/import-step-summary.tsx`: lo stato e' ora
  `autoConvertPreference = useState(true)` e il valore effettivo e' **derivato**
  (`autoConvertPreference && can('opportunities.create')`), non solo inizializzato. Motivo: le
  abilities arrivano da una query asincrona, quindi un initializer di `useState` congelerebbe il
  valore pre-fetch; e `ImportController::confirm()` risponde **403** se arriva
  `convert_to_opportunity: true` senza `opportunities.create` — con il default a `true` un operatore
  senza quel permesso non avrebbe piu' potuto confermare **nessun** import.
- Conseguenza voluta da tenere presente: se la run **non** e' conversion-ready, ora al primo paint
  i blocker sono gia' visibili e **Conferma e' disabilitato**; l'operatore sistema i blocker (Torna
  alla revisione) oppure spegne il toggle. Prima partiva spento e non se ne accorgeva.
- Test aggiornati in `import-step-summary.test.tsx`: il default-on sostituisce il vecchio
  "toggle stays off", piu' un test nuovo che con `opportunities.create` assente il payload resta
  `false`. Il mock di `useAbilities` e' ora pilotabile via `vi.hoisted`.

**Verifica eseguita:** `npx vitest run src/features/imports` -> 23 file / 177 test verdi;
`npx tsc -b --force --pretty false` -> EXIT=0.

## LAYOUT PREVENTIVO STANDARD SEEDATO (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: una **riga** in `document_layouts` che riproduca il `.docx` di riferimento del
cliente ("Layout Accordo Sindacale_ Qualifica Group Training S.r.l."), intestazione e logo inclusi;
poi, in un secondo giro, **resa generica** ("va bene l'header e il footer, ma il preventivo dev'essere
generico") e **predefinita**. Anticipa il punto "layout iniziale dal `.docx`" della spec 0070; il
resto della fase 2 (PhpWord, `quotes.layout_id`, azione Genera Word) resta da fare.

**File nuovi**
- `backend/database/seeders/QualificaCatalog/StandardQuoteLayout.php` — il `config` come dati
  puri (~414 righe: sotto l'hard limit, in linea con `QualificaCatalogSeeder.php` a 406).
- `backend/database/seeders/QualificaDocumentLayoutSeeder.php` — la logica (riga + upload letterhead).
- `backend/tests/Feature/Seeding/QualificaDocumentLayoutSeederTest.php` — 5 test.
- `backend/database/seeders/assets/quote-letterhead.png` — `word/media/image1.png` del `.docx`
  (md5 verificato identico), gia' presente ma prima non referenziato da nessuno.

**Registrato come ultimo step di `QualificaTemplateSeeder`** (non di `QualificaProductionDataSeeder`:
l'utente lancia il template seeder da solo e li' si aspetta il layout). Delegato, non inline: carica
un binario e possiede l'invariante D-7, quindi ha le sue due dipendenze iniettate e la sua suite.
Il docblock di `QualificaTemplateSeeder` diceva "structure only, mai righe di dominio": aggiornato,
ora e' "la forma dell'installazione" = definizioni custom field + layout. Dati reali del cliente,
quindi `Qualifica*` e NON `Demo*` (backend.md §3.1); `DemoDocumentLayoutSeeder` resta com'e'.

**Conseguenza sui test:** il template seeder ora scrive un file. Ogni suite che lo seeda deve fare
`Storage::fake(config('attachments.disk'))` o lascia binari veri sotto `storage/app`. Aggiunto a
`QualificaTemplateSeederTest` e `QualificaProductionDataSeederTest`.

**Vincoli risolti, da non rompere**
- **Uovo/gallina immagine-layout:** un blocco `image` referenzia un `attachment_id` che deve gia'
  appartenere al layout, ma l'attachment richiede il layout esistente. Il seeder inserisce la riga
  con `StandardQuoteLayout::emptyConfig()`, carica il letterhead, poi aggiorna il `config` —
  tutto in **una** `DB::transaction`, quindi nessuno stato intermedio e' osservabile.
- **Idempotenza:** chiave naturale `code = 'qualifica_standard'` (globale, immutabile). Un re-run
  aggiorna nome/descrizione/config e **non** ricarica il letterhead.
- **Predefinito per volonta' esplicita dell'utente:** la riga ri-rivendica `is_default` (+`is_active`,
  che D-7c impone) a **ogni** run, poi chiama `DocumentLayoutDefaultManager::clearOtherDefaults()`
  nella stessa transazione. Quindi un DB dove i layout demo (o un operatore) avevano promosso un
  altro layout riconverge qui. Non e' un refuso: e' la regola richiesta.

**Trascrizione: cosa e' stato letto dall'OOXML, non ricordato** — margini `sectPr` (top 1985 /
right 1134 / bottom 1560 / left 1134 twips), letterhead 7551317x10677155 EMU = **595x841 pt**
(`wrap: behind_page`), 4 righe orizzontali flottanti `v:line` strokeweight **1pt = thickness 8**
(ottavi di punto) colore `A5A5A5`, header tabella `323E4F`, `tblGrid` 4082/1770/1900/1037/1417 twips
normalizzato a 40/17/19/10/14 pct. Le `w:sz` di Word sono **mezzi punti**: dimezzate.

**Due scostamenti deliberati dalla trascrizione letterale** (il contratto 0069 non ha la primitiva):
1. i `${...}` del `.docx` sono i placeholder del sistema **legacy**, mappati sul catalogo variabili
   di questo modulo (`{quote.code}`, `{quote.created_at}`, `{client.name}`, `{client.address}`,
   `{totals.revenue_net|revenue_vat|revenue_gross}`);
2. l'area firme, allineata a **tab stop** in Word, e' un blocco `table` a 2 colonne senza bordi: un
   `run` non ha tab e riempire di spazi non sopravvive a un cambio font.

**Generalizzazione (richiesta esplicita).** Struttura, letterhead e piede invariati; cambia solo il
testo legato a quel singolo accordo: titolo `OFFERTA ECONOMICA` (non "... E CONTRATTO DI
AVVALIMENTO"), saldo "alla sottoscrizione della presente offerta" (non "del presente accordo" — che
nel `.docx` era anche ripetuto due volte, refuso ora rientrato con la riscrittura). Un test blocca
il ritorno di quelle stringhe: se serve un layout per un tipo di documento specifico, si clona
questo dal configuratore, non si specializza il predefinito.

**Verifica eseguita:** `pest tests/Feature/Seeding tests/Feature/DocumentLayouts
tests/Unit/DocumentLayouts tests/Feature/SeederFlowTest.php` + `tests/Feature/CustomFields/
QualificaTemplateSeederTest.php` -> verde. Pint pulito. Il test piu' importante e' quello che passa
il `config` seedato dentro `DocumentLayoutConfigValidator`: e' l'unica prova che l'editor riuscira'
ad aprirlo e a risalvarlo.

## GENERAZIONE WORD DEI PREVENTIVI, FASE 2 (2026-07-30) — VERDE, NON COMMITTATO

Spec `docs/specs/0070-quote-word-generation.xml` implementata. La fase 1 (spec 0069, modulo
document-layouts) e' gia' committata in `c7a17d9`.

### Cosa c'e' ora

`phpoffice/phpword ^1.4` installato (dipendenza autorizzata dall'utente). **Licenza LGPL-3.0-only:
vietato patchare `vendor/phpoffice/phpword`** — se manca un comportamento si scrive codice nostro
sopra la sua API. Il `require` ha aggiunto esattamente 2 pacchetti (`phpword` + `phpoffice/math`);
le 4 advisory di sicurezza che composer segnala sono su `guzzlehttp/guzzle`, PREESISTENTI
(verificato: guzzle era gia' nel lock a HEAD), non introdotte da noi.

- `quotes.layout_id` (FK nullable -> document_layouts, `nullOnDelete`), relazione `Quote::layout()`,
  campo nel form/detail/Resource/meta, field permission `layout_id` (NON mandatory).
- Renderer completo in `app/Services/DocumentLayouts/Rendering/` (19 file): `QuoteDocumentGenerator`
  (entry point), `DocxRenderer`, un renderer per famiglia di blocchi, `ProductsTableRenderer`,
  `VariableResolver` + 4 resolver di categoria, `ValueFormatter`.
- `POST /api/quotes/{quote}/document` (gate `quotes.view`) e
  `POST /api/document-layouts/{documentLayout}/preview` (gate `document-layouts.view`), entrambi
  binari in streaming, sincroni, **senza persistere nulla**.
- Delete-guard `layout_in_use` (422 col NUMERO di preventivi nel messaggio), valutato DOPO il guard
  sul predefinito.
- Azione di riga `generate_document` + bottone "Genera Word" nel detail.
- Layout iniziale del cliente: `QualificaDocumentLayoutSeeder` + `QualificaCatalog/StandardQuoteLayout`.

### Decisioni e deviazioni dalla spec, da sapere

- **Il seed sta nella famiglia `Qualifica*`, non in `DatabaseSeeder`** come diceva la spec 0070 D-5.
  Il repo aveva gia' `QualificaTemplateSeeder`/`QualificaProductionDataSeeder` per la forma
  dell'installazione del cliente: e' il posto giusto, la spec era meno informata del repo.
- **Il titolo del layout e' "OFFERTA ECONOMICA"**, non "OFFERTA ECONOMICA E CONTRATTO DI
  AVVALIMENTO" del file originale: e' il layout PREDEFINITO di tutti i preventivi, non di quel
  singolo tipo di accordo.
- **PhpWord scrive le immagini in VML (`w:pict`), MAI in DrawingML (`w:drawing`/`wp:extent`).**
  Verificato nel sorgente vendor, non assunto. Conseguenza: le dimensioni restano in PUNTI, non in
  EMU — l'AC-251 della spec ("punti x 12700") descrive qualcosa che la libreria non produce, e i
  test asseriscono il comportamento REALE (z-index negativo, `position:absolute`,
  `mso-position-*-relative:page`) invece di quello ipotizzato. Il "dietro al testo" funziona, ma
  non esiste un `w10:wrap type="behind"` letterale nell'XML.
- `page_break` in header/footer e' un no-op silenzioso: PhpWord lo consente solo nel Section.
- Il resolver del layout: `quote->layout` vince SEMPRE, anche se quel layout e' stato disattivato
  dopo (e' il layout con cui il preventivo e' stato fatto); solo se e' null si usa il predefinito;
  se non c'e' nessuno dei due -> 422 `quotes.no_layout_available`.
- Un riferimento a variabile non risolvibile -> stringa VUOTA e la generazione RIESCE. Il posto dove
  il problema si segnala e' il validator al salvataggio del layout, non il documento del cliente.

### Verifica ESEGUITA

- Backend intero: `XDEBUG_MODE=off php artisan test` -> **4669 test, 4654 passed, 20753 assertions,
  14 failed**, e i 14 sono ESATTAMENTE gli stessi file preesistenti di prima della fase 2.
- `Quote|DocumentLayout` mirato: **450 test / 2178 assertions verdi**. Renderer: 28/28.
- **Test di fedelta' end-to-end** `tests/Feature/Quotes/QuoteDocumentFidelityTest.php` (6 test, 38
  assertions): attraversa seeder -> generatore -> `.docx` e confronta col documento REALE del
  cliente — geometria pagina (11906x16838, margini 1985/1134/1560/1134), carta intestata a piena
  pagina nell'header ancorata alla pagina, tabella prodotti a 5 colonne con i rapporti
  40/17/19/10/14 e header 323E4F, testi statici, nessuna graffa residua, nessun footer.
  Era l'unico controllo che nessun singolo agente poteva fare, perche' attraversa due ownership.
- Frontend: `tsc -b --force` EXIT=0, ESLint pulito su quotes/document-layouts/i18n.
- Pint pulito.

### Attenzione: lavoro di terzi nello stesso working tree

Mentre chiudevo la fase 2 e' comparsa nell'albero un'ALTRA feature in corso, non nostra:
**spec 0071 lead-bulk-opportunity-conversion** (file `features/leads/*`, `Actions/Leads/`,
`config/leads.php`). L'unico test frontend rosso — `leads-table-assign.test.tsx > is wired only
with leads.update` — appartiene a QUEL lavoro, non a questo: nessun file di leads e' stato toccato
dalla fase 1 o 2. Non toccarlo qui.

### Prossimi passi

1. Verifica manuale a 375/768/1024 px dell'editor (AC-140) e apertura reale in Word di un `.docx`
   generato: la fedelta' e' asserita sull'XML, il giudizio visivo finale resta umano.
2. Se il cliente vuole la ripartizione IVA per aliquota nella riga totali (l'originale ce l'ha,
   noi rendiamo una riga di IVA aggregata, spec 0070 D-6) serve una struttura ripetuta per aliquota:
   e' una feature a se.
3. Sconto di riga e metodo di pagamento sul preventivo restano fuori: non esistono in schema.

## MODULO DOCUMENT-LAYOUTS, FASE 1 (2026-07-30) — COMMITTATO in c7a17d9

Spec `docs/specs/0069-document-layouts-module.xml` (approvata dall'utente) implementata per intero.
La **fase 2** (`docs/specs/0070-quote-word-generation.xml`: PhpWord, `quotes.layout_id`, azione
Genera Word, delete-guard sui layout in uso, layout iniziale dal `.docx` del cliente) NON e' ancora
iniziata.

### Cos'e'

Modulo `document-layouts`: layout di documento riutilizzabili, definiti dal gestionale con un
editor visuale a blocchi e anteprima A4, con variabili dinamiche. Agnostico rispetto al
consumatore (`module`, enum `DocumentLayoutModule`, oggi solo `quotes`).

**Nome:** il brief lo chiamava "Layout" ma l'identificatore era gia' occupato da
`AttributeLayout`/`attribute_layouts` (spec 0062) e da `frontend/src/layouts/`. Congelato:
tabella `document_layouts`, model `DocumentLayout`, dominio/permessi `document-layouts`,
feature `features/document-layouts/`, namespace i18n `documentLayouts`. Etichetta utente "Layout"
via i18next.

### Contratto congelato da rispettare

La colonna `config` (JSON) e' la superficie condivisa fra editor frontend, validator backend e
(fase 2) renderer `.docx`. 7 tipi di blocco: `text`, `image`, `table`, `products_table`,
`page_break`, `spacer`, `divider`. 3 zone: `header`/`body`/`footer`.
**Unita' di misura, sbagliarle e' il rischio numero uno:** margini pagina e spaziature paragrafo in
TWIPS; font, altezze e dimensioni immagine in PUNTI; larghezze in PERCENTUALE; colori esadecimali
`RRGGBB` **senza `#`**.
Fonte di verita' dei tipi: backend `App\Services\DocumentLayouts\DocumentLayoutConfigValidator`
(+ i suoi validator per famiglia di blocco e `DocumentLayoutConfigLimits` per le costanti),
frontend `features/document-layouts/layout-config.ts` + `layout-config-schema.ts`.

**La chiave si chiama `variable`, MAI `token`.** Non e' estetica: l'hook `secret-scan.sh` segnala
come segreto in chiaro qualunque `token` a cui si assegni un valore letterale e blocca la scrittura
del file. Vale in PHP, in TS e nelle spec.

**Tre elementi imposti dal `.docx` di riferimento del cliente** (ispezionato con parser XML, non
descritto a memoria): `image.wrap: 'behind_page'` (la sua grafica e' una carta intestata a piena
pagina 594.6x840.7 pt nell'header, non un logo); il blocco `divider` (i 4 "drawing" nel corpo sono
righe orizzontali da 0.8 pt, non immagini); `products_table.columns[].lines` con `keys` +
`separator` (la prima colonna impila codice-nome e descrizione nella stessa cella).

### Cose che il brief dava per vere e NON lo sono (verificate, non ipotizzate)

- **Nessuno sconto esiste in schema.** `grep -rniE "discount|sconto" backend/app backend/database
  frontend/src` = zero match. `discount` e' fuori dall'allow-list delle colonne prodotto.
- **Nessun metodo di pagamento sul preventivo:** `quotes` non ha `payment_method_id`. La categoria
  di variabili `payment_method` non esiste (decisione utente: escludere per ora).
- **Il cliente non e' in relazione col preventivo:** si raggiunge con `quote->opportunity->registry`
  e il nome canonico e' `registries.name` (NON `denomination`, che esiste solo su `companies`).
  Partita IVA, codice fiscale, SDI e contatti stanno sulla scheda `personalData`/`contacts`.
- **`opportunities` non ha `code`.** **`OperationalSite` non ha nome** (label via
  `OperationalSiteLabel`). **Non esisteva alcun composer di indirizzo stampabile**: aggiunto
  `App\Support\AddressLabel` (`singleLine`/`multiLine`, non interroga il DB, il chiamante eager-loada).

### Decisioni vincolanti gia' prese

- I riferimenti a variabile sono in INGLESE (`{quote.code}`, `{client.name}`,
  `{totals.revenue_gross}`) con label localizzate nel selettore. Gli esempi italiani del brief
  (`{preventivo.numero}`) erano illustrativi; `engineering.md §1.2` impone identificatori inglesi.
- **Le variabili rispettano i field permission dei dati personali** (D-6): un riferimento la cui
  sorgente e' un campo PII non visibile per il ruolo dell'attore non entra nel catalogo e, in
  fase 2, si risolvera' a stringa vuota. Un layout non e' un canale per aggirare la matrice dei
  permessi di campo.
- **Un solo predefinito per modulo** (D-7), invariante applicativa in
  `DocumentLayoutDefaultManager` dentro transazione (unique parziale non portabile MySQL+SQLite):
  primo layout del modulo -> predefinito automatico; `is_default=true` azzera gli altri; richiede
  `is_active=true`; un predefinito non si disattiva (422 `default_cannot_be_deactivated`);
  `is_default` non si porta a false direttamente (422 `default_must_be_reassigned`); non si cancella
  se non e' l'unico del modulo (422 `default_cannot_be_deleted`).
  **L'inline edit di `is_active` in tabella e' overridato** in `DocumentLayoutsTableDefinition::updateCell()`
  proprio perche' la pipeline generica scrive in mass-assignment e bypasserebbe l'invariante.
- Apertura del modulo in PAGINA (`OPEN_MODE_PAGE`): l'editor non sta in uno Sheet.
- Immagini del layout: solo `jpeg`/`png` (non webp/gif, anche se accettati altrove nel repo),
  max 5 per layout, disco privato `local`, esposte come data URI base64 su endpoint dedicato —
  mai un URL pubblico, e mai base64 dentro la response del detail o della tabella.

### Bug reali trovati e corretti durante il lavoro

1. **`#[Label('Quotes')]` rompeva `GET /api/config` con un 500 globale.** `HasMeta::label()` risolve
   l'etichetta con `__()`, e `__('Quotes')` matcha il GRUPPO di traduzione `lang/{it,en}/quotes.php`
   restituendo un ARRAY -> `TypeError: label(): Return value must be of type string`. Poiche' l'enum
   e' in `config/config.php` `form_enums`, il guasto era globale, non locale al modulo. Corretto
   usando la chiave puntata `document_layouts.modules.quotes` come `#[Label]`. Test di regressione:
   `tests/Unit/DocumentLayouts/DocumentLayoutModuleEnumTest.php`. **Attenzione per il futuro: mai
   dare a un `#[Label]` un testo che coincide col nome di un file di lingua.**
2. `Relation::enforceMorphMap()` e' in modalita' STRICT: senza la riga
   `'document_layout' => DocumentLayout::class` in `AppServiceProvider`, `LogsModelActivity` fa
   fallire QUALUNQUE create/update del model (chiama `subject()->associate()` prima di valutare se
   loggare), non solo l'uso di `images()`.
3. Frontend: passare un setter di `useState` come callback (`onActivate={setActiveInsert}`) faceva
   trattare la funzione come updater funzionale e crashare l'editor.

### Verifica ESEGUITA (non "dovrebbe passare")

- Backend mirato: `php artisan test --filter="Config|DocumentLayout"` -> **300 test, 300 passed,
  1768 assertions**. `pint --dirty` pulito.
- Backend INTERO: `XDEBUG_MODE=off php artisan test` -> **4590 test, 4575 passed, 14 failed**, e i
  14 sono tutti nella famiglia preesistente descritta sotto (nessuno in DocumentLayouts, Quotes,
  PaymentMethods, Config, Tables, Exports).
- Frontend: **435 file / 3007 test verdi**, `npx tsc -b --force` EXIT=0, ESLint pulito sui file nuovi.
- Completezza i18n: script di controllo su **174 chiavi** `documentLayouts.*` realmente usate nel
  codice -> 0 mancanti in `it`, 0 in `en`.
- `php artisan permissions:sync` -> le 8 permission `document-layouts.*` create.

### Rossi PREESISTENTI, verificati non nostri

- `tests/Unit/Migrations/AbstractMigrationSourcePreviewTest.php` (area spec 0013).
- Vari test di navigazione (`AttributeSecurityTest` e simili): falliscono con
  `Failed asserting that a traversable contains 'attributes'`. **Verificato stashando
  `config/navigation.php`**: il rosso si riproduce identico senza la nostra voce. Il nostro diff e'
  puramente additivo.
- `RequestManagementTableSearchTest` (over-length search term).
- `php artisan test` va in **segfault (signal 11)** con Xdebug attivo. Non e' un problema del
  codice: **`XDEBUG_MODE=off php artisan test` completa la suite intera senza segfault**. Usare
  sempre quella forma per un run completo.

### Prossimi passi

1. Verifica manuale a 375/768/1024 px dell'editor (AC-140: la regola di impilamento e' testata, il
   rendering pixel no).
2. Fase 2 = spec 0070. Ordine consigliato: `composer require phpoffice/phpword` (^1.4, **licenza
   LGPL-3.0-only**: vietato patchare `vendor/`) -> migration `quotes.layout_id` -> renderer
   (`DocxRenderer`/`VariableResolver`/`ProductsTableRenderer`) con test che aprono lo ZIP e
   asseriscono sull'XML -> endpoint generazione e anteprima -> delete-guard `layout_in_use` ->
   campo Layout nel form preventivo + azione Genera Word -> seed del layout iniziale
   (`QuoteDefaultLayoutSeeder` in `DatabaseSeeder`, con l'asset PNG della carta intestata estratto
   dal `.docx` del cliente).

## COSTO DI CONTESTO: HANDOFF ARCHIVIATO + HOOK ROTTI RIMOSSI (2026-07-30) — VERDE, NON COMMITTATO

Intervento di riduzione del costo token, autorizzato dall'utente (solo questi due punti; gli altri
proposti — dedup `CLAUDE.md` §2 vs `engineering.md` §1, lista spec nell'hook, auto-attach `**/*.tsx` —
sono stati esplicitamente esclusi, NON rifarli senza richiesta).

1. **`docs/HANDOFF.md` era 1.072.426 byte / 13.912 righe / 286 sezioni** (~268k token se letto
   intero: oltre un quarto di una finestra da 1M). L'hook `session-start.sh` ne inietta solo i primi
   6000 char, ma `CLAUDE.md` §3.1 ordina di leggerlo — un Read completo era una bomba di contesto.
   Split: le 275 voci dal 2026-07-28 all'indietro sono in **`docs/handoff-archive/2026-07.md`**
   (1.056.076 byte, con indice dei titoli in testa), spostate integralmente e verificate `diff`-identiche.
   Qui restano le 11 voci correnti (2026-07-29/30) + la sezione permanente sulla trappola
   `tsc --noEmit`. **43.695 byte, -96%.** Nessun contenuto perso o riassunto.
2. **Rimossi da `.claude/settings.json` i due hook `PreToolUse` che invocavano
   `C:\Users\PC\.local\bin\graphify.EXE`** (matcher `Bash` e `Read|Glob`): path Windows su macOS,
   binario inesistente e non nel PATH, quindi fallivano a ogni Bash/Read/Glob. JSON rivalidato con
   `node -e require(...)`; restano i 3 PreToolUse legittimi (`block-no-verify`, `config-protection`,
   `doc-guard`). Zero riferimenti `graphify` residui in `.claude/`.

3. **La rotazione e ora un gate deterministico, non un suggerimento** (§HOOK): `handoff-reminder.sh`
   (Stop) controlla la dimensione di `docs/HANDOFF.md` e, oltre `HANDOFF_MAX_BYTES` (default 51200),
   stampa il nudge ad archiviare. Il controllo sta **prima** dell'early-exit su git, cosi scatta anche
   ad albero pulito o fuori da un repo. Mai bloccante (exit 0 sempre). Verificato su 4 casi: sotto
   soglia, sopra soglia, sopra soglia senza git, sotto soglia senza git.
   NON e stata aggiunta la regola semantica in `CLAUDE.md` §4 (l'utente ha autorizzato solo l'hook):
   §3.1 continua a dire "leggi `docs/HANDOFF.md`" senza menzionare l'archivio.

Da sapere per chi continua: cercare nell'archivio con `grep` sul titolo della voce, **mai** leggerlo
per intero.

## SPEC 0069 — MODULO DOCUMENT LAYOUTS, WAVE 1 FRONTEND (contratto + form metadati) (2026-07-30) — VERDE, NON COMMITTATO

Spec: `docs/specs/0069-document-layouts-module.xml`. Questa e SOLO la wave 1 frontend: contratto TS
della colonna `config` + validazione Zod + CRUD/for-select/variables api + form metadati
(name/code/description/module/is_active/is_default). L'editor visuale a blocchi (wave 2) e la
tabella/pagina lista NON sono in questa wave. Lavoro backend (model/migration/service/policy) in
parallelo da un altro teammate, non toccato qui (file disgiunti, `frontend/src/` vs `backend/`).

### File creati (tutti nuovi, `frontend/src/features/document-layouts/`)

`types.ts` (92r, metadati CRUD + `DOCUMENT_LAYOUT_MODULES`), `layout-config.ts` (273r, contratto
TS puro dei 7 tipi di blocco/zone/page — split obbligato dal budget 300r), `layout-config-defaults.ts`
(226r, limiti dimensionali + default factory per blocco, `createDefaultBlock`), `layout-config-schema.ts`
(279r, Zod strutturale mirror del validator backend), `document-layout-schema.ts` (Zod metadati),
`document-layout-form-payload.ts` (`buildCreatePayload`/`buildUpdatePayload`, `config` mai
diffato se non passato — la config e di competenza dell'editor wave 2), `use-document-layout-form.ts`,
`use-document-layout-form-meta.ts`, `document-layout-form.tsx` + `document-layout-form-body.tsx`
(RHF+Zod, `MetaField`/`ResourcePermissionsProvider`, `code`/`module` readonly in edit), `api.ts`
(CRUD), `for-select-api.ts` (`module` obbligatorio, `meta:{is_default,code}`), `variables-api.ts`
(fetch+hook `GET .../variables`). Test colocati: `layout-config-schema.test.ts` (56 casi, copre
AC-030..AC-039b + AC-111: blocco sconosciuto, `discount` fra colonne prodotto, colore non hex,
margine oltre 5670, `wrap:behind_page` fuori header, ecc.), `document-layout-schema.test.ts` (AC-110),
`document-layout-form-payload.test.ts` (AC-112), `document-layout-form.test.tsx` (AC-113/AC-114).

### Esito verifica (eseguita davvero, non dichiarata)

- `LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8 npx vitest run src/features/document-layouts` → **4 file, 74
  test, tutti PASS**.
- `npx vitest run` (suite frontend intera) → **423 file, 2945 test, tutti PASS** (nessuna
  regressione sulle altre feature).
- `npx tsc -b --force --pretty false` → **EXIT=0**, zero errori.
- `npx eslint src/features/document-layouts --max-warnings=0` → **zero errori, zero warning**.

### Decisioni prese fuori dal contratto esplicito (da rispettare in wave 2)

- **`config` non e un campo del form metadati.** Il form wave 1 non tocca mai `config`: in create
  invia sempre `createEmptyDocumentLayoutConfig()` (pagina default 1 pollice/Calibri 11, tre zone
  vuote); in edit `buildUpdatePayload` accetta un parametro `config?` opzionale (mai passato dal
  form attuale) che confronta con `JSON.stringify` contro l'originale e lo include nel PATCH solo
  se e cambiato. L'editor wave 2 e chi dovra passarlo.
- **`layout-config.ts` splittato in due file** (tipi puri vs limiti+default) per restare sotto
  300 righe, come autorizzato dal task.
- **Limiti non congelati esplicitamente nella spec** (space_before/space_after di text/divider,
  dimensioni bordo tabella): lasciati solo `nonnegative int` senza upper bound inventato, per non
  divergere da un vincolo che il validator backend potrebbe definire diversamente. `DIVIDER_THICKNESS_MAX=96`
  e `WIDTH_PCT_MIN/MAX=1/100` dedotti da AC-039b (unica fonte che li menziona esplicitamente).
- **`layout-config-schema.ts` NON valida** `image.attachment_id` ownership, `totals.rows[].variable`
  contro il catalogo reale, ne l'esistenza di `custom_fields`/`opportunity_attributes`: dati non
  disponibili client-side, restano solo server-side (dichiarato in testa al file).
- **`for-select-api.ts`/`variables-api.ts` senza consumer nel form attuale** (nessuna UI li usa
  ancora, come da precedente `payment-methods/for-select-api.ts` "kept ready"): il primo consumer
  reale arriva con l'editor (wave 2, variabili) e con `quotes.layout_id` (spec 0070, for-select).
- **`createDefaultBlock` non copre `image`**: serve sempre un `attachment_id` reale, niente default
  sensato senza un'immagine caricata; usare `createDefaultImageBlock(id, attachmentId)` a parte.

### Chiavi i18n RICHIESTE (namespace `documentLayouts`, da creare in `en-document-layouts.ts`/`it-document-layouts.ts` — NON creati qui)

```
documentLayouts.form.{name,code,description,module,isActive,isDefault,save,saving,cancel,
  created,updated,nameRequired,nameMax,codeRequired,codeMax,codeInvalid,descriptionMax,
  moduleRequired,genericError}
documentLayouts.form.sections.identity.{title,description}
documentLayouts.form.hints.{codeLocked,moduleLocked,isDefault}
documentLayouts.modules.quotes
```
Testo EN esatto proposto (per parita 1:1 nel test `document-layout-form.test.tsx`, che le registra
a runtime via `i18n.addResourceBundle` non essendo questo teammate autorizzato a toccare
`i18n/locales/`): vedi `documentLayoutsEn` in cima a quel file di test. Riusate (gia esistenti,
non da ricreare): `authorization.loadError`, `common.retry`.

### Prossimi passi (fuori scope di questa wave, per chi continua)

- Wave 2 frontend: editor a blocchi (`features/document-layouts/editor/`), collega `config` al
  form tramite `buildCreatePayload`/`buildUpdatePayload` gia pronti ad accettarla.
- Tabella lista + pagina + registry modulo (`moduleScreen`, `OPEN_MODE_PAGE`) non in questa wave.
- i18n: un teammate deve creare `en-document-layouts.ts`/`it-document-layouts.ts` con le chiavi
  sopra e montarle in `en.ts`/`it.ts` + `navigation.documentLayouts`.
- Verificare il contratto contro il backend reale non appena la wave backend e verde (nomi
  `DocumentLayoutModule`, shape `DocumentLayoutResource`, endpoint `variables`/`for-select`).

## SPEC 0068 — MODULO METODI DI PAGAMENTO (2026-07-30) — VERDE, COMMITTATO IN 3c9e63c

Spec: `docs/specs/0068-payment-methods-module.xml`, 75 criteri. Nuovo lookup `payment-methods`
sul modello canonico di `reward-statuses` (spec 0060). Contratto congelato prima del dispatch,
backend e frontend implementati in parallelo senza rinegoziarlo.

### Esito dei 75 criteri (conteggio ricostruito enumerando gli id, non per intervalli)

**74 PASS** con test eseguiti, **1 MANUALE** (AC-140). 74+1 = 75. Zero FAIL, zero non verificati.
Un PASS porta una riserva sostanziale che va letta:
- **AC-051 PASS CON RISERVA** — il criterio come scritto e esercitato per intero (termine nel solo
  `code`, nel solo `name`, assente da entrambi), ma solo per termini SENZA underscore.
  Causa: `App\Services\Table\FilterApplier::escapeLike()` produce `\_` e SQLite (DB dei test) non
  onora l'escape a backslash senza clausola `ESCAPE`; MySQL (produzione) si. Verificato con probe
  SQLite isolato: il record cercato sparisce del tutto, non e un falso negativo collaterale.
  Difetto PREESISTENTE e cross-cutting su OGNI colonna searchable di OGNI dominio del repo.
  Il test lo documenta nel commento (`PaymentMethodTableTest.php:92-97`) e usa codici senza
  underscore. NON aggirato altrove: gli altri test usano codici snake_case solo come valore di
  campo, mai come termine `search=`.
- **AC-113 PASS** — chiuso con `features/modules/module-routes.test.tsx`, primo test del repo su
  `buildModuleRoutes()`. Nessun router montato e nessun refactor di produzione: la funzione era
  gia testabile. Attenzione alla forma reale del valore di ritorno, che il test asserisce e che
  e controintuitiva: **path RELATIVI senza slash iniziale** (`payment-methods/new`, non
  `/payment-methods/new`, per via di `basePath.replace(/^\//, '')`). Verifica anche `element.type`
  e `element.props` (`domain`, piu `variant: 'duplicate'` sulla quarta route) e include un caso
  negativo su un dominio inesistente.
- **AC-140 MANUALE** — responsive 375/768/1024, dichiarato non automatizzabile dalla spec stessa.
  Richiede un rendering reale. UNICO criterio ancora aperto.

### Decisioni vincolanti (D-1..D-5) — da rispettare per chi estende il modulo

- **D-1** `sort_order` server-managed, FUORI dal form. `StatusOrderManager` NON e riutilizzabile
  (accoppiato a `system_key`, `SYSTEM_HEAD_KEY`, union chiusa di 4 class-string): creato
  `App\Services\PaymentMethods\PaymentMethodOrderManager`, senza concetto di head/tail.
  `StatusOrderManager` NON e stato toccato.
- **D-2** DELETE senza guard: nessun consumatore esiste, quindi 204 e nessun 409. Un guard con
  relazioni inesistenti sarebbe dead code. Punto di estensione documentato nel docblock di
  `PaymentMethodService::delete()`: il primo modulo consumatore aggiunge lì il check e il 409.
- **D-3** `code` immutabile SEMPRE dopo la create, per chiunque, super-admin incluso. NON poggia
  sui field permission (il ruolo privilegiato li bypassa per progetto): la garanzia e la regola
  `['prohibited']` in `UpdatePaymentMethodRequest`, incondizionata. Coperto da AC-025.
- **D-4** `is_active` editabile inline in tabella: PRIMO caso nel repo di colonna
  `type: 'boolean'` con `'editable' => true`. Passa dalla pipeline generica
  `PATCH /api/tables/{domain}/rows/{row}`, default `updateCell()` (mass-assignment), nessun
  override, nessuna regola di business su `is_active`. Il ramo `'boolean' => ['boolean']` di
  `CellValueValidator::typeRules()` prima non era esercitato da alcun test: ora lo e.
- **D-5** La response di `POST /payment-methods/reorder` emette SEMPRE `"system_key": null`
  letterale, pur non esistendo quella colonna: e il contratto che il feature condiviso
  `status-reorder` pretende. Senza, `isPinned` leggerebbe `undefined !== null` come true e lo
  sheet perderebbe ogni maniglia dopo il primo drag.

### Contratto (per i futuri moduli consumatori)

`GET /api/payment-methods/for-select` -> `{id, label: name, subtitle: code, meta: {payment_days}}`.
Solo `is_active = true`, ordinati per `sort_order`. `ids[]` idrata anche gli INATTIVI (edit-mode)
senza gonfiare `total`. Un consumatore deve mostrare solo gli attivi e non alterare i record
storici quando un metodo viene disattivato.

### Due difetti PREESISTENTI scoperti, entrambi fuori scope, da triagare

1. **`FilterApplier::escapeLike()` + SQLite** — vedi AC-051 sopra. Serve `ESCAPE` esplicito o un
   PRAGMA lato test. Impatta la ricerca globale di TUTTI i domini, non solo questo.
2. **`use-status-reorder.ts` non invalida mai `['status-reorder', resource, 'list']`** dopo un
   reorder riuscito. Il guard adjust-state-during-render (righe 53-56) confronta `listQuery.data`
   con `syncedFrom` e, poiche la query non viene mai reinvalidata, ripristina lo stato pre-drag
   subito dopo il `.then()`. Giudicato FONDATO in modo indipendente dal verifier, e confermato
   empiricamente: lo stato riconciliato post-successo NON e osservabile nel DOM per NESSUNO dei
   4 moduli status esistenti (pipeline/opportunity/quote/reward). Nessun test attuale lo copre.
   Conseguenza pratica: dopo un drag riuscito lo sheet mostra l'ordine vecchio finche non si
   riapre. Merita una spec dedicata.

### Lezione di processo (costata un falso verde)

Il primo test di regressione per D-5 era VERDE e NON POTEVA FALLIRE: rimuovendo `?? null` restava
verde, perche il bug di resync sopra rende lo stato riconciliato non osservabile attraverso il
componente Sheet. Scoperto dal verifier con un esperimento di falsificazione, non per lettura.
Correzione: la mappatura e stata estratta in `features/status-reorder/reconcile-reordered-items.ts`
(funzione pura) e testata direttamente. **Criterio adottato: un test di regressione vale solo se
si dimostra che diventa rosso rimuovendo il fix.** Applicato anche ad AC-114 (svuota una stringa
i18n -> rosso) e AD AC-115 (rimuovi la entry icona -> rosso).

### Cucitura cross-stack da non dimenticare

Una voce di menu richiede il nome icona in DUE posti: `backend/config/navigation.php` E
`frontend/src/features/navigation/icon-map.ts`. Senza il secondo, `resolveIcon()` cade in
silenzio sul fallback `Circle` — nessun errore di lint o di tipo. Questa spec l'aveva omesso e il
difetto e arrivato all'utente; ora e coperto da AC-115 (`icon-map.test.ts`).

### Verifica eseguita

- Backend `--filter=PaymentMethod`: **91 test, 393 assertion**, verdi (87 + 4 whitebox AC-003).
- Backend suite INTERA: **4409 test, 4394 pass, 14 fail** = esattamente il baseline preesistente
  (11 `*SecurityTest` sui nodi di navigazione, `AbstractMigrationSourcePreviewTest`,
  `CustomFieldWritePipelineTest`, `RequestManagementTableSearchTest`). Zero regressioni.
- Frontend: **418 file / 2869 test** verdi. `npx tsc -b --force` **EXIT=0**. ESLint pulito.
- Una regressione introdotta e chiusa: `FieldCatalogueEndpointTest` asserisce con
  `toEqualCanonicalizing()` l'elenco ESATTO delle resource di `config/authorization.php`;
  registrare un nuovo dominio la fa cadere. Chi aggiunge una resource deve aggiungere la propria
  voce a quella lista (convenzione del file, ogni spec precedente ha fatto lo stesso).

**ATTENZIONE ambiente:** la suite backend intera va lanciata con Xdebug spento, altrimenti va in
SIGSEGV prima di eseguire un test: `XDEBUG_MODE=off php -d memory_limit=1G vendor/bin/pest
--no-coverage`. I test frontend vanno lanciati con `LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8`.
Il typecheck e `npx tsc -b --force` — `tsc --noEmit` in questo repo e un falso verde garantito.

### Stato e prossimo passo

Codice committato in `3c9e63c` (162 file, che include anche lavoro concorrente non correlato:
commissioni, categorie prodotto `requires_quote`, quotes company-sites, default country da env).
**Restano NON TRACCIATI due file di test**, entrambi nati dopo quel commit e da aggiungere se si
vogliono in cronologia:
- `frontend/src/features/navigation/icon-map.test.ts` (AC-115)
- `frontend/src/features/modules/module-routes.test.tsx` (AC-113)

Prossimo passo: AC-140 (verifica responsive su UI reale, unico criterio aperto) e il triage dei
due difetti preesistenti sopra. Suite frontend finale: 419 file / 2871 test verdi.

## FIX: ICONA MENU "MODALITA' DI PAGAMENTO" (2026-07-30) — VERDE, NON COMMITTATO

`config/navigation.php:400` dichiara `'icon' => 'credit-card'` per `payment-methods`, ma
`frontend/src/features/navigation/icon-map.ts` non aveva la chiave: `resolveIcon()` cadeva
sul fallback `Circle`, quindi la voce compariva col pallino neutro. Aggiunta la coppia
`'credit-card': CreditCard`. Verificato che era l'UNICO nome icona del config privo di
mapping (confronto tra i 30 valori `'icon' =>` di `navigation.php` e le chiavi della mappa):
chi aggiunge una voce di menu deve registrare l'icona in ENTRAMBI i posti, il fallback
silenzioso non segnala nulla.

Verifica: `tsc -b --force` EXIT=0, vitest `src/components src/features/navigation`
30 file / 274 test pass.

**Nota ambiente (tsc):** un `tsc -b --force` con `node_modules/.tmp/*.tsbuildinfo` stantio
puo' riportare errori fantasma su file non toccati (visti su `use-status-reorder.ts`,
inesistenti con `tsc -b tsconfig.app.json --force`). Se compaiono rossi in file fuori
scope, rilancia il build prima di inseguirli.

## CATEGORIE PRODOTTO: FLAG "PREVEDE PREVENTIVO" EREDITATO (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: nuovo campo booleano sulla categoria prodotto che dice se la categoria
prevede il preventivo; i figli lo EREDITANO dalla madre e nel loro form lo vedono in sola
lettura. Scelte confermate dall'utente prima di scrivere codice: **il flag e' del ROOT**
(solo una categoria senza madre lo scrive, ogni discendente porta il valore della radice —
non "antenato piu' vicino" come `business_function_id`), **default false** su tutte le
righe esistenti, **nessun effetto sul flusso Offerte** (solo il campo), visibile **anche
nel dettaglio e come colonna di tabella**.

### Invariante e chi la mantiene

Colonna `product_categories.requires_quote` (boolean, default false). Il valore e'
**DENORMALIZZATO su ogni riga**, non risolto con un walk a read time: cosi' griglia,
filtri, export e for-select leggono una colonna reale senza query per riga. Questo e'
sicuro solo finche' l'unico scrittore fuori dall'edit della radice e'
`App\Services\ProductCategories\RequiresQuoteInheritance` (106 righe, classe a se' perche'
`CategoryHierarchy` e' a 470 righe, vicino all'hard limit di 500):
`inheritedValueFor(?parentId)` = flag della radice del ramo, `sourceCategoryFor()` = quale
radice, `syncSubtree()` idempotente (non tocca le righe gia' allineate → niente UPDATE
inutili ne' rumore nell'activity log).

`ProductCategoryService::update()` chiama `syncSubtree()` sullo stesso trigger del cascade
business function: `hasParentId() || requiresQuoteSubmitted`. Il **bulk move** (spec 0063)
passa riga per riga da `update()`, quindi e' coperto senza codice dedicato.

### Guard 422 (e perche' NON e' nel field-permission ceiling)

`assertRequiresQuoteNotOverridden(?parentId, bool)` scatta solo se il valore inviato
**diverge** da quello ereditato: rieccheggiare il valore mostrato dal form e' un no-op
accettato. I ruoli di riferimento sono quelli SUBMITTED (parent inviato, non persistito).

`ProductCategoriesAuthorization` lascia il campo `visibleEditable` di proposito. Un
ceiling contestuale ("readonly se il model ha una madre") era stato scritto e poi
**rimosso**: il ceiling vede solo il model persistito, quindi avrebbe fatto 422 sul caso
legittimo "promuovo una figlia a radice E imposto il flag nello stesso salvataggio"
(payload `{parent_id: null, requires_quote: x}`). Stesso trattamento di
`business_function_id`: il guard del Service e' l'autorita'. C'e' un test dedicato.

### Contratto (additivo)

- `ProductCategoryResource`: `requires_quote` (bool, gia' EFFETTIVO su ogni riga).
- Il controller aggiunge in `resourceWithInherited()` — accanto a
  `effective_business_function` — `requires_quote_source_category`: `{id,name}` della
  radice, **null quando la categoria E' la radice** (= possiede il flag).
- Nodo di `GET /product-categories/tree`: nuova chiave `requires_quote`, gia' effettiva.
  Serve al form per risolvere live cosa erediterebbe da una madre candidata, senza
  richieste extra.
- Store/UpdateRequest: `requires_quote` `['sometimes','boolean']`.
- Tabella: colonna reale `requires_quote` (`type`/`filterType` `boolean`, sortable) —
  ora **8 colonne**, non 7.

### Frontend

- `requires-quote-inheritance.ts` (`resolveInheritedQuoteFlag`): walk fino alla RADICE
  sull'albero in cache, riusa `indexCategoryTree` di `business-function-inheritance.ts`.
- `ProductCategoryRequiresQuoteField`: `Switch` dentro `MetaField`, editabile finche'
  `parent_id` e' null; appena si seleziona una madre diventa disabled col valore della
  radice e l'hint che la nomina. Fallback sul `requires_quote_source_category` del
  dettaglio finche' l'albero non ha risolto (stesso schema del campo business function).
- **Regola dei payload builder: il flag viaggia SOLO con `parent_id === null`.** Su una
  figlia il diff non lo manda mai (sarebbe un override); il server riallinea da solo il
  sottoalbero spostato.
- Dettaglio: riga sola lettura con badge di provenienza. i18n it/en.
  **Attenzione:** l'hint ereditato usa un testo DIVERSO da quello del business function
  ("Ereditato dalla categoria radice X" / "Quoting is inherited from the root category X").
  Erano identici e i `getByText` dei test business function trovavano due nodi.

### Verifica eseguita

Backend `tests/Feature/ProductCategories/`: **132/133**. Run allargato
`ProductCategory|Product|Meta|FieldPermission|Role`: **789/791**. Nuovo file
`ProductCategoryRequiresQuoteTest.php` (15 test: ereditarieta' su create, cascata sul
sottoalbero, riparent, bulk move, promozione a radice, 422 override, dettaglio, riga
tabella). Frontend: **410 file / 2814 test pass**, `tsc -b --force` EXIT=0, eslint pulito.

Due test esistenti aggiornati perche' il requisito e' cambiato (dichiarato, non tampering):
`ProductCategoryTableTest` 7→8 colonne; l'asserzione "root: nessuno switch" in
`product-category-form-body.test.tsx` ora filtra per nome accessibile
(`{ name: 'Inherit from parent' }`), altrimenti contava anche il nuovo switch.

**Rossi PREESISTENTI non toccati:** `ProductCategorySecurityTest` e `ProductSecurityTest`
sul nodo di navigazione — asseriscono la sezione `configuration`, mentre
`config/navigation.php:272` colloca ormai `product-categories` sotto `products-group`
(idem `products`). Da triagare a parte.

**Nota ambiente:** i comandi Pest vanno lanciati con `XDEBUG_MODE=off`, altrimenti ogni
processo PHP stampa su stderr un warning Xdebug che sommerge l'output del test runner.

**Prossimo passo naturale (NON fatto, come deciso):** agganciare il flag al flusso
Offerte (es. rifiutare righe offerta su categorie non preventivabili) — e' una spec a se'.

## MODALITA' NAZIONALE: NAZIONE DI DEFAULT DA ENV (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: passare da app internazionale a nazionale, con l'Italia come default.
Deve essere "per ora" una impostazione di env: se attiva, quando il sistema mostra il
campo nazione pesca direttamente l'Italia. Scelte confermate dall'utente:
**prefill modificabile** (non bloccato, non nascosto), env **backend** esposta via
`/api/config`, **solo UI** (nessun default server-side su creazioni/import).

### Contratto (additivo, retro-compatibile)

`DEFAULT_COUNTRY_ISO2` (backend `.env`) → `config/geo.php` `default_country_iso2` →
`GET /api/config` ora restituisce `data.localization.default_country_iso2`
(uppercase, oppure `null` = modalita' internazionale). E' un **codice ISO 3166-1
alpha-2, mai un id DB**: gli id delle countries differiscono per ambiente (in locale
Italia e' id 107), quindi il client risolve il codice contro la lista countries.
`ConfigService::localization()` normalizza (trim + uppercase, vuoto → null).
`.env` locale e `.env.example` gia' valorizzati a `IT`.
**Se la config e' cachata serve `php artisan config:clear`.**

### Frontend — un solo punto di innesto

Il campo nazione esiste **solo** in `features/geo/geo-select.tsx` (7 call site: companies,
address personal-data, operational-sites, projects, campaigns, import wizard), quindi il
default vive tutto lì. Nuovo hook `features/geo/use-default-country.ts`
(`useDefaultCountryId()`): legge `useConfig()` + `useCountries()` (stessa query key →
zero richieste extra) e risolve ISO2 → id, `null` se internazionale/non caricato/codice
sconosciuto. `AppConfig` in `features/config/types.ts` ha la nuova sezione
`localization: AppLocalization` (required).

**Guardia anti-perdita-dati (importante):** il seeding in `GeoSelect` scatta SOLO se la
cascata e' *pristine* — tutti e quattro i livelli `null` — e mai se `disabled` o se
`country` e' in `lockedLevels`. Un valore parziale (paese sconosciuto ma citta' gia'
risolta, tipico delle righe di import; o scope ereditato da un'entita' collegata) non
viene mai riscritto, perche' impostare la nazione azzera i discendenti.

**Effetto collaterale noto (accettato):** in projects/campaigns il bridge scrive con
`shouldDirty: true`, quindi il form si apre `isDirty`. Nessun componente con `GeoSelect`
gatea il Salva su `isDirty` (l'unico che lo fa e' request-management, che non usa
`GeoSelect`), quindi nessun impatto funzionale oggi.

### Verifica eseguita

Backend: `pest --filter="Config|Geo"` → **246 pass**; `ConfigTest` da 18 a 22 test
(uppercase/normalizzazione, null in modalita' internazionale, binding su env).
`pint --dirty` pulito. End-to-end su `.env` reale: `bootstrap(null)["localization"]` →
`{"default_country_iso2":"IT"}`; `Country::where("iso2","IT")` risolve.
Frontend: **409 file / 2802 test pass**, `tsc -b --force` EXIT=0, eslint pulito.
Nuovo `use-default-country.test.ts` (5 test) + blocco `national mode` in
`geo-select.test.tsx` (8 test: prefill, seeding una volta sola, modalita'
internazionale, nessun overwrite, nessun seeding su parziale/disabled/locked).

I 4 test rossi transitori di `review-geo-editor.test.tsx` ("No QueryClient set") erano
causati dalla nuova dipendenza da `useConfig` dentro `GeoSelect`: risolti stubbando
`useDefaultCountryId` nei 4 file che renderizzano il `GeoSelect` reale
(review-geo-editor + i 3 company-form), coerentemente col mock di `use-geo` che quei
file avevano già. Nessuna asserzione preesistente modificata.

### Segnalazione fuori scope (non implementata)

`geo-select.tsx` e' passato da 301 a 330 righe: era **gia'** oltre il soft limit di 300
prima di questa modifica. Split naturale disponibile: estrarre il sub-componente privato
`GeoField` in `features/geo/geo-field.tsx` (~110 righe) lasciando `geo-select.tsx` a ~230.
Non eseguito per non allargare il blast radius su un file condiviso da 7 call site.

## OFFERTE: SOCIETA', SOCIETA' SEDE, SEDE OPERATIVA (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: aggiungere all'Offerta Societa' aziendali, Societa' Sedi e Sedi
operative, e le stesse tre colonne nella tabella AG Grid. Decisioni prese con l'utente:
FK proprie sull'offerta con la sede operativa EREDITATA dall'Opportunita'; tutte e tre
opzionali con cascata Societa' -> Societa' Sede; colonne appese IN CODA al catalogo.

### Schema

Migrazione `2026_07_30_120000_add_company_and_sites_to_quotes_table.php`:
`company_id`/`company_site_id`/`operational_site_id` su `quotes`, nullable,
**nullOnDelete** (deviazione voluta dai restrictOnDelete del resto della tabella: stessa
ragione di spec 0056 BR-3 per l'Opportunita' — togliere una societa'/sede dal catalogo
non deve bloccare ne' cancellare un'offerta). Nessun `index()` esplicito: `constrained()`
crea gia' la FK e il suo indice.

### Ereditarieta' e guardia

`QuoteService::applySnapshotDefaults()` ora tratta `operational_site_id` come i 3 ruoli
D-3: chiave assente nel payload -> valore corrente dell'Opportunita'; valore inviato
(anche null) -> vince. `company_id`/`company_site_id` NON sono ereditabili (l'Opportunita'
non ha quelle colonne, rimosse dalla direttiva 2026-07-17).

`App\Http\Requests\Concerns\ValidatesQuoteCompanySite` (nuovo trait, usato da Store e
Update): il `company_site_id` deve appartenere al `company_id` EFFETTIVO (submitted se
la chiave c'e', altrimenti persistito) -> altrimenti 422 su `company_site_id`
(`lang/{it,en}/quotes.php` -> `company_site_mismatch`). La cascata nel form e' solo
un'affordance: la regola vive server-side (security.md §1).

### Contratto (additivo)

`QuoteResource` espone `company_id`/`company` e `company_site_id`/`company_site` come
ref `{id, name}` — per la Societa' il `name` E' `denomination` (`companies` non ha
`name`) — e `operational_site_id`/`operational_site` come `{id, label}` via
`OperationalSiteLabel` (la sede non ha nome: e' il suo indirizzo primario).

`OpportunityForSelectResource::meta` guadagna `operational_site` ({id,label}) accanto ai
3 ruoli, cosi' il form Offerta precompila la sede senza una seconda fetch
(`OpportunityService::forSelectBaseQuery` eager-loada `operationalSite.addresses.city`).

### Tabella AG Grid

`QuoteColumnCatalog`: 3 colonne derivate appese DOPO `created_at` (appese in coda di
proposito: inserirle in mezzo sposterebbe il layout colonne persistito per utente, spec
0001). `company`/`company_site` entrano in `QuoteRelationColumns`, che ora supporta una
`label` column per relazione (default `name`, `denomination` per `companies`);
`operational_site` e' delegata alla `OperationalSiteColumn` condivisa in
`QuotesTableDefinition` (filtro/sort/distinct su `line1` dell'indirizzo primario),
esattamente come su Opportunita'. Advanced filter NON aggiunti (fuori richiesta).

### Frontend

Nuovi file: `quote-sites-section.tsx` (i 3 select, estratti perche' `quote-form-body.tsx`
era gia' a 306 righe) + il suo test. La sede operativa si precompila dal `meta`
dell'Opportunita' scelta; il picker Societa' Sede e' `forceDisabled` finche' non c'e' una
Societa' e si scoping via `params={{ company_id }}`, azzerandosi quando la Societa'
cambia. Aggiunti anche i 3 campi al `quote-detail.tsx` e i renderer di colonna.

### Verifica eseguita

Frontend: `tsc -b --force` EXIT=0, eslint pulito, **408 file / 2789 test pass** (con
`LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8`, vedi nota ambiente sotto). Backend: Pint passed,
suite COMPLETA **4318 test / 4303 pass / 14 failed**, con il set dei 14 rossi
BYTE-IDENTICO a quello della stessa suite su HEAD pulito (baseline 4288/4273/14, misurata
con `git stash`): zero regressioni introdotte. `--filter="Quote|Opportunity"` 595 pass, nuovo file
`tests/Feature/Quotes/QuoteCompanySitesTest.php` (11 test: persistenza, ereditarieta',
submitted-vince, no re-sync, 422 sede estranea, 422 sede senza societa', PATCH contro la
societa' effettiva, azzeramento, proiezione riga, sort/filtro, distinct values).

**Due test aggiornati per requisito cambiato** (non tampering): `QuoteAuthorizationTest`
AC-064 (elenco campi `permissions.fields`) e `QuoteTableTest` AC-069c (elenco id
colonne) congelavano liste ora piu' lunghe di 3 voci.

**Rossi PREESISTENTI confermati su HEAD** (14, verificati con `git stash` + diff dei nomi):
gli 11 test "navigation node" (`*SecurityTest`/`*PermissionsTest` di attributes, companies,
company-sites, custom-fields, operational-sites, product-categories, products,
referent-types, referents, reward-types, vat-rates), `CustomFieldWritePipelineTest`
(PATCH partial merge), `RequestManagementTableSearchTest` (lunghezza termine) e il test
`page/per_page -> offset/limit` della migrazione. Nessuno toccato da questa modifica; da
triagare a parte.

## COMMISSIONI OFFERTE: DESTINATARIO BLOCCATO (2026-07-30) — VERDE, NON COMMITTATO

Richiesta utente: nel popup commissioni di un'Offerta il destinatario non si sceglie —
e' sempre chi e' stato selezionato a monte per quel ruolo; se a monte non c'e' nessuno,
quel ruolo non deve essere selezionabile. Piu' la resa grafica: le card del popup erano
dello stesso colore dello sfondo.

### Regola (unica, condivisa da 3 consumatori)

`App\Services\Commissions\CommissionRecipientResolver` — estratto da
`QuoteCommissionInitializer`, e' l'UNICA fonte di verita' su chi puo' ricevere una
commissione: `COMMERCIAL`→`quote.commercial_id` (referent), `REPORTER`→`reporter_id`
(referent), `SUPERVISOR`→`supervisor_id` (user), `SUPPLIER`→`product.supplier_id`
(registry). Ruolo senza selezione a monte = `null` = non commissionabile.
Lo usano: l'initializer dei defaults, il nuovo endpoint recipients, la validazione write.

**Precedenza invertita in `QuoteCommissionInitializer`**: prima era
`$quote?->commercial_id ?? $data->commercialId` (il persistito vinceva). Ora vince il
SUBMITTED (`$data->commercialId ?? $quote?->commercial_id`): il chiamante e' un form
aperto i cui ruoli possono essere gia' cambiati e non ancora salvati — con la vecchia
precedenza i defaults risolvevano il commerciale STALE. `QuoteLineCommissionWriter::defaults()`
passa null su tutti e tre, quindi per lui non cambia nulla.

### Contratto nuovo (additivo)

`POST /api/quotes/commission-recipients` → `data` = oggetto chiavato per ruolo, TUTTI e 4
sempre presenti, valore `{type, id, name}` oppure `null`. Stessi input di
`commission-defaults` meno `line_net_amount`/`reference_date`. Authz identica salvo che
`commissions`/`commission_recipient` bastano VISIBLE (non editable): legge identita', non scrive.
Documentato in `docs/api/0006-commission-configurator-and-quote-integration.md`.

### Enforcement server-side (non e' solo UI)

`ValidatesQuoteLineCommissions::enforceCommissionRecipients()`, agganciato in `withValidator`
di Store/UpdateQuoteRequest. Ruolo senza destinatario → 422 su
`offer_lines.{i}.commissions.{j}.recipient_role`; destinatario diverso da quello risolto →
422 su `...recipient_id`. I ruoli di riferimento sono quelli SUBMITTED nella stessa request,
con fallback sul persistito solo per le chiavi assenti (`effectiveRoleId`).

**Split di file:** `ValidatesQuoteLines` aveva superato 300 righe → i due guard commissioni
sono usciti in `App\Http\Requests\Concerns\ValidatesQuoteLineCommissions` (256 righe); il
trait originale resta a 74 e tiene solo le regole di FORMA delle righe. Entrambe le request
usano ora i due trait.

### Frontend

- `QuoteCommissionsDialog`: niente piu' `AsyncPaginatedSelect` sul destinatario — e' un
  `<output>` in sola lettura. Nuove prop `productId` e `commissionContext`; risolve il lock
  con `useQuery` su `fetchQuoteCommissionRecipients` (staleTime 60s), **disabilitata quando
  `disabled`** (il dettaglio read-only non chiama l'endpoint, che pretende create/update).
  Ruolo `null` → nessun bottone "Aggiungi", messaggio `roleUnavailable`. Commissione gia'
  presente su un ruolo che ha perso il titolare → nota `recipientStale` con `role="alert"` e
  **Salva disabilitato** (sarebbe un 422 garantito).
- `quote-lines-field.tsx`: l'effetto sul cambio ruolo ora fa `Promise.all([recipients, defaults])`
  e **riscrive** il destinatario delle commissioni gia' presenti (prima le teneva col
  destinatario vecchio → con il nuovo guard sarebbe stato un 422); quelle il cui ruolo ha
  perso il titolare vengono droppate.
- Prop threading: `QuoteLinesField` → `QuoteLineRow` (nuova prop `commissionContext`) → dialog.
  Tipo `QuoteCommissionContext` centralizzato in `features/quotes/types.ts` (era inline).
- Superfici (ui-design.md): `DialogContent` resta rung 1 `bg-background`; header/footer del
  dialog salgono a `bg-surface` (rung 2); **le card di ruolo sono `bg-card`** (rung 3, bianche).

### Verifica eseguita

Backend `--filter="Quote|Commission"`: 187 pass. Nuovo file
`tests/Feature/Quotes/QuoteCommissionRecipientLockTest.php` (6 test: endpoint, precedenza
submitted-vince, 403, 422 destinatario estraneo, 422 ruolo senza titolare, PATCH validato
contro i ruoli della stessa request). Frontend: 407 file / 2786 test pass,
`tsc -b --force` EXIT=0, eslint pulito.

**ATTENZIONE ambiente:** i test frontend vanno lanciati con `LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8`.
Le asserzioni sugli importi usano `toLocaleString(undefined)` e su macchina it-IT rendono
`20,00` invece di `20.00` (sensibilita' preesistente, non introdotta qui).

**Rossi PREESISTENTI** nella suite backend completa, non toccati da questa modifica e rossi
anche in isolamento su HEAD: 14 test (nodi di navigazione `*SecurityTest`,
`AbstractMigrationSourcePreviewTest` per una colonna `description` in piu',
`CustomFieldWritePipelineTest` su `vat_number`, `RequestManagementTableSearchTest` sulla
lunghezza del termine). Da triagare a parte.

## SPEC 0067 — PANNELLO OFFERTE NELLA VIEW OPPORTUNITA' (2026-07-29) — VERDE, NON COMMITTATO

Spec: `docs/specs/0067-opportunity-quotes-panel.xml` (47 criteri). Verifier indipendente:
**46 PASS con evidenza, 1 NON VERIFICATO (AC-035**, responsive 375/768/1024: serve un
rendering reale, non simulabile in questo ambiente — resta l'unico criterio aperto).
Zero test tampering, zero migrazioni, zero nuove dipendenze. `tsc -b --force` EXIT=0.

AC-062 ("decrementa il contatore") era inizialmente PASS solo per costruzione: il test
mockava `TableView` e lo spy di refresh non rifaceva scattare `onRowCountChanged`. Chiuso
con un test che, dopo il refresh, fa riemettere al mock il nuovo totale e asserisce il badge.
Nota sulla cucitura: il test verifica che il PANNELLO reagisca al nuovo totale, non che il
refresh ne provochi la riemissione — quel secondo anello e' comportamento di `TableView`
(purge + refetch della cache SSRM) e sta nei test di `table-view.test.tsx`.

### Il modulo Offerte NON e' stato ricreato

La spec 0065 era gia' implementata per intero. `quotes.opportunity_id` esisteva gia' NOT NULL,
FK `restrictOnDelete`, indicizzato; `opportunity_id` era gia' immutabile in update (`prohibited`
in `UpdateQuoteRequest`) e readonly nel ceiling di `QuotesAuthorization`. Il lavoro reale era
un altro: **non esisteva alcun modo di scopare una tabella backend-driven su un record padre.**

### Capacita' nuova: scoping tabellare (riusabile)

`App\Tables\Quotes\OpportunityScopedTableDefinition` — decorator sul modello di
`AttributeScopedTableDefinition` (spec 0064) ma molto piu' piccolo: **tocca SOLO `baseQuery()`**
(`where('quotes.opportunity_id', ?)`), passthrough puro quando lo scope e' null. Colonne,
allow-list sort/filter/search e catalogo azioni sono identici scopati o no, quindi NON esiste
un equivalente di `scopeToAllProductCategories()` e gli endpoint `preferences`/`filters`
restano intatti. Composto in `TableRegistry::wrapIfOpportunityScoped()` dopo
`wrapIfAttributeScoped()`.

Setter: `scopeToOpportunity(?int $opportunityId): void`.

Parametro: `opportunityId` (body) in `TableRowsRequest`/`TableValuesRequest`, `opportunity_id`
(query) in `TableColumnsRequest`, applicato da `TableController` in `columns()`/`rows()`/
`values()` e **non** negli endpoint di persistenza. No-op per ogni altro domain.

**DECISIONE D-1 da rispettare:** un solo parametro ad-hoc, NON un'astrazione generica di scope
multi-chiave. E' il secondo caso nel codebase (dopo `productCategoryId`). **Al terzo dominio
che richiede scoping si generalizza, non prima.**

### Export scopato — il punto che si sbaglia in silenzio

L'export e' ASINCRONO: `ExportController::store()` congela lo state in `ExportRun` e il job
ri-risolve la definition da zero in `ExportService::generate()`. Applicare lo scope solo nel
controller passa i test in sincrono e produce un file sbagliato in produzione. Quindi
`opportunityId` e' persistito nello state (`export_runs.state` e' JSON: nessuna migrazione) e
riapplicato nel job prima di `TableQueryBuilder::build()`. Il test legge il CONTENUTO del CSV,
non lo state.

### Altri contratti congelati

- `GET /api/opportunities/{opportunity}` espone `quotes_count: int`, sempre presente, via
  `loadCount('quotes')` in `OpportunityService::loadDetail()`. Serve come valore iniziale del
  contatore **e** come discriminante dei due stati vuoti (vedi sotto). `Opportunity::quotes()`
  HasMany aggiunta (prima il rovescio della relazione esisteva solo a livello DB).
- `TableView` ha due prop additive: `rowScope?: TableRowScope` (`{opportunityId?: number}`) e
  `onRowCountChanged?: (count: number|null) => void`. **`rowScope` non e' `scope`**: `scope`
  seleziona una FORMA di config ed entra in `tableKeys.config`; `rowScope` seleziona un INSIEME
  DI RIGHE e non entra in alcuna query key. Va letto come primitiva, non per identita' oggetto.
- `useModuleOpener` accetta `forceMode?: OpenMode`: il pannello forza `OPEN_MODE_MODAL` cosi'
  view/edit/create restano in Sheet sopra l'Opportunita' anche con la preferenza utente su
  pagina (D-3). Dalla pagina lista Offerte la preferenza resta rispettata.
- `QuoteFormMode` ha ora `{ type: 'create'; params?: ModuleCreateParams }`; il pannello passa
  `openCreateWith({ opportunity_id })` (meccanismo spec 0045 riusato, non nuovo).

### Preferenze CONDIVISE — scelta esplicita (D-4)

Colonne, filtri persistiti e viste salvate restano chiavate `(user_id, domain)`: il pannello e
la pagina Offerte **condividono** la configurazione. Nascondere una colonna nel pannello la
nasconde anche nella pagina. Accettato per non migrare tre tabelle del framework tabellare.

### Divergenza dichiarata e accettata (D-9)

Il gate stato-vuoto/griglia e' un booleano seminato al mount da `quotes_count`
(`use-opportunity-quotes-panel.ts`), non rivalutato sul totale live. Quindi
**delete-to-zero mostra l'overlay di AG Grid invece del box CTA**. AC-032 resta PASS alla
lettera (i suoi due rami sono "nessuna Offerta" e "filtro attivo con zero risultati"), ma D-9
parla di condizione live: la divergenza e' reale, non coperta da alcun AC, accettata perche'
distinguere "0 perche' vuoto" da "0 perche' filtrato" richiederebbe un segnale che `TableView`
non espone. Il bottone Crea Offerta resta comunque nell'header.

`quotes_count?: number` e' opzionale nel tipo FE (compatibilita' fixture) ma la chiave e'
SEMPRE presente nella response; il consumo ha fallback `?? 0`.

### Scostamento da D-8: guard 409 su OpportunityService::delete()

Cancellare un'Opportunita' con Offerte dava un **500** (`QueryException` da `restrictOnDelete`
non gestita). Aggiunto guard esplicito `abort(409)` sul modello di `RegistryService::delete()`.
Fuori dal perimetro dichiarato (D-8 parlava di `QuoteService::delete()`), accettato dall'utente.
Lato UI nessun cambiamento visibile: `opportunities-table.tsx:78` intercetta solo il 403, quindi
sia 500 sia 409 finivano nello stesso toast generico.

## TRAPPOLA DI VERIFICA — `tsc --noEmit` NEL FRONTEND E' UN FALSO VERDE

`frontend/tsconfig.json` e' solution-style (`"files": []` + `"references"`): **senza `-b` tsc
non ha file da controllare e restituisce sempre EXIT=0**, qualunque errore ci sia. Verificato
il 2026-07-29 confrontando i due comandi sullo stesso albero: `--noEmit` EXIT=0 mentre l'hook
Stop segnalava errori TS6133 reali.

Il comando valido, quello dell'hook `.claude/hooks/typecheck.sh`:

```
cd frontend && npx tsc -b --force --pretty false
```

**`CLAUDE.md §5`, la `§CHECKLIST PRE-RISPOSTA` e `rules/frontend.md §10` prescrivono ancora
`tsc --noEmit`: la regola scritta e' non verificabile in questo repo.** Chi la segue alla
lettera dichiara "typecheck pulito" senza aver controllato nulla — e' successo a piu' teammate
in buona fede in questa sessione. Correzione di quelle tre righe da decidere con l'utente.

Corollario per il lavoro multi-agent: **mai `pint --dirty` in un albero condiviso** (riformatta
i file WIP di altri); sempre l'elenco esplicito dei file. E mai `git stash` con altri agent che
scrivono.

## ATTRIBUZIONE DEI ROSSI (2026-07-29) — albero condiviso 0067 + 0066

Le due spec hanno convissuto nello stesso working tree. Tre teammate hanno etichettato dei
rossi come "preesistenti" usando `git stash` dei SOLI propri file — metodo valido per dire
"non causato da me", NON per dire "preesistente", perche' la baseline conteneva ancora l'altra
feature. Attribuzione accertata:

**TUTTI RISOLTI il 2026-07-30** — vedi la voce in testa al file: la suite e' interamente verde su
entrambi gli stack. Tabella conservata perche' documenta il metodo di attribuzione sbagliato, non
perche' ci siano rossi aperti.

| Test | Attribuzione | Esito |
|---|---|---|
| `RequestManagementTableSearchTest` over-length | storico | RISOLTO — cap alzato a 255 di proposito, test fermo a 100 |
| `quote-costs-tab.test.tsx` | 0066 (`useConfirm` in `quote-lines-field.tsx` senza provider) | RISOLTO |
| `cell-renderers.test.tsx` (3) | storico | RISOLTO |
| `CustomFieldAdminSecurityTest:41` | attribuito a 0066, **sbagliato**: era l'helper di navigazione inerte | RISOLTO |
| `FieldCatalogueEndpointTest:80` | 0066 (entry in `config/authorization.php`) | RISOLTO |
| `CustomFieldWritePipelineTest` (422 vat_number) | ne' 0066 ne' 0067, origine terza | RISOLTO — VAT ora validata davvero, fixture `IT999` non valida |
| `MetaEndpointTest` permissions_resource | inquinamento d'ordine fra test | RISOLTO |
| `commission-configuration-detail.test.tsx`, `quote-commissions-dialog.test.tsx` | attribuito a 0066, **sbagliato**: locale ambientale nel codice, non nei test | RISOLTO |

**Lezione, oltre al `git stash`:** meta' di queste attribuzioni erano errate perche' fatte per
prossimita' (il rosso e' comparso mentre lavoravo su X, quindi e' di X). Le cause vere erano due
difetti trasversali — un helper di test inerte e una formattazione dipendente dal locale di
sistema — che non appartenevano a nessuna delle spec sospettate.

## PROSSIMI PASSI

1. **Commit non fatto** (§3.6: serve via libera esplicito). La contaminazione 0066/0067 e'
   limitata a DUE file: `features/quotes/types.ts` e `features/quotes/use-quote-form.ts`. Gli
   altri 25 file della 0067 sono puliti. Non si possono escludere quei due (senza il ramo
   `params` di `QuoteFormMode` il pannello non compila) e lo staging interattivo non e'
   disponibile: la via pulita e' **far committare prima la 0066, poi la 0067 sopra**.
2. AC-035 (responsive 375/768/1024) resta da verificare visivamente sull'app reale.
3. ~~I rossi 0066 vanno girati a chi possiede quella feature.~~ Fatto il 2026-07-30: erano 5 cause
   trasversali, nessuna di 0066. Suite interamente verde, vedi la voce in testa al file.


---

## Archivio storico

Le voci precedenti al 2026-07-29 sono in `docs/handoff-archive/2026-07.md` (indice in testa al file).
NON leggere quel file per intero: e grande. Cercaci dentro con grep sul titolo della voce.

Regola di manutenzione: quando questo file supera ~50 KB, spostare le voci piu vecchie
nell archivio invece di lasciarlo crescere.
