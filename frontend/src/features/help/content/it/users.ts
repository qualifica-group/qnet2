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
        { type: 'steps', items: ['Apri **Amministrazione › Utenti**.', 'Fai clic su **Nuovo utente**.', 'Compila le sezioni del modulo (vedi le tabelle qui sotto).', 'Fai clic su **Salva**. Compare il messaggio "Utente creato con successo."'] },
        { type: 'paragraph', text: 'Il pulsante **Salva** si trova sia in alto sia in fondo al modulo. Nella colonna laterale un **Riepilogo** si aggiorna mentre compili.' },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Avatar**', 'Immagine del profilo (JPEG, PNG, GIF o WebP, massimo 10 MB).'],
            ['Dati anagrafici', 'Dati identificativi della persona o dell’azienda.'],
            ['**Email**', 'Indirizzo usato per accedere. È obbligatorio.'],
            ['**La password dell’account**', 'Password iniziale, di almeno 8 caratteri.'],
            ['**Ripeti la password**', 'La stessa password, per conferma.'],
            ['**Ruoli**', 'Uno o più ruoli. I permessi dell’utente derivano dai suoi ruoli.'],
            ['**Attivo**', 'Se lo spegni, l’account non può accedere.'],
          ],
        },
        { type: 'tip', text: 'Se una sezione non compare, il tuo ruolo non ti permette di vederne i campi.' },
      ],
    },
    {
      id: 'assignment-configuration',
      title: 'Configurazione assegnazione',
      blocks: [
        { type: 'paragraph', text: 'Questa sezione decide quali richieste possono arrivare alla persona. Una richiesta la raggiunge solo se la persona appartiene alla sede della richiesta e ha una competenza sulla categoria prodotto richiesta.' },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Competente per tutte le categorie**', 'Attivalo se la persona segue qualunque categoria prodotto. Le righe di competenza spariscono e vengono azzerate.'],
            ['**Competenza**', 'Una riga per ogni coppia funzione aziendale e categoria prodotto. Una categoria madre copre anche le sue sottocategorie. Spunta "Tutte" per coprire ogni categoria di quella funzione.'],
            ['**Sede fisica**', 'La sede operativa principale della persona.'],
            ['**Sedi remote**', 'Altre sedi operative in cui lavora.'],
          ],
        },
        { type: 'paragraph', text: 'Sede fisica e sedi remote valgono allo stesso modo per l’assegnazione. In alto nel modulo un’etichetta indica **Assegnabile** oppure **Non assegnabile**.' },
        { type: 'warning', text: 'Senza almeno una competenza e una sede, la persona non riceve nessuna assegnazione. Il modulo lo segnala con "Manca la competenza" o "Manca la sede".' },
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
            ['**Responsabile**', 'Attivalo se la persona è responsabile di altri dipendenti.'],
            ['**Mansione**', 'Descrizione del lavoro svolto (massimo 255 caratteri).'],
            ['**Risponde a**', 'Il responsabile diretto, scelto tra gli utenti.'],
            ['**Tipo di rapporto**', 'Il tipo di rapporto di lavoro.'],
            ['**Società**', 'La società di riferimento.'],
            ['**Qualifica**', 'La qualifica contrattuale.'],
            ['**Assunto il** / **Cessato il**', 'Date di inizio e fine del rapporto. La cessazione non può precedere l’assunzione.'],
            ['**Durata giornaliera standard**', 'Tempo di lavoro previsto in una giornata.'],
            ['**Durata pausa giornaliera**', 'Durata della pausa quotidiana.'],
          ],
        },
        { type: 'paragraph', text: 'Il modulo contiene anche le sezioni **Contatti** e **Indirizzi**. In fondo può comparire la sezione **Altri campi**, con i campi personalizzati degli utenti.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Modificare, disattivare ed eliminare',
      blocks: [
        { type: 'paragraph', text: 'Ogni riga dell’elenco ha un menu azioni con **Visualizza**, **Modifica**, **Elimina**, **Attività** e **Impersona**. Vedi solo le azioni permesse dal tuo ruolo.' },
        { type: 'list', items: ['**Modificare:** scegli **Modifica**, cambia i dati e fai clic su **Salva**.', '**Disattivare:** apri **Modifica** e spegni **Attivo**. La persona non può più accedere, ma scheda e storico restano. In alto compare **Non attivo**.', '**Eliminare:** scegli **Elimina** e conferma.', '**Attività:** mostra lo storico delle modifiche fatte sulla scheda.'] },
        { type: 'warning', text: 'Non puoi eliminare il tuo stesso account, né l’ultimo utente con ruolo super-admin.' },
        { type: 'tip', text: 'Se una persona lascia l’azienda, disattivala invece di eliminarla. Così conservi lo storico del suo lavoro.' },
      ],
    },
    {
      id: 'reset-password',
      title: 'Reimpostare la password di un utente',
      blocks: [
        { type: 'steps', items: ['Apri la scheda dell’utente con **Modifica**.', 'Nella sezione **Autenticazione** scrivi la **Nuova password** e poi **Ripeti la password**.', 'Fai clic su **Salva**.'] },
        { type: 'note', text: 'Se lasci vuoti i campi password, la password attuale resta invariata. In alternativa, la persona può reimpostarla da sola con **Password dimenticata?** nella pagina di accesso.' },
      ],
    },
    {
      id: 'impersonate',
      title: 'Impersonare un utente',
      blocks: [
        { type: 'paragraph', text: 'Con **Impersona** entri nel gestionale come quell’utente e vedi esattamente ciò che vede. Serve per verificare i permessi o capire un problema segnalato.' },
        { type: 'steps', items: ['Nell’elenco utenti apri il menu azioni della riga.', 'Scegli **Impersona**. Si apre la dashboard.', 'Un avviso in alto mostra "Stai operando come" seguito dal nome dell’utente.', 'Per uscire fai clic su **Torna al tuo account**.'] },
        { type: 'paragraph', text: 'Serve il permesso **Impersona** sul modulo Utenti. Non puoi impersonare te stesso né un utente disattivato. Solo un super-admin può impersonare un altro super-admin. Ogni inizio e fine di impersonificazione resta registrato.' },
        { type: 'warning', text: 'Mentre impersoni, ogni operazione viene fatta a nome dell’altro utente.' },
      ],
    },
  ],
}

export default guide
