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
            ['**Responsabile**', 'L’utente responsabile della funzione. Si cambia anche dalla scheda dell’utente, riga **Responsabile delle funzioni**: la funzione passa alla persona scelta.'],
            ['**Utenti associati**', 'Gli utenti che ne fanno parte. È lo stesso dato delle **Funzioni aziendali di appartenenza** della scheda utente: cambiarlo qui o là è equivalente. Gli **Ambiti di assegnazione** dell’utente sono invece separati e facoltativi.'],
            ['**Funzione padre**', 'Per creare una gerarchia tra funzioni aziendali.'],
            ['**Attiva**', 'Se spenta, la funzione e tutte le sue sotto-funzioni non compaiono più negli elenchi a scelta. Una sotto-funzione di una funzione non attiva mostra **Non attiva perché lo è «…»**.'],
            ['**Società**', 'Le società a cui la funzione è collegata (anche più di una).'],
            ['**Sedi operative**', 'Le sedi fisiche in cui opera questa funzione.'],
          ],
        },
        { type: 'paragraph', text: 'Le Funzioni aziendali si usano nelle Categorie Prodotto, nelle righe prodotto di Progetti, Campagne e Opportunità, nelle sedi operative e nei criteri dei workflow offerta.' },
      ],
    },
    {
      id: 'counts',
      title: 'Elenco e conteggi',
      blocks: [
        { type: 'paragraph', text: 'L’elenco mostra per ogni funzione quante associazioni ha nel sistema, una colonna per tipo: **Utenti** (persone che ne fanno parte), **Categorie prodotto** (categorie che hanno la funzione come propria), **Persone con ambito di assegnazione** (persone con almeno un ambito di assegnazione sulla funzione) e **Opportunità**. Dal selettore delle colonne puoi mostrare anche **Progetti**, **Campagne**, **Richieste di acquisto** e **Sotto-funzioni**. Ogni colonna si ordina; opportunità, progetti, campagne e persone si contano una volta anche con più righe sulla stessa funzione.' },
        { type: 'note', text: 'Le funzioni sono quelle della pianta organica. Le funzioni del vecchio gestionale senza corrispondenza arrivano con il nome che inizia per **[OLD]** e non attive: restano per lo storico.' },
      ],
    },
    {
      id: 'manage',
      title: 'Creare, modificare, disattivare o eliminare',
      blocks: [
        { type: 'paragraph', text: 'Dall’elenco premi il pulsante per creare una nuova funzione aziendale: si apre la scheda con le righe chiuse, per ogni riga premi la matita (o il valore), compila e premi **Fatto** (**Ripristina** riporta la riga com’era), poi **Salva**. Usa **Visualizza** ed **Elimina** sulla riga (se il tuo ruolo lo consente). Per modificare non c’è un modulo a parte: apri la scheda con **Visualizza**, premi la matita sulla riga (nome, tipo, padre, responsabile, utenti associati, società, sedi operative), cambia il valore e premi **Salva** (si salva solo quella riga); **Annulla** o Esc lasciano la riga com’era.' },
        { type: 'note', text: 'Per dismettere una funzione senza eliminarla, spegni **Attiva**: la scheda mostra il badge **Non attiva**. La funzione sparisce dalle scelte di utenti (appartenenza, funzioni gestite, ambiti di assegnazione), categorie prodotto, richieste di acquisto e organigramma, ma i collegamenti già salvati restano validi e la scheda che li contiene si salva ancora. Resta visibile come **Funzione padre**, nei filtri **Funzione** di Opportunità e Progetti e nel filtro del Conto economico. Le righe prodotto prendono la funzione dalla categoria e si salvano anche se quella funzione non è attiva: per spostarle, cambia la funzione della categoria.' },
        { type: 'note', text: 'Una persona attiva (non account di servizio) deve avere almeno una funzione di appartenenza: da **Utenti associati** non puoi togliere chi non ne ha altre (l’errore elenca i nomi), e non puoi eliminare una funzione che lascerebbe delle persone attive senza funzione.' },
        { type: 'warning', text: 'Una funzione aziendale con funzioni figlie non si può eliminare: sposta prima le funzioni figlie su un altro padre, oppure eliminale.' },
      ],
    },
  ],
}

export default guide
