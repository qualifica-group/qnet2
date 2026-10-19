import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'staff-courses',
  title: 'Corsi',
  summary: 'Il catalogo dei corsi interni del personale, con durata, validità e mansioni che li richiedono.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Configurazione**. Il catalogo elenca i corsi di formazione interna del personale; le formazioni fatte dalle persone si registrano nella pagina **Formazione**. Non c’entra con i corsi venduti ai clienti.',
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
            ['Nome', 'Il nome del corso, univoco.'],
            ['Descrizione', 'Una nota facoltativa sul contenuto.'],
            ['Durata', 'Le ore del corso, anche decimali (per esempio 7,5). Facoltativa.'],
            ['Validità', 'Per quanti mesi (da 1 a 240) il corso vale dopo il completamento. Vuota = non scade.'],
            ['Attivo', 'Se il corso si può scegliere quando si registra una formazione.'],
            ['Mansioni che lo richiedono', 'Le persone con una di queste mansioni devono avere il corso valido; se manca risultano “Mancante” nella Situazione.'],
          ],
        },
      ],
    },
    {
      id: 'validity',
      title: 'Validità e scadenza',
      blocks: [
        {
          type: 'paragraph',
          text: 'La scadenza di una formazione si calcola al salvataggio con la validità che il corso ha in quel momento. Cambiare la validità del corso non modifica le formazioni già registrate.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli',
      blocks: [
        {
          type: 'warning',
          text: 'Un corso con formazioni registrate non si può eliminare: disattivalo. I corsi disattivati non si scelgono nelle nuove registrazioni; le formazioni già fatte restano com’erano.',
        },
      ],
    },
  ],
}

export default guide
