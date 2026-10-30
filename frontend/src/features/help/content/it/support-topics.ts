import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'support-topics',
  title: 'Assistenza tecnica',
  summary: 'I tipi di problema che gli utenti possono scegliere quando chiedono assistenza al reparto IT.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si configura in **Configurazione › Task › Assistenza tecnica**. Ogni voce è un **tipo di problema** (per esempio Stampante, Password, PC lento) che compare nella finestra **Assistenza tecnica** dell’intestazione. Quando un utente invia una richiesta, QNet crea da solo un task già completo usando i valori di questo tipo.',
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Nome', 'Obbligatorio, al massimo 191 caratteri. È il nome che vede l’utente.'],
            ['Descrizione', 'Facoltativa. Un breve aiuto mostrato sotto il nome.'],
            ['Icona', 'Facoltativa. Compare sul pulsante del tipo.'],
            ['Ordine', 'Decide la posizione nell’elenco mostrato agli utenti.'],
            ['Attivo', 'Se lo spegni, il tipo sparisce dalla finestra degli utenti.'],
            ['Tipologia, Categoria, Priorità, Importanza', 'Valori che il task creato avrà già impostati.'],
            ['Priorità se urgente', 'Facoltativa. Sostituisce la priorità normale quando l’utente indica che è urgente.'],
            ['Scadenza dopo / Scadenza dopo se urgente', 'Giorni di calendario da oggi alla scadenza del task. Se il campo urgente è vuoto valgono i giorni normali.'],
            ['Task privato, Feedback di chiusura richiesto', 'Impostazioni che il task creato avrà già attive o spente.'],
            ['Assegnatari', 'Obbligatori: almeno un utente a cui arriva il task e la notifica.'],
            ['Osservatori', 'Facoltativi. Non possono essere anche assegnatari.'],
          ],
        },
        {
          type: 'warning',
          text: 'Un tipo senza almeno un assegnatario attivo non viene mostrato agli utenti. Se nessun tipo è attivo, la finestra dice che l’assistenza non è ancora configurata.',
        },
      ],
    },
    {
      id: 'managing',
      title: 'Creare, modificare, disattivare',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuovo tipo di problema**.',
            'Per ogni riga premi la matita, compila e premi **Fatto**; poi premi **Salva**.',
            'Per modificare, apri la scheda con **Visualizza**, premi la matita sulla riga, cambia il valore e premi **Salva** (si salva solo quella riga).',
            'Per nasconderlo agli utenti senza perderlo, spegni **Attivo**.',
          ],
        },
        {
          type: 'note',
          text: 'Eliminare un tipo non tocca i task già creati: restano com’erano.',
        },
      ],
    },
  ],
}

export default guide
