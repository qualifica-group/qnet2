# HANDOFF — living project memory

> Injected at session start. Update at every green state.
> Tenere questo file sotto ~50 KB: le voci vecchie vanno in `docs/handoff-archive/`, non cancellate.

## MODULO TASK — SPLIT SERVIZI + SPEC 0123 IN BOZZA — VERDE, NON COMMITTATO (2026-09-14)

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

**Spec 0123 (bozza, in attesa di approvazione)** `docs/specs/0123-task-completion-time-entry-and-subtask-coherence.xml`:
segnatempo obbligatorio e atomico su `/complete` (completare concede l'inserimento, niente
`time-entries.create`); PATCH verso `in_validation`/`closed_positive` rifiutato per tutti, opzioni
disabilitate nel form; padre non completabile con figli diretti aperti; date del figlio dentro il
padre (padre che restringe -> 422); cascata solo strutturale del write lock; collegamenti del padre
precompilati lato client. Blocca/Sblocca invariato (vale la matrice). Prossimo passo: approvazione,
poi `/build-feature` sul piano MT-B1..B6, MT-U1, MT-F1..F3, MT-V.

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

