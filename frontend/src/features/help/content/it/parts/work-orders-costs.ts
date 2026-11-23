import type { HelpSection } from '../../../types'

/** Costs section of the work-orders guide, split out to stay under the file-size limit. */
export const costsSections: HelpSection[] = [
    {
      id: 'costs',
      title: 'Costi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio della Commessa, **Costi** è una scheda di **Amministrazione**, insieme a Proforma attiva e Proforma passiva (se puoi vedere solo i Costi, la scheda in alto è direttamente **Costi**). I **Dati contrattuali** non sono una scheda: stanno nella scheda **Info**, nella sezione **Dati contrattuali**. La scheda **Costi** confronta i costi **preventivati** (le righe di costo dell\'offerta imputate alle righe di ricavo della commessa) con i costi **effettivi** inseriti qui. Compare solo se hai il permesso di vedere i costi.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Scostamento', 'Costo effettivo meno costo preventivato: se positivo è uno **sforamento** (segnalato con icona e testo, non solo con il colore).'],
            ['Margine', 'Ricavo delle righe della commessa meno i costi. Il ricavo è l\'imponibile, tranne sulle righe con commissione Fornitore **ricevuta**, dove è la sola commissione Fornitore (come nei Dati contrattuali). Le altre provvigioni sono escluse.'],
            ['Non attribuiti', 'Costi effettivi senza una riga offerta di riferimento: entrano nel costo effettivo totale.'],
            ['Consuntivo complessivo', 'Somma, al netto dell\'IVA, di tre fonti: i **costi inseriti a mano**, le righe degli **ODA impegnati** della commessa (approvati, inviati al fornitore, parzialmente o interamente evasi) **non ancora fatturati** (l’impegnato di una riga è la quantità ordinata non ancora fatturata per il prezzo unitario, oppure la quantità ricevuta se il residuo è chiuso), e le righe delle **fatture passive e proforma** della commessa. Una riga ODA fatturata conta una sola volta, con l\'importo del documento passivo. Bozze, ODA in validazione e annullati sono esclusi.'],
            ['Margine consuntivo', 'Ricavo meno il consuntivo complessivo; lo scostamento complessivo è il consuntivo complessivo meno il costo preventivato.'],
            ['Per tipo di costo', 'Tabella con, per ogni **tipo di costo** (colore e natura: diretto, indiretto, generale), l\'importo delle tre fonti e il totale. I costi senza tipo sono nella riga **Senza tipo di costo**. Il budget non è ripartito per tipo di costo.'],
          ],
        },
        {
          type: 'note',
          text: 'I costi generici dell\'offerta, non imputati a nessuna riga di ricavo, sono mostrati a parte come informazione e non entrano nei totali del confronto.',
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Costi effettivi**: un unico riquadro con a sinistra il **Catalogo prodotti** e a destra i costi effettivi come card (su schermi stretti uno sotto l\'altro). Senza il permesso di gestire i costi il catalogo non compare e le card sono in sola lettura.',
            'Nel **Catalogo prodotti** cerca per nome o descrizione: ogni prodotto mostra nome, codice, tipologia, prezzo di costo con unità di misura, categoria e descrizione.',
            'Spunta uno o più prodotti e premi **Aggiungi N selezionati**: ogni prodotto diventa un costo con data di oggi, quantità 1, prezzo di costo, IVA, unità di misura e **sottoconto costo** del prodotto. Lo stesso prodotto si può aggiungere più volte.',
            "Se il prodotto ha dei fornitori, il **fornitore** si sceglie solo tra quelli del prodotto: il predefinito e il suo costo sono proposti e, cambiando fornitore, cambia il prezzo. Se il fornitore ha una commissione Fornitore su quel prodotto, il prezzo è 0 e non modificabile, con l'avviso accanto al fornitore. Con un prodotto senza fornitori, o senza prodotto, il fornitore è libero come prima.",
            'In ogni card trovi in alto prodotto, codice e categoria con il cestino per rimuoverla; sotto quantità per prezzo unitario, IVA, **Riga offerta di riferimento** e imponibile, IVA e totale; in fondo data, **sottoconto costo**, **fornitore**, **riferimento documento** e descrizione aggiuntiva. In fondo al riquadro c\'è il **Totale costi**.',
            'Per cambiare prodotto rimuovi la card e scegli il prodotto dal catalogo.',
            'Premi **Salva** per sostituire l\'intero elenco dei costi, oppure **Annulla** per tornare all\'ultimo salvataggio.',
          ],
        },
        {
          type: 'tip',
          text: 'I costi effettivi si possono inserire anche su una commessa completata o chiusa, perché spesso arrivano dopo la chiusura.',
        },
      ],
    },
]
