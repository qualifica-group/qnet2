import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'companies',
  title: 'Società aziendali',
  summary: 'Le Società aziendali sono le società della tua organizzazione, con denominazione, partita IVA e indirizzo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le società aziendali',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una società aziendale rappresenta una delle società della tua organizzazione. Ogni Società sede appartiene a una società aziendale.',
        },
      ],
    },
    {
      id: 'creating-a-company',
      title: 'Creare una società',
      blocks: [
        {
          type: 'paragraph',
          text: 'Premi Nuova società e compila il modulo:',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Denominazione', 'Il nome della società. Obbligatoria.'],
            ['Partita IVA', 'Il sistema ne controlla la validità.'],
            ['Indirizzo, CAP, Indirizzo (riga 2)', 'La sede legale. Facoltativo, ma se lo inserisci la via è obbligatoria.'],
          ],
        },
      ],
    },
  ],
}

export default guide
