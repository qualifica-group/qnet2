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
          text: 'Si trova in **Configurazione**. Ogni tipo stabilisce se l’assenza scala un saldo e se serve l’approvazione del responsabile.',
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
