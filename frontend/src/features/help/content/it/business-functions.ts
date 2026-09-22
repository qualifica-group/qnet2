import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'business-functions',
  title: 'Funzioni aziendali',
  summary: 'Le Funzioni aziendali descrivono l’organizzazione interna e alimentano le Categorie Prodotto, le sedi operative e i criteri dei workflow offerta.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Funzioni aziendali**. È uno dei dati di riferimento da preparare presto: la scheda utente propone le funzioni aziendali negli elenchi a scelta, e le Categorie Prodotto le usano per assegnare l’ambito.' },
        { type: 'tip', text: 'Nell’ordine consigliato di configurazione, le Funzioni aziendali vengono prima di tutto: servono alle Categorie Prodotto e ai criteri dei workflow.' },
      ],
    },
    {
      id: 'fields',
      title: 'Campi',
      blocks: [
        {
          type: 'table',
          headers: ['Campo', 'Cosa indicare'],
          rows: [
            ['**Nome**', 'Il nome della funzione aziendale.'],
            ['**Tipo**', '**Business Unit**, **Business Service** o **Nessuno**.'],
            ['**Responsabile**', 'L’utente responsabile della funzione.'],
            ['**Utenti associati**', 'Gli utenti che ne fanno parte.'],
            ['**Funzione padre**', 'Per creare una gerarchia tra funzioni aziendali.'],
            ['**Sedi operative**', 'Le sedi fisiche in cui opera questa funzione.'],
          ],
        },
        { type: 'paragraph', text: 'Le Funzioni aziendali si usano nelle Categorie Prodotto, nelle righe prodotto di Progetti, Campagne e Opportunità, nelle sedi operative e nei criteri dei workflow offerta.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare, disattivare o eliminare',
      blocks: [
        { type: 'paragraph', text: 'Dall’elenco premi il pulsante per creare una nuova funzione aziendale, oppure usa **Visualizza**, **Modifica** ed **Elimina** sulla riga (se il tuo ruolo lo consente).' },
        { type: 'warning', text: 'Una funzione aziendale con funzioni figlie non si può eliminare: sposta prima le funzioni figlie su un altro padre, oppure eliminale.' },
      ],
    },
  ],
}

export default guide
