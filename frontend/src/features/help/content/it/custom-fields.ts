import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'custom-fields',
  title: 'Campi personalizzati',
  summary: 'I campi personalizzati aggiungono informazioni che i moduli non prevedono, come una targa o una data di scadenza.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Sistema › Campi personalizzati**. Il campo compare nella scheda e nell’elenco del modulo scelto.' },
      ],
    },
    {
      id: 'create-custom-field',
      title: 'Creare un campo personalizzato',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Sistema › Campi personalizzati**.', 'Fai clic su **Nuovo campo personalizzato**.', 'Il riquadro **Anteprima** mostra in tempo reale come apparirà il campo. Le righe sono chiuse: premi la matita (o fai clic sul valore) di **Modulo**, **Tipo**, **Chiave** ed **Etichetta**, compila e conferma con **Fatto**; **Ripristina** riporta la riga com’era.', 'Gli editor che dipendono dal tipo (impostazioni, opzioni, relazione, colonne della tabella, validazione) compaiono già aperti sotto le righe.', 'Fai clic su **Salva**, in alto o in fondo alla pagina. **Annulla** chiede conferma prima di scartare i dati inseriti.'] },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Modulo**', 'Dove comparirà il campo, ad esempio Utenti, Prodotti, Società aziendali o Referenti.'],
            ['**Tipo**', 'Il tipo di dato (vedi la sezione Tipi di campo).'],
            ['**Chiave**', 'Nome interno univoco, in minuscolo con trattini bassi, ad esempio scadenza_contratto.'],
            ['**Etichetta**', 'Il nome visibile nella scheda e come titolo di colonna.'],
            ['**Descrizione**', 'Nota mostrata sopra il campo. Facoltativa.'],
            ['**Testo di aiuto**', 'Suggerimento breve sotto il campo. Facoltativo.'],
            ['**Placeholder**', 'Testo di esempio nel campo vuoto, ad esempio GG/MM/AAAA. Facoltativo.'],
            ['**Icona**', 'Icona accanto all’etichetta. Facoltativa.'],
            ['**Gruppo**', 'Riunisce più campi sotto un titolo comune.'],
            ['**Scheda**', 'La scheda del modulo in cui mostrare il campo, se il modulo usa le schede.'],
            ['**Ordine**', 'Posizione tra i campi personalizzati: i numeri più bassi vengono prima.'],
            ['**Attivo**', 'Se lo spegni, il campo sparisce da schede ed elenchi.'],
            ['**Indicizzato**', 'Rende più veloci filtri e ordinamenti su questo campo.'],
          ],
        },
      ],
    },
    {
      id: 'field-types',
      title: 'Tipi di campo e impostazioni',
      blocks: [
        {
          type: 'table',
          headers: ['Tipo', 'Uso tipico'],
          rows: [
            ['**Testo**', 'Riga breve: codice cliente, targa, matricola.'],
            ['**Testo lungo**', 'Note o descrizioni su più righe.'],
            ['**Numero intero**', 'Quantità, numero di posti.'],
            ['**Numero decimale**', 'Prezzo, peso, percentuale.'],
            ['**Sì/No**', 'Consenso privacy, attivo sì o no.'],
            ['**Elenco di opzioni**', 'Scelta tra valori fissi, ad esempio bassa, media o alta.'],
            ['**Relazione**', 'Collegamento a schede di un altro modulo.'],
            ['**Data**, **Data e ora**, **Ora**', 'Scadenze, appuntamenti, orari.'],
            ['**Email**, **URL**', 'Indirizzo email o sito web.'],
            ['**Colore**', 'Un colore selezionabile.'],
            ['**Tabella**', 'Più righe con colonne proprie, ad esempio le verifiche ispettive di una commessa.'],
          ],
        },
        { type: 'list', items: ['**Elenco di opzioni:** aggiungi i valori con **Aggiungi opzione**. Ogni opzione ha **Valore**, **Etichetta** e, se vuoi, colore e icona. Serve almeno un’opzione.', '**Relazione:** scegli il **Modulo target** e la **Cardinalità**: **Singola** collega una scheda, **Multipla** più schede. Come modulo target puoi scegliere anche **Province**: il campo diventa una scelta tra le province.', '**Testo** e numeri: puoi fissare lunghezze, minimo, massimo e cifre decimali.'] },
        { type: 'paragraph', text: 'Nella sezione **Validazione**, **Obbligatorio** impedisce di salvare con il campo vuoto. **Univoco** impedisce che due schede dello stesso modulo abbiano lo stesso valore.' },
      ],
    },
    {
      id: 'table-field',
      title: 'Campo di tipo Tabella',
      blocks: [
        { type: 'paragraph', text: 'Il tipo **Tabella** raccoglie più righe di dati con le stesse colonne, ad esempio data verifica, ispettore, supporto On/Off Site e fase Stage 1/Stage 2. Nel form del campo compare l’editor **Colonne**.' },
        { type: 'steps', items: ['Scegli il tipo **Tabella**.', 'Con **Aggiungi colonna** inserisci le colonne (fino a 20); con le frecce le sposti su e giù, con il cestino le rimuovi.', 'Per ogni colonna indica **Etichetta**, **Chiave** (proposta in automatico dall’etichetta: minuscole, numeri e trattini bassi; **id** è riservata), **Tipo** e se è **Obbligatoria**.', 'Facoltativo: imposta **Righe minime** e **Righe massime** (fino a 200).', 'Fai clic su **Salva**.'] },
        { type: 'list', items: ['**Tipi di colonna:** Testo, Testo lungo, Intero, Decimale, Sì/No, Scelta (con opzioni Valore ed Etichetta), Data, Data e ora, Ora, Email, URL, Colore.', '**Selezione riga:** attiva l’opzione e indica etichetta e chiave: ogni riga avrà un pulsante di selezione e in ogni scheda puoi selezionare al massimo una riga.', '**Riepilogo in griglia:** scegli la colonna da mostrare negli elenchi (data, data e ora, ora, intero, decimale, testo o scelta) e la strategia: **Riga selezionata** (richiede la selezione riga), **Massimo** o **Minimo**. Oppure nessun riepilogo.'] },
        { type: 'paragraph', text: 'Negli elenchi, se il campo ha la selezione riga e una riga è selezionata, la cella mostra in linea i valori di quella riga (date nel formato locale, scelte con la loro etichetta, Sì/No con il nome della colonna); altrimenti mostra il riepilogo seguito dal numero di righe, ad esempio “12/10/2026 · 3”. Senza righe la cella è vuota. Passando con il mouse o spostando il focus da tastiera sulla cella compare un riquadro con la tabella completa, con la riga selezionata evidenziata. Ordinamento e filtro testo lavorano sul riepilogo. La colonna non si modifica nell’elenco: si modifica dalla scheda. Nei modelli di documento la variabile del campo mostra il riepilogo.' },
        { type: 'note', text: 'Per un campo Tabella l’opzione **Indicizzato** non è disponibile. Se il campo è **Obbligatorio**, serve almeno una riga.' },
        { type: 'warning', text: 'Puoi modificare le colonne anche quando esistono già dati: una colonna rimossa non viene più mostrata, mentre cambiare il tipo di una colonna non converte i valori già salvati.' },
      ],
    },
    {
      id: 'table-field-fill',
      title: 'Compilare una tabella nella scheda',
      blocks: [
        { type: 'list', items: ['Fai clic su **Aggiungi riga** e compila le celle; il pulsante di rimozione elimina la riga.', 'Se il campo ha la selezione riga, usa il pulsante di scelta per indicare la riga selezionata.', 'Gli errori compaiono sulla singola cella. Su smartphone ogni riga è una scheda.', 'Nel dettaglio la tabella è in sola lettura e la riga selezionata è evidenziata.'] },
      ],
    },
    {
      id: 'display-in-records',
      title: 'Come appaiono nelle schede',
      blocks: [
        { type: 'paragraph', text: 'I campi con un **Gruppo** compaiono sotto il titolo del gruppo; gli altri nella sezione **Altri campi**. L’ordine segue il valore di **Ordine**.' },
      ],
    },
    {
      id: 'edit-in-place',
      title: 'Modificare un campo sul posto',
      blocks: [
        { type: 'paragraph', text: 'La scheda di un campo non ha un modulo di modifica: ogni riga si modifica sul posto. Premi la matita su una riga (ad esempio **Etichetta**, **Gruppo**, **Ordine**, **Attivo** o **Indicizzato**), cambia il valore e premi **Salva**; **Annulla** o Esc lasciano la riga com’era. Ogni riga salva solo i propri dati.' },
        { type: 'paragraph', text: '**Presentazione** è una riga unica che raccoglie descrizione, testo di aiuto, placeholder e icona. Le impostazioni che dipendono dal tipo sono righe a tutta larghezza con la loro matita: **Impostazioni campo**, **Opzioni** (elenco), **Relazione**, **Colonne** (tabella) e **Validazione**; la matita apre lo stesso editor della creazione e **Salva** invia solo quella parte.' },
        { type: 'note', text: '**Modulo**, **Tipo** e **Chiave** si scelgono alla creazione e non si possono cambiare dopo: non hanno la matita. Senza il permesso di modifica nessuna riga ha la matita.' },
      ],
    },
    {
      id: 'edit-deactivate-delete',
      title: 'Modificare, disattivare ed eliminare',
      blocks: [
        { type: 'list', items: ['**Modificare:** apri la scheda con **Visualizza** e modifica le righe sul posto (vedi la sezione precedente).', '**Disattivare:** spegni **Attivo**. Il campo sparisce ovunque, ma i valori già inseriti restano.', '**Eliminare:** scegli **Elimina** e conferma.'] },
        { type: 'warning', text: 'Quando un campo contiene già dei valori, non puoi più cambiarne **Modulo**, **Tipo** e **Chiave**. Eliminando un campo cancelli anche tutti i suoi valori: nel dubbio, disattivalo.' },
      ],
    },
  ],
}

export default guide
