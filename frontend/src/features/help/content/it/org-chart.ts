import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'org-chart',
  title: 'Organigramma',
  summary: "L'organigramma mostra chi lavora in quale funzione e chi riporta a chi, e permette di spostare persone e funzioni.",
  sections: [
    {
      id: 'overview',
      title: "Cos'è l'organigramma",
      blocks: [
        {
          type: 'paragraph',
          text: "La pagina ha due schede ad albero: **Funzioni** e **Persone**. Mostra solo le persone attive e non gli account di servizio. Il nome di una persona apre la sua scheda utente, il nome di una funzione ne apre il dettaglio.",
        },
      ],
    },
    {
      id: 'views',
      title: 'Le due viste',
      blocks: [
        {
          type: 'table',
          headers: ['Vista', 'Cosa mostra'],
          rows: [
            ['Funzioni', "L'albero delle funzioni aziendali: per ognuna il tipo (business unit o business service), il responsabile, il numero di persone, le sotto-funzioni e le persone che vi appartengono con le loro mansioni. Una corona indica il responsabile."],
            ['Persone', "Chi riporta a chi: in cima le persone senza responsabile, sotto ognuna i suoi diretti riporti. Chi ha più responsabili compare sotto ciascuno di essi."],
          ],
        },
        {
          type: 'note',
          text: 'Le persone che coprono tutte le funzioni compaiono nel gruppo **Tutte le funzioni**, in fondo alla vista Funzioni.',
        },
      ],
    },
    {
      id: 'search-and-navigation',
      title: 'Ricerca e navigazione',
      blocks: [
        {
          type: 'list',
          items: [
            'La **ricerca** filtra per nome, mansione o funzione e apre i rami che contengono un risultato.',
            '**Espandi tutto** / **Comprimi tutto** apre o chiude tutti i rami (non attivo durante la ricerca).',
            'Da tastiera: le frecce su e giù spostano la selezione, destra apre il ramo (o entra nel primo figlio), sinistra lo chiude (o torna al padre), Invio apre la scheda della riga.',
          ],
        },
      ],
    },
    {
      id: 'moves',
      title: 'Spostamenti',
      blocks: [
        {
          type: 'paragraph',
          text: 'Si sposta con **Sposta in…** dal menu della riga oppure trascinando la riga su un’altra (da computer). Ogni spostamento chiede conferma e viene registrato nello storico della persona o della funzione.',
        },
        {
          type: 'table',
          headers: ['Spostamento', 'Effetto'],
          rows: [
            ['Persona sotto un altro responsabile (vista Persone)', 'Nel campo “Riporta a” il responsabile di partenza viene sostituito con quello di arrivo; gli altri responsabili restano. Spostandola a livello radice, perde quel responsabile.'],
            ['Funzione sotto un’altra funzione (vista Funzioni)', 'Cambia la funzione padre; a livello radice la funzione non ha padre.'],
            ['Persona da una funzione a un’altra (vista Funzioni)', 'Le righe di competenza della funzione di partenza sono sostituite da una sola riga “funzione intera” sulla funzione di arrivo.'],
          ],
        },
        {
          type: 'warning',
          text: 'Spostare una persona di funzione cambia le assegnazioni automatiche (per esempio di lead e richieste): la finestra di conferma lo ricorda. Le categorie di prodotto della funzione di partenza non vengono mantenute; si curano poi dalla scheda utente.',
        },
      ],
    },
    {
      id: 'constraints',
      title: 'Vincoli e permessi',
      blocks: [
        {
          type: 'list',
          items: [
            'Niente cicli: una persona non può andare sotto se stessa o sotto un suo sottoposto; una funzione non può andare sotto se stessa o sotto una sua sotto-funzione.',
            'Un responsabile (persona con il flag responsabile) non può riportare a nessuno.',
            'Una persona che copre tutte le funzioni non si sposta di funzione, e ogni persona deve restare in almeno una funzione.',
            "Per vedere l'organigramma serve il permesso di visualizzazione dell'organigramma. Per spostare una persona sotto un responsabile o di funzione servono la modifica degli utenti e dei relativi campi; per spostare una funzione serve la modifica delle funzioni aziendali. Senza permesso l'azione non compare.",
          ],
        },
      ],
    },
  ],
}

export default guide
