import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'campaigns',
  title: 'Campagne',
  summary: "La campagna è l'azione di marketing che genera i lead, autonoma o collegata a un progetto.",
  sections: [
    {
      id: 'overview',
      title: "Cos'è una campagna",
      blocks: [
        {
          type: 'paragraph',
          text: 'La campagna è l\'azione di marketing che genera i lead. Può essere **Autonoma** oppure **Collegata a un progetto**.',
        },
      ],
    },
    {
      id: 'create-a-campaign',
      title: 'Creare una campagna',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri **Marketing e Lead › Campagne** e clicca **Nuova campagna**.',
            'Compila **Codice** e **Denominazione**.',
            'In **Collegamento al progetto** scegli un **Progetto**, se serve.',
            'Completa date, **Budget totale** e **Target lead**.',
            'Clicca **Salva**.',
          ],
        },
      ],
    },
    {
      id: 'linking-a-project',
      title: 'Collegare un progetto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Collegando un progetto:',
        },
        {
          type: 'list',
          items: [
            'la **Classificazione** (stato, funzione aziendale, categoria prodotto) viene dal progetto ed è in sola lettura;',
            'i livelli geografici del progetto vengono ereditati e bloccati;',
            '**Partner** e **Sede** vengono precompilati (la sede resta modificabile);',
            'sotto il budget compare il **Budget residuo del progetto**.',
          ],
        },
        {
          type: 'tip',
          text: 'Per modificare la classificazione di una campagna collegata, scollega prima il progetto.',
        },
      ],
    },
  ],
}

export default guide
