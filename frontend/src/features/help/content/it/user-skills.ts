import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'user-skills',
  title: 'Competenze',
  summary: 'Cosa sa fare ogni persona, a quale livello, e se le competenze richieste dalle sue mansioni sono coperte.',
  sections: [
    {
      id: 'overview',
      title: 'A cosa serve',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina **Competenze** registra le competenze di ogni persona con un livello e mostra se quelle richieste dalle sue mansioni sono coperte. Ha due schede, **Persone** e **Copertura**, in alto a destra accanto al pulsante **Assegna competenza**. Le competenze si definiscono nel **Catalogo competenze**, in Configurazione.',
        },
        {
          type: 'note',
          text: 'Non vanno confuse con gli **Ambiti di assegnazione** della scheda utente: quelli decidono solo chi riceve Lead, richieste e iscritti. Le competenze descrivono ciò che la persona sa fare e non influiscono sulle assegnazioni.',
        },
      ],
    },
    {
      id: 'levels',
      title: 'Livelli',
      blocks: [
        {
          type: 'table',
          headers: ['Livello', 'Nome'],
          rows: [
            ['1', 'Base'],
            ['2', 'Intermedio'],
            ['3', 'Avanzato'],
            ['4', 'Esperto'],
          ],
        },
        {
          type: 'paragraph',
          text: 'Il livello compare sempre con il numero e il nome, non solo con un colore.',
        },
      ],
    },
    {
      id: 'permissions',
      title: 'Chi vede e chi scrive',
      blocks: [
        {
          type: 'table',
          headers: ['Permesso', 'Cosa permette'],
          rows: [
            ['Visualizza elenco', 'Apre la pagina e mostra le tue competenze.'],
            ['Avere dei sottoposti', 'Non serve un permesso: se qualcuno fa capo a te nell’organigramma, apri la pagina anche senza Visualizza elenco e vedi le tue competenze e quelle dei tuoi sottoposti, in sola lettura.'],
            ['Visualizza tutti', 'Mostra le competenze di tutte le persone, in sola lettura.'],
            ['Gestire', 'Assegna, modifica e rimuove le competenze di chiunque e vede tutti: solo l’ufficio del personale scrive.'],
          ],
        },
        {
          type: 'warning',
          text: 'Nessuno modifica le proprie competenze, salvo chi ha il permesso Gestire. Modifica e rimozione compaiono solo se puoi scrivere su quella persona.',
        },
      ],
    },
    {
      id: 'people',
      title: 'Persone',
      blocks: [
        {
          type: 'paragraph',
          text: 'L’elenco delle competenze che puoi vedere, con persona, competenza, categoria, livello e data di valutazione. Fai clic su una riga per aprire il dettaglio: in alto la competenza, la persona, il livello e, se puoi scrivere su quella persona, **Modifica** ed **Elimina**; sotto la sezione **Valutazione** (categoria, data, chi l’ha valutata, note).',
        },
        {
          type: 'steps',
          items: [
            'Fai clic su **Assegna competenza** (visibile solo con Gestire).',
            'Nella sezione **Persona e competenza** scegli la persona e la competenza; nella sezione **Valutazione** il livello, la data di valutazione (non può essere futura) ed eventuali note.',
            'Premi **Salva**, in alto a destra o in fondo al modulo.',
          ],
        },
        {
          type: 'note',
          text: 'C’è una sola riga per persona e competenza: per cambiare il livello modifica quella esistente. La storia dei cambi resta nel registro delle attività.',
        },
      ],
    },
    {
      id: 'coverage',
      title: 'Copertura',
      blocks: [
        {
          type: 'paragraph',
          text: 'Per ogni persona e per ogni competenza attiva richiesta dalle sue mansioni mostra il livello richiesto (il più alto tra le sue mansioni), quello attuale e uno stato, sempre con icona e testo. Puoi filtrare per stato, funzione aziendale e competenza; il clic su una riga con competenza assegnata ne apre il dettaglio.',
        },
        {
          type: 'table',
          headers: ['Stato', 'Significato'],
          rows: [
            ['Coperta', 'Il livello della persona raggiunge quello richiesto.'],
            ['Sotto il livello', 'La persona ha la competenza, ma a un livello inferiore a quello richiesto.'],
            ['Mancante', 'La competenza è richiesta da una mansione e non è assegnata alla persona.'],
          ],
        },
        {
          type: 'tip',
          text: 'Le competenze non richieste da nessuna mansione compaiono solo nella scheda Persone.',
        },
      ],
    },
  ],
}

export default guide
