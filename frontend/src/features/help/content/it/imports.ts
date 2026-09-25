import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'imports',
  title: 'Importa lead',
  summary: 'Con Importa lead carichi molti lead da un file; QNet crea le anagrafiche o aggiorna quelle già presenti.',
  sections: [
    {
      id: 'overview',
      title: 'Come funziona',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina mostra lo storico degli import (**Data**, **Operatore**, **File**, **Record**, **Importati**, **Errori**, **Stato**). Per iniziare clicca **Nuovo import**.',
        },
        {
          type: 'steps',
          items: ['**Caricamento**', '**Mappatura**', '**Revisione**', '**Riepilogo**'],
        },
      ],
    },
    {
      id: 'prepare-the-file',
      title: 'Preparare il file',
      blocks: [
        {
          type: 'list',
          items: [
            'Formato **.csv** oppure **.xlsx**, con i nomi delle colonne nella prima riga, senza nomi ripetuti.',
            'Ogni riga deve avere almeno nome e cognome, ragione sociale, oppure email o telefono (in formato valido).',
            'Per indicare la campagna riga per riga, aggiungi una colonna con il codice campagna.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Campi riempibili: Nome completo, Nome, Cognome, Ragione sociale, Codice fiscale, Partita IVA, Email, Telefono, Indirizzo, CAP, Nazione, Regione, Provincia, Comune, Note, Codice campagna.',
        },
      ],
    },
    {
      id: 'step-1-upload',
      title: 'Passaggio 1 — Caricamento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Trascina il file nel riquadro, oppure clicca per cercarlo.',
            'Clicca **Analizza file** e controlla **Colonne rilevate**, **Righe rilevate** e **Nomi colonna duplicati**.',
            'Clicca **Continua alla configurazione**.',
          ],
        },
      ],
    },
    {
      id: 'step-2-mapping',
      title: 'Passaggio 2 — Mappatura',
      blocks: [
        {
          type: 'steps',
          items: [
            'Per ogni **Colonna del file** scegli il **Campo di destinazione**, oppure **Campo extra** o **Ignora questa colonna**.',
            'In **Configurazione globale** indica **Campagna** (**Una sola per tutto il file** o **Dal file**), **Fonte** e **Prodotti di interesse**.',
            'In **Gestione duplicati** scegli cosa fare con le righe già presenti (vedi tabella).',
            'Clicca **Salva mappatura e continua**.',
          ],
        },
        {
          type: 'table',
          headers: ['Opzione', 'Effetto'],
          rows: [
            ['**Crea sempre un nuovo lead**', 'Crea sempre un nuovo record.'],
            ["**Aggiorna l'anagrafica corrispondente**", "Aggiorna l'anagrafica già presente."],
            ['**Salta le righe corrispondenti**', 'Non importa le righe già presenti.'],
            ['**Decidi in revisione**', 'Decidi riga per riga nel passaggio successivo.'],
          ],
        },
        {
          type: 'paragraph',
          text: 'I duplicati si riconoscono da email, telefono, codice fiscale e partita IVA.',
        },
        {
          type: 'tip',
          text: 'Attiva **Salva questa mappatura come modello riutilizzabile**. Con un file uguale, la volta dopo ti basta cliccare **Applica**.',
        },
      ],
    },
    {
      id: 'step-3-review',
      title: 'Passaggio 3 — Revisione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni riga ha uno stato: **Valida**, **Avviso**, **Errore**, **Duplicato** o **Saltata**.',
        },
        {
          type: 'list',
          items: [
            'Correggi i valori direttamente nella griglia: ogni modifica si salva subito.',
            'Per ogni riga puoi cambiare campagna, operatore, sede, prodotti e localizzazione.',
            'Per i duplicati scegli la **Risoluzione**: **Salta**, **Crea nuovo** o **Aggiorna esistente**.',
            'Su più righe selezionate usa **Assegna operatori** (con **Smistamento equo** compare la lista degli operatori per Sede, tutti selezionati: deseleziona chi non deve ricevere righe) o **Assegna prodotti**.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Poi clicca **Continua al riepilogo**.',
        },
      ],
    },
    {
      id: 'step-4-summary',
      title: 'Passaggio 4 — Riepilogo e conferma',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il riepilogo mostra totali, **Valori selezionati**, **Risoluzione duplicati**, **Colonne mappate**, **Campi extra** e **Avvisi**. **Converti automaticamente in Opportunità** è attiva in partenza.',
        },
        {
          type: 'paragraph',
          text: "Se l'import non è pronto (righe senza operatore o sede, campagna senza linea di prodotto) QNet lo dice: usa **Torna alla revisione**. Altrimenti clicca **Conferma e importa**.",
        },
      ],
    },
    {
      id: 'results-and-errors',
      title: 'Risultato ed errori',
      blocks: [
        {
          type: 'paragraph',
          text: "L'import prosegue in background: puoi chiudere la pagina e riceverai una notifica. Dallo storico clicca **Apri** su un import per vedere **Statistiche**, **Metadati**, **Errori** (con **Scarica il report errori**) e **Record** importati.",
        },
        {
          type: 'tip',
          text: 'Se un import non è stato completato, aprilo e clicca **Riprendi import**.',
        },
      ],
    },
  ],
}

export default guide
