import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'request-imports',
  title: 'Importa richieste',
  summary:
    'Importa richieste carica da un file Excel o CSV le trattative già in corso e crea direttamente le richieste in Gestione Richieste: anagrafica, opportunità e offerta.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa fa',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si trova in **Develop › Importa richieste**, riservato a chi ha il permesso di importare richieste. Ogni riga del file diventa una richiesta in **Gestione Richieste**: QNet crea l\'anagrafica (o riusa quella già presente), l\'opportunità e l\'offerta. **Non vengono creati lead** e **nessun operatore riceve notifiche** di assegnazione.',
        },
        {
          type: 'steps',
          items: ['**Caricamento**', '**Mappatura e configurazione**', '**Revisione**', '**Riepilogo**'],
        },
        {
          type: 'note',
          text: 'Un\'esecuzione importa una sola **linea di prodotto**. Se il file ne contiene più di una, dividilo e fai un\'esecuzione per ciascuna.',
        },
      ],
    },
    {
      id: 'configuration',
      title: 'Configurazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel passaggio di mappatura, sotto le colonne, imposti i valori validi per tutto il file:',
        },
        {
          type: 'list',
          items: [
            '**Linea di prodotto** (obbligatoria): la categoria delle offerte create. Determina gli stati di lavorazione disponibili e le informazioni aggiuntive mappabili. Se il file ha una colonna categoria con un solo valore riconosciuto, è già compilata.',
            '**Sede operativa** (facoltativa): di norma la richiesta prende la sede principale del suo operatore; questa sede si usa solo per gli operatori che non ne hanno una.',
            '**Fonte di ripiego** (obbligatoria): usata quando la riga non ha una fonte riconosciuta.',
            '**Operatore di ripiego** (facoltativo): usato quando l\'operatore della riga non viene riconosciuto; senza, la richiesta è assegnata a te.',
          ],
        },
      ],
    },
    {
      id: 'column-mapping',
      title: 'Mappatura delle colonne e Informazioni aggiuntive',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni colonna scegli il campo di destinazione, oppure **Ignora questa colonna**. Oltre ai dati dell\'anagrafica puoi mappare **Operatore**, **Segnalatore** (il referente), **Stato di lavorazione**, **Fonte**, **Data richiamo**, **Prodotto** e **Note**.',
        },
        {
          type: 'paragraph',
          text: 'Dopo aver scelto la linea di prodotto il menu delle destinazioni mostra anche le voci **Informazioni aggiuntive: <nome>**, una per ogni informazione aggiuntiva dell\'offerta di quella categoria (ad esempio il prezzo o la tipologia di sussidio). Se l\'intestazione di una colonna coincide con il nome di un\'informazione, QNet la propone da solo; le altre vanno scelte a mano (ad esempio "Data App APL" su **OK app. APL**).',
        },
        {
          type: 'tip',
          text: 'Cambiando la linea di prodotto le colonne mappate su informazioni che non esistono più per la nuova categoria tornano su **Ignora questa colonna**.',
        },
        {
          type: 'note',
          text: 'Le colonne ignorate o non mappabili non vanno perse: se hanno un valore, finisce nelle **Note generali** dell\'opportunità come "Intestazione: valore".',
        },
      ],
    },
    {
      id: 'values-panel',
      title: 'Il pannello Valori',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se mappi **Stato di lavorazione** o **Fonte**, compare il pannello **Valori** con i valori diversi presenti nel file e il numero di righe in cui ciascuno compare. Per ognuno scegli:',
        },
        {
          type: 'list',
          items: [
            'uno **stato esistente** del workflow della linea di prodotto (o una **fonte esistente**);',
            '**Crea nuovo stato** (solo per gli stati): indichi il nome, già precompilato con il valore del file, e il gruppo (**Aperto**, **In attesa**, **Validato**, **Chiuso con esito positivo**, **Chiuso con esito negativo**). Lo stato viene creato nel workflow alla conferma dell\'import, una sola volta anche se rilanci l\'import.',
          ],
        },
        {
          type: 'paragraph',
          text: 'QNet precompila gli abbinamenti evidenti. Una cella vuota è indicata come **(vuoto)** e di solito va sullo stato iniziale. Le voci senza abbinamento sono evidenziate e **non puoi proseguire** finché ogni valore non ha una scelta.',
        },
      ],
    },
    {
      id: 'recognition',
      title: 'Cosa riconosce QNet',
      blocks: [
        {
          type: 'list',
          items: [
            '**Operatore**: per nome e cognome tra gli utenti attivi, senza distinguere maiuscole e accenti. Se il nome è ambiguo o sconosciuto la riga riceve un avviso e usa l\'operatore di ripiego.',
            '**Segnalatore**: per nome tra i referenti esistenti. QNet non crea referenti: se il nome non esiste, o se più referenti hanno lo stesso nome, il campo resta vuoto, la riga ha un avviso e il nome va nelle note generali.',
            '**Prodotto**: per somiglianza con i prodotti della linea scelta (anche sigle). Se non lo riconosce, l\'offerta nasce senza righe prodotto e il testo del file va nelle note generali.',
            '**Data richiamo**: lette le date Excel e quelle scritte come testo; il richiamo è fissato alle **09:00**. Una data illeggibile resta vuota, con un avviso.',
            '**Telefoni multipli** in una cella (separati da "/" o da " - "): diventano più contatti, il primo è quello principale.',
          ],
        },
      ],
    },
    {
      id: 'warnings',
      title: 'Avvisi non bloccanti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Un dato che QNet non sa usare, come un\'email malformata, una data illeggibile, un prodotto non riconosciuto o un valore non previsto di un campo a scelta, **non fa fallire la riga**: la riga viene importata, ha un avviso nel passaggio di revisione e il valore originale è conservato nelle **Note generali** dell\'opportunità.',
        },
        {
          type: 'note',
          text: 'QNet segnala, non corregge: la pulizia dei dati nel file resta a te.',
        },
      ],
    },
    {
      id: 'duplicates',
      title: 'Duplicati',
      blocks: [
        {
          type: 'paragraph',
          text: 'La gestione dei duplicati riguarda l\'anagrafica e funziona come in **Importa lead**:',
        },
        {
          type: 'table',
          headers: ['Opzione', 'Effetto'],
          rows: [
            ['**Crea sempre una nuova anagrafica**', 'Ogni riga crea una nuova anagrafica.'],
            ['**Usa l\'anagrafica corrispondente e ne aggiorna i contatti**', 'Opportunità e offerta si agganciano all\'anagrafica già presente, senza crearne una nuova.'],
            ['**Salta le righe corrispondenti**', 'Le righe già presenti non scrivono nulla.'],
            ['**Decidi in revisione**', 'Scegli riga per riga se saltarla o crearne una nuova.'],
          ],
        },
      ],
    },
    {
      id: 'review-and-confirm',
      title: 'Revisione e conferma',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella revisione puoi correggere i dati nella griglia e cambiare per riga o in blocco **operatore**, **sede** e **prodotti**. Nel riepilogo controlli i totali e confermi: l\'import prosegue sul server anche se chiudi la pagina. Una riga il cui stato non è valido per l\'offerta va in errore e non scrive nulla; le altre righe vengono importate.',
        },
      ],
    },
    {
      id: 'templates',
      title: 'Modelli di mappatura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Spuntando **Salva questa mappatura come modello riutilizzabile** salvi anche gli abbinamenti di stati e fonti scelti su elementi esistenti (gli stati da creare non vengono salvati). Caricando un file con le stesse colonne, QNet riconosce il modello, propone di applicarlo e preseleziona nel pannello **Valori** gli abbinamenti salvati, senza toccare le scelte che hai già fatto a mano.',
        },
      ],
    },
  ],
}

export default guide
