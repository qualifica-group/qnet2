import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'work-orders',
  title: 'Commesse',
  summary: 'Il lavoro da svolgere su un contratto vinto.',
  sections: [
    {
      id: 'costs',
      title: 'Costi',
      blocks: [
        {
          type: 'paragraph',
          text: 'Nel dettaglio della Commessa la sezione **Costi** confronta i costi **preventivati** (le righe di costo dell\'offerta imputate alle righe di ricavo della commessa) con i costi **effettivi** inseriti qui. Compare solo se hai il permesso di vedere i costi.',
        },
        {
          type: 'table',
          headers: ['Voce', 'Significato'],
          rows: [
            ['Scostamento', 'Costo effettivo meno costo preventivato: se positivo è uno **sforamento** (segnalato con icona e testo, non solo con il colore).'],
            ['Margine', 'Ricavo netto delle righe della commessa meno i costi, calcolato sull\'imponibile. Le provvigioni sono escluse.'],
            ['Non attribuiti', 'Costi effettivi senza una riga offerta di riferimento: entrano nel costo effettivo totale.'],
          ],
        },
        {
          type: 'note',
          text: 'I costi generici dell\'offerta, non imputati a nessuna riga di ricavo, sono mostrati a parte come informazione e non entrano nei totali del confronto.',
        },
        {
          type: 'steps',
          items: [
            'Apri la scheda **Costi effettivi** (solo lettura senza il permesso di gestire i costi).',
            'Premi **Aggiungi riga**: la data del costo è precompilata a oggi.',
            'Scegli il **prodotto** di costo: prezzo unitario, aliquota IVA e unità di misura si precompilano dal prodotto.',
            'Indica quantità, prezzo, IVA, **fornitore**, **riferimento documento** e la **riga offerta di riferimento**.',
            'Premi **Salva** per sostituire l\'intero elenco dei costi, oppure **Annulla** per tornare all\'ultimo salvataggio.',
          ],
        },
        {
          type: 'tip',
          text: 'I costi effettivi si possono inserire anche su una commessa completata o chiusa, perché spesso arrivano dopo la chiusura.',
        },
      ],
    },
    {
      id: 'proforma-request',
      title: 'Richiesta di proforma (pulsante €)',
      blocks: [
        {
          type: 'paragraph',
          text: 'Se hai il permesso di creare richieste proforma, nella colonna **Azioni** dell\'elenco Commesse (anche nel tab Commesse del contratto) trovi il pulsante **€**. Il colore indica lo stato: **grigio** nessuna richiesta, **blu** richiesta inviata e non ancora evasa, **giallo** proforma emessa.',
        },
        {
          type: 'steps',
          items: [
            'Premi il pulsante **€** grigio: si apre la finestra **Richiesta emissione Proforma: Commessa #numero**.',
            'Controlla la **Modalità di pagamento** presa dall\'offerta (se manca compare **Non indicata**).',
            'Scrivi le **Note per la Contabilità** (obbligatorie, massimo 5000 caratteri): il testo è precompilato con il titolo della finestra.',
            'Premi **Invia richiesta**. Il sistema crea una richiesta per le righe di tipo Consulenza e una per ciascun fornitore delle righe di tipo Ente.',
          ],
        },
        {
          type: 'note',
          text: 'Con il pulsante **€** blu la finestra mostra **Ultima richiesta del** e la data: finché la richiesta non è evasa non se ne può inviare un\'altra e **Invia richiesta** resta disattivato. Con il pulsante giallo non si apre nulla.',
        },
        {
          type: 'tip',
          text: 'Le richieste inviate si consultano in **Contabilità › Attiva › Richieste Proforma**.',
        },
      ],
    },
    {
      id: 'in-development',
      title: 'Modulo in sviluppo',
      blocks: [
        {
          type: 'note',
          text: 'Modulo in fase di sviluppo. La guida verrà pubblicata quando il modulo sarà completato.',
        },
      ],
    },
  ],
}

export default guide
