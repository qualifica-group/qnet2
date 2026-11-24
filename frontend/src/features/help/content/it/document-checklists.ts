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
            '**Categoria**: facoltativa, una sola per checklist; si sceglie tra le categorie create in **Configurazione › Documenti › Categorie checklist** e si può svuotare. Serve a filtrare checklist e richieste in ArchivioSmart.',
            '**Descrizione**: nota libera.',
            '**Attiva**: per smettere di usare una checklist è meglio disattivarla che eliminarla.',
          ],
        },
      ],
    },
    {
      id: 'groups',
      title: 'I gruppi di documenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'La card **Gruppi di documenti** raggruppa le voci: nel portale del cliente ogni gruppo diventa una scheda, e le voci senza gruppo vanno in "Altri documenti". Si possono creare fino a **20 gruppi** con **Nuovo gruppo**; ognuno ha un nome (unico nella checklist) e una descrizione, e mostra quante voci contiene. Riordinali con **Sposta su** / **Sposta giù**. Eliminando un gruppo le sue voci non vengono cancellate: passano a "Nessun gruppo".',
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
            ['Gruppo', 'Si sceglie tra i gruppi della checklist; "Nessun gruppo" porta la voce in "Altri documenti".'],
            ['Tipi di file ammessi', 'pdf, jpg, jpeg, png, webp, heic, docx, odt, txt; ne serve almeno uno.'],
            ['Dimensione massima', 'Fino a 20480 KB (default 10240).'],
            ['Più documenti', 'Se attiva il cliente può caricare fino a 10 file per la voce; ogni file viene controllato da solo con lo stesso prompt e le stesse regole. Se spenta, un solo file.'],
            ['Prompt di validazione', 'Istruzione in italiano per l\'AI, per esempio "Verifica che sia una visura camerale e che riporti la ragione sociale".'],
            ['Età massima del documento', 'Facoltativa, in giorni (1-3650).'],
            ['Campo data estratto', 'Nome del campo data che l\'AI estrae dal documento (per esempio issue_date), con lettere minuscole, cifre e _. Si compila solo con l\'età massima; se vuoto vale issue_date.'],
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
          text: 'Colonne: nome, codice, categoria, attiva, voci, sincronizzazione, ultima sincronizzazione, creata il. Lo stato di sincronizzazione può essere:',
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
            '**Sincronizza tutte**: allineamento iniziale; invia prima tutte le categorie e poi tutte le checklist, e un messaggio indica quante sono state messe in coda.',
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
          text: 'Visibile solo a chi ha il permesso dedicato. Si imposta l\'**indirizzo base dell\'API** e il **token**. Il token non viene mai mostrato: lascia il campo vuoto per non cambiarlo. Anche il **segreto del webhook** (che inizia con whsec_) non viene mai mostrato: serve a verificare gli aggiornamenti che ArchivioSmart invia a QNet. Copia l\'**URL del webhook** con **Copia** e incollalo in ArchivioSmart; senza segreto gli aggiornamenti non vengono accettati. **Prova connessione** verifica che i dati siano corretti.',
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
