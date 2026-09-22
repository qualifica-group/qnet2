import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'operational-sites',
  title: 'Sedi operative',
  summary: "Le Sedi operative sono i luoghi fisici in cui l'organizzazione lavora.",
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le sedi operative',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una sede operativa si riconosce dal suo indirizzo: non ha un nome proprio, è il suo indirizzo. Si sceglie, per esempio, nelle opportunità e nei profili utente.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Premi Nuova sede operativa e compila:',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Alias', 'Un nome breve per riconoscere la sede. Facoltativo.'],
            ['Via', 'Obbligatoria.'],
            ['CAP', 'Il codice postale.'],
            ['Comune', 'Obbligatorio.'],
            ['Sede attiva', 'Se la disattivi, la sede non viene più proposta negli elenchi di scelta.'],
          ],
        },
      ],
    },
    {
      id: 'deactivating-instead-of-deleting',
      title: 'Disattivare invece di eliminare',
      blocks: [
        {
          type: 'tip',
          text: 'Per una sede che non usi più, disattiva Sede attiva invece di eliminarla.',
        },
      ],
    },
  ],
}

export default guide
