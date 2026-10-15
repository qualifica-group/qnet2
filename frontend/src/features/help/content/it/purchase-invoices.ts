import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'purchase-invoices',
  title: 'Fatture passive',
  summary: 'Fatture e proforma dei fornitori: registrazione da zero o dagli ODA, righe con tipo di costo e commessa, scadenze e allegati.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è',
      blocks: [
        {
          type: 'paragraph',
          text: 'Il modulo si trova in **Contabilità › Passiva › Fatture passive**. Raccoglie le **fatture e i proforma ricevuti dai fornitori**: ogni documento ha le sue **righe** (ciascuna con **tipo di costo** e, se serve, **commessa**), le **scadenze** di pagamento e gli **allegati** del fornitore.',
        },
        {
          type: 'paragraph',
          text: 'Un documento può nascere **da zero** oppure **da uno o più ODA** dello stesso fornitore e della stessa società. Gli ODA collegati sono sempre visibili nella scheda del documento. Gli imponibili delle righe con una **commessa** alimentano il **consuntivo** della commessa (scheda Costi), al posto delle righe ODA collegate.',
        },
      ],
    },
    {
      id: 'list',
      title: 'L’elenco',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’elenco ha **una riga per documento**: tipo, numero del fornitore, data, fornitore, società, ODA collegati, **totale**, **pagato**, **residuo** e **stato del pagamento**. Espandi la riga del documento per vedere **Righe**, **Scadenze** e **ODA collegati** (con il permesso sugli ODA, ognuno è un link all’ordine) senza aprire la scheda. Come in ogni elenco puoi ordinare, cercare, filtrare, salvare viste ed esportare.',
        },
        {
          type: 'table',
          headers: ['Azione', 'Cosa fa'],
          rows: [
            ['**Visualizza**', 'Apre la scheda del documento, dove si modifica tutto: ogni campo ha la **matita** (premi, cambia, **Salva**; si salva solo quel campo). In **Testata** cambi data, pagamento, banca, sedi, tipo di costo e commessa predefiniti (solo proforma; con pagamenti data e pagamento sono bloccati; fornitore e società non cambiano). In **Registrazione e note** inserisci il **numero** del fornitore e le note, modificabili anche dopo la registrazione. Nella sezione **Righe** (solo proforma) ogni riga ha la **matita** (si apre al suo posto, cambi e premi **Salva**) e il **cestino** (chiede conferma; non sull’ultima riga); **Aggiungi riga** propone una riga da catalogo o a testo libero che si apre in modifica. Le scadenze si ricalcolano al salvataggio.'],
            ['**Data documento** (cella)', 'Sui proforma senza pagamenti la data si cambia anche direttamente nell’elenco, con doppio clic.'],
            ['**Elimina**', 'Elimina il documento dopo conferma (solo proforma senza pagamenti).'],
            ['**Attività**', 'Storico delle modifiche del documento.'],
          ],
        },
        {
          type: 'note',
          text: 'Le azioni che vedi dipendono dai tuoi permessi e dallo stato del documento: quelle non consentite non compaiono.',
        },
      ],
    },
    {
      id: 'create',
      title: 'Registrare un documento',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova fattura passiva** e scegli **Fornitore**, **Società**, **Data documento** e **Modalità di pagamento** (e, se serve, conto, sedi, note).',
            'Aggiungi le **righe**: dal catalogo prodotti oppure libere. Per ogni riga indica descrizione, quantità, prezzo, IVA, **tipo di costo** (obbligatorio) e **commessa**. Il tipo di costo e la commessa di testata sono solo il valore proposto alle nuove righe; per una riga dal catalogo, se la testata non ne ha uno, il tipo di costo si propone dal prodotto (se ne ha uno predefinito).',
            'Se serve, indica **Ritenuta d’acconto** ed **ENASARCO a carico** (opzionali, nella sezione **Trattenute**).',
            'Controlla i **totali**, compreso il **Netto da pagare**, e l’**anteprima delle scadenze**, generate dalla modalità di pagamento.',
            'Premi **Salva**.',
          ],
        },
        {
          type: 'list',
          items: [
            'Un documento ha da 1 a 200 righe.',
            'Gli importi di riga si calcolano con la quantità a 3 decimali; l’IVA vuota vale 0. L’IVA è calcolata una volta per ogni aliquota sull’imponibile delle righe, come nella fattura elettronica, e ripartita sulle righe; i totali del documento sono la somma delle righe.',
            'Fornitore e società **non si cambiano** dopo la creazione.',
          ],
        },
      ],
    },
    {
      id: 'from-orders',
      title: 'Registrare dagli ODA',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dal dettaglio di un ODA **Approvato**, **Inviato al fornitore**, **Parzialmente evaso** o **Evaso** premi **Registra fattura**: l’editor si apre con le righe dell’ordine già compilate (descrizione, prodotto, quantità, prezzo, IVA, tipo di costo, commessa) e l’ODA collegato. Puoi aggiungere altri ODA dello stesso fornitore e società e modificare, aggiungere o togliere righe.',
        },
        {
          type: 'warning',
          text: 'Le righe d’ordine si fatturano **per quantità**, anche in più documenti. La quantità proposta è quella ancora da fatturare: sia **proforma** sia **fattura** possono coprire la quantità ordinata non ancora fatturata; se il residuo della riga è stato chiuso, il tetto è la quantità ricevuta. Ogni riga da ODA mostra il **massimo** disponibile (“max X”) e una quantità superiore viene rifiutata. Nello stesso documento puoi mettere più righe di più ODA, purché dello stesso fornitore e della stessa società.',
        },
        {
          type: 'list',
          items: [
            'Cambiare il **tipo di documento** non modifica le righe da ODA: restano come le hai compilate.',
            'Se la bozza risulta vuota, l’ordine non ha più nulla da fatturare.',
            'L’editor e il campo **numero** della scheda avvisano che salvare come **Fattura** registra la ricezione delle quantità non ancora ricevute (vedi “Proforma e fattura”).',
          ],
        },
      ],
    },
    {
      id: 'proforma-invoice',
      title: 'Proforma e fattura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Come per le fatture attive, il documento nasce come **proforma**. Diventa **fattura** quando, nella scheda (sezione **Registrazione e note**), inserisci il **numero** del documento del fornitore.',
        },
        {
          type: 'list',
          items: [
            'Salvando come **Fattura** (o dando a un proforma il numero del fornitore) il sistema registra **in automatico una ricezione** su ogni ODA collegato, per le quantità fatturate non ancora ricevute: data del documento, nota “Ricezione da fattura n. X”; lo stato dell’ODA e le righe RDA collegate si aggiornano. Per più ODA si registra una ricezione per ODA. Il **proforma non riceve**. Non serve il permesso **Registra ricezioni**.',
            'Se un ODA non è ricevibile (bozza, in validazione, annullato) e servirebbe una ricezione, la fattura viene rifiutata.',
            'La ricezione automatica compare nell’ODA come “da fattura n. X” e non si può annullare finché esiste la fattura; se la fattura torna proforma la ricezione resta.',
            'Una **fattura** è **bloccata**: righe, importi, fornitore, data ed eliminazione sono rifiutati.',
            'Cambiare o svuotare numero e data di una fattura richiede il permesso **Correggi/annulla registrazione** e viene registrato nell’attività; svuotando il numero il documento torna proforma.',
            'Il numero del fornitore deve essere **unico** per lo stesso fornitore e lo stesso anno tra le fatture; non vale per i proforma.',
          ],
        },
      ],
    },
    {
      id: 'installments',
      title: 'Scadenze e pagamenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Le scadenze sono generate dalla **modalità di pagamento** e, in modifica di un proforma senza pagamenti, vengono **ricalcolate**. Le scadenze sommano al **Netto da pagare**, non al totale del documento. Se esistono già pagamenti, data, modalità, fornitore, **Ritenuta d’acconto** ed **ENASARCO a carico** sono congelati e le scadenze si **ribilanciano** lasciando intatte le rate pagate. I pagamenti si registrano da **Contabilità › Passiva › Scadenze**, dove l’azione **Apri documento** porta alla scheda del documento.',
        },
        {
          type: 'note',
          text: 'Un documento con pagamenti registrati non si elimina: annulla prima i pagamenti.',
        },
      ],
    },
    {
      id: 'documents',
      title: 'Documenti allegati',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nella scheda del documento la sezione **Documenti** conserva il PDF o gli altri file del fornitore. Caricare ed eliminare file richiede i permessi sugli allegati. Eliminando il documento, anche i suoi allegati vengono rimossi.',
        },
      ],
    },
    {
      id: 'withholdings',
      title: 'Ritenuta d’acconto, ENASARCO e netto da pagare',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una fattura passiva può avere due trattenute, inserite a mano: **Ritenuta d’acconto** ed **ENASARCO a carico**. Se le lasci vuote valgono zero e nulla cambia.',
        },
        {
          type: 'list',
          items: [
            '**Netto da pagare** = totale del documento − ritenuta d’acconto − ENASARCO a carico. Compare nei totali dell’editor e nel dettaglio e deve restare maggiore di zero: le trattenute devono essere inferiori al totale del documento.',
            'Le **scadenze** si calcolano sul netto da pagare: la trattenuta riduce la quota di imponibile delle rate e la somma delle rate è il netto da pagare. Il **Partitario** del fornitore e l’elenco mostrano il documento al netto da pagare.',
            'Dopo il **primo pagamento** i due importi sono bloccati, come data e modalità di pagamento.',
          ],
        },
      ],
    },
    {
      id: 'from-settlement',
      title: 'Fatture generate da un estratto',
      blocks: [
        {
          type: 'paragraph',
          text: 'Approvando un estratto di provvigioni (vedi la guida **Estratti**) nasce una fattura passiva con ritenuta ed ENASARCO dell’agente. Nell’elenco e nella scheda compare il badge **Da estratto n/anno**, con il link all’estratto.',
        },
        {
          type: 'paragraph',
          text: 'Una fattura collegata a un estratto è **bloccata**: non si modifica, non si elimina e il numero non cambia, anche con il permesso di correggere la registrazione. Restano consentiti note, documenti allegati, pagamenti e la modifica di scadenza e modalità delle rate non pagate.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Permessi',
      blocks: [
        {
          type: 'table',
          headers: ['Azione', 'Permesso'],
          rows: [
            ['Vedere l’elenco e la scheda', 'Visualizzazione fatture passive'],
            ['**Nuova fattura passiva**, **Registra fattura** dall’ODA', 'Creazione fatture passive'],
            ['Modificare la scheda e le righe', 'Modifica fatture passive'],
            ['**Elimina**', 'Eliminazione fatture passive'],
            ['Cambiare o svuotare numero e data di una fattura', 'Correggi/annulla registrazione'],
            ['**Esporta**, **Attività**', 'Esportazione e visualizzazione attività'],
          ],
        },
      ],
    },
  ],
}

export default guide
