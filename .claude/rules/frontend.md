# Rules — Frontend (React 19 / TypeScript)

> Caricare quando il task tocca: pagina, componente, form, stato client, fetch, routing.
> Presuppone il CORE in `CLAUDE.md`.

## 1. Struttura

Feature-based: `features/<dominio>/{api.ts, types.ts, *-schema.ts, hooks, components}`. Componenti atomici condivisi in `components/ui/`. Mai un cartellone monolitico organizzato per tipo.

## 2. Server state vs client state (regola cardine)

- **Dati dal server → SEMPRE TanStack Query.** Mai mettere risposte API in Redux/Zustand/`useState`: genera logica di sincronizzazione che React Query risolve già (cache, refetch, stale-while-revalidate).
- **Stato puro di UI → `useState`** (o Zustand/Jotai se condiviso). Regola: se il dato vive sul server, è React Query; se è UI client, è state locale.

## 3. Data fetching — l'anti-pattern AI numero uno

- **Vietato `useEffect` + `fetch` per il data fetching.** È l'errore più comune del codice generato: `useEffect` è nato per la sincronizzazione, non per i dati. Comporta race condition, stale closure, doppio fetch in Strict Mode, richieste duplicate, flickering del loader.
- Usa `useQuery` / `useMutation` con **generics tipizzati**: `useQuery<User[], AxiosError>(...)`. Chiavi query centralizzate (`query-keys`); invalidazione corretta dopo le mutation.
- Per i casi che lo richiedono, gestisci loading/error con **Suspense + Error Boundary** invece di catene di `if (isLoading)`.

## 4. HTTP

- **SEMPRE il client axios configurato** (interceptor token/401). Mai `fetch` nudo.
- **Non** forzare un `Content-Type` globale: axios lo inferisce per richiesta (`application/json` per oggetti, `multipart/form-data` con boundary per i file). Forzarlo rompe gli upload.
- API solo su **HTTPS** (mixing http/https rompe i cookie e fa scattare warning di sicurezza sui browser mobili).

## 5. Form

- React Hook Form + **schema Zod** + resolver. I tipi del form derivano dallo schema Zod (single source of truth).
- La validazione client **rispecchia** quella server, non la sostituisce (vedi `security.md`).

## 6. Logica e tipi

- **Niente logica di business nel JSX**: è non testabile e illeggibile. Estraila in custom hook; il componente resta UI pura.
- TypeScript: **niente `any`**. Tipizza gli event handler (`React.ChangeEvent<HTMLInputElement>` ecc.) e gli hook. Interfacce prop chiare per ogni componente.
- Meno stati gestisci nel componente, meglio è: raggruppali o delegali a React Query.

## 7. Performance e permessi

- **Lazy load** di route e componenti pesanti con `React.lazy()` + Suspense.
- Gate dell'UI con `<Can>` / `use-abilities`: **l'UI nasconde, il backend autorizza** (defense in depth, mai fidarsi del solo controllo client).
- Niente prop drilling profondo: usa context/hook (in React 19, `use()` per context come tema/auth/i18n). Niente store globale non necessario.

## 8. i18n e tabelle

- Stringhe via **i18next**, mai hardcoded nel template.
- Rispetta il contratto del **SSRM datasource** di AG Grid: non rompere la shape `columns`/`rows` né i parametri inviati al backend.

## 9. Anti-pattern frontend

- Non rifare componenti che shadcn/ui fornisce già: usa quelli in `components/ui/` (vedi `ui-design.md`).
- **Un bottone non deve mai avere lo stesso colore del body/pagina.** Deve restare distinguibile dallo sfondo su cui poggia: mai lasciare un `Button` che si confonde col background (es. `variant="outline"`, che è `bg-transparent`, sopra una pagina chiara sembra "senza sfondo"). Dagli un fill proprio — variante con riempimento (`default`/`secondary`) oppure un background esplicito (`bg-white`/`bg-card`) sull'outline — così il bottone si stacca sempre dal body.
- **Attenzione alla "casa di carte"**: il codice React generato sembra finito ma, aggiungendo auth/sorting/realtime, cresce a file da 1K+ righe ingestibili. Tieni i file piccoli (CORE §2) e **verifica eseguendo** (Vitest), non a vista.

> Skill di riferimento on-demand in `.claude/skills/`: **`react-testing`**, **`vite-patterns`**, **`react-performance`**. Le regole `react-hooks.md` e `react-security.md` si auto-attaccano via `paths:` ai file `**/*.tsx`.

## 10. Regole avanzate (estratte da audit ECC — alto valore)

Errori che i modelli AI fanno in modo ricorrente. Vincoli, non consigli.

- **Triade errore accessibile** — ogni errore di campo va cablato come `aria-describedby={errorId} aria-invalid={!!error}` + `<span id={errorId} role="alert">`. RHF+Zod ti dà lo stato `error` ma **non** cabla l'ARIA: questo è il collante mancante.
- **`safeUrl()` con allow-list di schemi** — prima di ogni `href` derivato dall'utente: `new URL()` + allow-list `["http:","https:","mailto:"]`. React **non** blocca a runtime `javascript:`/`data:`.
- **`vite build` NON fa typecheck** — gli errori di tipo vengono spediti in silenzio. (Coperto qui dall'hook Stop `typecheck.sh`.)
- **`tsc --noEmit` in questo repo è un FALSO VERDE** — `frontend/tsconfig.json` è solution-style (`"files": []` + `"references"` a `tsconfig.app.json`/`tsconfig.node.json`): senza `-b` tsc non entra nei progetti referenziati, non ha file da controllare e **restituisce sempre EXIT=0**, qualunque errore ci sia. L'unico comando che verifica davvero, lo stesso dell'hook Stop: `cd frontend && npx tsc -b --force --pretty false`. Chi riporta "`tsc --noEmit` pulito" non ha verificato nulla.
- **`VITE_` non è un confine di segreto** — tutto ciò che ha prefisso `VITE_` finisce nel bundle pubblico. Mai mettere un segreto lì. Usa `loadEnv(mode, cwd, ['VITE_'])`, mai `''`. Per chiamare l'API in dev usa `server.proxy` `/api` → Laravel con `changeOrigin: true` (evita problemi cookie/CORS in dev).
- **Ternario, non `&&`, per condizioni numeriche** — `{count > 0 ? <Badge/> : null}`: `{count && <Badge/>}` renderizza un `0` letterale a schermo.
- **Non definire componenti dentro componenti** — un nuovo tipo a ogni render annulla la reconciliation e smonta i figli. Estrai sempre a livello modulo.
- **Hoista le prop non-primitive di default** — `const EMPTY: Item[] = []` a livello modulo, non `items ?? []` inline: l'inline crea un nuovo riferimento a ogni render e rompe `memo`/dipendenze stabili.
- **(test) QueryClient stabile per-test, non per-render** — crearlo nella closure del wrapper resetta la cache a ogni render → test flaky. Istanziane uno per test.
- **Query a11y-role-first; `data-testid` solo per Playwright E2E.** Standard unico: i test interrogano per ruolo accessibile; il `data-testid` è riservato all'E2E, non ai test unit/component.
