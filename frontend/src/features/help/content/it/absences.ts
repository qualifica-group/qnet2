import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absences',
  title: 'Assenze',
  summary: 'Chiedi ferie, permessi e altre assenze; il responsabile le approva e tu vedi i tuoi saldi in ore.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova alla voce **Assenze** del menu. Qui chiedi un’assenza, segui l’esito e controlli quante ore ti restano per ogni tipo.',
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
            ['Da approvare', 'Le richieste inviate di cui sei tu a decidere.'],
            ['Tutte', 'Le richieste della tua squadra e le tue. L’ufficio del personale vede quelle di tutti.'],
          ],
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
            'Scegli il **Tipo** di assenza e indica se è a **giornata intera** o **a ore** (le ore valgono per un solo giorno).',
            'Indica **Dal** e **Al** (per le ore, un solo giorno) e, se serve, una **Nota**.',
            'Se vuoi, allega un file (pdf, jpg o png), per esempio un certificato.',
            'Premi **Salva bozza** per continuare dopo, oppure **Invia** per mandarla al responsabile.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Nel modulo vedi il residuo del tipo scelto e, per le richieste a ore, quante ore ti resterebbero dopo. Il saldo non blocca l’invio: decide il responsabile.',
        },
        {
          type: 'note',
          text: 'Per le giornate intere la durata si calcola quando invii la richiesta: si contano i soli giorni lavorativi (weekend e festività esclusi) e si moltiplicano per il tuo orario giornaliero. Non puoi sovrapporre due richieste ancora attive.',
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
      ],
    },
    {
      id: 'balances',
      title: 'I saldi',
      blocks: [
        {
          type: 'paragraph',
          text: 'I saldi sono in ore, per tipo di assenza e per anno, e solo per i tipi che scalano un saldo.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Spettanza', 'Le ore annue a cui hai diritto.'],
            ['Riporto', 'Il residuo dell’anno precedente.'],
            ['Maturato', 'Le ore maturate finora: 1/12 della spettanza per ogni mese; un mese conta se sei in forza almeno 15 giorni.'],
            ['Goduto', 'Le ore delle richieste approvate.'],
            ['In attesa', 'Le ore delle richieste inviate, non ancora decise.'],
            ['Residuo', 'Riporto + Maturato − Goduto.'],
          ],
        },
        {
          type: 'note',
          text: 'Il residuo può andare sotto zero: decide il responsabile se approvare. Il 1 gennaio il residuo passa in automatico all’anno nuovo. Solo l’ufficio del personale imposta spettanze e riporto.',
        },
      ],
    },
    {
      id: 'calendar',
      title: 'Il calendario',
      blocks: [
        {
          type: 'paragraph',
          text: 'La vista **Calendario**, accanto a **Richieste**, mostra mese per mese chi è assente: una riga per persona, una colonna per giorno. Weekend e festivi sono evidenziati, i festivi hanno il numero in rosso.',
        },
        {
          type: 'table',
          headers: ['Simbolo', 'Significato'],
          rows: [
            ['Riquadro pieno con la spunta', 'Assenza approvata.'],
            ['Riquadro tratteggiato con l’orologio', 'Richiesta inviata, in attesa di decisione.'],
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
            ['Calendario: solo le proprie', 'Solo te stesso.'],
            ['Calendario: proprie e della squadra', 'Te e le persone che ti rispondono, anche indirettamente.'],
            ['Calendario: di tutti', 'Tutte le persone attive.'],
          ],
        },
        {
          type: 'list',
          items: [
            'Con le frecce passi al mese precedente o successivo; "Mese corrente" ti riporta a oggi.',
            'Se vedi più persone, puoi filtrarle per funzione aziendale.',
            'Passando sopra un riquadro leggi tipo, durata e stato. Se puoi già vedere quella richiesta nelle Richieste, un clic ne apre il dettaglio.',
          ],
        },
        {
          type: 'note',
          text: 'Senza nessuno dei tre permessi la vista Calendario non compare. Bozze, richieste rifiutate e annullate non sono nel calendario.',
        },
      ],
    },
  ],
}

export default guide
