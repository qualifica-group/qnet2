import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'workspaces',
  title: 'App',
  summary:
    'App permette al super-admin di creare ambienti di lavoro (per esempio HR o Contabilità), ciascuno con le sole voci di menu che gli servono.',
  sections: [
    {
      id: 'overview',
      title: 'Cos’è e chi la vede',
      blocks: [
        {
          type: 'paragraph',
          text: 'La pagina si trova in **Develop › App** ed è riservata al ruolo **super-admin**: per gli altri utenti la voce di menu non compare. Un’**app** è un sottoinsieme del menu: chi entra in un’app vede solo le pagine che le hai assegnato, e passa da un’app all’altra dal launcher, in alto a sinistra nell’intestazione.',
        },
        {
          type: 'note',
          text: 'Finché non crei nessuna app, tutti vedono il menu completo come prima. Dalla prima app creata, solo il super-admin può ancora usare la **Vista completa**; gli altri lavorano sempre dentro un’app.',
        },
        {
          type: 'paragraph',
          text: 'L’installazione iniziale crea già tre app, che puoi modificare o eliminare: **Qualifica Contabilità** (fatture, prima nota, acquisti, provvigioni, controllo di gestione), **Qualifica HR** (utenti, organigramma, segnatempo, assenze, formazione, dotazioni e configurazione del personale) e **Qualifica Sales** (lead, opportunità, offerte, commesse, contratti, anagrafiche). Ogni app contiene solo le pagine del suo settore, più Dashboard e Task.',
        },
      ],
    },
    {
      id: 'create-edit',
      title: 'Creare o modificare un’app',
      blocks: [
        {
          type: 'steps',
          items: [
            'Premi **Nuova app** (oppure l’icona matita su un’app esistente): il modulo si apre in un pannello a destra, diviso in **Identità**, **Aspetto** e **Voci di menu**.',
            'Scrivi il **Nome**: l’**Indirizzo (slug)**, che compare nell’URL come **/app/indirizzo**, si compila da solo mentre scrivi. Puoi cambiarlo; da quel momento non segue più il nome.',
            'Facoltativa: una **Descrizione** breve (fino a 160 caratteri) che compare sotto il nome dell’app nel launcher.',
            'Scegli l’**Icona** dall’elenco (puoi cercarla per nome), il **Colore** e l’**Ordine** nel launcher. Icona e colore sono facoltativi: l’**Anteprima nel launcher** mostra subito come apparirà l’app.',
            'Spunta le **Voci di menu** che l’app deve mostrare, raggruppate per sezione. La casella accanto al nome della sezione la seleziona tutta; il campo **Cerca voci di menu** filtra l’elenco, e **Seleziona tutte** / **Deseleziona** agiscono sulle voci visibili.',
            'Premi **Salva** (oppure **Ctrl+Invio**). Se chiudi il pannello con modifiche non salvate, ti viene chiesta conferma.',
          ],
        },
        {
          type: 'table',
          headers: ['Campo', 'Regola'],
          rows: [
            ['**Nome**', 'Obbligatorio, al massimo 60 caratteri.'],
            ['**Indirizzo (slug)**', 'Obbligatorio e univoco, al massimo 40 caratteri: lettere minuscole, numeri e trattini singoli.'],
            ['**Descrizione**', 'Facoltativa, al massimo 160 caratteri.'],
            ['**Icona**', 'Facoltativa: si sceglie dall’elenco. Senza icona compare una griglia generica.'],
            ['**Colore**', 'Facoltativo: si sceglie dalla tavolozza. Il nome dell’app compare in questo colore sopra il nome del prodotto nell’intestazione del menu e nel launcher; senza colore il testo resta neutro.'],
            ['**Voci di menu**', 'Almeno una. Una voce può stare in più app.'],
          ],
        },
        {
          type: 'note',
          text: 'Se un campo non è valido, il messaggio compare sotto il campo stesso. Una voce è visibile a un utente solo se ne ha già il permesso: l’app non concede accessi.',
        },
      ],
    },
    {
      id: 'visibility',
      title: 'Chi vede quale app',
      blocks: [
        {
          type: 'list',
          items: [
            'Un utente vede un’app se può vedere almeno una delle sue voci, con gli stessi permessi di sempre.',
            'L’app decide **solo** cosa compare nel menu: i link tra moduli e gli indirizzi diretti continuano a funzionare secondo i permessi.',
            'Il menu dell’app mantiene l’ordine, le sezioni e i gruppi del menu completo.',
            'La prima voce visibile dell’app è la sua pagina iniziale.',
          ],
        },
      ],
    },
    {
      id: 'delete',
      title: 'Eliminare un’app',
      blocks: [
        {
          type: 'paragraph',
          text: 'Premi l’icona cestino e conferma. L’app e le sue assegnazioni di menu vengono rimosse; pagine, dati e permessi non cambiano. Chi aveva quell’app come ultima usata viene portato in un’altra app disponibile.',
        },
        {
          type: 'warning',
          text: 'Eliminando l’ultima app, tutti tornano a vedere il menu completo.',
        },
      ],
    },
  ],
}

export default guide
