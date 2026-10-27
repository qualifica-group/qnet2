import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'company-closures',
  title: 'Chiusure aziendali',
  summary: 'I giorni in cui l’azienda è chiusa: festivi di sede (santo patrono) o ferie collettive.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione › Personale**. Una chiusura può valere come un festivo oppure generare le ferie di tutti in un colpo solo.',
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
            ['Nome', 'Il nome della chiusura (es. “Santo patrono”, “Ferie di Ferragosto”).'],
            ['Dal / Al', 'Il primo e l’ultimo giorno; al massimo 31 giorni.'],
            ['Tipo', '“Come un festivo”: il giorno non è lavorativo, quindi non conta nella durata delle richieste, è segnato nel calendario e il segnatempo non lo richiede. “Ferie collettive”: genera una richiesta approvata per ogni persona.'],
            ['Tipo di assenza', 'Solo per le ferie collettive: il tipo (attivo) con cui si generano le richieste.'],
            ['Tutte le sedi / Sedi', 'La chiusura vale a tutte le sedi oppure solo alle sedi operative scelte. Chi non ha sedi riceve solo le chiusure valide per tutte le sedi.'],
            ['Ricorrente ogni anno', 'Solo per un festivo di un solo giorno (es. il santo patrono): vale ogni anno alla stessa data.'],
          ],
        },
      ],
    },
    {
      id: 'collective-leave',
      title: 'Ferie collettive',
      blocks: [
        {
          type: 'paragraph',
          text: 'Al salvataggio il sistema crea una richiesta **approvata** a giornata intera per ogni persona attiva con profilo d’impiego a cui la chiusura vale, senza notifiche individuali. Subito dopo vedi il riepilogo: quante richieste sono state create e chi è stato saltato, con il motivo (ha già una richiesta inviata o approvata nel periodo, oppure non ha giorni lavorativi).',
        },
        {
          type: 'paragraph',
          text: 'La persona non può annullare una richiesta generata dalla chiusura; può farlo l’ufficio del personale, esentando quella persona.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Una chiusura già iniziata non si può più modificare né eliminare (le ricorrenti sì, e valgono dal salvataggio in poi). Le richieste già inviate non vengono ricalcolate quando si aggiunge un festivo. Modificando o eliminando ferie collettive non iniziate, le richieste generate vengono cancellate e rigenerate.',
        },
      ],
    },
  ],
}

export default guide
