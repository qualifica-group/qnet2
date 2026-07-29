# Rules — Engineering / Code Craft (cross-cutting, sempre attivo)

> Convenzioni di ingegneria del software valide su TUTTO lo stack
> (PHP 8.3 / Laravel 13 + TypeScript / React 19). Contestualizzazione su questo stack
> delle istruzioni "senior engineer" dell'utente. Presuppone il CORE in `CLAUDE.md`.
> **Si applicano sempre**, non on-demand. Obiettivo: codice corretto, minimale,
> manutenibile, ingegnerizzato — e far sì che il codice sbagliato non sopravviva al loop.
>
> Formato: per ogni voce → **Problema** (cosa va storto, con grounding) → **Mitigazione
> obbligatoria** (cosa fare / non fare). Le regole sono vincoli, non suggerimenti.

---

## §1 — ANTI-PATTERN DEL CODICE AI (prevenire, non correggere dopo)

### 1.1 Context rot — degradazione del contesto
**Problema.** Su molti scambi il modello perde coerenza con le decisioni prese: variabili rinominate (`tipoBando` → `BandoType`), import dimenticati, logica contraddittoria. Il recall degrada al crescere dei token (Chroma, *context rot* 2025).

**Mitigazione obbligatoria.**
- Prima di scrivere, **dichiara**: quali file tocchi, quali nomi (classi/metodi/colonne/componenti) esistono già, quali convenzioni sono in uso.
- Se non sei sicuro di un nome o di una struttura, **leggi il file reale** (glob/grep) — non ipotizzare.
- **Mai** introdurre un nome alternativo per qualcosa che esiste già: riusa il nome esistente esatto.

### 1.2 Naming drift — inconsistenza dei nomi
**Problema.** Il codice AI genera ~2× le inconsistenze di nomi rispetto a quello umano (CodeRabbit 2025). Nomi generici (`data`, `result`, `temp`, `item`, `obj`), terminologia oscillante.

**Mitigazione obbligatoria.**
- **Lingua degli identificatori: INGLESE, obbligatorio.** Tutti i nomi nel codice sono in inglese: variabili, metodi/funzioni, classi/componenti/type, **tabelle e colonne DB**, **rotte/URI e nomi di route**, chiavi, costanti. Vietato l'italiano o lingue miste negli identificatori (`grantDeadline`/`grant_deadline`, non `bandoScadenza`/`bando_scadenza`; `GET /grants`, non `/bandi`). Fanno eccezione **solo**: i valori di dominio esposti all'utente (stringhe i18n, contenuti), e i nomi già esistenti nel codebase — che si riusano esattamente com'è (§1.1) anche se non inglesi, senza introdurne di nuovi non inglesi accanto.
- Una sola convenzione, mantenuta ovunque:
  - **PHP:** `snake_case` variabili/metodi e colonne DB, `PascalCase` classi, `UPPER_SNAKE` costanti.
  - **TS/React:** `camelCase` variabili/funzioni, `PascalCase` componenti/type, `UPPER_SNAKE` costanti, hook con prefisso `use`.
  - **CSS:** `kebab-case` per classi custom.
- Nomi **specifici e semantici**: `grantDeadline`/`grant_deadline` non `date1`; `parseGrantResponse` non `handleData`.
- Se un concetto ha già un nome nel codebase, usa **esattamente** quello.

### 1.3 Abstraction bloat — sovra-ingegnerizzazione
**Problema.** L'AI scrive 1000 righe dove ne bastano 100; gerarchie elaborate dove una funzione basterebbe (Addy Osmani, *The 80% Problem*, 2026).

**Mitigazione obbligatoria.**
- Scrivi il **minimo** che risolve. Nessun pattern speculativo.
- Una funzione/metodo è preferibile a una classe se non c'è stato interno.
- **Laravel:** niente Repository sopra Eloquent senza ragione concreta; niente Service anemico pass-through; stai vicino ai default del framework.
- **React:** niente Context/HOC/`useReducer` prematuri; niente astrazione "riusabile" su un solo call-site.
- Oltre **300 righe** in un file → fermati e valuta lo split (§6).

### 1.4 Dead code — accumulo di codice morto
**Problema.** Quando corregge, l'agente scrive il nuovo senza cancellare il vecchio: il file cresce a strati di codice non usato.

**Mitigazione obbligatoria.**
- Quando correggi: **cancella** il codice vecchio, non commentarlo.
- A ogni modifica verifica: nessun import inutile, nessuna funzione orfana, nessuna variabile assegnata-mai-letta, nessun file svuotato/obsoleto.
- Preferisci **riscrittura compatta** a patch-su-patch. Se un file ha >30% di codice morto, riscrivilo da zero.

### 1.5 Verification gap — dichiarare "fatto" senza prova *(fallimento #1)*
**Problema.** Il fallimento numero uno del lavoro multi-step: l'agente dichiara "fatto/funziona" prima che i test confermino.

**Mitigazione obbligatoria.**
- **Mai** "fatto/funziona" senza aver **eseguito davvero** test e lint (Pest/Vitest, Pint/ESLint, `tsc -b --force` — **non** `tsc --noEmit`, che in questo repo è un falso verde: vedi `frontend.md §10`). Se non puoi eseguirli, **dillo**.
- Un test **scritto ma non eseguito NON conta**. "Dovrebbe passare" non è una verifica.

### 1.6 Scope creep / drift — deviare dalla richiesta
**Problema.** La "piccola modifica" tocca file non richiesti; l'agente devia dallo scope.

**Mitigazione obbligatoria.**
- Tocca **solo** i file nello scope dichiarato (e nella tua ownership, se sei un teammate).
- Un miglioramento fuori scope si **segnala**, non si implementa. Blast radius minimo: niente reformat di file estranei.

### 1.7 Hallucination — inventare API/strutture
**Problema.** L'AI inventa colonne, relazioni, route, metodi, componenti che non esistono.

**Mitigazione obbligatoria.**
- Non inventare API, colonne, relazioni, route o componenti. **Verifica** contro migrazioni/route/componenti reali prima di usarli.
- Informazione mancante → leggi il file o **chiedi**, non ipotizzare interfacce o nomi.

---

## §2 — STRUTTURA INTERNA DEL CODICE (orchestrazione leggibile)

**Problema.** Logica srotolata senza un punto di ingresso chiaro → illeggibile e non testabile.

**Mitigazione obbligatoria.**
- Ogni unità con logica non banale ha **un metodo di orchestrazione** che rende esplicita la sequenza; i **sotto-metodi** sono unità logiche testabili (una cosa ciascuno).
- **Commenti di flusso numerati** `// Step N` **solo** nell'orchestrazione.
- Inietta le dipendenze (costruttore in PHP; parametri/hook in TS), non istanziarle a mano dentro la logica.

**Laravel (Action class):**
```php
final class ApprovaBando
{
    public function __construct(private readonly NotificaService $notifiche) {}

    public function handle(Bando $bando, User $approvatore): Bando
    {
        // Step 1: valida la transizione di stato
        $this->assertApprovabile($bando);
        // Step 2: applica e persisti
        $bando->update(['stato' => StatoBando::Approvato, 'approvato_da' => $approvatore->id]);
        // Step 3: side-effect non bloccante
        $this->notifiche->bandoApprovato($bando);

        return $bando->fresh();
    }

    private function assertApprovabile(Bando $bando): void { /* ... */ }
}
```

**React (logica nel custom hook, componente UI puro):**
```tsx
// la logica orchestra qui; il componente renderizza soltanto
function useApprovaBando(bandoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => api.post(`/api/bandi/${bandoId}/approva`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['bandi', bandoId] }),
  });
}
```

---

## §3 — ARCHITETTURA (SOLID / Clean, senza sovra-astrazione)

**Problema.** O codice monolitico senza confini, o l'opposto: astrazioni inutili in nome dell'"enterprise".

**Mitigazione obbligatoria** — principi mappati sullo stack, applicati con misura:
- **SRP** — una classe/un hook = una ragione per cambiare. Controller coordina, non calcola; componente renderizza, non fa fetch.
- **SoC** — confini netti: HTTP (Controller / api client axios) ≠ logica (Service/Action / custom hook) ≠ dati (Model/Query / types+Zod) ≠ presentazione (Resource / JSX).
- **OCP** — estendi creando **nuovi file** (nuova Action, nuovo componente, nuova `TableDefinition`), non gonfiando i core.
- **DIP** — dipendi da astrazioni **solo dove serve davvero** (es. un'interface con ≥2 implementazioni reali). Niente interface a implementazione singola.
- **DRY** — logica duplicata → estrai in unità condivisa. Ma astrai sulla **ripetizione reale**, non ipotetica (YAGNI).

---

## §4 — PROTOCOLLO OPERATIVO

### 4.1 Prima di scrivere
1. **Valuta fattibilità:** la richiesta è implementabile correttamente, in modo coerente e testabile, nel contesto attuale?
2. **Dichiara la profondità:** fino a dove arrivi senza compromettere qualità/manutenibilità.
3. **Elenca le dipendenze:** quali file esistenti tocchi, quali nomi/strutture vanno rispettati.
4. **Top-down:** struttura (file, classi/hook, contratto) **prima** dell'implementazione.

### 4.2 Durante
- Metodo di orchestrazione + sotto-metodi testabili (§2). Niente codice speculativo "per il futuro".
- File entro le soglie (§6). Niente magic value (§6).

### 4.3 Dopo
- Pulizia: nessun import inutile, nessuna funzione orfana, nessun `// TODO` senza contesto.
- Coerenza nomi col codebase. Responsive (se UI, vedi `ui-design.md`).
- **Esegui** test+lint+typecheck (§1.5).

### 4.4 Quando correggi un bug
- **Root cause prima del fix:** spiega *perché* è avvenuto. Niente cerotti.
- **Cancella** il codice errato (non commentarlo); riscrivi compatto.
- **Effetti collaterali:** verifica di non rompere test/altro. TDD: il test che riproduce il bug **prima** del fix; non modificare i test per farli passare (§1.5).

---

## §5 — COMMENTI E DOCUMENTAZIONE

**Problema.** Commenti rumorosi che ripetono il codice; file di doc generati senza richiesta; emoticon.

**Mitigazione obbligatoria.**
- **Nessuna emoticon** nel codice o nei commenti.
- Commenti **minimi**, sul **PERCHÉ** (il codice dice già il cosa). Eccezione: i `// Step N` di orchestrazione (§2).
- **PHPDoc/JSDoc solo su classi/metodi pubblici** dove il tipo non è autoesplicativo.
- **Non creare file di documentazione** (README, CHANGELOG, `.md`) **se non esplicitamente richiesto**. Eccezione di progetto: `docs/HANDOFF.md` e `docs/specs/` fanno parte del workflow.

---

## §6 — DIMENSIONI, COSTANTI, PULIZIA

- **File:** 300 righe = soft limit (proponi lo split in moduli/feature coerenti col dominio); **500 = hard limit**.
- **Metodi/funzioni** piccoli, < ~50 righe, a singola responsabilità.
- **Magic values → costanti** con nome semantico. Niente stringhe/numeri inline ripetuti.
  - Soglie/limiti/timeout → costanti o `config()`/`import.meta.env`.
  - Selettori, chiavi, classi CSS custom, definizioni colonna → centralizzate (per il framework tabellare: nella `TableDefinition`, non sparse nel codice).
- **Hard-coding vietato** salvo autorizzazione esplicita: usa `config()` / `.env` (PHP), `import.meta.env` con prefisso `VITE_` (FE, pubblico — mai segreti).

---

## §7 — FORMATO DELLA RISPOSTA

- Tecnico, strutturato per sezioni. Codice **solo** quando utile o richiesto.
- **Mostra SOLO le modifiche**, mai ripetere codice invariato.
- Se non puoi completare tutto: implementa ciò che riesci, **elenca esplicitamente cosa manca** e cosa chiedere nel passo successivo.

---

## §8 — VINCOLI (riferimento rapido)

| Vincolo | Regola |
|---|---|
| Lingua identificatori | **INGLESE obbligatorio**: variabili, metodi, classi/type, tabelle/colonne DB, rotte/URI, chiavi, costanti. Eccezione: valori esposti a UI (i18n) e nomi già esistenti nel codebase |
| File max righe | 300 (soft → proponi split) · 500 (hard) |
| Metodo/funzione | < ~50 righe, singola responsabilità |
| Codice non richiesto | NON generare funzioni/classi/file non necessari |
| Hard-coding | Vietato salvo autorizzazione → `config()`/`.env`/`import.meta.env` |
| Codice morto | Cancellare subito, mai commentare |
| Import inutili | Rimuovere a ogni modifica |
| Codice duplicato | Estrarre in unità condivisa (sulla ripetizione reale) |
| Magic values | Estrarre in costanti con nome semantico |
| Documentazione | Solo se richiesta (escluse spec/HANDOFF di workflow) |
| Emoticon | Mai nel codice/commenti |
| Test | Parte della Definition of Done; **eseguiti**, non solo scritti |

---

## §9 — CHECKLIST PRE-RISPOSTA (codice)

- [ ] Nomi verificati (non ipotizzati) e coerenti col codebase?
- [ ] Ho toccato solo i file nello scope (e nella mia ownership)?
- [ ] Orchestrazione leggibile (metodo `handle`/hook + `// Step N`), sotto-metodi testabili?
- [ ] Zero dead code / import inutili / magic value?
- [ ] File entro 300/500 righe? Se no, ho proposto/eseguito lo split?
- [ ] È la soluzione **più semplice** che risolve (no over-engineering)?
- [ ] Test aggiunti/aggiornati e **eseguiti** (Pest/Vitest), lint+typecheck puliti?
- [ ] Handoff: cosa fatto, cosa verificare, prossimo passo? `docs/HANDOFF.md` aggiornato se verde?

---

## Grounding / letteratura

- **Context rot** — Chroma, report sulla degradazione del recall al crescere del contesto (2025).
- **Naming drift** — CodeRabbit, analisi 2025 (codice AI ~2× inconsistenze di naming).
- **Abstraction bloat** — Addy Osmani, *The 80% Problem* (2026); Qodo 2025 sulla qualità del codice generato.
- **Verification gap** e **muro dei tre mesi** — pratica spec-driven vs vibe-coding (debito tecnico che accumula).
- **Spec + harness di verifica** come leva di qualità (più del prompt) — context engineering, Anthropic.
