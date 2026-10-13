import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'companies',
  title: 'Società aziendali',
  summary: 'Le Società aziendali sono le società della tua organizzazione, con denominazione, partita IVA e indirizzo.',
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le società aziendali',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una società aziendale rappresenta una delle società della tua organizzazione. Ogni Società sede appartiene a una società aziendale.',
        },
      ],
    },
    {
      id: 'creating-a-company',
      title: 'Creare una società',
      blocks: [
        {
          type: 'paragraph',
          text: "Premi Nuova società: il modulo ha lo stesso aspetto del dettaglio, con le righe chiuse. Fai clic sulla riga (o sulla matita) per aprirla, poi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era; **Salva** controlla tutto e crea la società (se esci senza salvare ti viene chiesta conferma). I campi sono:",
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Denominazione', 'Il nome della società. Obbligatoria.'],
            ['Partita IVA', 'Il sistema ne controlla la validità.'],
            ['Indirizzo, CAP, Indirizzo (riga 2)', 'La sede legale. Facoltativo, ma se lo inserisci la via è obbligatoria.'],
          ],
        },
      ],
    },
    {
      id: 'editing',
      title: 'Modificare una società',
      blocks: [
        {
          type: 'paragraph',
          text: "Non esiste una pagina di modifica separata: la società si modifica **direttamente dal suo dettaglio**, una riga alla volta, campi personalizzati compresi. Premi la **matita** (o fai clic sul valore), cambia il valore e premi **Salva**; **Annulla** o Esc lasciano la riga com'era. Il Comune si cambia insieme a Provincia, Regione e Nazione, in un'unica riga; l'indirizzo viene salvato per intero.",
        },
      ],
    },
  ],
}

export default guide
