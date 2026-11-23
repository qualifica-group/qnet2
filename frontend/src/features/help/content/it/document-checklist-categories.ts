import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-checklist-categories',
  title: 'Categorie checklist',
  summary: 'Categorie per raggruppare le checklist documentali, sincronizzate con ArchivioSmart.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Configurazione › Documenti › Categorie checklist**. Una categoria raggruppa le checklist documentali; in **ArchivioSmart** serve a filtrare checklist e richieste. Ogni salvataggio viene inviato automaticamente ad ArchivioSmart, sempre prima delle checklist che la usano.',
        },
      ],
    },
    {
      id: 'category-fields',
      title: 'Campi della categoria',
      blocks: [
        {
          type: 'list',
          items: [
            '**Nome**: obbligatorio, al massimo 255 caratteri; non può essere uguale a quello di un\'altra categoria (maiuscole e minuscole non contano).',
            '**Descrizione**: nota libera, al massimo 2000 caratteri.',
            '**Ordine**: numero intero da 0 a 65535 (predefinito 0); le categorie sono elencate dal numero più basso.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Una checklist può avere una sola categoria, che si sceglie nel campo **Categoria** del suo modulo.',
        },
      ],
    },
    {
      id: 'list',
      title: 'L\'elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'Colonne: nome, descrizione, ordine, numero di checklist, sincronizzazione, ultima sincronizzazione, creata il. Lo stato di sincronizzazione può essere:',
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
            '**Nuova categoria**: crea una categoria.',
            '**Sincronizza di nuovo** (riga): rinvia la categoria ad ArchivioSmart.',
            '**Elimina** (riga): possibile solo se la categoria non contiene più checklist; la categoria viene tolta anche da ArchivioSmart.',
          ],
        },
        {
          type: 'paragraph',
          text: 'Se la categoria contiene ancora checklist, l\'eliminazione viene rifiutata con un messaggio che indica quante sono: spostale in un\'altra categoria o eliminale prima. Per un primo allineamento di tutte le categorie usa **Sincronizza tutte** nella pagina delle checklist.',
        },
      ],
    },
    {
      id: 'sync-log',
      title: 'Sincronizzazioni nel dettaglio',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio di una categoria, la sezione **Sincronizzazioni** mostra lo stato attuale, l\'ultimo errore e le ultime 20 chiamate ad ArchivioSmart con data, operazione, esito, codice HTTP e messaggio.',
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
            '**Nome già usato in ArchivioSmart**: una categoria creata direttamente in ArchivioSmart ha lo stesso nome; scegli un altro nome.',
            '**Categoria non sincronizzata** su una checklist: la checklist non parte finché la sua categoria non è allineata; correggi la categoria e usa **Sincronizza di nuovo**.',
            '**Connessione non configurata** o **token non valido**: vedi la guida delle checklist documentali.',
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
          text: 'Le categorie usano gli stessi permessi delle checklist documentali: **visualizzare**, **creare**, **modificare** ed **eliminare**. Non esistono permessi dedicati.',
        },
      ],
    },
  ],
}

export default guide
