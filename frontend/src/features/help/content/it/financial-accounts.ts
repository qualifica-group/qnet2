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
        { type: 'paragraph', text: 'Il modulo si trova in **Contabilità › Finanziario › Gestione Conti**. Raccoglie in un unico elenco tre tipi di conto: **Conto Corrente**, **Carta** e **Cassa**. Puoi filtrare, ordinare ed esportare l’elenco; la colonna **Società** è nascosta per impostazione predefinita e si attiva dal selettore colonne.' },
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
      id: 'opening-balance',
      title: 'Saldo di apertura e saldo attuale',
      blocks: [
        { type: 'paragraph', text: 'Ogni conto ha un **Saldo di apertura**: l’importo da cui parte il conto alla **Data di apertura**. Se non lo imposti il saldo è 0 e la data è il **01/01/2026**; se il conto è stato aperto più tardi (ad esempio a marzo) puoi indicare una data propria. Movimenti, incassi e pagamenti precedenti alla data di apertura non entrano nel saldo né nell’**Estratto conto**.' },
        { type: 'steps', items: ['Apri la scheda del conto con **Visualizza**.', 'Nel riquadro **Saldo di apertura** premi **Modifica**.', 'Inserisci **Importo** e **Data di apertura** e premi **Salva**.'] },
        { type: 'list', items: ['Il saldo di apertura lo modifica solo chi ha il permesso dedicato; gli altri lo vedono in sola lettura. Ogni modifica finisce nello storico.', 'Non puoi spostare la data di apertura dopo un movimento già registrato sul conto.', 'Il **Saldo attuale** compare solo a chi può consultare i movimenti di tesoreria, con il link **Vedi estratto conto** per aprire l’Estratto conto di quel conto.'] },
        { type: 'note', text: 'Solo i conti hanno un saldo di apertura: per clienti e fornitori si parte dal residuo delle rate.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare ed eliminare',
      blocks: [
        { type: 'steps', items: ['Apri **Contabilità › Finanziario › Gestione Conti** e premi **Nuovo conto**.', 'Scegli il **Tipo** e compila i campi.', 'Premi **Salva**.'] },
        { type: 'paragraph', text: 'Sulle righe dell’elenco trovi **Visualizza** ed **Elimina**, se il tuo ruolo lo consente. Per modificare apri la scheda con **Visualizza** e premi **Modifica**.' },
        { type: 'warning', text: 'Un conto usato da fatture, fatture passive e loro rate, movimenti di tesoreria o riconciliazioni bancarie non si può eliminare, così come un Conto Corrente con carte collegate (elimina o scollega prima le carte). Il messaggio dice da cosa è usato.' },
      ],
    },
  ],
}

export default guide
