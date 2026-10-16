# Modulo HR — documento di prodotto

> Stato: bozza da seguire per lo sviluppo, 2026-10-16.
> Fonte: le due pagine "Il flusso di WeA HR" e "Le tabelle di WeA HR" (misure del 24/09/2026), confrontate con
> quanto esiste oggi in qnet-2 e con i dati del QNet legacy.
> Il documento elenca **informazioni (campi) e regole**, non tabelle: dove integrare ogni campo (tabella esistente o
> nuova) si decide area per area, nella spec di sviluppo. La colonna "Oggi in qnet-2" serve a quella decisione.
> I campi segnati *proposta* non sono descritti a livello di colonna nella fonte: vanno confermati.

## 1. Decisioni di prodotto

| # | Decisione | Conseguenza |
|---|---|---|
| D-1 | I dati si importano **sempre** dal framework di migrazione (`backend/app/Migrations/`) | Ogni area ha il suo importer; il legacy invia i dati già pronti (`.claude/rules/backend.md §9`). Un campo che il legacy non ha nasce vuoto e si compila in app |
| D-2 | Non esiste una persona senza account | Il dipendente **è** l'utente: tutti i campi HR si appoggiano all'utente e al suo profilo d'impiego |
| D-3 | Niente accesso unico (SSO) | Fuori scope |
| D-4 | Le assenze sono rinviate ma **obbligatorie** | Vedi §11: requisito di prodotto da sviluppare in una fase successiva |
| D-5 | Il segnatempo resta quello esistente (`time_entries`) | HR lo legge, non lo rifà |
| D-6 | **Mansione e ruolo di permessi sono la stessa cosa** | La mansione è il ruolo esistente (Spatie), arricchito dei dati organizzativi (§5). Assegnare una mansione a una persona le dà anche i permessi di quel ruolo |
| D-7 | Il Controllo di Gestione è fuori da questo documento | Nessuna matrice di attribuzione, chiusura mensile o export verso il CdG per ora |

## 2. Regole che valgono su tutto il modulo

1. **Ogni persona attiva ha almeno una funzione aziendale (BU o BS).** Non si può attivare una persona senza funzione,
   né togliere l'ultima funzione a una persona attiva: l'operazione fallisce con un messaggio chiaro.
2. **La struttura viene prima delle persone.** Una persona senza posizione non si può allocare, né costare, e non
   compare nell'organigramma.
3. **Il contratto ha una storia.** Un cambio di retribuzione o inquadramento crea una nuova condizione con data di
   decorrenza; quella precedente resta. I costi di un mese passato usano la condizione valida in quel mese.
4. **Le allocazioni di una persona in un periodo fanno 100%.** Un totale diverso blocca il salvataggio: non deve
   esistere costo che finisce "da nessuna parte".
5. **Ogni modifica è tracciata:** chi, che cosa, quando, da quale valore a quale (activity log su ogni nuovo dato).
6. **Visibilità a tre livelli:** la persona vede sé stessa, il responsabile la propria squadra, l'ufficio del
   personale vede tutto (stesso schema già usato da segnatempo e attività).
7. **Dati sensibili** (retribuzione, IBAN) visibili e modificabili solo con permesso dedicato; l'IBAN è cifrato.
8. **Un catalogo vuoto esiste comunque.** CCNL, livelli e metodi di pagamento nascono anche senza dati, con la
   schermata che li compila.

## 3. Scheda del dipendente

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Nome, cognome | anagrafica | presente (`personal_data`) |
| Codice fiscale, data e comune di nascita, residenza, genere | anagrafica | presente (`personal_data`) |
| Email di accesso | login | presente (`users`) |
| Recapiti personali | telefono, email personale | presente (`contacts`) |
| Recapiti di lavoro | telefono e caselle aziendali della persona | **da aggiungere**: oggi non si distingue un recapito di lavoro da uno personale |
| Società | per conto di quale società del gruppo lavora | presente (`employment_profiles.company_id`) |
| Tipo di rapporto | dipendente, autonomo, altro | presente (`relationship_type`) |
| Inquadramento | qualifica | presente come elenco fisso (`qualification_type`); **da collegare** a CCNL e livello (§4) |
| Data di assunzione, data di cessazione | ingresso e uscita | presente (`hired_at`, `terminated_at`) |
| Responsabili | a chi risponde (anche più di uno) | presente (`employment_profile_manager`) |
| È responsabile | se guida una squadra | presente (`is_manager`) |
| Sede principale, sedi remote | dove lavora | presente (`employment_profile_operational_site`) |
| Orario standard e pausa giornaliera | minuti | presente |
| Funzioni aziendali (BU/BS) | appartenenza, obbligatoria (regola 1) | presente (`business_function_user`); **vincolo da aggiungere** |
| Posizione | il posto organizzativo che occupa | **da aggiungere** (§5) |
| Mansioni | che cosa fa: i ruoli assegnati alla persona (D-6) | presente come assegnazione dei ruoli; `job_description` resta testo libero |
| Attività escluse | attività di una sua mansione che quella persona non svolge | **da aggiungere** |
| Stato attivo / cessato | chi è uscito si conserva, non si cancella | presente (`is_active`, `terminated_at`); **manca** una vista dei cessati |

## 4. Contratto, CCNL e pagamento

### 4.1 Condizioni contrattuali (storico)

Una riga per ogni cambio, mai sovrascritta.

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Decorrenza (dal) | da quando vale | **da aggiungere** |
| Fine (al) | fino a quando, vuoto se vigente | **da aggiungere** |
| Tipo di rapporto | come in §3, ma storicizzato | oggi solo il valore corrente |
| CCNL e livello | inquadramento contrattuale | **da aggiungere** |
| Retribuzione | importo e periodicità (*proposta*: lorda annua) | **da aggiungere** |
| Società | società del contratto in quel periodo | oggi solo il valore corrente |
| Motivo del cambio | *proposta*: nota libera | **da aggiungere** |

### 4.2 CCNL e livelli

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| CCNL: nome, codice | contratto collettivo applicato | **da aggiungere** |
| Livello: CCNL, codice, descrizione, ordine | livelli dentro ogni CCNL | **da aggiungere** |

### 4.3 Metodo di pagamento

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| IBAN | conto su cui si paga la persona (cifrato) | **da aggiungere**: oggi l'IBAN esiste solo per le banche delle società |
| Intestatario | se diverso dalla persona | **da aggiungere** |
| Valido dal | *proposta*: per tenere lo storico dei conti | **da aggiungere** |

## 5. Struttura organizzativa

La scala è Divisione → Funzione aziendale → Posizione → Mansione → Attività; sedi e società stanno accanto.

| Elemento | Campi | Oggi in qnet-2 |
|---|---|---|
| Divisione | nome; società in cui esiste | **da aggiungere** |
| Funzione aziendale | nome, BU o BS, funzione padre, responsabile | presente (`business_functions`) |
| | divisione di appartenenza; società in cui esiste | **da aggiungere** |
| Posizione | nome, funzione di appartenenza, descrizione (*proposta*) — il posto, non la persona | **da aggiungere** |
| Mansione | è il ruolo di permessi (D-6): nome presente; **da aggiungere** descrizione, posizioni in cui sta, attività, competenze richieste (§8) | presente come ruolo (seeder `QualificaRoleSeeder`); mancano i dati organizzativi |
| Attività | nome, mansione di appartenenza | **da aggiungere** (`task_types` è il tipo attività del segnatempo, non la stessa cosa) |
| Reparto | nome | **da aggiungere** (*da confermare* se serve accanto a divisione e funzione) |
| Sede | nome, indirizzo, attiva | presente (`operational_sites`) |
| Società | denominazione, partita IVA, sedi | presente (`companies`, `company_sites`) |

Legami da gestire: posizione ↔ mansioni, mansione ↔ attività, persona ↔ mansioni (presente: assegnazione dei ruoli),
persona ↔ funzione (presente), funzione ↔ società, divisione ↔ società, eccezioni persona ↔ attività esclusa.
Da definire nella spec: se assegnare una posizione a una persona le assegna anche le mansioni (quindi i permessi)
di quella posizione, o se le mansioni restano assegnate a mano.

## 6. Organigramma

La stessa struttura vista dall'alto, non un disegno separato.

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Unità | riquadri dell'organigramma (*proposta*: ricavati da divisioni, funzioni, posizioni) | **da aggiungere**; oggi esiste solo l'albero dei responsabili nel team del segnatempo |
| Ruolo nell'unità | i ruoli dentro ogni riquadro | **da aggiungere** |
| Assegnazione | chi occupa quale ruolo | **da aggiungere** |
| Registro spostamenti | chi ha spostato cosa | coperto dall'activity log (regola 5) |

## 7. Allocazioni e costo del personale

| Elemento | Campi | Oggi in qnet-2 |
|---|---|---|
| Allocazione | persona, periodo (mese/anno), destinazione (funzione, commessa o progetto interno), percentuale; somma 100 (regola 4) | **da aggiungere**. Il legacy ha `user_allocazioni` (persona, società, categoria analitica, anno, % per mese): fonte dell'importer |
| Progetto interno | nome, funzione, date di inizio e fine (*proposta*) | **da aggiungere** (il modulo `projects` esistente è marketing, non questo) |
| Commessa | destinazione dell'allocazione | presente (`work_orders`) |
| Costo mensile per persona | persona, mese, costo pieno e sue componenti dal foglio paghe | **da aggiungere**; componenti da definire sul tracciato reale del foglio paghe |
| Voce extra del mese | persona, mese, tipo voce, importo, nota — costi che non stanno in busta paga | **da aggiungere** |
| Registro dei caricamenti | file, mese, righe importate e scartate, chi, quando | **da aggiungere** |
| Imputazione | quanto costo di ogni persona va a ogni funzione, commessa o progetto nel mese (calcolato da allocazione × costo) | **da aggiungere** |

## 8. Documenti, formazione, competenze, obiettivi, onboarding

Stesso modello per tutti: un **catalogo** e le **assegnazioni** alle persone. Oggi in qnet-2 nessuno di questi esiste come
concetto HR (esistono solo allegati generici e la mail di benvenuto).

| Area | Catalogo | Assegnazione alla persona |
|---|---|---|
| Documenti | titolo, tipo, file, versione, obbligatorio (*proposta*) | persona, documento, data assegnazione, scadenza, data firma, stato |
| Corsi | titolo, durata in ore, obbligatorio, validità (*proposta*) | persona, corso, scadenza, data completamento, esito |
| Competenze | nome, categoria (*proposta*) | persona ↔ competenza con livello e data; mansione ↔ competenza richiesta con livello minimo |
| Obiettivi (KPI) | nome, unità di misura, target, periodicità (*proposta*) | persona, periodo, valore raggiunto, target, nota |
| Onboarding | modello: nome, a quale mansione o posizione si applica; passi: titolo, ordine, responsabile, giorni dall'ingresso, tipo (documento, corso, attività) | percorso della persona: modello, data inizio, stato; per ogni passo: stato, completato il, da chi |

## 9. Il quotidiano della persona e del responsabile

| Chi | Che cosa vede o fa |
|---|---|
| Persona | il proprio profilo; firma dei documenti assegnati; propri obiettivi, competenze, formazione; il proprio segnatempo in lettura; notifiche |
| Responsabile | la propria squadra, gli obiettivi del team; (con §11) approvazioni, calendario, saldi |
| Ufficio del personale | tutto, più struttura, contratti, cataloghi |
| Amministrazione | allocazioni, costi mensili, voci extra, imputazione |

## 10. Importazione dei dati

Ogni area ha un importer in `backend/app/Migrations/`, con lookup `old_id`, upsert idempotente e warning sui
riferimenti mancanti. Già esistono gli importer di utenti (con profilo d'impiego), ruoli, funzioni aziendali e membri,
società, sedi. Da aggiungere, in ordine di dipendenza: struttura (divisioni, posizioni, mansioni, attività e legami),
condizioni contrattuali e CCNL, metodi di pagamento, allocazioni, costi mensili, cataloghi e assegnazioni.
Per ognuno va verificato prima che il legacy abbia il dato; se non lo ha, il campo nasce vuoto.

## 11. Assenze e richieste di ferie (rinviate, da fare)

Requisito di prodotto da sviluppare in una fase successiva, sul modello delle app HR di mercato:

- **Catalogo dei tipi:** ferie, permesso, malattia, e altri configurabili; per ognuno se scala un saldo e se richiede
  approvazione.
- **Richiesta della persona:** tipo, dal/al, giornata intera o a ore, nota, eventuale allegato (es. certificato).
- **Approvazione a due livelli quando serve:** prima il responsabile diretto, poi l'ufficio del personale; stati
  della richiesta (bozza, inviata, approvata dal responsabile, approvata, rifiutata, annullata); motivo del rifiuto.
- **Saldi per persona e per anno:** quanto spetta, quanto è goduto, quanto resta; maturazione e residuo dell'anno
  precedente.
- **Calendario della squadra:** chi c'è e chi non c'è, con festività (riusare il calendario lavorativo esistente).
- **Notifiche** a ogni passaggio di stato.
- Una sola fonte delle richieste: nessuna tabella doppia.

## 12. Decisioni aperte

1. **Campi *proposta*:** da confermare area per area prima della spec.
2. **Reparti:** servono accanto a divisioni e funzioni?
3. **Posizione e mansioni:** assegnare una posizione assegna anche le sue mansioni (e i permessi)? Vedi §5.

## 13. Ordine di sviluppo

1. Struttura organizzativa (§5) e vincolo BU/BS (regola 1).
2. Scheda dipendente estesa e contratto (§3, §4).
3. Allocazioni e costo del personale (§7).
4. Organigramma (§6).
5. Cataloghi e assegnazioni (§8), una spec per area.
6. Assenze (§11).

Ogni passo: spec in `docs/specs/` con `/plan-feature`, importer (§10), guida in-app IT/EN e manuale utente.
