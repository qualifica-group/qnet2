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
          text: "La pagina mostra un organigramma grafico in due schede: **Funzioni aziendali** e **Persone**. Ogni riquadro è collegato da una linea al riquadro da cui dipende, dall'alto verso il basso. Mostra solo le persone attive e non gli account di servizio. Il nome di una persona apre la sua scheda utente, il nome di una funzione ne apre il dettaglio.",
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
            ['Funzioni aziendali', "Le persone sono raggruppate per funzione aziendale di appartenenza (non per ambiti di assegnazione). Un riquadro per ogni funzione aziendale con tipo (business unit o business service), foto e nome del responsabile e numero di persone; sotto, le persone della funzione come riquadri (prima i responsabili, poi le altre in ordine alfabetico) e le sotto-funzioni. Il pulsante con il numero di persone resta e apre un pannello laterale con i **Responsabili** e le altre **Persone** della funzione, con le loro mansioni."],
            ['Persone', "Chi riporta a chi: un riquadro per persona con foto, nome, mansioni e funzioni; in cima le persone senza responsabile, sotto ognuna i suoi diretti riporti. Una corona indica chi è responsabile di almeno una funzione. Chi ha più responsabili compare sotto ciascuno di essi. Le persone senza collegamenti stanno dopo le gerarchie."],
          ],
        },
        {
          type: 'note',
          text: 'Le persone attive senza una funzione di appartenenza sono nel riquadro **Non assegnati**, dopo le altre funzioni: il suo pulsante ne apre l’elenco. Trascinandole (o con **Sposta in…**) su una funzione, le si assegna a quella funzione come appartenenza. Anche le persone di **Non assegnati** sono riquadri sotto il gruppo.',
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
            'All’apertura si vedono i riquadri di primo livello e quelli subito sotto. Il pallino sotto un riquadro apre o chiude il suo ramo: da chiuso mostra **+N**, il numero di riquadri nascosti.',
            'Quando un riquadro ha più di quattro riporti senza altri sotto di sé, questi sono impilati in colonne da quattro.',
            'La **ricerca** filtra per nome, mansione o funzione, apre i rami che contengono un risultato ed evidenzia i riquadri trovati.',
            '**Espandi tutto** / **Comprimi tutto** apre o chiude tutti i rami (non attivo durante la ricerca).',
            'Lo **zoom** in basso a destra ingrandisce (+), riduce (−) o adatta il grafico alla finestra. Per spostarsi si usano le barre di scorrimento o si trascina lo sfondo.',
            'Da tastiera: le frecce su e giù spostano la selezione, destra apre il ramo (o entra nel primo figlio), sinistra lo chiude (o torna al padre), Invio apre la scheda del riquadro.',
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
          text: 'Si sposta con **Sposta in…** dal menu del riquadro (o della persona nel pannello laterale) oppure trascinando un riquadro su un altro (da computer). Ogni spostamento chiede conferma e viene registrato nello storico della persona o della funzione.',
        },
        {
          type: 'table',
          headers: ['Spostamento', 'Effetto'],
          rows: [
            ['Persona sotto un altro responsabile (vista Persone)', 'Nel campo “Riporta a” il responsabile di partenza viene sostituito con quello di arrivo; gli altri responsabili restano. Spostandola a livello radice, perde quel responsabile.'],
            ['Funzione sotto un’altra funzione (vista Funzioni aziendali)', 'Cambia la funzione padre; a livello radice la funzione non ha padre.'],
            ['Persona da una funzione a un’altra (vista Funzioni aziendali, dal pannello laterale)', 'La funzione di appartenenza di partenza è sostituita da quella di arrivo. Dai **Non assegnati** la persona riceve la sua prima appartenenza.'],
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
            'Anche un responsabile di funzione può avere superiori e può essere spostato come ogni altra persona.',
            'Una persona che copre tutte le funzioni non si sposta di funzione, e ogni persona deve restare in almeno una funzione.',
            "Per vedere l'organigramma serve il permesso di visualizzazione dell'organigramma. Per spostare una persona sotto un responsabile o di funzione servono la modifica degli utenti e dei relativi campi; per spostare una funzione serve la modifica delle funzioni aziendali. Senza permesso l'azione non compare.",
          ],
        },
      ],
    },
  ],
}

export default guide
