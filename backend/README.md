# Backend

Applicazione **Laravel (PHP)**. Owner: Backend Agent.

Tutto il codice server-side vive esclusivamente qui. Nessun codice frontend in questa cartella.
Il confine con il frontend è il **contratto API**, documentato in [`../docs/api/`](../docs/api/).

## Stato

- ✅ Laravel **13.x** installato (PHP 8.4)
- ✅ **API-only**: livello web rimosso (niente `routes/web.php`, niente Blade/asset pipeline). Solo `routes/api.php` + health check `GET /up`
- ✅ **Laravel Sanctum** 4.x — auth API a token (`HasApiTokens` sul model `User`)
- ✅ **Spatie Permission** 8.x — ruoli e permessi (`HasRoles` sul model `User`)
- ✅ **Spatie Activitylog** 4.x — audit/activity log
- ✅ **Pest** 4.x — framework di test (config + esempi)
- ✅ Database configurato su **MySQL** (`.env.example`)
- ✅ Backend starter allineato al pattern **Laravel Layered Service Architecture**

### Migrazioni incluse (eseguire `php artisan migrate` con DB attivo)

`users` · `cache` · `jobs` · `personal_access_tokens` (Sanctum) · `roles`/`permissions` (Spatie) · `activity_log` (Spatie)

## Stack

Laravel · PHP 8.4 · MySQL · Laravel Sanctum · Spatie Activitylog · Spatie Permission · Queue Jobs · Events & Listeners · Notifications · Policies

> Riferimento: [`../standards/architecture.md`](../standards/architecture.md).

## Layering (vedi `standards/architecture.md`)

```
Request → FormRequest → Controller → Service → Model → Database
```

Pattern di riferimento: **Laravel Layered Service Architecture**.

- **Controller**: sottili (validazione, autorizzazione, chiamata Service, risposta)
- **Service**: tutta la business logic
- **Model**: dominio (relazioni, scope, accessor/mutator)
- **DTO**: contratti espliciti tra i layer applicativi

## Setup locale

Prerequisiti: PHP 8.4+, Composer 2.x, MySQL/MariaDB.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Crea il database `laravel` (o aggiorna DB_* in .env), poi:
php artisan migrate

php artisan serve   # http://127.0.0.1:8000

# Crea ambiente pulito solo con un utente superadmin
php artisan db:seed

# Crea dati fake per prove
php artisan db:seed --class=DemoDataSeeder

# Crea i dati simil-produzione di Qualifica Group: struttura + catalogo +
# tester + import legacy, nell'ordine giusto
php artisan db:seed --class=QualificaProductionDataSeeder
```

### Dati simil-produzione (`QualificaProductionDataSeeder`)

Punto di ingresso unico per i dati reali del cliente — l'opposto dei seeder
`Demo*`, che fabbricano fixtures finte. Orchestra quattro passi, ognuno
**eseguibile anche da solo e idempotente**:

| # | Seeder | Cosa provvisiona |
|---|---|---|
| 1 | `QualificaTemplateSeeder` | **Solo struttura**: le definizioni di campo personalizzato per modulo (company-sites, products). Nessuna riga di dominio |
| 2 | `QualificaCatalogSeeder` | I dati di riferimento scritti a codice: fonti, tipi premio, albero categorie prodotto (Formazione/Consulenza + GOL regionali) con l'attributo "Ore complessive", e i 252 corsi GOL |
| 3 | `TestUsersSeeder` | Gli account tester + i ruoli supervisor/commercial/marketing |
| 4 | `QualificaLegacyImportSeeder` | Le tabelle di appoggio importate dal gestionale legacy. No-op con warning se `EXTERNAL_MIGRATION_BASE_URL` non è configurato |

Lanciando **il solo passo 2** (`php artisan db:seed --class=QualificaCatalogSeeder`),
a fine seed viene chiesto se importare anche le tabelle di configurazione da
q-crm — cioè se proseguire col passo 4, che è il seguito naturale e dipende da
ciò che il passo 2 ha appena creato:

```
Importare anche le tabelle di configurazione da q-crm? (yes/no) [no]:
```

La domanda **non** compare quando: il seeder è chiamato da codice (incluso
`QualificaProductionDataSeeder`, che lancia l'import da sé come passo 4), il run
è `--no-interaction`, oppure `EXTERNAL_MIGRATION_BASE_URL` non è configurato (non
ci sarebbe nulla da importare). Il default è **no**.

L'ordine è un contratto, non una preferenza:

- il passo 4 **adotta per nome** il catalogo fonti del passo 2 invece di
  duplicarlo, e annida la tassonomia importata sotto la radice `Consulenza`
  creata dal passo 2. Senza, le categorie importate restano a livello zero;
- il passo 4 agisce per conto di un super-admin, che il passo 3 garantisce
  esista (lancia da sé `permissions:sync` e `roles:create-super-admin`).

## Utenti di test (`TestUsersSeeder`)

Passo 3 di `QualificaProductionDataSeeder`, eseguibile anche da solo e
idempotente: NON è agganciato a `DatabaseSeeder` né a `DemoDataSeeder`.

```bash
php artisan db:seed --class=TestUsersSeeder
```

Ogni account è upsertato per **email** (chiave naturale) e ogni ruolo per nome:
un secondo run non duplica nulla. Il seeder lancia da sé `permissions:sync` e
`roles:create-super-admin`, così un run su database vergine non produce mai
ruoli vuoti.

### Account creati

| Nome | Email | Ruolo |
|---|---|---|
| Rosa Falzarano | `rosa.falzarano@qualificagroup.com` | `supervisor` |
| Fabrizio Aliberti | `fabrizio.aliberti@qualificagroup.com` | `supervisor` |
| Ciro Cacciapuoti | `ciro.cacciapuoti@qualificagroup.com` | `super-admin` |
| Commerciale Campania | `campania@commerciale.com` | `commercial` |
| Commerciale Lazio | `lazio@commerciale.com` | `commercial` |
| Umberto Santamaria | `umberto.santamaria@qualificagroup.com` | `marketing` |

**Credenziali di sviluppo:** password **`Qualifica2026!`** per tutti,
dal valore di `config('seeding.test_users_password')` (override con
`TEST_USERS_SEED_PASSWORD` in `.env`). È una credenziale condivisa di comodo per
gli ambienti non di produzione, mai un segreto di produzione.

Chiave **separata** da `config('seeding.password')`, che resta `password` e
continua a servire gli account demo/fixture (`demo@app.com` e i generati da
`DemoUsersSeeder`): i due gruppi non si spostano insieme.

A differenza degli altri seeder, la password viene **riscritta a ogni run**, non
solo alla creazione: così ruotare il valore raggiunge anche i tester già
seedati. Il rovescio è voluto — una password cambiata dall'interfaccia viene
riportata a quella condivisa al re-seed.

### Ruolo `supervisor`

Ha tutte le funzionalità operative del gestionale **tranne**:

- **Amministrazione**: utenti, ruoli, campi personalizzati, migrazioni;
- **Configurazione**: funzioni aziendali, settori, tag, fonti;
- in **Anagrafiche**: tipi di referente, società aziendali, società sedi, sedi operative.

Restano accessibili anagrafiche e referenti, progetti, campagne, lead,
opportunità (con stati e workflow), gestione richieste, prodotti e premi.

### Ruolo `commercial`

Vede e usa **esclusivamente** il modulo **Gestione Richieste**, attraverso il suo
set di permessi dedicato `request-management.*` (mai `opportunities.*`). Ogni
altro modulo, voce di menu e endpoint risponde 403.

Include `request-management.viewAll`: senza, il commerciale vedrebbe solo le
richieste in cui è già assegnato come Operatore e su un database appena seedato
la griglia sarebbe vuota. Togliere quel permesso dalla lista ripristina lo
scoping per operatore.

### Ruolo `marketing`

Vede e usa **esclusivamente** il gruppo di menu **Marketing e Lead**
(`config/navigation.php`): progetti, campagne, lead — con la procedura di
import, che è un'abilità del modulo lead (`leads.import`) — e il pick-list stati
pipeline con cui progetti e campagne si classificano. Su quei quattro moduli ha
il set completo, scritture incluse.

Tutto il resto è chiuso: opportunità, gestione richieste, prodotti, premi,
anagrafiche, configurazione e amministrazione rispondono 403 e non compaiono a
menu.

### Permessi `viewAny` di supporto (residuo noto)

I select relazione dei moduli concessi leggono dagli endpoint `for-select`, che
sono autorizzati dal `viewAny` della risorsa di origine. I tre ruoli
ricevono quindi il solo `viewAny` di alcune risorse altrimenti negate:

- `supervisor`: `business-functions`, `sectors`, `sources`, `referent-types`, `operational-sites`, `users`;
- `commercial`: `registries`, `sources`, `referents`, `operational-sites`, `users`;
- `marketing`: `business-functions`, `referents`, `product-categories`, `operational-sites`, `registries`, `sources`, `users`.

Il `view` — su cui `config/navigation.php` gatea ogni voce di menu — resta negato,
quindi **il menu non mostra quelle sezioni** e nessuna scrittura è possibile.
Residuo accettato: l'endpoint tabellare generico autorizza sullo **stesso**
`viewAny`, perciò quelle liste restano leggibili digitando l'URL a mano.
Chiuderlo richiede un'abilità dedicata di sola selezione, non un seed diverso.

Il comportamento è verificato in `tests/Feature/Users/TestUsersSeederTest.php`,
sia sul menu (`NavigationService`) sia sugli endpoint reali.

## Testing

Pest · Feature Test · Unit Test

```bash
./vendor/bin/pest          # oppure: php artisan test
```
