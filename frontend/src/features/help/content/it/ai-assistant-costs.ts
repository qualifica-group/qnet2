import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'ai-assistant-costs',
  title: 'Costi assistente AI',
  summary:
    "Qui il super-admin vede quanto costa l'assistente AI in un periodo: totale, andamento per giorno e ripartizione per funzione aziendale, persona e modello.",
  sections: [
    {
      id: 'overview',
      title: 'Che cos’è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina è in **Develop › Costi assistente AI** ed è riservata al ruolo **super-admin**: gli altri utenti non vedono la voce di menu e il server rifiuta le loro richieste. Mostra solo cifre e conteggi: **mai il testo di una conversazione**.',
        },
        {
          type: 'note',
          text: 'I costi sono in **dollari**, come li addebita OpenRouter. Cancellare una conversazione non toglie il suo costo da questa pagina.',
        },
      ],
    },
    {
      id: 'period',
      title: 'Scegliere il periodo',
      blocks: [
        {
          type: 'list',
          items: [
            'La pagina si apre sul **mese corrente**, dal primo giorno a oggi.',
            'Cambia **Dal** e **Al** per guardare un altro periodo; i due giorni sono inclusi. **Mese corrente** riporta alla partenza.',
            'La data di inizio non può essere successiva a quella di fine, e il server limita la durata massima di un periodo.',
          ],
        },
      ],
    },
    {
      id: 'figures',
      title: 'Che cosa si vede',
      blocks: [
        {
          type: 'table',
          headers: ['Parte', 'Significato'],
          rows: [
            ['**Riquadri**', 'Costo, richieste e token del periodo. Il costo è la somma di ogni chiamata al modello, riassunti compresi.'],
            ['**Costo per giorno**', "L'andamento giorno per giorno."],
            ['**Per funzione aziendale**', 'Ogni richiesta si divide in parti uguali fra le funzioni aziendali della persona che ha chiesto. Chi non ha una funzione finisce in **Senza funzione**. La somma delle funzioni è uguale al totale.'],
            ['**Per persona**', 'Richieste, token e costo di ciascuna persona. Una persona eliminata compare come **Persona non più presente**.'],
            ['**Per modello**', 'Richieste, token e costo di ogni modello usato.'],
            ['**Mese corrente e tetti**', "Quanto hanno speso tutti questo mese rispetto ai tetti fissati nelle impostazioni dell'assistente."],
          ],
        },
      ],
    },
  ],
}

export default guide
