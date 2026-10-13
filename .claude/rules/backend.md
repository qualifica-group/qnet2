# Rules — Backend (Laravel 13 / PHP 8.3)

> Caricare quando il task tocca: endpoint, model, migrazione, service, permessi, query.
> Presuppone il CORE in `CLAUDE.md`.

## 1. Layering

`Controller (thin) → FormRequest (validazione) → Service (logica) → DataObject/DTO → Resource (output) → Policy (authz)`.

- Il controller **coordina, non calcola**. Logica di business oltre ~15 righe → Service o Action class.
- Inietta le dipendenze nel costruttore, non istanziarle a mano.
- **Non sovra-astrarre.** Stai vicino ai default di Laravel: aggiungi Service/DTO/pattern solo quando rimuovono duplicazione o coupling reali, non perché "fa più enterprise". Niente Service anemico pass-through, niente Repository sopra Eloquent senza una ragione concreta.

## 2. Endpoint

- Ogni endpoint ha **FormRequest** (validazione) e **autorizzazione server-side** (Policy o `authorize()`/`can`). Nessuna eccezione, incluso il framework tabellare generico (authz nella `TableDefinition::viewAny`).
- Usa `Route::resource` / route model binding per il CRUD: niente file di route gonfio di GET/POST manuali.
- **Mai restituire un model Eloquent raw**: sempre via API **Resource**. Il giorno che aggiungi un campo `password`/`api_secret` e dimentichi di nasconderlo è un incidente, non un errore.
- Rispetta l'**envelope di risposta** del progetto (`{ success, message, ... }`). Gli errori non espongono mai nomi di classi/model interni.
- **Rate limiting** (`throttle:...`) SOLO sugli endpoint di credenziali auth (login, forgot/reset password, cambio password): sono i bersagli di brute-force. NON applicarlo agli altri endpoint (CRUD/browse autenticati, SSRM, import/export) — decisione utente 2026-07-15, "Too Many Attempts" bloccava l'uso normale. Non reintrodurre `throttle` altrove senza richiesta esplicita. **Unica eccezione:** le richieste fatte con un token di un client API (chiave del client o login da client, cioè token con `api_client_id`) passano dal limiter per client `api-client` (`ThrottleApiClientRequests`, limite in `api_clients.rate_limit_per_minute`, default `config('external-api.rate_limit.default')`) — decisioni utente 2026-10-09, spec 0210. Le chiamate dell'app (token senza `api_client_id`) restano senza throttle; `auth/client-login` ha `throttle:6,1` come gli altri endpoint di credenziali.

## 3. Eloquent / Database

- **Mass assignment**: definisci sempre `$fillable` (o `$guarded`). Senza, ogni colonna è assegnabile e un attaccante può settare `is_admin: true` o `role` via form. Difesa in profondità: protegge anche da errori futuri.
- **N+1**: abilita la strictness Eloquent in locale/test — `Model::preventLazyLoading()` (in `AppServiceProvider::boot`, solo fuori produzione) — così il lazy loading dimenticato **fallisce rumorosamente** invece di degradare in silenzio. Usa eager loading (`with`) per le relazioni note.
- **Indici** su foreign key e colonne usate nei filtri/ordinamenti: la loro assenza rallenta le pagine report al crescere dei dati.
- Query via Eloquent o parametrizzate. **Mai** concatenazione/interpolazione SQL.
- `$casts` espliciti per date/enum/bool/json.
- **Non inventare** relazioni o colonne: verifica le migrazioni reali prima.
- **Nomi in inglese, obbligatorio** — tabelle, colonne, rotte/URI e nomi di route in inglese (`grants`, non `bandi`; `expires_at`, non `scadenza`). Eccezione solo per i nomi già esistenti nel codebase, che si riusano invariati. Vedi `engineering.md §1.2`.
- **Migrazioni**: usale come version control dello schema; mai mischiare SQL grezzo. Sempre reversibili (`down`). **Mai** modificare una migrazione già committata: creane una nuova.

### 3.1 Seeder e factory — separazione seed pulito / dati fake (vincolante)

- **`DatabaseSeeder` = seed pulito**, minimale: SOLO inizializzazione/reference (es. `locations:add`), il catalogo permessi + l'unico ruolo privilegiato `super-admin` (`RolePermissionSeeder`) e l'unico utente demo (`DemoUserSeeder`). Nient'altro.
- **Ogni altro seeder = dati fake/demo** → prefisso **`Demo`** obbligatorio nel nome della classe e richiamato **solo** da `DemoDataSeeder` (eseguito on-demand: `php artisan db:seed --class=DemoDataSeeder`). Mai aggiungere un seeder di dati fake a `DatabaseSeeder`.
- **Ogni nuova factory/generazione di dati fake** appartiene al percorso `DemoDataSeeder`, mai al seed pulito. Un nuovo modulo con fixtures → nuovo `Demo<Entità>Seeder` aggiunto alla lista di `DemoDataSeeder`, nell'ordine di dipendenza corretto.
- Idempotenza: i seeder demo usano `firstOrCreate`/`updateOrCreate`/`syncRoles` così che re-run non duplichino righe.

## 4. Permessi e audit

- RBAC con `spatie/laravel-permission`: permessi via Policy/`can`, sync delle abilities.
- Audit con `spatie/laravel-activitylog` (concern `LogsModelActivity`) sui model che lo richiedono.
- Nuova entità tabellare → nuova `TableDefinition` registrata nel `TableRegistry`, **non** nuovi endpoint ad-hoc.

## 5. Code style e job

- PSR-12, type hints ovunque, `declare(strict_types=1)` dove sensato. Formattazione con **Pint** prima di considerare completo.
- Metodi piccoli e a singola responsabilità.
- Lavoro lento/retryable → **queue job**. `dispatchAfterResponse()` solo per follow-up minimi non critici.
- Niente valori di config hard-coded nel codice: usa `config()`/`.env`.

## 6. Test (Pest)

- Feature test per ogni endpoint: **happy path, error path, autorizzazione** (utente senza permesso → 403; risorsa altrui → 403/404).
- Non modificare i test per farli passare (vedi CORE §2).
- I test fanno parte della Definition of Done ed **eseguili davvero**.
- **La suite gira in parallelo** (`composer test` = `--parallel` con `XDEBUG_MODE=off`): ogni worker carica solo i SUOI file di test. Una funzione helper usata da piu' di un file di test va in `tests/Helpers/<Dominio>Helpers.php` (caricato da `tests/Pest.php`), mai dichiarata in un file di test e richiamata da un altro: in seriale passa per caso, in parallelo fallisce con `Call to undefined function`. Durante lo sviluppo esegui i test mirati (`XDEBUG_MODE=off ./vendor/bin/pest tests/Feature/<Area>`), mai `php artisan test` nudo (seriale, sotto Xdebug).

## 7. Manutenzione

- Resta su release supportate; gli upgrade guide fanno parte della manutenzione ordinaria, non dell'emergenza.
- Ogni dipendenza è codice di cui ti fidi: niente pacchetti speculativi.

## Risorsa consigliata

Per un audit automatico, esiste la skill ufficiale **Laravel Boost `laravel-best-practices`** (~189 regole su performance DB, sicurezza, uso Eloquent). Utile come secondo livello di verifica sul codice generato.

Skill di riferimento on-demand in `.claude/skills/`: **`laravel-security`**, **`laravel-tdd`**, **`mysql-patterns`** (caricale quando il task lo richiede).

## 8. Regole avanzate (estratte da audit ECC — alto valore)

Regole specifiche che il codice AI sbaglia di frequente. Trattale come vincoli, non come consigli.

- **`whereRaw`/`orderByRaw`/`groupByRaw` sono sink di SQL injection** anche quando il resto di Eloquent parametrizza. `Model::orderByRaw($input)` è **vulnerabile**. È l'escape hatch che l'AI prende per costruire sort/filtri dinamici → **vale doppio per il framework tabellare generico (`TableDefinition`/SSRM)**: ordinamento e filtri lato server vanno costruiti da una allow-list di colonne, mai dall'input grezzo.
- **`$request->only()` NON è una difesa mass-assignment.** Passa solo dopo validazione: usa `$request->safe()->only([...])` (richiede un FormRequest con regole). `->only()` da solo non valida nulla.
- **`Route::scopeBindings()` sulle route annidate** — `/accounts/{account}/projects/{project}` deve risolvere `project` solo se appartiene ad `account`. Senza, l'AI genera route annidate che aprono buchi IDOR/cross-tenant.
- **Cast moderni invece di mutator manuali:** `'password' => 'hashed'` (auto-hash on set, L10+), `'metadata' => 'encrypted:array'` (L11+). Non scrivere `Hash::make()` nei mutator.
- **Scope dei token Sanctum a livello route:** `->middleware('abilities:posts:write')` + `abort_unless($user->tokenCan('write'), 403)`. Non fermarti a "usa Sanctum": imponi gli scope per-token.
- **`'expiration' => 60 * 24` in `config/sanctum.php`** — i token Sanctum di default **non scadono mai**. Impostare la scadenza è hardening obbligatorio, non opzionale.
- **File upload: regola `extensions:` OLTRE a `mimes:`** — `'mimes:pdf,doc', 'extensions:pdf,doc'`: verifica che l'estensione reale combaci col MIME (batte lo spoof). `extensions:` è recente e spesso dimenticata.
- **Coverage target per-layer** (non un "80%" piatto): Model 95% / Policy 95% / FormRequest 90% / Action-Service 90% / Controller 85% / globale 80%. Policy e FormRequest al 95% mappano sul layering Controller→FormRequest→Service→DTO→Resource→Policy.
- **(MySQL prod) Keyset/seek pagination invece di `OFFSET`** su tabelle grandi: `OFFSET` profondo degrada al crescere dei dati. Pairing con indice composito sull'ordine. **Diretto per AG Grid SSRM** (paging profondo lato server).
