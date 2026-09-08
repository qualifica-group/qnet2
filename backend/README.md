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
# tester + import legacy, nell'ordine giusto. Nessuna riga finta
php artisan db:seed --class=QualificaProductionDataSeeder

# Solo per prove: la pipeline commerciale di esempio (anagrafiche, lead,
# opportunita', gestione richieste) sopra i dati del comando precedente
php artisan db:seed --class=QualificaSampleDataSeeder

# Stessa cosa, ma decidendo da terminale quante righe
php artisan qualifica:seed-sample --leads=200 --opportunities=50 --requests=30
```

### Dati simil-produzione (`QualificaProductionDataSeeder`)

Punto di ingresso unico per i dati reali del cliente — l'opposto dei seeder
`Demo*`, che fabbricano fixtures finte. **Non produce nessuna riga finta**: la
pipeline commerciale di esempio (anagrafiche, lead, opportunità, gestione
richieste) è uscita da questa catena il 2026-09-08 e vive in
`QualificaSampleDataSeeder`, on-demand.

Orchestra sette passi, ognuno **eseguibile anche da solo e idempotente**:

| # | Seeder | Cosa provvisiona |
|---|---|---|
| 1 | `QualificaTemplateSeeder` | **Solo struttura**: le definizioni di campo personalizzato per modulo (company-sites, products) e il layout documento delle offerte. Nessuna riga di dominio |
| 2 | `QualificaCatalogSeeder` | I dati di riferimento scritti a codice: fonti, tipi premio, albero categorie prodotto (Formazione/Consulenza + GOL regionali) con i suoi attributi, e i corsi GOL |
| 3 | `QualificaTaskTaxonomySeeder` | Le quattro lookup di classificazione dei Task + la pick-list degli stati |
| 4 | `TestUsersSeeder` | Gli account tester + i ruoli supervisor/commercial/marketing |
| 5 | `QualificaLegacyImportSeeder` | Le tabelle di appoggio importate dal gestionale legacy. No-op con warning se `EXTERNAL_MIGRATION_BASE_URL` non è configurato |
| 6 | `QualificaBusinessFunctionLinkSeeder` | Assegna la radice "Formazione" del passo 2 alle funzioni aziendali importate dal passo 5 |
| 7 | `QualificaOperatorSiteLinkSeeder` | Dà agli account del passo 4 la sede operativa importata dal passo 5, così sono selezionabili come operatori |

Lanciando **il solo passo 2** (`php artisan db:seed --class=QualificaCatalogSeeder`),
a fine seed viene chiesto se importare anche le tabelle di configurazione da
q-crm — cioè se proseguire col passo 5, che è il seguito naturale e dipende da
ciò che il passo 2 ha appena creato:

```
Importare anche le tabelle di configurazione da q-crm? (yes/no) [no]:
```

La domanda **non** compare quando: il seeder è chiamato da codice (incluso
`QualificaProductionDataSeeder`, che lancia l'import da sé come passo 5), il run
è `--no-interaction`, oppure `EXTERNAL_MIGRATION_BASE_URL` non è configurato (non
ci sarebbe nulla da importare). Il default è **no**.

L'ordine è un contratto, non una preferenza:

- il passo 5 **adotta per nome** il catalogo fonti del passo 2 invece di
  duplicarlo, e annida la tassonomia importata sotto la radice `Consulenza`
  creata dal passo 2. Senza, le categorie importate restano a livello zero;
- il passo 5 agisce per conto di un super-admin, che il passo 4 garantisce
  esista (lancia da sé `permissions:sync` e `roles:create-super-admin`);
- i passi 6 e 7 servono entrambe le sponde (catalogo del passo 2 / account del
  passo 4 da un lato, righe importate dal passo 5 dall'altro): per questo
  stanno dopo l'import e non dentro i passi che li precedono.

### Dati di esempio (`QualificaSampleDataSeeder`)

La pipeline commerciale **fabbricata**, on-demand e separata dal seed di
produzione (direttiva utente 2026-09-08):

```bash
php artisan db:seed --class=QualificaSampleDataSeeder
```

| # | Seeder | Cosa provvisiona |
|---|---|---|
| 1 | `QualificaSampleLeadSeeder` | Un progetto, una campagna, un'Anagrafica per lead e un batch di 40 lead, 12 dei quali già convertiti in opportunità |
| 2 | `QualificaSampleOpportunitySeeder` | 10 opportunità senza lead alle spalle (trattativa diretta), sulle Anagrafiche del passo 1 |
| 3 | `QualificaSampleRequestSeeder` | 8 richieste di Gestione Richieste, ognuna un'Opportunità + la sua Offerta **con una riga d'offerta valorizzata**, create dal vero write path del modulo |

**Prerequisito**: lanciare prima `QualificaProductionDataSeeder`. Serve l'albero
categorie con le funzioni aziendali effettive (senza, nessun lead converte e
nessuna richiesta ha `product_lines` valide), più gli account e le sedi
operative. Su database vergine ogni passo si salta da sé con un warning invece
di seminare a metà.

**Si può rilanciare quante volte si vuole: ogni run AGGIUNGE un batch**, non
converge (direttiva utente 2026-09-08). Solo il progetto e la campagna di
esempio vengono riusati per nome — quelli restano uno soltanto. I dati fake non
sono a seed fisso, quindi ogni run produce righe diverse.

#### Decidere le quantità da terminale

Le dimensioni dei batch si passano come flag al comando dedicato, che è un
front-end sullo stesso seeder (stessi passi, stesso ordine, nessuna seconda
implementazione):

```bash
php artisan qualifica:seed-sample --leads=200 --converted-leads=60 \
    --opportunities=50 --requests=30

php artisan qualifica:seed-sample --help   # elenco dei flag
```

| Flag | Default | Cosa controlla |
|---|---|---|
| `--leads` | 40 | Quanti lead aggiunge il passo 1 (e il `target_lead` di progetto/campagna) |
| `--converted-leads` | 12 | Quanti di quei lead diventano opportunità. È un **tetto**: converte un lead ogni tre, quindi il numero effettivo non supera mai un terzo di `--leads` |
| `--opportunities` | 10 | Quante trattative diritte aggiunge il passo 2 |
| `--requests` | 8 | Quante richieste aggiunge il passo 3 |

Un flag assente prende il default del suo seeder, quindi
`db:seed --class=QualificaSampleDataSeeder` resta equivalente al comando senza
flag. Un flag con un valore non intero positivo (`--leads=abc`) fa fallire il
comando con exit code 2: non semina un batch vuoto fingendo di aver funzionato.
In produzione il comando chiede conferma prima di scrivere (`--force` per
saltarla).

L'ordine è un contratto: i passi 2 e 3 consumano entrambi le Anagrafiche del
passo 1 e un'anagrafica porta **una sola opportunità aperta per volta**, quindi
ognuno prende quelle che il precedente ha lasciato libere. È anche il vero
limite di un run: finite le anagrafiche libere, il batch è vuoto — per averne
altre si rilancia la catena, che al passo 1 ne crea 40 nuove.

## Utenti di test (`TestUsersSeeder`)

Passo 4 di `QualificaProductionDataSeeder`, eseguibile anche da solo e
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
