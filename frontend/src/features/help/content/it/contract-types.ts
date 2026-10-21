import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contract-types',
  title: 'Tipologie contrattuali',
  summary: 'L’elenco delle tipologie contrattuali che si scelgono nelle condizioni dello storico contrattuale (tempo determinato, tempo indeterminato, apprendistato, tirocinio...).',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione**. La tipologia si indica in ogni condizione dello **Storico contrattuale** di un utente; quella della condizione in vigore compare nel **Rapporto contrattuale** della scheda e nella colonna **Tipologia contrattuale** dell’elenco utenti.',
        },
        {
          type: 'paragraph',
          text: 'L’elenco parte con **Tempo determinato**, **Tempo indeterminato**, **Apprendistato** e **Tirocinio**; puoi aggiungerne altre.',
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
            ['Nome', 'Il nome della tipologia, univoco.'],
            ['Attiva', 'Se la tipologia si può scegliere nelle nuove condizioni.'],
            ['Ordine', 'La posizione nell’elenco delle tipologie.'],
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
          text: 'Una tipologia già usata da una condizione contrattuale non si può eliminare: disattivala. Le tipologie disattivate non si scelgono nelle nuove condizioni; quelle già salvate restano com’erano.',
        },
      ],
    },
  ],
}

export default guide
