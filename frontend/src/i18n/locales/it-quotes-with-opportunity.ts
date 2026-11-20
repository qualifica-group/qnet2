/**
 * Procedura "Nuova opportunità + offerta" del modulo Offerte (spec 0396).
 * File affiancato a `it-quotes.ts` (già oltre il limite dimensionale):
 * confluisce in `quotes.withOpportunity`.
 */

export const quotesWithOpportunity = {
  chooser: {
    title: 'Nuova offerta',
    description: "Scegli come iniziare: l'offerta può andare su un'opportunità esistente oppure nascere insieme a una nuova.",
    existing: {
      title: "Da un'opportunità esistente",
      description: "Scegli l'opportunità su cui creare l'offerta.",
    },
    new: {
      title: 'Nuova opportunità + offerta',
      description: "Compila l'opportunità e l'offerta in due passi e salva tutto insieme.",
    },
  },
  tabsLabel: 'Passi della procedura',
  tabs: {
    opportunity: 'Opportunità',
    quote: 'Offerta',
  },
  hint: {
    opportunity:
      "Passo 1 di 2: compila l'opportunità. Il salvataggio avviene una sola volta, alla fine del passo 2.",
    quote:
      "Passo 2 di 2: compila l'offerta. Salvando vengono create sia l'opportunità sia l'offerta.",
  },
  back: 'Indietro',
  next: 'Avanti',
  checking: 'Controllo in corso',
  newOpportunity: 'Nuova opportunità (verrà creata al salvataggio)',
  fixErrors: 'Compila i campi obbligatori del passo 1 per continuare.',
  existingOpen: "L'anagrafica ha già un'opportunità aperta: {{name}}.",
  offerOnExisting: "Crea l'offerta su questa opportunità",
  created: 'Opportunità e offerta create.',
  genericError: "Impossibile completare l'operazione. Riprova.",
}
