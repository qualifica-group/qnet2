import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'formalab',
  title: 'Formalab',
  summary:
    'Formalab collega QNet al gestionale dei corsi: il catalogo delle classi viene sincronizzato in automatico e le commesse GOL collegate a un\'edizione ricevono i dati del corso.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › Servizi esterni › Formalab** ed è riservata al ruolo **super-admin**. La voce di menu, la pagina e la scheda sulla commessa compaiono **solo se l\'integrazione è attiva** nella tua installazione.',
        },
      ],
    },
    {
      id: 'status-card',
      title: 'Le card di stato',
      blocks: [
        {
          type: 'list',
          items: [
            '**Configurazione**: indirizzo del servizio, se le credenziali e la ricezione delle notifiche sono configurate, ogni quanti minuti avviene la sincronizzazione, classi nel catalogo e commesse collegate.',
            '**Ultima sincronizzazione**: esito (in corso, riuscita, fallita), origine, orari di avvio e fine, classi e commesse aggiornate ed eventuale messaggio di errore.',
            '**Chiamate nelle ultime 24 ore**: totale, fallite, in entrata e in uscita.',
            '**Campi corso non trovati**: compare solo se uno dei 12 campi flessibili del corso non esiste più tra gli attributi, perché il suo codice è stato rinominato o eliminato in Configurazione › Attributi. Elenca i codici mancanti: quei campi non vengono aggiornati finché il codice non viene ripristinato.',
          ],
        },
        {
          type: 'note',
          text: 'Le credenziali non sono mai mostrate: vedi solo se sono configurate (Sì/No).',
        },
      ],
    },
    {
      id: 'actions',
      title: 'Le azioni',
      blocks: [
        {
          type: 'table',
          headers: ['Pulsante', 'Cosa fa'],
          rows: [
            ['**Sincronizza ora**', 'Avvia subito la sincronizzazione del catalogo classi; lo stato si aggiorna a pochi secondi dal clic.'],
            ['**Verifica connessione**', 'Prova a contattare il servizio e mostra il tempo di risposta oppure il motivo dell\'errore.'],
            ['**Collega commesse**', 'Collega in automatico le commesse non ancora collegate il cui corso coincide con una sola classe del catalogo. Mostra quante sono state collegate, quante sono ambigue e quante senza corrispondenza.'],
          ],
        },
      ],
    },
    {
      id: 'call-log',
      title: 'Registro chiamate',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sotto le card trovi la tabella di **tutte le chiamate** verso Formalab e da Formalab: data, direzione, metodo, indirizzo, codice di risposta, esito, durata, origine ed eventuale errore. Puoi filtrare per direzione, esito e data. I dati più vecchi di 90 giorni vengono eliminati automaticamente.',
        },
      ],
    },
    {
      id: 'work-order-tab',
      title: 'La scheda Formalab sulla commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sulle commesse che prevedono i campi del corso GOL, o che sono già collegate, compare la scheda **Formalab**. Se la commessa è collegata mostra i dati della classe: stato, date, ore, sede, discenti e ultimo aggiornamento.',
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Formalab** della commessa.',
            'Cerca l\'edizione per codice, corso o città e sceglila dall\'elenco: è il catalogo delle classi Formalab, aggiornato ogni 10 minuti.',
            'Fai clic su **Collega**: i campi del corso della commessa vengono compilati con i dati della classe.',
            'A commessa collegata, **Sincronizza** rilegge subito l\'edizione da Formalab e aggiorna i campi del corso, senza aspettare l\'aggiornamento automatico.',
            'Per cambiare edizione usa **Cambia edizione**; per interrompere il collegamento usa **Scollega** (i campi restano con gli ultimi valori ricevuti).',
          ],
        },
        {
          type: 'note',
          text: 'Collegare, sincronizzare, cambiare e scollegare richiede il permesso di modificare la commessa.',
        },
      ],
    },
    {
      id: 'automatic-sync',
      title: 'Sincronizzazione automatica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni **10 minuti** QNet rilegge il catalogo delle classi. Se una classe collegata a una commessa è cambiata, i campi del corso della commessa vengono aggiornati; se non è cambiato nulla, la commessa non viene toccata.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Cosa succede in caso di errore',
      blocks: [
        {
          type: 'list',
          items: [
            'Se Formalab non è raggiungibile la sincronizzazione risulta **fallita**, l\'errore è nel registro chiamate e il catalogo resta quello dell\'ultima sincronizzazione riuscita; al giro successivo si riprova.',
            'Se l\'aggiornamento di una singola commessa fallisce, sulla sua scheda compare l\'avviso con il motivo e le altre commesse vengono aggiornate normalmente.',
            'Collegando un\'edizione con Formalab non raggiungibile, il collegamento **non viene salvato** e vedi il messaggio di errore.',
            'Se il codice di uno dei campi corso viene rinominato o eliminato, quel campo smette di aggiornarsi: la card Formalab di **Stato del sistema** passa a Degradato con il controllo **Campi corso**, e la cronologia delle sincronizzazioni lo annota.',
            'Lo stato dell\'integrazione compare anche nella pagina **Stato del sistema**.',
          ],
        },
      ],
    },
  ],
}

export default guide
