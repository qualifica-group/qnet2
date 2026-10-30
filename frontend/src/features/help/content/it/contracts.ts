import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'contracts',
  title: 'Contratti',
  summary: "Il contratto nasce quando un'offerta passa a uno stato Chiuso con esito positivo.",
  sections: [
    {
      id: 'overview',
      title: 'Come nasce un contratto',
      blocks: [
        {
          type: 'paragraph',
          text: "Il contratto non si crea a mano: nasce quando un'offerta passa a uno stato **Chiuso con esito positivo**, con data di **Accettazione** del giorno e lo stato **Predefinito** di **Stati Contratto**. Non nasce se la categoria prodotto è impostata per non generare contratti. Se l'offerta esce dallo stato positivo, il contratto viene **Sospeso** e QNet ricorda lo stato precedente.",
        },
        {
          type: 'paragraph',
          text: "Le schede di Opportunità, Offerte, Contratti e Commesse hanno le stesse sezioni nello stesso posto. Qui trovi **Dati contratto** (Offerta, Opportunità, Data offerta) e **Contesto**, poi **Anagrafica e contatti** (Anagrafica, Referente con il suo tipo, Commerciale, Segnalatore) e **Buoni**, poi **Team** (Supervisore e Gestori account dell'offerta) e **Società e sedi**, poi **Documento e pagamento** e **Ciclo di vita**. I dati dell'offerta sono in sola lettura e si modificano dall'offerta; Note di pagamento, Rinnovo, Scadenza e Commenti si modificano qui.",
        },
      ],
    },
    {
      id: 'contract-actions',
      title: 'Azioni sul contratto',
      blocks: [
        {
          type: 'table',
          headers: ['Stato attuale', 'Azioni disponibili'],
          rows: [
            ['Aperto o In attesa', 'Dati modificabili sul posto, **Cambia stato**, **Valida contratto** (solo se non è mai stato validato), **Disdici contratto**'],
            ['Chiuso positivo', '**Programma**, **Disdici contratto**, **Riapri contratto**'],
            ['Chiuso negativo', '**Riapri contratto**'],
            ['Sospeso', '**Riapri contratto**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Valida contratto**: indica la **Data di validazione** (non futura). Non compare su un contratto che ha già una data di validazione, anche se il suo stato è In attesa (per esempio Da programmare, Programmato o In scadenza).',
            '**Disdici contratto**: indica **Data di disdetta**, **Motivazione** e **Stato di destinazione**.',
            'Dati del contratto: **Scadenza**, **Rinnovo**, **Note di pagamento** e **Commenti** si modificano direttamente nella scheda, finché il contratto è Aperto o In attesa: premi la matita accanto al campo (o fai clic sul valore), cambia il valore e premi **Salva** (Annulla, Esc o un clic fuori chiudono senza salvare). I Commenti si scrivono dentro il loro riquadro in cima alla scheda.',
            '**Riapri contratto**: riporta il contratto in lavorazione; se era sospeso, torna allo stato precedente. Riaprendo un contratto Chiuso positivo o Chiuso negativo si azzerano anche la data e l\'autore della validazione, così il contratto si può validare di nuovo; i valori precedenti restano in **Cronologia attività**.',
          ],
        },
        {
          type: 'note',
          text: "L'azione **Programma** crea una o più commesse dalle righe prodotto dell'offerta: vedi la sezione **Programmare le commesse**.",
        },
      ],
    },
    {
      id: 'program-work-orders',
      title: 'Programmare le commesse',
      blocks: [
        {
          type: 'paragraph',
          text: "**Programma** apre una finestra a due pannelli: a sinistra le **righe dell'offerta**, a destra le **commesse da creare** (gruppi). Con un solo **Crea N commesse** salvi tutti i gruppi insieme.",
        },
        {
          type: 'steps',
          items: [
            'Seleziona una o più righe libere a sinistra e premi **Nuovo gruppo**: compare il Gruppo 1 e le righe mostrano il badge **G1**.',
            'Per aggiungere altre righe a un gruppo, selezionale e usa **Aggiungi a gruppo**. Una riga sta in un solo gruppo: se è già in un altro, viene **spostata**.',
            'In ogni gruppo compila **Tipo**, **Data inizio** e, se servono, i **Responsabili** (facoltativi) e il **Modello di Task**. Con il pulsante cestino elimini un gruppo e ne liberi le righe.',
            'Premi **Crea N commesse**.',
          ],
        },
        {
          type: 'list',
          items: [
            '**Una commessa per riga**: crea un gruppo per ogni riga selezionata (senza selezione, per tutte le righe libere).',
            '**Raggruppa per categoria**: crea un gruppo per categoria di prodotto, più uno per le righe **senza categoria**.',
            '**Valori comuni**: Tipo, Data inizio, Responsabili e Modello di Task che i **nuovi** gruppi ereditano; la Data inizio parte da oggi ed è modificabile. Cambiarli dopo non tocca i gruppi già creati, salvo **Applica a tutti i gruppi** (non cambia mai titoli e righe).',
            '**Duplica gruppo**: copia i quattro valori del gruppo, senza titolo né righe.',
          ],
        },
        {
          type: 'note',
          text: 'Il salvataggio è **tutto o niente**: se un gruppo ha un errore non viene creata nessuna commessa. Il gruppo con l\'errore si apre e mostra il campo da correggere. Le righe già in un\'altra commessa restano visibili ma non selezionabili; le righe che lasci libere non sono un errore. Chiudendo con gruppi non salvati ti viene chiesta conferma. Al massimo 50 commesse per volta.',
        },
        {
          type: 'tip',
          text: 'Il **Titolo** del gruppo è facoltativo: se lo lasci vuoto la commessa prende il titolo automatico **codice - prodotti** (es. COM-0042 - Consulenza + Audit), mostrato come anteprima nel campo.',
        },
      ],
    },
    {
      id: 'program-multi-quote-work-order',
      title: 'Programmare una commessa unica da più contratti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se le categorie prodotto del ramo hanno attiva l\'impostazione **Commessa multi-offerta**, puoi raccogliere in **una sola commessa** le righe di più contratti vinti, anche di clienti diversi.',
        },
        {
          type: 'steps',
          items: [
            'Nella tabella **Contratti** seleziona da 2 a 50 contratti **Chiusi con esito positivo**.',
            'Premi l\'azione **Programma commessa unica** (serve il permesso di programmare).',
            'Nella finestra le righe sono raggruppate per contratto. Sono selezionabili solo le **righe di ricavo** di un ramo con l\'impostazione attiva e **non ancora in una commessa**; le altre restano visibili ma disattivate.',
            'Scegli le righe: devono essere tutte dello **stesso ramo** e provenire da **almeno due offerte** diverse.',
            'Compila i campi comuni: **Titolo** (facoltativo), **Tipo**, **Data inizio**, **Responsabili** (facoltativi) e, se serve, il **Modello di Task**.',
            'Premi il pulsante di conferma: viene creata **una commessa** e si apre subito.',
          ],
        },
        {
          type: 'note',
          text: 'Il salvataggio è **tutto o niente**: se qualcosa non va non viene creata nessuna commessa e l\'errore compare sul campo delle righe. Una riga già in un\'altra commessa non si può riutilizzare. Se tra i contratti selezionati ce n\'è almeno uno non ancora in uno stato **Chiuso positivo** (per esempio **Da validare**) o che non si può cumulare (la sua offerta non ha righe di un ramo con **Commessa multi-offerta** attivo), la finestra si apre comunque e mostra quali contratti, con il cliente, vanno tolti dalla selezione.',
        },
      ],
    },
    {
      id: 'expiry-and-renewal',
      title: 'Scadenze e rinnovi',
      blocks: [
        {
          type: 'paragraph',
          text: 'La colonna **Avviso** mostra **In scadenza** (scadenza entro 30 giorni) o **Da rinnovare** (rinnovo entro 30 giorni); se valgono entrambe, prevale **In scadenza**. I contratti in **Chiuso negativo** non mostrano avvisi. La colonna **Linee di prodotto** elenca i prodotti delle righe di ricavo dell\'offerta, ciascuno con la sua categoria tra parentesi (sola lettura). La colonna **Categoria madre** mostra la macrocategoria dei prodotti dell\'offerta, ad esempio Formazione (sola lettura, filtrabile).',
        },
        {
          type: 'tip',
          text: 'Compila sempre **Scadenza** e **Rinnovo** nella scheda del contratto: senza queste date gli avvisi non compaiono.',
        },
      ],
    },
    {
      id: 'category-tabs',
      title: "Filtrare per categoria prodotto",
      blocks: [
        {
          type: 'paragraph',
          text: "Sopra l'elenco trovi la barra delle categorie: la tab **Tutte** e una tab per ogni categoria prodotto presente, con il numero di contratti. Cliccando una tab l'elenco mostra solo i contratti di quella categoria. La scelta resta anche dopo aver ricaricato la pagina.",
        },
        {
          type: 'paragraph',
          text: "Un contratto compare nella tab di ogni categoria dei prodotti presenti nelle **righe di ricavo dell'offerta del contratto** (conta una volta sola anche con più righe della stessa categoria). Le righe di costo non contano; un contratto la cui offerta non ha righe di ricavo compare solo in **Tutte**. Il numero accanto al nome è quanti contratti, tra quelli che puoi vedere, rientrano nella categoria.",
        },
        {
          type: 'steps',
          items: ["Se le tab non entrano nella riga, le altre finiscono nel menu **Altre**, dove puoi cercare una categoria per nome.","Clicca la **stellina** accanto a una categoria (nel menu) per aggiungerla alle **preferite**: le preferite compaiono per prime.","Attiva **Mostra solo preferite** per vedere in barra solo le preferite; le altre restano nel menu **Altre**."],
        },
        {
          type: 'paragraph',
          text: "Nel menu **Altre** l'interruttore **Categorie madri** limita la barra alle sole categorie di primo livello: la tab di una categoria madre raccoglie anche le sottocategorie (un record conta una volta sola). La scelta resta salvata nel browser per questo elenco, ogni modalità ricorda la propria tab e le **preferite** sono separate tra le due modalità. Spegnendolo torni a tutte le categorie.",
        },
        {
          type: 'note',
          text: "Le preferite sono separate per ogni elenco (Opportunità, Offerte, Contratti, Gestione Richieste). La prima volta ti vengono proposte in base alle categorie per cui sei abilitato; appena usi la stellina diventano le tue. L'esportazione non segue la tab: considera sempre tutti i contratti.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Quali contratti vedi",
      blocks: [
        { type: 'paragraph', text: "Cosa vedi dipende dal tuo ruolo. Serve sempre il permesso **Visualizza**; i livelli si sommano e dipendono dall'offerta del contratto. I permessi dei Contratti sono indipendenti da quelli delle Offerte." },
        { type: 'table', headers: ["Permesso", "Contratti visibili"], rows: [["**Visualizza**", "Quelli in cui sei **Supervisore** o **Gestore account** dell'offerta del contratto."], ["**Visualizza per sede**", "I tuoi e quelli la cui offerta è della tua **Sede operativa**."], ["**Visualizza tutti**", "Tutti."]] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di un contratto che non puoi vedere mostra un errore di accesso. Vedere un contratto non permette di modificarlo o eliminarlo: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
      ],
    },
  ],
}

export default guide
