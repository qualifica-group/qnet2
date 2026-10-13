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
            'Tabella',
          ],
        },
        {
          type: 'note',
          text: "Un Elenco di opzioni richiede almeno un'opzione, con valori tutti diversi; una Relazione richiede il modulo collegato.",
        },
      ],
    },
    {
      id: 'table-field',
      title: 'Attributo di tipo Tabella',
      blocks: [
        { type: 'paragraph', text: 'Il tipo Tabella raccoglie più righe con le stesse colonne, ad esempio le verifiche ispettive di una commessa (data verifica, ispettore, supporto On/Off Site, fase Stage 1/Stage 2). Nella scheda dell\'attributo compare l\'editor Colonne.' },
        {
          type: 'list',
          items: [
            "Aggiungi le colonne (fino a 20), spostale su e giù o rimuovile. Per ognuna indica Etichetta, Chiave (proposta dall'etichetta: minuscole, numeri e trattini bassi; id è riservata), Tipo e se è Obbligatoria.",
            'Tipi di colonna: Testo, Testo lungo, Intero, Decimale, Sì/No, Scelta (con opzioni Valore ed Etichetta), Data, Data e ora, Ora, Email, URL, Colore.',
            'Selezione riga: aggiunge una colonna di selezione; in ogni record si può selezionare al massimo una riga.',
            'Riepilogo in griglia: la colonna da mostrare negli elenchi e la strategia (Riga selezionata, Massimo, Minimo), oppure nessun riepilogo. Riga selezionata richiede la selezione riga.',
            'Righe minime e massime (fino a 200).',
          ],
        },
        { type: 'paragraph', text: 'Nel form del record (ad esempio la commessa) la tabella si compila con Aggiungi riga; su smartphone ogni riga è una scheda. Se l\'attributo è obbligatorio serve almeno una riga; gli errori compaiono sulla singola cella. Negli elenchi, se c\'è la selezione riga e una riga è selezionata, la cella mostra in linea i valori di quella riga (date nel formato locale, scelte con la loro etichetta, Sì/No con il nome della colonna); altrimenti mostra il riepilogo e il numero di righe (ad esempio "12/10/2026 · 3"). Senza righe la cella è vuota. Passando con il mouse o col focus da tastiera sulla cella compare un riquadro con la tabella completa, con la riga selezionata evidenziata. La colonna non è modificabile in griglia. Nel dettaglio la tabella è in sola lettura, con la riga selezionata evidenziata.' },
        { type: 'note', text: "Le colonne si possono modificare anche con dati già inseriti: una colonna rimossa non viene più mostrata, mentre cambiare il tipo di una colonna non converte i valori già salvati." },
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
            'Il form di creazione ha la stessa forma della scheda: le righe (Codice, Nome, Tipo, Presentazione e i campi personalizzati) sono chiuse. Premi la matita (o il valore) per aprirne una e inserire Codice, Nome e Tipo di campo; Fatto conferma la riga senza salvare nulla, Ripristina la riporta com\'era.',
            "Gli editor che dipendono dal Tipo (Impostazioni del campo, Opzioni, Relazione, Colonne) sono già aperti: per un Elenco di opzioni aggiungi almeno un'opzione, con Valore ed Etichetta.",
            'Per una Relazione scegli il modulo collegato: oltre ai moduli puoi scegliere Province, per collegare una provincia.',
            "In alto vedi l'anteprima del campo, che segue le tue scelte.",
            'Premi Salva (in alto o in fondo): il nuovo attributo viene creato con tutti i dati e si apre la sua scheda.',
          ],
        },
        {
          type: 'note',
          text: 'Se esci dal form (Annulla, chiusura del pannello, cambio pagina) prima di salvare, ti viene chiesto di confermare: i dati inseriti andrebbero persi.',
        },
      ],
    },
    {
      id: 'editing-an-attribute',
      title: 'Modificare un attributo',
      blocks: [
        {
          type: 'paragraph',
          text: "La scheda di un attributo non ha un modulo di modifica: ogni riga si modifica sul posto, una alla volta. Premi la matita (o il valore) su Nome, Presentazione (descrizione, testo di aiuto, segnaposto, icona), Impostazioni del campo, Opzioni, Relazione, Colonne o su un campo personalizzato, cambia il valore e premi Salva; Annulla o Esc lasciano la riga com'era. Ogni Salva invia solo ciò che hai cambiato in quella riga.",
        },
        {
          type: 'list',
          items: [
            'Codice e Tipo si scelgono alla creazione e non hanno la matita.',
            'Le righe che compaiono dipendono dal Tipo: Opzioni per un Elenco di opzioni, Relazione per una Relazione, Colonne per una Tabella.',
            'Senza il permesso di modifica le righe non mostrano la matita.',
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
          text: 'Codice e tipo di campo non si cambiano dopo la creazione. Un attributo assegnato a una categoria o con valori sui prodotti non si può eliminare.',
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
