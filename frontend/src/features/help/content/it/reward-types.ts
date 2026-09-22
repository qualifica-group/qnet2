import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'reward-types',
  title: 'Buoni, Premi e Incentivi',
  summary: 'Le tipologie di buono, premio o incentivo che si possono assegnare a un referente segnalatore.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa servono',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configurano in **Premi e Incentivi › Buoni, Premi e Incentivi** e compaiono nel campo **Tipologia** quando assegni un buono a un referente.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri.'],
            ['Colore', 'Obbligatorio.'],
          ],
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, eliminare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova tipologia**.',
            'Compila **Nome** e **Colore** e premi **Salva**.',
            'Per modificare, scegli **Modifica** sulla riga, cambia i dati e premi **Salva**.',
            'Per eliminare, scegli **Elimina** e conferma.',
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
          text: 'Una tipologia già usata da un buono non si può eliminare.',
        },
      ],
    },
  ],
}

export default guide
