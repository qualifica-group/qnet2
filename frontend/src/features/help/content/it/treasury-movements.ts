import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'treasury-movements',
  title: 'Movimenti di tesoreria',
  summary: 'Registra a mano entrate, uscite, giroconti e rettifiche di saldo che non passano da una fattura.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Contabilità › Finanziario › Movimenti di tesoreria**. Serve a registrare i **fatti di cassa e di banca che non passano da una fattura**: interessi, commissioni, versamenti dei soci, F24, stipendi, prelievi. Gli incassi e i pagamenti delle fatture restano sulle rispettive rate e compaiono da soli nell’**Estratto conto**.' },
        { type: 'note', text: 'Non è una contabilità in partita doppia: ogni movimento incide sul saldo del conto che scegli.' },
      ],
    },
    {
      id: 'types',
      title: 'I quattro tipi di movimento',
      blocks: [
        {
          type: 'table',
          headers: ['Tipo', 'Quando usarlo', 'Esempi'],
          rows: [
            ['**Entrata**', 'Soldi che entrano su un conto senza una fattura.', 'Interessi attivi, versamento dei soci.'],
            ['**Uscita**', 'Soldi che escono da un conto senza una fattura.', 'Commissioni bancarie, F24, stipendi.'],
            ['**Giroconto**', 'Spostamento tra due conti **della stessa società**: scende il conto di origine e sale quello di destinazione, il totale non cambia.', 'Prelievo dalla banca per la cassa, addebito mensile della carta.'],
            ['**Rettifica di saldo**', 'Allinea il saldo del conto all’estratto della banca. Indica la **Direzione** (In entrata o In uscita) e spiega il motivo nella descrizione.', 'Differenza di pochi centesimi, arrotondamento.'],
          ],
        },
        { type: 'warning', text: 'Il giroconto è ammesso **solo tra conti della stessa società**. Tra società diverse non puoi registrarlo: il salvataggio viene rifiutato.' },
      ],
    },
    {
      id: 'new-movement',
      title: 'Registrare un movimento',
      blocks: [
        { type: 'steps', items: ['Apri **Movimenti di tesoreria** e premi **Nuovo movimento**.', 'Scegli il **Tipo**: la finestra mostra solo i campi pertinenti (per il giroconto il **Conto di destinazione**, per la rettifica la **Direzione**).', 'Compila **Data**, **Importo**, **Conto**, **Descrizione** e **Sottoconto**; se serve aggiungi **Anagrafica** e **Commessa**.', 'Premi **Salva**.'] },
        {
          type: 'list',
          items: [
            'Sono obbligatori: **Data**, **Conto**, **Importo** (maggiore di zero, al massimo due decimali), **Descrizione** (fino a 255 caratteri) e **Sottoconto**.',
            'Nel giroconto il **Conto di destinazione** è obbligatorio e deve essere diverso dal conto di origine.',
            'Nella rettifica la **Direzione** è obbligatoria.',
            'La data non può essere precedente alla **data di apertura** del conto (vedi la guida Gestione Conti).',
          ],
        },
      ],
    },
    {
      id: 'sub-accounts',
      title: 'Il sottoconto è obbligatorio',
      blocks: [
        { type: 'paragraph', text: 'Ogni movimento va classificato con un **Sottoconto** del piano dei conti. Il tipo di movimento decide quali sottoconti puoi scegliere:' },
        {
          type: 'table',
          headers: ['Tipo', 'Sottoconti ammessi'],
          rows: [
            ['**Entrata**', 'Sottoconti di **ricavo** oppure **finanziari**.'],
            ['**Uscita**', 'Sottoconti di **costo** oppure **finanziari**.'],
            ['**Giroconto** e **Rettifica di saldo**', 'Solo sottoconti **finanziari**.'],
          ],
        },
        { type: 'paragraph', text: 'I sottoconti finanziari stanno nel **mastro 90 Movimenti finanziari** (Giroconti, Finanziamenti, Tributi e contributi, Rettifiche di saldo) e si gestiscono dal **Piano dei conti**. I sottoconti disattivati non si possono scegliere per un nuovo movimento, ma restano sui movimenti già registrati.' },
      ],
    },
    {
      id: 'attachments',
      title: 'Allegati',
      blocks: [
        { type: 'paragraph', text: 'Apri un movimento in **modifica** per allegare documenti, ad esempio la **contabile** del bonifico o l’avviso della banca. Gli allegati si possono aprire ed eliminare dalla stessa finestra.' },
      ],
    },
    {
      id: 'edit-delete',
      title: 'Modifica e cancellazione',
      blocks: [
        { type: 'paragraph', text: 'I movimenti si possono **modificare ed eliminare sempre**, anche a distanza di tempo: non esiste un blocco per periodo. Ogni modifica e cancellazione viene registrata nello **storico** (chi, cosa, quando, valori prima e dopo).' },
        { type: 'tip', text: 'Se sbagli conto o importo, correggi il movimento invece di crearne uno opposto: l’Estratto conto e i saldi si aggiornano subito.' },
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
            ['Vedere l’elenco, l’**Estratto conto**, i saldi e il riquadro **Da verificare**', 'Consultazione movimenti di tesoreria'],
            ['**Nuovo movimento**, modifica ed eliminazione', 'Gestione movimenti di tesoreria'],
            ['**Esporta**', 'Esportazione movimenti di tesoreria'],
            ['Storico delle modifiche', 'Visualizzazione storico movimenti'],
            ['Gestire i sottoconti finanziari', 'Permessi sui sottoconti finanziari (Piano dei conti)'],
          ],
        },
        { type: 'note', text: 'Chi può solo consultare vede tutto ma non può creare, modificare o eliminare movimenti.' },
      ],
    },
  ],
}

export default guide
