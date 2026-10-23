import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absences',
  title: 'Assenze',
  summary: 'Chiedi ferie, permessi e altre assenze vedendo subito durata e saldo; il responsabile le approva anche in blocco, con la squadra sotto gli occhi.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova alla voce **Assenze** del menu. Qui chiedi un’assenza, segui l’esito e controlli quante ore ti restano per ogni tipo.',
        },
        {
          type: 'list',
          items: [
            'Come il Segnatempo, Assenze è aperto a **tutti gli utenti**: ognuno vede le proprie assenze.',
            'Chi è **responsabile** vede anche le assenze delle persone che gli rispondono, anche indirettamente.',
            'Le assenze di **tutti** le vede solo chi ha il permesso: l’ufficio del personale e gli amministratori.',
          ],
        },
      ],
    },
    {
      id: 'summary',
      title: 'Il riepilogo',
      blocks: [
        {
          type: 'paragraph',
          text: 'In cima alla vista **Richieste** trovi **Il mio riepilogo**: le tue **prossime assenze** (un clic apre il dettaglio) e quante **richieste in attesa** di decisione hai.',
        },
        {
          type: 'paragraph',
          text: 'Se ci sono richieste che tocca a te decidere, compare l’avviso "Hai N richieste da approvare" con il pulsante per andarci, e la voce **Assenze** del menu mostra lo stesso numero (anche a menu chiuso).',
        },
        {
          type: 'note',
          text: 'Durate e saldi si leggono in **giorni e ore** (per esempio "1 g 2 h"), calcolati con il tuo orario giornaliero.',
        },
      ],
    },
    {
      id: 'tabs',
      title: 'Le schede',
      blocks: [
        {
          type: 'table',
          headers: ['Scheda', 'Cosa mostra'],
          rows: [
            ['Le mie', 'Le tue richieste.'],
            ['Da approvare', 'Le richieste inviate di cui sei tu a decidere, con il loro numero accanto al nome. L’ufficio del personale vede tutte quelle inviate, tranne le proprie.'],
            ['Tutte', 'Le richieste della tua squadra e le tue. L’ufficio del personale vede quelle di tutti.'],
          ],
        },
        {
          type: 'tip',
          text: 'Se hai richieste da approvare, la pagina si apre direttamente sulla scheda **Da approvare**.',
        },
      ],
    },
    {
      id: 'new-request',
      title: 'Nuova richiesta',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi "Nuova richiesta".',
            'Nella sezione **Assenza** scegli il **Tipo**, poi la modalità: **Giornata intera** (con **Dal** e **Al**), **Mezza giornata** (un giorno, **Mattina** o **Pomeriggio**) oppure **A ore** (un giorno, con la durata). Vedi solo le modalità che il tipo ammette.',
            'Controlla l’**anteprima** sotto le date (vedi sotto).',
            'Nella sezione **Note e allegato** aggiungi, se serve, una **Nota** e un file (pdf, jpg o png), per esempio un certificato.',
            'Premi **Salva bozza** per continuare dopo, oppure **Invia** per mandarla al responsabile. I pulsanti sono in alto a destra e ripetuti in fondo al modulo.',
          ],
        },
        {
          type: 'table',
          headers: ['Nell’anteprima', 'Cosa ti dice'],
          rows: [
            ['Giorni lavorativi e durata', 'Quanto vale la richiesta, in giorni e ore. Weekend, festività e chiusure aziendali sono esclusi ed elencati con il motivo.'],
            ['Saldo attuale e dopo', 'Quanto ti resta del tipo scelto oggi e dopo questa richiesta. Il saldo non blocca l’invio: decide il responsabile.'],
            ['Fuori preavviso', 'Il tipo chiede un preavviso minimo e la richiesta arriva più tardi. Puoi inviarla lo stesso: il responsabile la vede segnata.'],
            ['Sovrapposizioni', 'Hai già un’assenza attiva in quelle date: va cambiato il periodo, altrimenti l’invio non riesce.'],
            ['Colleghi assenti', 'I colleghi della tua funzione o con il tuo stesso responsabile già assenti in quelle date, tra quelli che puoi vedere nel calendario.'],
          ],
        },
        {
          type: 'note',
          text: 'La mezza giornata vale metà del tuo orario giornaliero. L’ufficio del personale può inserire una richiesta **per conto** di un’altra persona scegliendola nel campo **Persona**.',
        },
      ],
    },
    {
      id: 'detail',
      title: 'Il dettaglio',
      blocks: [
        {
          type: 'paragraph',
          text: 'Fai clic su una richiesta per aprirne il dettaglio. In alto trovi il tipo di assenza (con il suo colore), la persona, lo stato, gli eventuali segni **Fuori preavviso** e **Ferie collettive** e, a destra, solo i pulsanti delle azioni che puoi fare (Modifica, Invia, Approva, Rifiuta, Annulla, Elimina).',
        },
        {
          type: 'table',
          headers: ['Sezione', 'Cosa contiene'],
          rows: [
            ['Richiesta', 'Periodo (giornata intera, mezza giornata con mattina o pomeriggio, a ore), durata in giorni e ore, data di invio e nota.'],
            ['Approvazione', 'Approvatori, chi ha deciso e quando, motivo del rifiuto.'],
            ['Contesto', 'Solo per chi può approvare o rifiutare: saldo della persona prima e dopo la richiesta, preavviso e colleghi già assenti nello stesso periodo.'],
            ['Allegato', 'Il file allegato, da scaricare, caricare o togliere.'],
          ],
        },
      ],
    },
    {
      id: 'quick-approval',
      title: 'Approvare in fretta',
      blocks: [
        {
          type: 'list',
          items: [
            'Nella scheda **Da approvare** ogni riga ha i pulsanti **Approva** (con conferma) e **Rifiuta** (chiede il motivo): non serve aprire il dettaglio.',
            'Per decidere più richieste insieme selezionale con le caselle e usa **Approva selezionate** o **Rifiuta selezionate** (un solo motivo per tutte).',
            'Alla fine un messaggio dice quante richieste sono state decise e quali no, con il perché (per esempio già decise da un altro).',
          ],
        },
        {
          type: 'note',
          text: 'Ogni decisione, singola o multipla, manda le stesse notifiche e resta nello storico.',
        },
      ],
    },
    {
      id: 'statuses',
      title: 'Stati di una richiesta',
      blocks: [
        {
          type: 'table',
          headers: ['Stato', 'Significato'],
          rows: [
            ['Bozza', 'Non ancora inviata: puoi modificarla ed eliminarla.'],
            ['Inviata', 'In attesa della decisione del responsabile.'],
            ['Approvata', 'Accettata: le ore sono conteggiate come godute.'],
            ['Rifiutata', 'Respinta, con il motivo indicato dal responsabile.'],
            ['Annullata', 'Ritirata: non conta più.'],
          ],
        },
        {
          type: 'tip',
          text: 'I tipi che non richiedono approvazione (per esempio Malattia) risultano subito approvati; il responsabile viene avvisato.',
        },
      ],
    },
    {
      id: 'approvers',
      title: 'Chi approva',
      blocks: [
        {
          type: 'list',
          items: [
            'Il tuo **responsabile diretto**; se ne hai più di uno, basta uno qualunque.',
            'Se non ne hai, il **responsabile della funzione** a cui appartieni.',
            'In ultima istanza, l’**ufficio del personale**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Oltre al responsabile, l’**ufficio del personale** può approvare o rifiutare qualunque richiesta inviata, tranne le proprie.',
        },
        {
          type: 'note',
          text: 'Nessuno approva le proprie richieste.',
        },
      ],
    },
    {
      id: 'cancelling',
      title: 'Annullare, modificare, eliminare',
      blocks: [
        {
          type: 'list',
          items: [
            'Puoi annullare una richiesta **inviata** o **approvata** che non è ancora iniziata.',
            'L’ufficio del personale può annullare una richiesta inviata o approvata in qualsiasi momento.',
            'Le **bozze** si modificano e si eliminano; le altre richieste no.',
            'Le **ferie collettive** create da una chiusura aziendale (segno "Ferie collettive") non si annullano da soli: solo l’ufficio del personale può toglierle a una persona.',
          ],
        },
      ],
    },
    {
      id: 'notifications',
      title: 'Notifiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'A ogni passaggio (invio, approvazione, rifiuto, annullamento) le persone coinvolte ricevono una notifica nell’app e una email. Chi compie l’azione non riceve nulla.',
        },
        {
          type: 'list',
          items: [
            '**Invio**: gli approvatori della richiesta.',
            '**Approvazione o rifiuto**: chi ha chiesto l’assenza (con il motivo, se rifiutata) e gli altri approvatori.',
            '**Annullamento**: chi ha chiesto l’assenza e gli approvatori.',
            '**Tipo senza approvazione**: chi ha chiesto l’assenza e gli approvatori.',
          ],
        },
        {
          type: 'note',
          text: 'L’ufficio del personale riceve una copia di ogni passaggio. Se a decidere non è il responsabile, il responsabile riceve l’esito. Le ferie collettive create da una chiusura non mandano notifiche.',
        },
      ],
    },
    {
      id: 'balances',
      title: 'I saldi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni tipo che scala un saldo ha una tessera con il **residuo** in giorni e ore e una barra di quanto hai goduto rispetto al maturato. I conti sono per anno: l’anno si sceglie in alto.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Spettanza', 'Quanto ti spetta nell’anno. Se l’ufficio del personale non l’ha impostata per te, vale la spettanza di default del tipo, in giorni, moltiplicata per il tuo orario giornaliero (la tessera lo dice).'],
            ['Riporto', 'Il residuo dell’anno precedente.'],
            ['Maturato', 'Quanto hai maturato finora: 1/12 della spettanza per ogni mese; un mese conta se sei in forza almeno 15 giorni.'],
            ['Goduto', 'Le richieste approvate.'],
            ['In attesa', 'Le richieste inviate, non ancora decise.'],
            ['Residuo', 'Riporto + Maturato − Goduto.'],
          ],
        },
        {
          type: 'note',
          text: 'Il residuo può andare sotto zero: decide il responsabile se approvare. Il 1 gennaio il residuo passa in automatico all’anno nuovo. Solo l’ufficio del personale imposta spettanze e riporto, in giorni o in ore, oppure riporta la persona alla spettanza di default del tipo.',
        },
      ],
    },
    {
      id: 'staff-balances',
      title: 'I saldi di tutti (ufficio del personale)',
      blocks: [
        {
          type: 'paragraph',
          text: 'Chi gestisce le assenze di tutti ha in alto la vista **Saldi**: una tabella con una riga per persona e una colonna per tipo, con il residuo in giorni e ore. Passando sopra una cella leggi spettanza, maturato, goduto e in attesa.',
        },
        {
          type: 'list',
          items: [
            'Filtra per anno, funzione aziendale, sede operativa o nome.',
            'Seleziona le persone e premi **Imposta spettanza**: scegli tipo e giorni (convertiti con l’orario di ognuno, così il part-time ne ha in proporzione) oppure il ritorno al default del tipo. Il riporto non cambia.',
            'Con **Esporta CSV** o **Esporta XLSX** scarichi il file con una riga per persona e tipo.',
          ],
        },
      ],
    },
    {
      id: 'calendar',
      title: 'Il calendario',
      blocks: [
        {
          type: 'paragraph',
          text: 'La vista **Calendario** si sceglie in alto a destra, accanto a **Richieste**: mostra mese per mese chi è assente, una riga per persona e una colonna per giorno. Weekend e festivi sono evidenziati, i festivi hanno il numero in rosso; i giorni di **chiusura aziendale** della sede di ogni persona sono segnati sulla sua riga.',
        },
        {
          type: 'table',
          headers: ['Simbolo', 'Significato'],
          rows: [
            ['Riquadro pieno con la spunta', 'Assenza approvata, nel colore del suo tipo.'],
            ['Riquadro tratteggiato con l’orologio', 'Richiesta inviata, in attesa di decisione.'],
            ['Mezzo riquadro', 'Mezza giornata: a sinistra la mattina, a destra il pomeriggio.'],
            ['Riga Assenti', 'In fondo, quante persone sono assenti ogni giorno.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Chi vedi dipende dai permessi del tuo ruolo:',
        },
        {
          type: 'table',
          headers: ['Permesso', 'Chi vedi'],
          rows: [
            ['Calendario: solo le proprie', 'Te stesso e, se hai dei sottoposti, le persone che ti rispondono, anche indirettamente.'],
            ['Calendario: di tutti', 'Tutte le persone attive.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Con il calendario **solo le proprie** vedi in automatico anche la tua squadra: chi non ha persone che gli rispondono vede solo se stesso. Avere dei sottoposti, da solo, non apre il calendario. Il calendario **di tutti** è dell’ufficio del personale e degli amministratori.',
        },
        {
          type: 'list',
          items: [
            'Con le frecce passi al mese precedente o successivo; "Mese corrente" ti riporta a oggi.',
            'Se vedi più persone, puoi filtrarle per funzione aziendale.',
            'Passando sopra un riquadro leggi tipo, durata e stato. Se puoi già vedere quella richiesta nelle Richieste, un clic ne apre il dettaglio.',
            'Un clic su un giorno lavorativo libero della tua riga apre la nuova richiesta con quella data. L’ufficio del personale può farlo su ogni riga: la richiesta è già intestata a quella persona.',
          ],
        },
        {
          type: 'note',
          text: 'Senza nessuno dei due permessi la vista Calendario non compare. Bozze, richieste rifiutate e annullate non sono nel calendario.',
        },
      ],
    },
  ],
}

export default guide
