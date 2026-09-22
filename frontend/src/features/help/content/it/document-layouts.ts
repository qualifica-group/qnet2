import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'document-layouts',
  title: 'Layout',
  summary: 'Un layout è il modello grafico con cui QNet genera il documento di un’offerta.',
  sections: [
    {
      id: 'overview',
      title: 'Panoramica',
      blocks: [
        { type: 'paragraph', text: 'Il modulo si trova in **Configurazione › Layout**. Oggi l’unico **Modulo** disponibile è **Preventivi**; il documento si scarica con **Scarica preventivo** sull’offerta.' },
      ],
    },
    {
      id: 'create-layout',
      title: 'Creare un layout',
      blocks: [
        { type: 'steps', items: ['Apri **Configurazione › Layout** e premi **Nuovo layout**.', 'Nella scheda **Dettagli** compila **Nome**, **Codice**, **Modulo** e **Descrizione**; imposta **Attivo** e, se serve, **Predefinito**.', 'Salva il layout.', 'Apri la scheda **Contenuto** e costruisci la pagina con l’editor visuale.'] },
        { type: 'warning', text: 'Dopo la creazione **Codice** e **Modulo** non si modificano.' },
      ],
    },
    {
      id: 'editor',
      title: 'L’editor visuale',
      blocks: [
        { type: 'paragraph', text: 'L’editor ha tre zone: **Intestazione**, **Corpo** e **Piè di pagina**. In ognuna aggiungi blocchi con **Aggiungi blocco** e li riordini trascinandoli.' },
        {
          type: 'table',
          headers: ['Blocco', 'Uso'],
          rows: [
            ['**Testo**', 'Paragrafi con stile, font, colore e allineamento; qui trovi **Inserisci numero di pagina** e **Inserisci totale pagine**.'],
            ['**Immagine**', 'Per esempio un logo, **In linea** o **Dietro la pagina** come sfondo. Per caricarla salva prima il layout.'],
            ['**Tabella**', 'Una tabella libera, con righe, colonne e bordi.'],
            ['**Tabella prodotti**', 'Elenca **Righe di offerta** o **Righe di costo**, con le colonne scelte (Codice, Nome, Quantità, Prezzo unitario, Aliquota IVA, importi) e le righe dei totali.'],
            ['**Interruzione di pagina**, **Spaziatore**, **Separatore**', 'Impaginazione.'],
          ],
        },
        { type: 'note', text: 'Il pannello **Pagina** imposta orientamento (Verticale o Orizzontale), margini e font predefinito.' },
      ],
    },
    {
      id: 'variables',
      title: 'Variabili',
      blocks: [
        { type: 'paragraph', text: 'Sono segnaposti che, alla generazione, diventano i dati reali dell’offerta. Seleziona un testo nell’anteprima e, nel pannello **Variabili**, clicca il dato che ti serve.' },
        { type: 'paragraph', text: 'Gruppi disponibili: **Preventivo**, **Totali**, **Cliente**, **Opportunità**, **Referente**, **Commerciale**, **Segnalatore**, **Supervisore**, **Società**, **Sede** (con banca e IBAN), **Sede operativa**, **Campi personalizzati** e **Attributi offerta**. Gli ultimi due si aggiornano da soli quando aggiungi campi o attributi.' },
      ],
    },
    {
      id: 'default-and-deletion',
      title: 'Layout predefinito ed eliminazione',
      blocks: [
        { type: 'paragraph', text: 'Il layout **Predefinito** si usa quando l’offerta non ne indica un altro. Un’offerta conserva il layout con cui è stata creata.' },
        { type: 'note', text: 'Il predefinito deve essere attivo e non si può disattivare né eliminare finché non ne designi un altro.' },
        { type: 'warning', text: 'Un layout usato da preventivi non si può eliminare, solo disattivare.' },
      ],
    },
  ],
}

export default guide
