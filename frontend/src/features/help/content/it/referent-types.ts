import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'referent-types',
  title: 'Tipi referente',
  summary: 'I Tipi referente classificano il ruolo dei referenti, per esempio "Titolare" o "Amministrazione".',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i tipi referente',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel menu Anagrafiche, un tipo referente indica il ruolo di un referente, per esempio "Responsabile acquisti". Ha il solo campo Nome.',
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
            ['Nome', 'Il nome del tipo referente, per esempio "Titolare" o "Amministrazione".'],
          ],
        },
      ],
    },
    {
      id: 'usage',
      title: 'Dove si usa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si assegna nella scheda Referente e compare tra le variabili disponibili nei layout di stampa.',
        },
      ],
    },
  ],
}

export default guide
