import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'formalab',
  title: 'Formalab',
  summary:
    'Formalab collega QNet al gestionale dei corsi: il catalogo delle classi viene sincronizzato in automatico e le commesse delle categorie collegate a Formalab ricevono i dati dell\'edizione nei campi scelti nella mappatura della categoria.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › Servizi esterni › Formalab** ed è riservata al ruolo **super-admin**. La voce di menu, la pagina, la sezione sulla categoria e la scheda sulla commessa compaiono **solo se l\'integrazione è attiva** nella tua installazione.',
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
            '**Campi mappati non trovati**: compare solo se un campo della commessa usato nella mappatura di una categoria non esiste più tra gli attributi, perché il suo codice è stato rinominato o eliminato in Configurazione › Attributi. Elenca i codici mancanti: quei campi non vengono aggiornati finché il codice non viene ripristinato o la mappatura corretta.',
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
            ['**Sincronizza ora**', 'Avvia subito la sincronizzazione: rilegge il catalogo classi, collega le commesse non ancora collegate il cui nome corso coincide con una sola classe e aggiorna i campi delle commesse collegate. Lo stato si aggiorna a pochi secondi dal clic.'],
            ['**Verifica connessione**', 'Prova a contattare il servizio e mostra il tempo di risposta oppure il motivo dell\'errore.'],
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
      id: 'category-settings',
      title: 'Collegare una categoria a Formalab',
      blocks: [
        {
          type: 'paragraph',
          text: 'Quali commesse si collegano a Formalab lo decide la **categoria di prodotto**. Nel dettaglio della categoria (Configurazione › Categorie prodotto) trovi la sezione **Formalab** con due impostazioni.',
        },
        {
          type: 'list',
          items: [
            '**Collegata a Formalab**: si decide sulla **categoria radice** e vale per tutte le sue sottocategorie, che la mostrano come "Ereditato da …" e non possono cambiarla.',
            '**Mappatura dei campi**: per ogni campo di Formalab (nome corso / edizione, stato, date, ore, decreti, sede, discenti…) scegli il campo della commessa in cui scriverlo. L\'elenco propone solo i campi commessa della categoria di tipo compatibile; i campi lasciati "Non collegato" non vengono scritti.',
            'Il **Nome corso / edizione** è obbligatorio: è il campo da cui la commessa trova la sua edizione su Formalab.',
            'Una sottocategoria usa la mappatura della categoria superiore ("Mappatura ereditata da …"); attivando **Mappatura propria** può definirne una diversa, e spegnendola torna a ereditare.',
            'Sulle commesse delle categorie collegate i campi mappati sono **in sola lettura**: li aggiorna solo Formalab e accanto al nome del campo compare l\'icona di sincronizzazione (passandoci sopra il mouse si legge che il valore arriva da Formalab). Fa eccezione il campo del **Nome corso / edizione**, che resta modificabile perché serve a trovare l\'edizione.',
          ],
        },
        {
          type: 'steps',
          items: [
            'Apri la categoria radice e, nella sezione **Formalab**, fai clic su **Modifica**.',
            'Attiva **Collegata a Formalab**.',
            'Controlla la mappatura: se la categoria ha i campi del corso GOL, sono già proposti. Scegli o correggi il campo per ogni dato che vuoi ricevere.',
            'Fai clic su **Salva**.',
          ],
        },
        {
          type: 'note',
          text: 'Modificare la sezione richiede il permesso di modificare la categoria. Se una categoria viene scollegata, le sue commesse già sincronizzate smettono di aggiornarsi e la loro scheda mostra il motivo.',
        },
      ],
    },
    {
      id: 'work-order-tab',
      title: 'La scheda Formalab sulla commessa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Sulle commesse di una categoria collegata a Formalab, o che sono già sincronizzate, compare la scheda **Formalab**. L\'edizione non si sceglie a mano: il sistema la trova dal campo della commessa mappato sul **Nome corso / edizione** (per le commesse GOL il **Nome Corso / Ed.**), che deve essere scritto esattamente come su Formalab ("codice - titolo"). Una volta sincronizzata, la scheda mostra i dati della classe: stato, date, ore, sede, discenti e ultimo aggiornamento.',
        },
        {
          type: 'steps',
          items: [
            'Controlla che il campo del nome corso (per le commesse GOL il **Nome Corso / Ed.**) sia compilato.',
            'Apri la scheda **Formalab** e fai clic su **Sincronizza**.',
            'Il sistema cerca nel catalogo Formalab l\'edizione con lo stesso nome, la rilegge subito da Formalab e copia i dati nei campi mappati dalla categoria. Da quel momento la commessa si aggiorna anche da sola ogni 10 minuti.',
            'Se cambi il nome corso, premi di nuovo **Sincronizza**: il sistema passa all\'edizione con il nuovo nome.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Commessa non collegabile**: la sua categoria non è collegata a Formalab, oppure la mappatura non indica il campo del nome corso.',
            '**Nome corso vuoto**: compila prima il campo del nome corso.',
            '**Nessuna edizione con questo nome**: il nome non coincide con quello su Formalab, oppure l\'edizione è nuova e il catalogo non l\'ha ancora ricevuta (si aggiorna ogni 10 minuti).',
            '**Più edizioni con questo nome**: su Formalab il nome non è univoco e il sistema non può scegliere.',
          ],
        },
        {
          type: 'note',
          text: 'Sincronizzare richiede il permesso di modificare la commessa.',
        },
      ],
    },
    {
      id: 'automatic-sync',
      title: 'Sincronizzazione automatica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni **10 minuti** QNet rilegge il catalogo delle classi e collega le commesse non ancora collegate il cui nome corso coincide con una sola classe, scrivendo subito i loro campi. Se una classe collegata a una commessa è cambiata, i campi mappati della commessa vengono aggiornati; se non è cambiato nulla, la commessa non viene toccata.',
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
            'Se premi **Sincronizza** mentre Formalab non è raggiungibile, i campi della commessa **non vengono toccati** e vedi il messaggio di errore.',
            'Se il codice di un campo usato in una mappatura viene rinominato o eliminato, quel campo smette di aggiornarsi: la card Formalab di **Stato del sistema** passa a Degradato con il controllo **Campi mappati**, e la cronologia delle sincronizzazioni lo annota.',
            'Lo stato dell\'integrazione compare anche nella pagina **Stato del sistema**.',
          ],
        },
      ],
    },
  ],
}

export default guide
