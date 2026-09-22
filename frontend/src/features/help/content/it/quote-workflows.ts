import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'quote-workflows',
  title: 'Configuratore Stati Offerta',
  summary:
    "Il configuratore workflow definisce gli stati di lavorazione delle offerte, con criteri che decidono a quali offerte si applica ciascun workflow.",
  sections: [
    {
      id: 'overview',
      title: "Cos'è un workflow",
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova nel menu **Opportunità e Commesse** (pagina **Configuratore Stati Offerta**) e definisce gli stati di lavorazione delle offerte. Ogni workflow ha un nome, dei criteri che decidono a quali offerte si applica e un proprio elenco di stati.',
        },
      ],
    },
    {
      id: 'criteria',
      title: 'Criteri',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un workflow vale per le offerte che soddisfano tutti i suoi criteri (almeno uno): **Fonte**, **Funzione aziendale**, **Categoria prodotto**, **Categoria prodotto (ramo)** (include le sottocategorie) e i campi personalizzati di tipo Relazione delle Opportunità. Fonte e campi personalizzati vengono dall\'opportunità ("Ereditato dall\'opportunità").',
        },
        {
          type: 'paragraph',
          text: 'Se un\'offerta corrisponde a più workflow, vince quello con più criteri; a parità, quello con la categoria più vicina. Due workflow non possono avere gli stessi criteri.',
        },
      ],
    },
    {
      id: 'processing-statuses',
      title: 'Stati di lavorazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni workflow nasce con tre stati fissi: **Aperto** in testa, **Chiuso con esito positivo** e **Chiuso con esito negativo** in coda. In mezzo aggiungi stati con **Aggiungi stato** e li riordini trascinandoli. Per ognuno indichi **Nome stato**, **Gruppo** (Aperto, In attesa, Validato, Chiuso con esito positivo, Chiuso con esito negativo), **Descrizione dello stato** e **Richiede una nota esplicativa**.',
        },
        {
          type: 'paragraph',
          text: 'Le offerte senza workflow attivo usano gli **Stati di default globali**, modificabili con **Stati di default**.',
        },
      ],
    },
    {
      id: 'typical-path',
      title: 'Percorso tipico',
      blocks: [
        {
          type: 'list',
          items: [
            'Aperto è lo stato di partenza di ogni offerta.',
            'Da Aperto si passa agli stati In attesa oppure direttamente a uno stato Validato.',
            'Dagli stati In attesa o Validato si arriva a Chiuso con esito positivo oppure a Chiuso con esito negativo.',
            "Chiuso con esito positivo fa nascere il contratto; se l'offerta esce da questo stato, il contratto viene sospeso.",
          ],
        },
        {
          type: 'paragraph',
          text: "QNet non impone un ordine fisso, ma applica queste regole:",
        },
        {
          type: 'list',
          items: [
            "un'offerta può passare solo agli stati del workflow che le corrisponde in quel momento;",
            'per gli stati Validato o Chiuso serve almeno una riga prodotto ("Non puoi portare l\'offerta a questo stato senza almeno una riga prodotto.");',
            "se lo stato richiede una nota, va scritta; viene salvata tra le note dell'opportunità;",
            'entrando in Chiuso con esito positivo nasce il contratto, salvo che la categoria abbia **Prevede un contratto** spento;',
            "se l'offerta esce da Chiuso con esito positivo, il contratto passa a **Sospeso** (non viene cancellato);",
            "se i dati dell'offerta cambiano, QNet ricalcola il workflow: lo stato resta se esiste anche nel nuovo, altrimenti diventa lo stato fisso equivalente o **Aperto**;",
            'eliminando un workflow, le sue offerte passano al workflow corrispondente o agli stati di default.',
          ],
        },
        {
          type: 'tip',
          text: 'Per sospendere un workflow, spegni **Attivo** invece di eliminarlo.',
        },
      ],
    },
  ],
}

export default guide
