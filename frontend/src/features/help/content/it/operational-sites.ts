import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'operational-sites',
  title: 'Sedi operative',
  summary: "Le Sedi operative sono i luoghi fisici in cui l'organizzazione lavora.",
  sections: [
    {
      id: 'overview',
      title: 'Cosa sono le sedi operative',
      blocks: [
        {
          type: 'paragraph',
          text: 'Una sede operativa si riconosce dal suo indirizzo: non ha un nome proprio, è il suo indirizzo. Si sceglie, per esempio, nelle opportunità e nei profili utente.',
        },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'paragraph',
          text: "Premi Nuova sede operativa: il modulo ha lo stesso aspetto del dettaglio, con le righe chiuse. Fai clic sulla riga (o sulla matita) per aprirla, poi **Fatto** per tenere il valore o **Ripristina** per riportarlo com'era; **Salva** controlla tutto e crea la sede (se esci senza salvare ti viene chiesta conferma). I campi sono:",
        },
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['Alias', 'Un nome breve per riconoscere la sede. Facoltativo.'],
            ['Via', 'Obbligatoria.'],
            ['CAP', 'Il codice postale.'],
            ['Comune', 'Obbligatorio.'],
            ['Sede attiva', 'Se la disattivi, la sede non viene più proposta negli elenchi di scelta.'],
          ],
        },
      ],
    },
    {
      id: 'editing',
      title: 'Modificare una sede',
      blocks: [
        {
          type: 'paragraph',
          text: 'Non esiste una pagina di modifica separata: la sede si modifica **direttamente dal suo dettaglio**, una riga alla volta, campi personalizzati compresi. Premi la **matita** (o fai clic sul valore), cambia il valore e premi **Salva**: si salva solo quella riga. **Annulla** o Esc la lasciano com\'era. Il Comune si cambia insieme a Provincia, Regione e Nazione, in un\'unica riga.',
        },
      ],
    },
    {
      id: 'deactivating-instead-of-deleting',
      title: 'Disattivare invece di eliminare',
      blocks: [
        {
          type: 'tip',
          text: 'Per una sede che non usi più, spegni Sede attiva (matita sulla riga) invece di eliminarla.',
        },
      ],
    },
  ],
}

export default guide
