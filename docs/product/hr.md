# Modulo HR — documento di prodotto

> Stato: bozza da seguire per lo sviluppo, 2026-10-16; perimetro ridotto il 2026-10-19 (D-10).
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
| D-4 | Le assenze sono **obbligatorie** | Vedi §9: sviluppate in spec 0278 e 0279 (calendario) |
| D-5 | Il segnatempo resta quello esistente (`time_entries`) | HR lo legge, non lo rifà |
| D-6 | **La mansione è un catalogo a sé, senza legame con ruoli e permessi** (rivista il 2026-10-16, sostituisce "mansione = ruolo") | Una persona ha una o più mansioni scelte da un select; il testo libero `job_description` sparisce |
| D-8 | Le posizioni non servono | Sede e mansioni descrivono già dove e cosa fa una persona |
| D-9 | Le divisioni non servono (2026-10-16) | Le funzioni aziendali (business unit e business service), con il loro albero, sono già il primo livello |
| D-7 | Il Controllo di Gestione è fuori da questo documento | Nessuna matrice di attribuzione, chiusura mensile o export verso il CdG per ora |
| D-10 | **Perimetro ridotto (2026-10-19):** oltre a quanto già sviluppato restano solo il calendario della squadra, corsi e formazione, competenze | Fuori scope: allocazioni e costo del personale (progetti interni, costi mensili, voci extra, imputazione), documenti con firma, obiettivi (KPI), onboarding, reparti |

## 2. Regole che valgono su tutto il modulo

1. **Ogni persona attiva ha almeno una funzione aziendale (business unit o business service).** Non si può attivare una persona senza funzione,
   né togliere l'ultima funzione a una persona attiva: l'operazione fallisce con un messaggio chiaro.
2. **La struttura viene prima delle persone.** Una persona senza funzione e mansioni non compare nell'organigramma.
3. **Il contratto ha una storia.** Un cambio di retribuzione o inquadramento crea una nuova condizione con data di
   decorrenza; quella precedente resta. I costi di un mese passato usano la condizione valida in quel mese.
4. **Ogni modifica è tracciata:** chi, che cosa, quando, da quale valore a quale (activity log su ogni nuovo dato).
5. **Visibilità a tre livelli:** la persona vede sé stessa, il responsabile la propria squadra, l'ufficio del
   personale vede tutto (stesso schema già usato da segnatempo e attività).
6. **Dati sensibili** (retribuzione, IBAN) visibili e modificabili solo con permesso dedicato; l'IBAN è cifrato.
7. **Un catalogo vuoto esiste comunque.** CCNL, livelli e metodi di pagamento nascono anche senza dati, con la
   schermata che li compila.

## 3. Scheda del dipendente

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Nome, cognome | anagrafica | presente (`personal_data`) |
| Codice fiscale, data e comune di nascita, residenza, genere | anagrafica | presente (`personal_data`) |
| Email di accesso | login | presente (`users`) |
| Recapiti personali | telefono, email personale | presente (`contacts`) |
| Recapiti di lavoro | telefono e caselle aziendali della persona | presente: opzione "di lavoro" sui recapiti (spec 0272) |
| Società | per conto di quale società del gruppo lavora | presente (`employment_profiles.company_id`) |
| Tipo di rapporto | dipendente, autonomo, altro | presente (`relationship_type`) |
| Inquadramento | qualifica | rimosso: sostituito da CCNL e livello nello storico contrattuale (spec 0272, §4) |
| Data di assunzione, data di cessazione | ingresso e uscita | presente (`hired_at`, `terminated_at`) |
| Responsabili | a chi risponde (anche più di uno) | presente (`employment_profile_manager`) |
| È responsabile | se guida una squadra | presente (`is_manager`) |
| Sede principale, sedi remote | dove lavora | presente (`employment_profile_operational_site`) |
| Orario standard e pausa giornaliera | minuti | presente |
| Funzioni aziendali (business unit o business service) | appartenenza, obbligatoria (regola 1) | presente (appartenenza, spec 0275) e obbligatoria per le persone attive (spec 0271) |
| Mansioni | che cosa fa: una o più mansioni dal catalogo (D-6) | presente (spec 0271); il testo libero `job_description` è stato rimosso |
| Stato attivo / cessato | chi è uscito si conserva, non si cancella | presente: alla cessazione l'utente viene disattivato, colonna "Stato" nella griglia (spec 0272) |

## 4. Contratto, CCNL e pagamento

### 4.1 Condizioni contrattuali (storico)

Una riga per ogni cambio, mai sovrascritta.

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Decorrenza (dal) | da quando vale | presente (spec 0272) |
| Fine (al) | fino a quando, vuoto se vigente | presente, ricavata dalla condizione successiva (spec 0272) |
| Tipo di rapporto | come in §3, ma storicizzato | presente (spec 0272) |
| CCNL e livello | inquadramento contrattuale | presente (spec 0272) |
| Retribuzione | importo e periodicità (mensile, annua, oraria) | presente, visibile solo con il permesso dati retributivi (spec 0272) |
| Società | società del contratto in quel periodo | presente (spec 0272) |
| Motivo del cambio | nota libera | presente (spec 0272) |

### 4.2 CCNL e livelli

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| CCNL: nome, codice | contratto collettivo applicato | presente (spec 0272) |
| Livello: CCNL, codice, descrizione, ordine | livelli dentro ogni CCNL | presente (spec 0272) |

### 4.3 Metodo di pagamento

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| IBAN | conto su cui si paga la persona (cifrato) | presente, cifrato e visibile solo con il permesso dati retributivi (spec 0272) |
| Intestatario | se diverso dalla persona | presente (spec 0272) |
| Valido dal | per tenere lo storico dei conti | non previsto: un solo conto vigente (spec 0272) |

## 5. Struttura organizzativa

Il primo livello è la Funzione aziendale (business unit o business service, con il suo albero padre-figlio); la persona ha mansioni, e ogni mansione comprende attività. Sedi e
società stanno accanto. Le posizioni non si fanno (D-8), i reparti nemmeno (D-10). Primo passo: spec 0271.

| Elemento | Campi | Oggi in qnet-2 |
|---|---|---|
| Funzione aziendale | nome, tipo (business unit o business service), funzione padre, responsabile | presente (`business_functions`) |
| | società in cui esiste | presente (spec 0271) |
| Mansione | nome, descrizione, attività comprese; competenze richieste (§7.2) | presente come catalogo (spec 0271); competenze richieste presenti (spec 0286) |
| Attività | nome, mansione di appartenenza | presente come catalogo (spec 0271) |
| Sede | nome, indirizzo, attiva | presente (`operational_sites`) |
| Società | denominazione, partita IVA, sedi | presente (`companies`, `company_sites`) |

Legami da gestire: mansione ↔ attività, persona ↔ mansioni, persona ↔ funzione (presente: le funzioni di
appartenenza, spec 0275, che valgono per la regola della funzione obbligatoria), funzione ↔ società. Gli
"Ambiti di assegnazione" (coppie funzione + categoria prodotto, un tempo chiamati "Competenza") servono solo
all'assegnazione di Lead, richieste e iscritti.

## 6. Organigramma

La stessa struttura vista dall'alto, non un disegno separato: presente (spec 0273), pagina Amministrazione >
Organigramma con due alberi espandibili.

| Campo | Significato | Oggi in qnet-2 |
|---|---|---|
| Vista Funzioni | albero delle funzioni aziendali con responsabile, persone e mansioni | presente (spec 0273) |
| Vista Persone | chi riporta a chi | presente (spec 0273) |
| Spostamenti | cambiare responsabile, spostare una funzione sotto un'altra, spostare una persona di funzione (trascinamento o "Sposta in…") | presente (spec 0273); lo spostamento di funzione cambia l'appartenenza |
| Registro spostamenti | chi ha spostato cosa | presente: activity log "Spostamento" sulla persona o sulla funzione |
| Unità e ruoli separati dalle funzioni | riquadri e ruoli propri dell'organigramma | non previsti: l'organigramma si ricava dalle funzioni aziendali |

## 7. Corsi, formazione e competenze

Stesso modello per entrambe le aree: un **catalogo** e le **assegnazioni** alle persone. Presenti: corsi e
formazione (spec 0284), competenze (spec 0286).

### 7.1 Corsi e formazione

| Elemento | Campi |
|---|---|
| Corso (catalogo) | nome, descrizione, durata in ore, validità in mesi, attivo, mansioni che lo richiedono (spec 0284) |
| Formazione della persona | persona, corso, data completamento, scadenza calcolata, esito, attestato (spec 0284) |

### 7.2 Competenze

Le competenze sono **ciò che la persona sa fare** (capacità, conoscenze, strumenti). Non sono gli "Ambiti di
assegnazione" della scheda utente (prima chiamati "Competenza"), rinominati in interfaccia dalla spec 0286 D-1.

| Elemento | Campi |
|---|---|
| Competenza (catalogo) | nome, categoria, descrizione, attiva (spec 0286) |
| Competenza della persona | persona ↔ competenza, livello 1-4 (Base, Intermedio, Avanzato, Esperto), data di valutazione (spec 0286) |
| Competenza richiesta dalla mansione | mansione ↔ competenza, con livello minimo (spec 0286) |

## 8. Il quotidiano della persona e del responsabile

| Chi | Che cosa vede o fa |
|---|---|
| Persona | il proprio profilo; la propria formazione e le proprie competenze; il proprio segnatempo in lettura; le proprie assenze; notifiche |
| Responsabile | la propria squadra; approvazioni delle assenze, calendario della squadra, saldi |
| Ufficio del personale | tutto, più struttura, contratti, cataloghi |

## 9. Assenze e richieste di ferie

Sviluppate in spec 0278 (tipi, richieste, approvazione di un livello, saldi in ore, target del segnatempo) e
0279 (calendario: chi c'è e chi non c'è, con weekend e festività, visibilità a tre permessi).

## 10. Importazione dei dati

Ogni area ha un importer in `backend/app/Migrations/`, con lookup `old_id`, upsert idempotente e warning sui
riferimenti mancanti. Già esistono gli importer di utenti (con profilo d'impiego), ruoli, funzioni aziendali e membri,
società, sedi. La struttura (mansioni, attività) non ha importer: il legacy non ha questi dati e si
compilano in app (spec 0271). Corsi, formazione e competenze: il legacy non ha dati, nessun importer (spec 0284, 0286).
Per ognuno va verificato prima che il legacy abbia il dato; se non lo ha, il campo nasce vuoto.

## 11. Decisioni aperte

Nessuna. Chiuse il 2026-10-19: visibilità del calendario con tre permessi (spec 0279 D-1); campi di corsi e
competenze (spec 0284, 0286).

## 12. Ordine di sviluppo

Tutto sviluppato: struttura organizzativa (0271, 0275, 0276, 0280, 0281), scheda dipendente e contratto (0272,
0277), organigramma (0273, 0274), assenze (0278), calendario della squadra (0279), corsi e formazione (0284),
competenze (0286). Il perimetro di D-10 è chiuso.

Ogni passo: spec in `docs/specs/` con `/plan-feature`, importer (§10), guida in-app IT/EN e manuale utente.
