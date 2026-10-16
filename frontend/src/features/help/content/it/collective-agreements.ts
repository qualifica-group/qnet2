import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'collective-agreements',
  title: 'CCNL',
  summary: 'I CCNL e i loro livelli si scelgono nella condizione contrattuale di una persona.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono i CCNL',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un CCNL (contratto collettivo nazionale di lavoro) raccoglie i **livelli** di inquadramento. Nella scheda utente, la condizione contrattuale di una persona indica il livello applicato; il CCNL è quello a cui il livello appartiene.',
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
            ['Nome', 'Il nome del CCNL, univoco.'],
            ['Codice', 'Codice facoltativo del CCNL.'],
            ['Livelli', 'Elenco ordinato dei livelli: ognuno ha un codice obbligatorio (univoco nel CCNL) e una descrizione facoltativa.'],
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
            'Premi **Nuovo CCNL** e compila nome e codice.',
            'Nella riga **Livelli** usa **Aggiungi livello**, compila codice e descrizione; con le frecce sposti un livello su o giù, con il cestino lo rimuovi. Poi premi **Salva**.',
            'Per modificare, apri il pannello con **Visualizza**, premi la matita sulla riga e salva: viene salvata solo quella riga. La lista dei livelli viene salvata per intero, nell’ordine mostrato.',
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
          text: 'Un livello usato da una condizione contrattuale non si può rimuovere, e un CCNL con un livello in uso non si può eliminare: cambia prima il livello nelle condizioni interessate.',
        },
      ],
    },
  ],
}

export default guide
