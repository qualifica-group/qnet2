import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-checklists',
  title: 'Checklist documentali',
  summary: 'Elenchi di documenti da raccogliere, sincronizzati con ArchivioSmart.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Configurazione › Documenti › Checklist documentali**. Una checklist definisce i documenti da raccogliere. Ogni salvataggio viene inviato automaticamente ad **ArchivioSmart**, che si occupa della raccolta e della validazione dei documenti con l\'AI. Il **codice** della checklist servirà per creare le richieste di documenti.',
        },
      ],
    },
    {
      id: 'checklist-fields',
      title: 'Campi della checklist',
      blocks: [
        {
          type: 'list',
          items: [
            '**Nome**: il nome mostrato negli elenchi.',
            '**Codice**: solo lettere, cifre, _ e -; viene salvato in maiuscolo ed è unico. Non si può più modificare dopo la prima sincronizzazione riuscita.',
            '**Descrizione**: nota libera.',
            '**Attiva**: per smettere di usare una checklist è meglio disattivarla che eliminarla.',
          ],
        },
      ],
    },
    {
      id: 'items',
      title: 'Le voci',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una checklist può avere fino a **200 voci**, una per documento richiesto. Riordinale trascinandole oppure con **Sposta su** / **Sposta giù**.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Significato'],
          rows: [
            ['Nome, Descrizione', 'Come si chiama il documento e cosa deve contenere.'],
            ['Obbligatoria', 'Il documento è indispensabile.'],
            ['Gruppo', 'Nel portale del cliente ogni gruppo è una scheda; le voci senza gruppo vanno in "Altri documenti".'],
            ['Tipi di file ammessi', 'pdf, jpg, jpeg, png, webp, heic, docx, odt, txt; ne serve almeno uno.'],
            ['Dimensione massima', 'Fino a 20480 KB (default 10240).'],
            ['Numero minimo e massimo di file', 'Da 1 a 10; 1 e 1 significa un solo file.'],
            ['Prompt di validazione', 'Istruzione in italiano per l\'AI, per esempio "Verifica che sia una visura camerale e che riporti la ragione sociale".'],
            ['Età massima del documento', 'Facoltativa, in giorni (1-3650).'],
          ],
        },
      ],
    },
    {
      id: 'list',
      title: 'L\'elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'Colonne: nome, codice, attiva, voci, sincronizzazione, ultima sincronizzazione, creata il. Lo stato di sincronizzazione può essere:',
        },
        {
          type: 'list',
          items: [
            '**Sincronizzata**: ArchivioSmart è allineato.',
            '**In errore**: passa il mouse o il focus sul badge per leggere l\'errore.',
            '**In attesa**: invio in corso; lo stato si aggiorna ricaricando la pagina.',
          ],
        },
      ],
    },
    {
      id: 'actions',
      title: 'Azioni',
      blocks: [
        {
          type: 'list',
          items: [
            '**Nuova checklist**: crea una checklist.',
            '**Sincronizza di nuovo** (riga): rinvia la checklist ad ArchivioSmart.',
            '**Attiva** / **Disattiva** (riga).',
            '**Elimina** (riga): la checklist viene tolta anche da ArchivioSmart.',
            '**Sincronizza tutte**: allineamento iniziale di tutte le checklist.',
          ],
        },
      ],
    },
    {
      id: 'sync-log',
      title: 'Sincronizzazioni nel dettaglio',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio di una checklist, la sezione **Sincronizzazioni** mostra le ultime 20 chiamate ad ArchivioSmart con data, operazione, esito, codice HTTP e messaggio.',
        },
      ],
    },
    {
      id: 'connection',
      title: 'Connessione ArchivioSmart',
      blocks: [
        {
          type: 'paragraph',
          text: 'Visibile solo a chi ha il permesso dedicato. Si imposta l\'**indirizzo base dell\'API** e il **token**. Il token non viene mai mostrato: lascia il campo vuoto per non cambiarlo. **Prova connessione** verifica che i dati siano corretti.',
        },
      ],
    },
    {
      id: 'errors',
      title: 'Errori comuni',
      blocks: [
        {
          type: 'list',
          items: [
            '**Token non valido**: aggiorna il token nella connessione.',
            '**Codice già usato in ArchivioSmart**: scegli un altro codice.',
            '**Connessione non configurata**: imposta indirizzo e token.',
            '**Troppe richieste**: il sistema riprova da solo; basta attendere.',
          ],
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Esistono i permessi per **visualizzare**, **creare**, **modificare** ed **eliminare** le checklist; la gestione della connessione ha un permesso separato.',
        },
      ],
    },
  ],
}

export default guide
