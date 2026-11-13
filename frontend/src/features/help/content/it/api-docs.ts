import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-docs',
  title: 'Documentazione API',
  summary:
    'Documentazione API elenca tutte le API di QNet con parametri, risposte ed esempi pronti da copiare, e viene rigenerata ogni notte o su richiesta.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è la documentazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › Documentazione API**. Descrive le API che i sistemi esterni possono usare con una chiave di un **client API** e **viene generata** a partire dalle API di QNet: aprire la pagina non la rigenera, mostra l\'ultima versione generata con data e ora in testata.',
        },
        {
          type: 'note',
          text: 'La pagina è visibile a chi può vedere i client API. Le chiavi si creano e si gestiscono in **Develop › API e integrazioni**.',
        },
      ],
    },
    {
      id: 'navigation',
      title: 'Navigare tra le API',
      blocks: [
        {
          type: 'list',
          items: [
            'Gli endpoint sono **raggruppati per modulo**, nello stesso ordine del menu.',
            'Usa la **ricerca** per trovare un endpoint dal percorso o dalla descrizione.',
            'Usa i filtri per **metodo** (GET, POST, PUT, PATCH, DELETE) per vedere solo un tipo di chiamata.',
          ],
        },
      ],
    },
    {
      id: 'endpoint',
      title: 'Dettaglio di un endpoint',
      blocks: [
        {
          type: 'paragraph',
          text: 'Aprendo un endpoint vedi i **parametri** (percorso e query), il **body** della richiesta, la **risposta** e gli **esempi di codice** in **cURL** e **JavaScript**, da copiare e adattare con la tua chiave.',
        },
      ],
    },
    {
      id: 'direct-link',
      title: 'Link diretto a un endpoint',
      blocks: [
        {
          type: 'paragraph',
          text: 'Ogni endpoint ha un **link diretto**: copialo e condividilo, chi lo apre arriva già sull\'endpoint indicato.',
        },
      ],
    },
    {
      id: 'downloads',
      title: 'Download e Postman',
      blocks: [
        {
          type: 'steps',
          items: [
            'Fai clic sul download **OpenAPI** per ottenere la specifica completa, oppure su **Postman** per la collezione.',
            'In Postman scegli **Import** e seleziona il file scaricato.',
            'Imposta la chiave del client nelle variabili della collezione e lancia le richieste.',
          ],
        },
      ],
    },
    {
      id: 'preparing',
      title: 'Aggiornare la documentazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'La documentazione viene rigenerata **ogni notte a mezzanotte** e dopo ogni rilascio di QNet. Per averla subito allineata usa il pulsante **Aggiorna** in testata: la rigenerazione richiede **qualche minuto**, nel frattempo resta visibile la versione precedente e la pagina **si aggiorna da sola** appena la nuova è pronta, senza ricaricarla.',
        },
        {
          type: 'note',
          text: 'Il pulsante **Aggiorna** è visibile a chi può modificare i client API. Se la documentazione non è mai stata generata la pagina lo segnala e i download restano disattivati fino alla prima generazione.',
        },
      ],
    },
  ],
}

export default guide
