/**
 * Componenti condivisi `components/rich-text/` (spec 0128 D-11): toolbar,
 * popover del link e stati del contenuto in sola lettura. File satellite per
 * mantenere `it.ts` entro i limiti dimensionali (engineering.md §6).
 */

export const richText = {
  toolbar: {
    bold: 'Grassetto',
    italic: 'Corsivo',
    underline: 'Sottolineato',
    strike: 'Barrato',
    bulletList: 'Elenco puntato',
    orderedList: 'Elenco numerato',
    heading2: 'Titolo 2',
    heading3: 'Titolo 3',
    blockquote: 'Citazione',
    codeBlock: 'Blocco di codice',
    link: 'Link',
    image: 'Immagine',
  },
  link: {
    urlLabel: 'Indirizzo',
    urlPlaceholder: 'https://…',
    apply: 'Applica',
    cancel: 'Annulla',
    invalidUrl: 'Indirizzo non valido. Usa un link http, https o mailto.',
  },
  errors: {
    invalidImageType: 'Formato immagine non supportato. Usa PNG, JPEG, GIF o WEBP.',
    imageTooLarge: "L'immagine supera la dimensione massima di {{size}} MB.",
    tooManyImages: 'Hai raggiunto il numero massimo di immagini per questo campo ({{max}}).',
    imageReadFailed: "Lettura dell'immagine non riuscita. Riprova.",
    payloadTooLarge: 'Le immagini sono troppo pesanti per il salvataggio: riduci o rimuovi qualche immagine.',
  },
  content: {
    imageLoading: 'Caricamento immagine…',
    imageUnavailable: 'Immagine non disponibile',
  },
}
