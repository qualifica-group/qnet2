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
          text: "Le schede di Opportunità, Offerte, Contratti e Commesse hanno le stesse sezioni nello stesso posto. Qui trovi **Dati contratto** (Offerta, Opportunità, Data offerta) e **Contesto**, poi **Anagrafica e contatti** (Anagrafica, Referente con il suo tipo, Commerciale, Segnalatore) e **Buoni**, poi **Team** (Supervisore e Gestori account dell'offerta) e **Società e sedi**, poi **Documento e pagamento** e **Ciclo di vita**. I dati dell'offerta sono in sola lettura e si modificano dall'offerta; Titolo, righe dei servizi, Note di pagamento, Rinnovo, Scadenza e Commenti si modificano qui.",
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
            ['Aperto o In attesa', 'Titolo, righe e dati modificabili sul posto, **Cambia stato**, **Valida contratto** (solo se non è mai stato validato), **Programma** e **Rinnova** (solo se è già validato), **Disdici contratto**'],
            ['Chiuso positivo', 'Titolo, righe e dati modificabili sul posto, **Programma**, **Rinnova**, **Disdici contratto**, **Riapri contratto**'],
            ['Chiuso negativo', '**Riapri contratto** (scheda in sola lettura)'],
            ['Sospeso', '**Riapri contratto**'],
          ],
        },
        {
          type: 'list',
          items: [
            '**Valida contratto**: indica la **Data di validazione** (non futura). Serve almeno una riga prodotto: se il contratto non ne ha, aggiungila dalla scheda prima di validare. Non compare su un contratto che ha già una data di validazione, anche se il suo stato è In attesa (per esempio Da programmare, Programmato o In scadenza): su quei contratti compare invece **Programma**, se non sono sospesi.',
            '**Disdici contratto**: indica **Data di disdetta**, **Motivazione** e **Stato di destinazione**. Con lo stato **Annullato** tutte le commesse del contratto vengono chiuse, anche quelle concluse, e restano chiuse se riapri il contratto. Con qualsiasi altro stato vengono chiuse solo le commesse ancora aperte, che **Riapri contratto** riporta allo stato che avevano. I task delle commesse non cambiano.',
            'Dati del contratto: **Scadenza**, **Rinnovo**, **Note di pagamento** e **Commenti** si modificano direttamente nella scheda, finché il contratto è Aperto, In attesa o Chiuso positivo (Validato): premi la matita accanto al campo (o fai clic sul valore), cambia il valore e premi **Salva** (Annulla, Esc o un clic fuori chiudono senza salvare). I Commenti si scrivono dentro il loro riquadro in cima alla scheda.',
            '**Riapri contratto**: riporta il contratto in lavorazione; se era sospeso, torna allo stato precedente. Riaprendo un contratto Chiuso positivo o Chiuso negativo si azzerano anche la data e l\'autore della validazione, così il contratto si può validare di nuovo; i valori precedenti restano in **Cronologia attività**.',
          ],
        },
        {
          type: 'note',
          text: "L'azione **Programma** crea una o più commesse dalle righe prodotto dell'offerta: vedi la sezione **Programmare le commesse**. L'azione **Rinnova** apre un nuovo periodo dello stesso contratto: vedi la sezione **Rinnovare il contratto**.",
        },
      ],
    },
    {
      id: 'edit-contract-in-force',
      title: 'Modificare il contratto in essere',
      blocks: [
        {
          type: 'paragraph',
          text: "Dalla scheda del contratto puoi cambiare **Titolo** e **Righe** (i servizi) direttamente sul posto, come gli altri dati, anche quando il contratto è **Validato**. Servono il permesso **Modifica** dei Contratti e il campo modificabile per il tuo ruolo: se manca, la matita non compare. Gli stati ammessi sono **Aperto**, **In attesa** e **Validato**; un contratto **Annullato** o **Disdetto** resta in sola lettura finché non lo riapri con **Riapri contratto**.",
        },
        {
          type: 'steps',
          items: [
            'Per il titolo premi la matita accanto al titolo in alto, scrivi il nuovo testo e premi **Salva**. Se lo lasci vuoto il contratto torna al titolo automatico.',
            'Per i servizi premi la matita sul riquadro **Righe dell\'offerta**: scegli altri prodotti dal catalogo per aggiungere righe, cambia quantità, prezzo, IVA e fornitore, oppure rimuovi una riga, poi premi **Salva**.',
          ],
        },
        {
          type: 'list',
          items: [
            'Le modifiche si scrivono sull\'offerta di origine: totali e margini si ricalcolano, mentre lo stato del contratto e quello dell\'offerta non cambiano.',
            'Le **provvigioni** non si modificano da qui: restano quelle impostate nell\'offerta e i loro importi si ricalcolano sulle nuove righe.',
            'Una riga **assegnata a una commessa** (mostra "Assegnata a COM-...") è bloccata: non si può né modificare né rimuovere finché la commessa non viene eliminata. La stessa regola vale dall\'offerta e da Gestione Richieste.',
          ],
        },
        {
          type: 'note',
          text: 'Se il salvataggio viene rifiutato (per esempio per una riga bloccata) il messaggio compare sulla riga e le tue modifiche restano nella finestra di modifica, così puoi correggerle.',
        },
      ],
    },
    {
      id: 'program-work-orders',
      title: 'Programmare le commesse',
      blocks: [
        {
          type: 'paragraph',
          text: "**Programma** apre una finestra a due pannelli: a sinistra le **righe dell'offerta**, a destra le **commesse da creare** (gruppi). Con un solo **Crea N commesse** salvi tutti i gruppi insieme. In alto la finestra indica il **Periodo** (Iniziale o Rinnovo N) in cui nascono le commesse.",
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
          text: 'Il salvataggio è **tutto o niente**: se un gruppo ha un errore non viene creata nessuna commessa. Il gruppo con l\'errore si apre e mostra il campo da correggere. Le righe già in un\'altra commessa **del periodo corrente** restano visibili ma non selezionabili (dopo un rinnovo tornano libere); le righe che lasci libere non sono un errore. Chiudendo con gruppi non salvati ti viene chiesta conferma. Al massimo 50 commesse per volta.',
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
            'Nella tabella **Contratti** seleziona da 2 a 50 contratti **Chiusi con esito positivo** o **già validati** (e non sospesi).',
            'Premi l\'azione **Programma commessa unica** (serve il permesso di programmare).',
            'Nella finestra le righe sono raggruppate per contratto. Sono selezionabili solo le **righe di ricavo** di un ramo con l\'impostazione attiva e **non ancora in una commessa**; le altre restano visibili ma disattivate.',
            'Scegli le righe: devono essere tutte dello **stesso ramo** e provenire da **almeno due offerte** diverse.',
            'Compila i campi comuni: **Titolo** (facoltativo), **Tipo**, **Data inizio**, **Responsabili** (facoltativi) e, se serve, il **Modello di Task**.',
            'Premi il pulsante di conferma: viene creata **una commessa** e si apre subito.',
          ],
        },
        {
          type: 'note',
          text: 'Il salvataggio è **tutto o niente**: se qualcosa non va non viene creata nessuna commessa e l\'errore compare sul campo delle righe. Una riga già in un\'altra commessa nel periodo corrente del suo contratto non si può riutilizzare. Se tra i contratti selezionati ce n\'è almeno uno che non si può ancora programmare (non validato, per esempio **Da validare**, oppure sospeso) o che non si può cumulare (la sua offerta non ha righe di un ramo con **Commessa multi-offerta** attivo), la finestra si apre comunque e mostra un avviso con il motivo e l\'elenco di quei contratti, con il cliente. Se ne restano almeno due, il pulsante **Continua senza questi contratti** li toglie da questa commessa e carica le righe degli altri (la selezione nella griglia non cambia); altrimenti chiudi la finestra e scegli nella griglia altri contratti.',
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
      id: 'renewal-periods',
      title: 'Rinnovare il contratto',
      blocks: [
        {
          type: 'paragraph',
          text: "**Rinnova** apre un nuovo periodo dello stesso contratto (Rinnovo 1, Rinnovo 2...): con la stessa offerta e le stesse righe programmi altre commesse. Prezzi e provvigioni restano quelli dell'offerta e il totale dell'offerta non cambia. L'azione è disponibile negli stessi stati di **Programma**, anche se la scadenza è già passata, e richiede lo stesso permesso.",
        },
        {
          type: 'steps',
          items: [
            'Premi **Rinnova** nella barra azioni del contratto (o dall\'azione di riga nell\'elenco Contratti).',
            'Controlla **Nuova scadenza** (obbligatoria, successiva a quella attuale) e **Nuova data di rinnovo** (facoltativa, non successiva alla scadenza). Sono già proposte spostando le date attuali della durata del periodo; se mancano le date di partenza viene proposta una scadenza a 12 mesi da oggi.',
            'Premi **Rinnova**: il contratto prende le nuove date e il periodo corrente diventa il nuovo rinnovo. Lo stato del contratto non cambia.',
            'Usa **Programma** per creare le commesse del nuovo periodo: in ogni periodo una riga dell\'offerta si programma una sola volta.',
          ],
        },
        {
          type: 'list',
          items: [
            'Le commesse dei periodi precedenti restano invariate; se modifichi una commessa esistente, le righe che aggiungi entrano nel **suo** periodo.',
            'In ogni commessa la riga si fattura una volta e la sua provvigione matura per intero: lo stesso prodotto si fattura di nuovo nella commessa del rinnovo.',
            'Nella scheda, il pannello **Rinnovi** sta in alto, subito sotto i pulsanti delle azioni: indica il periodo **in corso**, quanti giorni mancano alla scadenza (in rosso se è passata, in ambra se è vicina), la data entro cui rinnovare e una barra con la parte di periodo già trascorsa. Sotto c\'è la linea dei periodi (**Iniziale**, **Rinnovo 1**, **Rinnovo 2**...): clicca un periodo per vederne **Dal**, **Al**, **Data rinnovo**, **Date precedenti**, **Rinnovato da** (chi e quando) e numero di **Commesse**. Nell\'elenco Commesse (e nella scheda Commesse del contratto) la colonna **Periodo** indica Iniziale o Rinnovo N.',
          ],
        },
        {
          type: 'note',
          text: '**Annulla rinnovo** compare solo nel dettaglio dell\'ultimo rinnovo e solo se nel suo periodo non ci sono commesse: dopo la conferma le date del contratto tornano a quelle precedenti al rinnovo.',
        },
      ],
    },
    {
      id: 'status-tabs',
      title: "Stati del contratto",
      blocks: [
        {
          type: 'paragraph',
          text: "In alto a destra nella pagina **Contratti** trovi i tab degli stati, come nel vecchio QNet: **Tutti** e poi uno per ogni stato contratto attivo, nell'ordine della pagina **Stati contratto** (per esempio **Da validare**, **Validato**, **Da programmare**, **Programmato**, **In scadenza**, **Sospeso**, **Annullato**, **Disdetto**). Un tab mostra solo i contratti in quello stato, cioè filtra la colonna **Stato contratto**; **Tutti** toglie il filtro. Accanto a ogni tab, tranne **Tutti**, c'è il numero di contratti (solo se diverso da zero): tiene conto di ricerca e filtri attivi, quindi corrisponde a quello che vedi aprendo il tab. Se rinomini, aggiungi o disattivi uno stato, i tab si aggiornano di conseguenza. Il tab si combina con gli altri filtri, compresa la **Categoria madre**: se non vedi un contratto, controlla anche quelli.",
        },
      ],
    },
    {
      id: 'list-filters',
      title: "Filtri predefiniti e categoria",
      blocks: [
        {
          type: 'paragraph',
          text: "All'apertura la pagina **Contratti** mostra tutti i record che il tuo profilo ti permette di vedere, senza un filtro **Assegnazione**: con il permesso di vedere tutto vedi tutto, altrimenti vedi i contratti dove sei **Supervisore** o **Gestore account** (e quelli della tua sede o della tua competenza, se il profilo lo prevede). Nel pannello **Filtri avanzati** la **Categoria madre** parte vuota, e vuota vale per tutte le categorie madri in cui sei abilitato: se sei abilitato a tutte vedi i contratti di ogni categoria, altrimenti solo i contratti delle tue categorie (con tutte le loro sottocategorie). Aprendo l'elenco del campo trovi solo le categorie madri che puoi scegliere: tutte se sei abilitato a tutto, altrimenti solo le tue. Scegline una o più e premi **Applica** per restringere: la scelta resta salvata. Il campo **Cerca** trova i contratti anche per nome del cliente (l'anagrafica): basta una parte del nome, per esempio \"napoli\".",
        },
        {
          type: 'paragraph',
          text: "Le categorie in cui sei abilitato sono le categorie di competenza del tuo profilo (l'ambito di assegnazione). Se non hai categorie di competenza **Categoria madre** non limita nulla. I filtri non allargano mai ciò che il tuo profilo può vedere.",
        },
        {
          type: 'paragraph',
          text: "Nello stesso pannello c'è anche **Categoria prodotto** (solo quella categoria). Rientra in una categoria un contratto che la ha tra le **righe di ricavo** dell'offerta del contratto.",
        },
        {
          type: 'paragraph',
          text: "Le colonne dei campi flessibili compaiono in fondo all'elenco quando il filtro di categoria indica una sola categoria: una sola voce in **Categoria prodotto** oppure, se quella è vuota, una sola categoria madre attuale in **Categoria madre** (per esempio ISO). Scegliendo una categoria madre attuale entrano nell'elenco anche i record delle categorie vecchie collegate (per esempio [OLD] ISO), che nel filtro non compaiono come voci a parte, e le colonne sono prima quelle della categoria attuale, poi quelle delle vecchie. Con due categorie madri attuali diverse vedi i record di entrambe ma nessuna colonna; lo stesso con la **Categoria madre** vuota. Per vedere solo i record di una categoria vecchia o solo di una nuova usa **Categoria prodotto**. Un campo con lo stesso nome in più categorie può comparire due volte. Mostrano i campi dell'offerta di origine del contratto, in sola lettura: si ordinano, si filtrano e si esportano, mentre i valori si modificano dall'offerta.",
        },
        {
          type: 'note',
          text: "Il limite alle tue categorie con la **Categoria madre** vuota vale solo nella pagina **Contratti**: il campo non compare dentro una scheda. L'esportazione segue i filtri attivi.",
        },
      ],
    },
    {
      id: 'visibility',
      title: "Quali contratti vedi",
      blocks: [
        { type: 'paragraph', text: "Cosa vedi dipende dal tuo ruolo. Serve sempre il permesso **Visualizza**; i livelli si sommano e dipendono dall'offerta del contratto. I permessi dei Contratti sono indipendenti da quelli delle Offerte." },
        { type: 'table', headers: ["Permesso", "Contratti visibili"], rows: [["**Visualizza**", "Quelli in cui sei **Supervisore** o **Gestore account** dell'offerta del contratto."], ["**Visualizza per sede**", "I tuoi e quelli la cui offerta è della tua **Sede operativa**."], ["**Visualizza per competenza**", "I tuoi e quelli la cui offerta ha una categoria nel tuo ambito di assegnazione, in qualunque sede. Con **Ambito su tutte le categorie** tutti."], ["**Visualizza tutti**", "Tutti."]] },
        { type: 'paragraph', text: "Il filtro vale per elenco, scheda, filtri, esportazioni, statistiche e dashboard, selettori e documenti. Aprire il link di un contratto che non puoi vedere mostra un errore di accesso. Vedere un contratto non permette di modificarlo o eliminarlo: servono i permessi **Modifica** ed **Elimina**, e si modifica solo ciò che si vede." },
      ],
    },
  ],
}

export default guide
