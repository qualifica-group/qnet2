import type { HelpGuide } from '../../types'

const guide: HelpGuide = {
  key: 'dashboard',
  title: 'Dashboard',
  summary: 'La Dashboard è la prima pagina dopo l’accesso; oggi non mostra ancora dati.',
  sections: [
    {
      id: 'current-status',
      title: 'Stato attuale',
      blocks: [
        { type: 'note', text: 'Questa sezione non è ancora disponibile.' },
        { type: 'paragraph', text: 'La **Dashboard** si apre subito dopo aver premuto **Accedi** nella pagina di accesso, ma al momento non mostra numeri o grafici propri.' },
      ],
    },
    {
      id: 'where-to-find-numbers',
      title: 'Dove trovare i numeri',
      blocks: [
        { type: 'paragraph', text: 'I numeri principali si trovano nei singoli moduli, nel pannello **Statistiche**: per esempio in **Utenti**, **Anagrafiche**, **Società sedi**, **Prodotti**, **Categorie Prodotto**, **Progetti**, **Lead** e **Opportunità**.' },
        { type: 'steps', items: ['Apri il modulo che ti interessa.', 'Premi il pulsante con l’icona del grafico (**Mostra statistiche**).', 'Per chiudere il pannello premi di nuovo lo stesso pulsante (**Nascondi statistiche**).'] },
        { type: 'tip', text: 'QNet ricorda se hai lasciato aperto il pannello Statistiche di un modulo, così lo ritrovi aperto alla prossima visita.' },
      ],
    },
  ],
}

export default guide
