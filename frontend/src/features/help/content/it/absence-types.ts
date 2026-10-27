import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'absence-types',
  title: 'Tipi di assenza',
  summary: 'L’elenco dei tipi di assenza che si possono scegliere nelle richieste (ferie, permessi, malattia...).',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione › Personale**. Ogni tipo stabilisce se l’assenza scala un saldo e se serve l’approvazione del responsabile.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Il nome del tipo, univoco.'],
            ['Scala un saldo', 'Se le ore approvate si sottraggono dal saldo della persona (come ferie e permessi).'],
            ['Richiede approvazione', 'Se la richiesta deve essere approvata; se no, risulta subito approvata e il responsabile viene solo avvisato.'],
            ['Attivo', 'Se il tipo si può scegliere nelle nuove richieste.'],
            ['Ordine', 'La posizione nell’elenco dei tipi.'],
            ['Giornata intera / Mezza giornata / A ore', 'Le modalità in cui si può chiedere questo tipo. Almeno una deve restare attiva; una richiesta in una modalità non ammessa viene rifiutata.'],
            ['Preavviso minimo', 'I giorni di calendario che devono passare tra l’invio e il primo giorno di assenza. Vuoto = nessun preavviso. Una richiesta inviata con meno preavviso è segnalata come fuori preavviso (non cambia più dopo l’invio); quelle inserite dall’ufficio del personale per conto d’altri non lo sono mai.'],
            ['Colore', 'Il colore del tipo nel calendario, nelle tessere dei saldi e nelle prossime assenze.'],
            ['Spettanza di default', 'Solo per i tipi che scalano un saldo: i giorni annui di chi non ha una spettanza propria, adattati alle ore giornaliere della persona (part-time proporzionale). Vuoto = nessun default.'],
          ],
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Un tipo già usato da una richiesta non si può eliminare: disattivalo. I tipi disattivati non si scelgono nelle nuove richieste; quelle già fatte restano com’erano.',
        },
      ],
    },
  ],
}

export default guide
