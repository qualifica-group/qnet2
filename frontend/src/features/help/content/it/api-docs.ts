import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'api-docs',
  title: 'Documentazione API',
  summary:
    'Documentazione API elenca tutte le API di QNet con parametri, risposte ed esempi pronti da copiare, e si aggiorna da sola.',
  sections: [
    {
      id: 'overview',
      title: 'Cos\'è la documentazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › Documentazione API**. Descrive le API che i sistemi esterni possono usare con una chiave di un **client API** e **si genera da sola** a partire dalle API di QNet: quando l\'applicazione cambia, la documentazione resta allineata senza interventi manuali.',
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
      title: 'Documentazione in preparazione',
      blocks: [
        {
          type: 'paragraph',
          text: 'Dopo un aggiornamento di QNet la documentazione viene rigenerata e **la preparazione richiede qualche minuto**: la pagina mostra lo stato **in preparazione** e **si aggiorna da sola** appena è pronta, senza ricaricarla.',
        },
      ],
    },
  ],
}

export default guide
