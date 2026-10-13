# Rules — Security (cross-cutting)

> Caricare quando il task tocca: auth, dati sensibili, segreti, input esterni, CORS, dipendenze.
> Presuppone il CORE in `CLAUDE.md`. La sicurezza prevale sulla velocità della feature.

## 1. Principi

- **Trust nothing** — Nessun dato dal frontend è attendibile; ogni validazione è replicata sul backend.
- **Authorization first** — Ogni endpoint verifica autenticazione e permessi server-side (Policy), incluso il framework tabellare.
- **Least privilege** — Utenti/sistemi hanno solo i permessi necessari.

## 2. Autenticazione SPA (decisione raccomandata)

Per una SPA **first-party** (frontend e API sullo stesso top-level domain), lo standard di sicurezza è la **session auth a cookie di Sanctum**, NON i bearer token in localStorage:

- Flusso: la SPA chiama `GET /sanctum/csrf-cookie` per inizializzare il token CSRF, poi `/login`; da lì la **sessione vive in un cookie HttpOnly** che JavaScript non può leggere → niente token rubabile via XSS. axios invia il token CSRF automaticamente.
- Cookie: `HttpOnly`, `Secure` (in produzione), `SameSite=Lax` (o `Strict`).
- `SESSION_DRIVER` coerente; `SANCTUM_STATEFUL_DOMAINS` con i domini frontend.
- **localStorage**: un token rubato è grave quanto una password rubata; XSS legge tutto il localStorage. Se per vincoli devi usare il Bearer in localStorage, la **CSP stretta non è opzionale**: va configurata via header **e verificata da un gate/hook**, non assunta. Documenta la scelta.
- **Mobile** → keychain di piattaforma (iOS Keychain / Android Keystore), mai shared preferences.
- **SPA cross-origin** (domini diversi) → cookie solo se controlli entrambe le origini e configuri CORS con credenziali; altrimenti token a vita breve + refresh flow.

## 3. CORS (il pitfall #1)

- `config/cors.php`: **origine esplicita** in `allowed_origins` (mai `*` in produzione) e `supports_credentials: true` per l'auth a cookie. La combinazione `['*']` + `supports_credentials:false` rompe il cookie `XSRF-TOKEN` → 419.
- HTTPS su entrambi i lati (mixing http/https impedisce l'invio dei cookie).
- Con più server applicativi: sticky session o session store centralizzato (Redis) con chiavi coerenti, altrimenti 419 intermittenti.

## 4. Backend

- **FormRequest** su ogni input; **Policy/authz** su ogni endpoint.
- Query parametrizzate/Eloquent; **mai** interpolazione SQL.
- `$fillable`/`$guarded` sempre definiti (no escalation `is_admin`/`role` via mass assignment).
- Password: hashing Laravel (bcrypt/argon); mai algoritmi deboli.
- **Rate limiting** SOLO sugli endpoint di credenziali auth (login, forgot/reset password, cambio password). NON su CRUD/browse autenticati o endpoint costosi (decisione utente 2026-07-15). Non reintrodurre `throttle` altrove senza richiesta esplicita. **Unica eccezione:** rate limit per client (limiter `api-client`) sulle sole richieste fatte con un token di un client API (`api_client_id` valorizzato), spec 0210; mai sulle chiamate dell'app.
- Eccezioni senza leak di classi/struttura interna (envelope `{ success, message }`).

## 5. Frontend

- Escape automatico dei template; **mai** `dangerouslySetInnerHTML` con input utente.
- **CSP** headers configurati.
- Nessun secret nel bundle: solo variabili `VITE_*` (pubbliche per definizione).
- La validazione client non è mai l'unica difesa.

## 6. Segreti e dipendenze

- Mai password/token/API key/secret nel repo. `.env` gitignored; `.env.example` senza valori reali.
- Non loggare mai password, token, dati sensibili o PII non necessari.
- Nessuna dipendenza nuova senza autorizzazione; verifica vulnerabilità note; aggiornamenti regolari.
- **Nota legale:** `ag-grid-enterprise` richiede licenza a pagamento per uso commerciale — non assumere che sia gratuito; in alternativa `ag-grid-community` o TanStack Table.

## 7. Quando coinvolgere una verifica di sicurezza dedicata

Modifiche che toccano autenticazione, autorizzazioni, dati sensibili/segreti, input esterni o nuove dipendenze richiedono una verifica esplicita di: authz server-side su ogni endpoint, input validati/sanitizzati, nessun secret nel repo, nessun dato sensibile nei log.

## 8. Regole avanzate (estratte da audit ECC — alto valore)

- **`whereRaw`/`orderByRaw`/`groupByRaw` = sink SQLi** anche dentro Eloquent. Ordinamento/filtri dinamici (es. SSRM/`TableDefinition`) vanno da **allow-list di colonne**, mai dall'input. (Vedi `backend.md §8`.)
- **Mass-assignment: `$request->safe()->only()`**, mai `$request->only()` da solo (non valida). `$fillable`/`$guarded` sempre definiti (no escalation `is_admin`/`role`).
- **Token Sanctum scadono di default mai** → imposta `'expiration'` in `config/sanctum.php`. Scope per-token via `abilities:` + `tokenCan()`.
- **Route annidate → `Route::scopeBindings()`** per chiudere IDOR/cross-tenant.
- **Upload: `mimes:` + `extensions:`** per battere lo spoof del MIME.
- **(Frontend) `safeUrl()` allow-list schemi** prima di ogni `href` da input; **mai** `dangerouslySetInnerHTML` con input utente. `VITE_*` è pubblico → nessun segreto nel bundle.
- **Config-tamper guard:** l'agente non modifica config di linter/test (`pint.json`, `phpstan.neon`, `phpunit.xml`, `eslint.config.*`, `.prettierrc`, `vitest.config.*`) per far passare un check. Enforced dall'hook `config-protection.js`.

> Skill di riferimento on-demand: **`laravel-security`** (coppie vulnerabile/sicuro), **`production-audit`** (cap di prontezza: rischioso se manca auth / webhook non idempotente / nessun rollback / segreti nel bundle).
