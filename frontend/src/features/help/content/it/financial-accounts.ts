import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'financial-accounts',
  title: 'Gestione Conti',
  summary: 'Anagrafica dei conti aziendali: conti correnti, carte e casse.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Contabilità › Gestione Conti**. Raccoglie in un unico elenco tre tipi di conto: **Conto Corrente**, **Carta** e **Cassa**. Puoi filtrare, ordinare ed esportare l’elenco; la colonna **Società** è nascosta per impostazione predefinita e si attiva dal selettore colonne.' },
      ],
    },
    {
      id: 'types',
      title: 'Tipi di conto e campi',
      blocks: [
        { type: 'paragraph', text: 'Quando crei un conto scegli prima il **Tipo**: la finestra mostra solo i campi di quel tipo. Dopo la creazione il tipo non si può più cambiare.' },
        {
          type: 'table',
          headers: ['Tipo', 'Campi'],
          rows: [
            ['**Conto Corrente**', '**Banca**, **IBAN**, **Conto Corrente** (numero), Società, indirizzo (via, CAP, località), Note. Banca, IBAN e Conto Corrente sono obbligatori.'],
            ['**Carta**', '**Tipo carta** (Credito o Prepagata), **Banca**, **Circuito** (Visa, Mastercard, Amex), **Associa al conto**, **Intestatario**, **Numero carta**, **Scadenza** (MM/AAAA), Società, Note.'],
            ['**Cassa**', '**Nome**, Società, indirizzo, Note. Il Nome è obbligatorio.'],
          ],
        },
        { type: 'note', text: '**Associa al conto** elenca solo i Conti Correnti ed è obbligatorio solo per le carte di **Credito**; per le **Prepagate** è facoltativo.' },
      ],
    },
    {
      id: 'iban-check',
      title: 'Controllo dell’IBAN',
      blocks: [
        { type: 'paragraph', text: 'L’IBAN viene controllato formalmente (struttura e cifre di controllo, per qualsiasi paese) e salvato in maiuscolo e senza spazi. Lo stesso IBAN non può essere usato da due conti.' },
      ],
    },
    {
      id: 'card-security',
      title: 'Sicurezza dei dati della carta',
      blocks: [
        { type: 'warning', text: 'Il **CVV** e il **PIN** della carta non vengono mai richiesti né salvati.' },
        { type: 'paragraph', text: 'Il numero carta è conservato cifrato e compare sempre **mascherato** (ad esempio **** 1234). Chi ha il permesso dedicato vede nella scheda il pulsante **Mostra numero**, che mostra il numero completo: ogni visualizzazione viene registrata nel registro attività.' },
        { type: 'tip', text: 'In modifica il numero carta resta quello salvato: digitane uno nuovo solo se vuoi sostituirlo.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Contabilità › Gestione Conti** e premi **Nuovo conto**.', 'Scegli il **Tipo** e compila i campi.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare apri la scheda con **Visualizza** e premi **Modifica**.' },
        { type: 'warning', text: 'Un Conto Corrente con carte collegate non si può eliminare: elimina o scollega prima le carte.' },
      ],
    },
  ],
}

export default guide
