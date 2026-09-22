import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'field-change-requests',
  title: 'Richieste di modifica',
  summary:
    'Alcuni campi sono protetti: chi non può modificarli direttamente può proporre una modifica, che un responsabile approva o rifiuta.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i campi protetti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Oggi è protetto solo il campo **Fonte**, in **Gestione Richieste** e in **Gestione Iscritti**. Chi non può modificarlo direttamente può proporre una modifica, che un responsabile approva o rifiuta.',
        },
        {
          type: 'steps',
          items: [
            'Viene scelta una nuova Fonte e inviata la richiesta.',
            'La proposta resta **In attesa**.',
            'I gestori che possono decidere ricevono una notifica.',
            'Se **Approva**, il nuovo valore viene applicato.',
            'Se **Rifiuta**, il valore resta invariato.',
          ],
        },
      ],
    },
    {
      id: 'proposing-a-change',
      title: 'Proporre una modifica',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri la richiesta, oppure clicca direttamente la cella **Fonte** in tabella.',
            'Scegli il nuovo valore: si apre **Proponi una modifica a Fonte**, con il valore **Attuale** e quello **Proposto**.',
            'Se vuoi, spiega il motivo (massimo 1000 caratteri).',
            'Premi **Invia richiesta**.',
          ],
        },
        {
          type: 'note',
          text: 'Il valore cambia solo dopo l\'approvazione. Le proposte in attesa compaiono nella sezione **Richieste di modifica** della richiesta.',
        },
      ],
    },
    {
      id: 'approving-or-rejecting',
      title: 'Approvare o rifiutare',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le proposte si trovano in **Gestione Richieste › Richieste di modifica**, con valore attuale, valore richiesto, motivazione e richiedente.',
        },
        {
          type: 'steps',
          items: [
            'Apri la proposta.',
            'Premi **Approva** oppure **Rifiuta**.',
            'Se vuoi, aggiungi una **Nota**.',
            'Premi **Conferma**.',
          ],
        },
        {
          type: 'note',
          text: 'I pulsanti compaiono solo a chi può gestire le richieste di modifica; il richiedente può sempre consultare le proprie proposte.',
        },
        {
          type: 'warning',
          text: 'Se nel frattempo il campo è stato modificato, l\'approvazione viene bloccata e la proposta resta **In attesa**. Una proposta già gestita non si modifica più.',
        },
      ],
    },
    {
      id: 'notifications',
      title: 'Notifiche',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una nuova proposta avvisa (campanella ed email) chi può consultare le richieste di modifica, tranne il richiedente. L\'esito della decisione viene notificato al richiedente.',
        },
      ],
    },
  ],
}

export default guide
