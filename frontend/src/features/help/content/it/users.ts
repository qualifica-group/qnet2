import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'users',
  title: 'Utenti',
  summary: 'Il modulo Utenti contiene tutte le persone che possono accedere al gestionale, con dati anagrafici, accesso, ruoli e rapporto di lavoro.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Ogni utente ha una scheda con dati anagrafici, dati di accesso, ruoli e rapporto di lavoro. Il modulo si trova in **Amministrazione › Utenti** e compare solo a chi ha il permesso di vederlo.' },
      ],
    },
    {
      id: 'create-user',
      title: 'Creare un utente',
      blocks: [
        { type: 'steps', items: ['Apri **Amministrazione › Utenti** e fai clic su **Nuovo utente**: il modulo ha lo stesso aspetto della scheda, con le stesse sezioni e **righe chiuse**.', 'Fai clic su una riga (o sulla matita) per aprirla, compila e premi **Fatto** per tenere il valore o **Ripristina** per riportarlo com’era (vedi le tabelle qui sotto). I **Dati anagrafici** sono obbligatori.', '**Contatti** e **Indirizzi** si compilano nella colonna laterale e vengono salvati insieme all’utente.', 'Fai clic su **Salva**. Compare il messaggio "Utente creato con successo." Se esci senza salvare ti viene chiesta conferma.'] },
        { type: 'paragraph', text: 'Il pulsante **Salva** si trova sia in alto sia in fondo al modulo. La fascia dei numeri in alto (ambiti di assegnazione, sedi, ruoli, abbinamento) si aggiorna mentre compili.' },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Avatar**', 'Immagine del profilo (JPEG, PNG, GIF o WebP, massimo 10 MB).'],
            ['Dati anagrafici', 'Dati identificativi della persona o dell’azienda.'],
            ['**Email**', 'Indirizzo usato per accedere. È obbligatorio.'],
            ['**Password**', 'Facoltativa. Lasciala vuota per inviare un invito; se compili **La password dell’account** e **Ripeti la password** è una password provvisoria, di almeno 8 caratteri.'],
            ['**Ruoli**', 'Uno o più ruoli. I permessi dell’utente derivano dai suoi ruoli.'],
            ['**Attivo**', 'Se lo spegni, l’account non può accedere.'],
          ],
        },
        { type: 'paragraph', text: 'Alla creazione l’utente riceve automaticamente un’email di benvenuto. Le modalità sono due: **senza password** l’email contiene il link per scegliere la password; **con password provvisoria** l’email contiene il link per accedere e la password la comunichi tu all’utente (non viene mai inviata per email). In entrambi i casi, al primo accesso l’utente entra subito e trova una finestra di benvenuto che gli consiglia di scegliere la propria password; può rimandare con **Più tardi**.' },
        { type: 'tip', text: 'Se una sezione non compare, il tuo ruolo non ti permette di vederne i campi.' },
      ],
    },
    {
      id: 'assignment-configuration',
      title: 'Configurazione assegnazione',
      blocks: [
        { type: 'paragraph', text: 'Questa sezione decide quali richieste possono arrivare alla persona. Una richiesta la raggiunge solo se la persona appartiene alla sede della richiesta e ha un ambito di assegnazione sulla categoria prodotto richiesta.' },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Assegnabile**', 'Se lo spegni la persona non riceve nessuna assegnazione (Lead, Gestione Richieste, Iscritti, import), anche con sede e ambito di assegnazione. Ambito di assegnazione e sedi restano configurate, pronte per quando lo riaccendi. Di default è acceso.'],
            ['**Ambito su tutte le categorie**', 'Attivalo se la persona segue qualunque categoria prodotto. Le righe dell’ambito di assegnazione spariscono e vengono azzerate.'],
            ['**Ambito di assegnazione**', 'Una riga per ogni coppia funzione aziendale e categoria prodotto. Una categoria madre copre anche le sue sottocategorie. Spunta "Tutte" per coprire ogni categoria di quella funzione.'],
            ['**Sede fisica**', 'La sede operativa principale della persona.'],
            ['**Sedi remote**', 'Altre sedi operative in cui lavora.'],
          ],
        },
        { type: 'paragraph', text: 'Sede fisica e sedi remote valgono allo stesso modo per l’assegnazione. Nella fascia dei numeri in alto, **Abbinamento** indica **Assegnabile** oppure **Non assegnabile**.' },
        { type: 'note', text: 'Gli **Ambiti di assegnazione** sono facoltativi e separate dall’appartenenza: decidono solo le assegnazioni. L’obbligo per una persona attiva (non account di servizio) riguarda le **Funzioni aziendali di appartenenza** (vedi **Profilo e rapporto contrattuale**).' },
        { type: 'warning', text: 'La persona riceve assegnazioni solo se sono vere tutte e tre le condizioni: **Assegnabile** acceso, almeno un ambito di assegnazione e almeno una sede. Il modulo segnala la condizione mancante con "Assegnazione disattivata", "Manca l’ambito di assegnazione" o "Manca la sede".' },
      ],
    },
    {
      id: 'profile-and-contract',
      title: 'Profilo e rapporto contrattuale',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Mansioni**', 'Le mansioni svolte, scelte dall’elenco del modulo Mansioni: puoi indicarne più di una oppure nessuna. Nella scheda, un clic su una mansione apre la sua scheda con l’elenco delle attività (se puoi vedere il modulo Mansioni).'],
            ['**Funzioni aziendali di appartenenza**', 'Le funzioni aziendali di cui la persona fa parte, anche più di una. È lo stesso dato degli **Utenti associati** della funzione: cambiarlo qui o là è equivalente. È obbligatoria per una persona attiva (non account di servizio): altrimenti il salvataggio viene rifiutato e l’errore compare sotto il campo. Non è la stessa cosa degli **Ambiti di assegnazione**, che restano facoltativi.'],
            ['**Responsabile delle funzioni**', 'Le funzioni di cui la persona è responsabile, anche più di una. Si modifica sul posto se puoi modificare sia l’utente sia le funzioni aziendali (altrimenti la riga resta in sola lettura). Una funzione che aveva un altro responsabile passa a questa persona, che lo sostituisce; una funzione tolta dall’elenco resta senza responsabile. Lo stesso dato si cambia anche dalla scheda della funzione aziendale. Se non ce ne sono compare “Non è responsabile di nessuna funzione.”.'],
            ['**Risponde a**', 'I responsabili diretti, scelti tra gli utenti: puoi indicarne più di uno. Il campo c’è sempre, anche per chi è responsabile di una funzione.'],
            ['**Tipo di rapporto**, **Società**, **CCNL e livello**, **In vigore dal**', 'Sola lettura: mostrano la condizione contrattuale in vigore oggi. Si cambiano dallo **Storico contrattuale** (vedi sotto).'],
            ['**Assunto il** / **Cessato il**', 'Date di inizio e fine del rapporto. La cessazione non può precedere l’assunzione.'],
            ['**Durata giornaliera standard**', 'Tempo di lavoro previsto in una giornata.'],
            ['**Durata pausa giornaliera**', 'Durata della pausa quotidiana.'],
          ],
        },
        { type: 'paragraph', text: 'Le sezioni del rapporto di lavoro ci sono anche per un utente che non ha ancora un profilo: il primo salvataggio lo crea. Seguono **Contatti** e **Indirizzi**; in fondo può comparire la sezione **Altri campi**, con i campi personalizzati degli utenti.' },
        { type: 'note', text: 'Attivare **Ambito su tutte le categorie** azzera le righe di **Ambito di assegnazione**: per questo le due righe non hanno la matita se non puoi modificare anche il campo che svuotano.' },
        { type: 'paragraph', text: 'Sotto il nome, nell’intestazione della scheda, compaiono le funzioni aziendali di appartenenza come etichette, con una corona su quelle di cui la persona è responsabile; seguono le funzioni gestite di cui non fa parte. Nell’elenco utenti la colonna **Responsabile delle funzioni** (nascosta di default, filtrabile per funzione) sostituisce la vecchia colonna **Responsabile**.' },
      ],
    },
    {
      id: 'contract-history',
      title: 'Storico contrattuale',
      blocks: [
        { type: 'paragraph', text: 'Nella scheda di un utente già salvato, la sezione **Storico contrattuale** elenca le condizioni contrattuali della persona, dalla più recente: decorrenza, fine, tipo di rapporto, società, CCNL e livello e, se hai il permesso, la retribuzione. Quella in vigore oggi ha l’etichetta **Vigente**. Nella creazione di un utente la sezione dice che lo storico si compila dopo il salvataggio.' },
        { type: 'steps', items: ['Fai clic su **Nuova condizione**.', 'Indica la **Decorrenza** (una data non ancora usata per quella persona), il **Tipo di rapporto**, la **Società** e il **CCNL e livello**.', 'Se hai il permesso sui dati retributivi, indica anche **Retribuzione** e **Periodicità** (mese, anno, ora): vanno insieme.', 'Aggiungi un **Motivo** se serve e premi **Salva**.'] },
        { type: 'list', items: ['Le condizioni non si modificano: ogni cambio è una nuova condizione. La fine di una condizione è il giorno prima della decorrenza della successiva.', 'Per correggere un errore usa **Cancella ultima condizione** (compare solo sull’ultima) e inseriscine una nuova. Cancellare una condizione che non è l’ultima non è possibile.', 'Una condizione con decorrenza futura non cambia **Tipo di rapporto** e **Società** della scheda finché non arriva la data.', 'Le azioni richiedono il permesso di modifica sugli utenti e il campo **Storico contrattuale** modificabile nel tuo ruolo (vedi “Chi vede i dati contrattuali”).'] },
        { type: 'note', text: 'Se la decorrenza esiste già per quella persona il modulo lo segnala sul campo.' },
      ],
    },
    {
      id: 'payment',
      title: 'Pagamento e dati retributivi',
      blocks: [
        { type: 'paragraph', text: 'La sezione **Pagamento** contiene l’**IBAN** su cui pagare la persona e l’**Intestatario**; l’IBAN si legge a gruppi di quattro caratteri e si modifica sul posto come gli altri campi. Se l’IBAN non è valido compare un errore sul campo.' },
        { type: 'paragraph', text: 'Nella stessa sezione, in sola lettura, c’è il **Costo orario**: non si inserisce a mano ma si calcola dalla retribuzione della condizione contrattuale in vigore oggi. Una retribuzione oraria vale così com’è, una mensile si divide per 173 ore, una annua per 2.076 ore (173 × 12); il risultato è arrotondato al centesimo. È la retribuzione lorda, senza contributi né TFR. Senza retribuzione nella condizione in vigore il costo orario resta vuoto.' },
        { type: 'warning', text: 'IBAN e retribuzione sono dati retributivi: li vedi e li modifichi solo con il permesso **Gestione dati retributivi** (users.manageCompensation), che hanno il super-admin, l’admin e il ruolo **Risorse Umane**. Senza il permesso la sezione **Pagamento** (con il costo orario) e i campi retribuzione non compaiono.' },
      ],
    },
    {
      id: 'contract-visibility',
      title: 'Chi vede i dati contrattuali',
      blocks: [
        { type: 'paragraph', text: 'Le sezioni **Rapporto contrattuale**, **Storico contrattuale**, **Dati contrattuali** e **Pagamento** contengono dati delicati. Chi li vede lo decidono i **permessi ai campi** del ruolo (form Ruoli, campi di Utenti): un campo nascosto non compare nella scheda e la sezione sparisce se non resta nessun campo visibile.' },
        { type: 'list', items: ['Il ruolo **Risorse Umane** (ufficio del personale) vede e modifica tutto: anagrafica del personale, storico contrattuale, pagamento, CCNL, mansioni, attività, funzioni aziendali, organigramma, assenze di tutti e tipi di assenza, corsi e formazione di tutti, catalogo competenze e competenze di tutti; in più legge il segnatempo di tutti e le società. Non può cambiare i ruoli di un utente né accedere come un altro utente.', 'L’**admin** e il **super-admin** vedono tutto.', 'Gli altri ruoli dell’installazione (supervisore, coordinatore, marketing, commerciale, supervisore didattica, operatore base) non vedono i dati contrattuali, nemmeno nelle colonne della griglia Utenti (Società, Tipo di rapporto, Stato, Assunto il, Cessato il) né nell’export.', 'Chi ha più ruoli vede un campo se almeno uno dei suoi ruoli glielo mostra.'] },
        { type: 'note', text: 'Chi non vede le date e le durate del rapporto può comunque salvare la scheda: i valori che non vede restano come sono.' },
        { type: 'warning', text: 'Un ruolo nuovo creato dal form Ruoli, senza permessi ai campi, vede tutto: se deve vedere gli utenti ma non i dati contrattuali, nascondi nel suo form i campi di Rapporto, Storico contrattuale (employment.contracts), Dati contrattuali e Pagamento.' },
      ],
    },
    {
      id: 'work-contacts',
      title: 'Recapiti di lavoro',
      blocks: [
        { type: 'paragraph', text: 'Nei **Contatti** di un utente, aggiungendo o modificando un recapito puoi spuntare **Contatto di lavoro**. I recapiti di lavoro mostrano l’etichetta **Lavoro**.' },
      ],
    },
    {
      id: 'termination',
      title: 'Cessazione',
      blocks: [
        { type: 'paragraph', text: 'Quando la data **Cessato il** arriva (o è già passata al salvataggio) la persona viene disattivata e non può più accedere; scheda e storico restano in archivio. Se togli la data la persona non si riattiva da sola: riaccendi **Attivo** a mano.' },
        { type: 'paragraph', text: 'Nell’elenco utenti la colonna **Stato** indica **In forza** o **Cessato** ed è filtrabile.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Modificare, disattivare ed eliminare',
      blocks: [
        { type: 'paragraph', text: 'Ogni riga dell’elenco ha un menu azioni con **Visualizza**, **Elimina**, **Attività** e **Impersona**. Vedi solo le azioni permesse dal tuo ruolo.' },
        { type: 'paragraph', text: 'Nella scheda, sotto il nome, c’è la barra delle azioni: **Reinvia email di benvenuto** (solo se l’utente non ha ancora completato il primo accesso), **Disattiva** oppure **Attiva**, **Impersona** ed **Elimina**. Compaiono solo le azioni possibili per quell’utente e permesse dal tuo ruolo; sul tuo stesso account non trovi **Disattiva**, **Impersona** né **Elimina**.' },
        { type: 'list', items: ['**Modificare:** non esiste una pagina di modifica separata. Apri la scheda con **Visualizza**, passa col mouse sul campo e premi la **matita** (o fai clic sul valore), cambia il valore e premi **Salva**: si salva solo quel campo. **Annulla** (o Esc, o un clic fuori) chiude senza salvare. Contatti, indirizzi e avatar si salvano da soli.', '**Disattivare:** nella scheda premi **Disattiva** e conferma (oppure apri la riga **Attivo** e spegnilo). La persona non può più accedere, ma scheda e storico restano. In alto compare **Non attivo**.', '**Riattivare:** premi **Attiva**. Se la data **Cessato il** è già passata la persona resta non attiva e compare un avviso: togli prima la data. Una persona attiva deve appartenere ad almeno una funzione aziendale, altrimenti l’attivazione viene rifiutata con il motivo.', '**Eliminare:** scegli **Elimina** (dal menu della riga o dalla scheda) e conferma. Dalla scheda, dopo l’eliminazione torni all’elenco.', '**Attività:** mostra lo storico delle modifiche fatte sulla scheda.'] },
        { type: 'warning', text: 'Non puoi eliminare il tuo stesso account, né l’ultimo utente con ruolo super-admin. Non puoi eliminare nemmeno un utente che è operatore di lead o supervisore di opportunità: riassegnali prima.' },
        { type: 'tip', text: 'Se una persona lascia l’azienda, disattivala invece di eliminarla. Così conservi lo storico del suo lavoro.' },
      ],
    },
    {
      id: 'reset-password',
      title: 'Reimpostare la password di un utente',
      blocks: [
        { type: 'steps', items: ['Apri la scheda dell’utente con **Visualizza**.', 'Nella sezione **Credenziali e accesso** premi la matita della riga **Password**.', 'Scrivi la **Nuova password** e poi **Ripeti la password**.', 'Fai clic su **Salva**.'] },
        { type: 'note', text: 'Finché non salvi la riga, la password attuale resta invariata. La password che imposti tu è provvisoria: al prossimo accesso all’utente verrà consigliato di cambiarla. In alternativa, la persona può reimpostarla da sola con **Password dimenticata?** nella pagina di accesso.' },
      ],
    },
    {
      id: 'welcome-email',
      title: 'Email di benvenuto e primo accesso',
      blocks: [
        { type: 'paragraph', text: 'Finché un utente non ha completato il primo accesso, nella sua scheda compare l’etichetta **In attesa di primo accesso**.' },
        { type: 'steps', items: ['Apri la scheda dell’utente.', 'Nella barra delle azioni sotto il nome fai clic su **Reinvia email di benvenuto** (serve il permesso di modifica sugli utenti).', 'Compare il messaggio "Email di benvenuto inviata."'] },
        { type: 'list', items: ['Il link nell’email è valido **72 ore**.', 'Ogni nuovo invio invalida il link precedente.', 'Il reinvio manda sempre il link per scegliere la password, anche se l’utente era stato creato con una password provvisoria.', 'Finché l’utente rimanda la scelta, l’etichetta resta e la finestra di benvenuto ricompare a ogni accesso.', 'In alternativa l’utente può usare **Password dimenticata?** nella pagina di accesso.'] },
        { type: 'note', text: 'L’etichetta e il pulsante spariscono quando l’utente sceglie la propria password.' },
      ],
    },
    {
      id: 'impersonate',
      title: 'Impersonare un utente',
      blocks: [
        { type: 'paragraph', text: 'Con **Impersona** entri nel gestionale come quell’utente e vedi esattamente ciò che vede. Serve per verificare i permessi o capire un problema segnalato.' },
        { type: 'steps', items: ['Nell’elenco utenti apri il menu azioni della riga, oppure apri la scheda dell’utente.', 'Scegli **Impersona** e conferma. Si apre la dashboard.', 'Un avviso in alto mostra "Stai operando come" seguito dal nome dell’utente.', 'Per uscire fai clic su **Torna al tuo account**.'] },
        { type: 'paragraph', text: 'Serve il permesso **Impersona** sul modulo Utenti. Non puoi impersonare te stesso né un utente disattivato. Solo un super-admin può impersonare un altro super-admin. Ogni inizio e fine di impersonificazione resta registrato.' },
        { type: 'warning', text: 'Mentre impersoni, ogni operazione viene fatta a nome dell’altro utente.' },
      ],
    },
  ],
}

export default guide
