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
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Anagrafiche › Tipi referente** e premi il pulsante per creare un nuovo tipo referente.', 'Si apre la scheda con le righe chiuse: premi la matita (o il valore) su **Nome**, scrivilo e premi **Fatto** (**Ripristina** riporta la riga com’era).', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.' },
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
