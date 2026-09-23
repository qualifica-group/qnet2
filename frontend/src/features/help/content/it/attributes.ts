import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'attributes',
  title: 'Attributi',
  summary: 'Gli attributi sono campi aggiuntivi riutilizzabili da assegnare alle categorie prodotto.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono gli attributi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Gli attributi sono campi aggiuntivi riutilizzabili (colore, potenza, durata) da assegnare alle categorie prodotto. Una volta creato, lo stesso attributo può essere usato da più categorie.',
        },
      ],
    },
    {
      id: 'field-types',
      title: 'Tipi di campo',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni attributo indichi Codice, Nome e il tipo di campo:',
        },
        {
          type: 'list',
          items: [
            'Testo',
            'Testo lungo',
            'Numero intero',
            'Numero decimale',
            'Sì/No',
            'Elenco di opzioni',
            'Relazione',
            'Data',
            'Data e ora',
            'Ora',
            'Email',
            'URL',
            'Colore',
          ],
        },
        {
          type: 'note',
          text: "Un Elenco di opzioni richiede almeno un'opzione, con valori tutti diversi; una Relazione richiede il modulo collegato.",
        },
      ],
    },
    {
      id: 'creating-an-attribute',
      title: 'Creare un attributo',
      blocks: [
        {
          type: 'steps',
          items: [
            'Apri Prodotti › Attributi e premi Nuovo attributo.',
            'Inserisci Codice e Nome.',
            'Scegli il Tipo di campo.',
            "Per un Elenco di opzioni aggiungi almeno un'opzione, con Valore ed Etichetta.",
            'Per una Relazione scegli il modulo collegato.',
            'Premi Salva.',
          ],
        },
      ],
    },
    {
      id: 'duplicate-attribute',
      title: 'Duplicare un attributo',
      blocks: [
        {
          type: 'steps',
          items: [
            "Nella riga dell'attributo da copiare scegli Duplica (serve il permesso di creare attributi).",
            'Si apre il form di creazione con tutti i dati dell\'attributo: il Codice ha il suffisso "_copy", il Nome il suffisso " (copia)"; tipo, opzioni, configurazione e altri campi sono gli stessi.',
            'Cambia ciò che serve (il Codice deve restare unico) e premi Salva.',
          ],
        },
        {
          type: 'list',
          items: [
            'La copia è un attributo nuovo: non è assegnata ad alcuna categoria e non ha valori sui prodotti.',
            "L'attributo originale non cambia.",
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
          text: 'Il tipo di campo non si cambia dopo la creazione. Un attributo assegnato a una categoria o con valori sui prodotti non si può eliminare.',
        },
        {
          type: 'note',
          text: 'Per decidere dove un attributo compare (Prodotto, Offerta o Commessa) vai nella categoria prodotto: vedi la guida Categorie Prodotto.',
        },
      ],
    },
  ],
}

export default guide
