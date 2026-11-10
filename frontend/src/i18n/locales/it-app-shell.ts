/** App shell strings (`config.*`, `appVersion.*`, `theme.*`): split out of `it.ts` for the engineering size limit. */
export const config = {
  error: {
    title: "Impossibile avviare l'applicazione",
    description:
      "Non è stato possibile caricare la configurazione dell'applicazione. Verifica la connessione e riprova.",
    timeout: 'Il server non ha risposto in tempo. Riprova tra qualche istante.',
    retry: 'Riprova',
  },
}

export const appVersion = {
  available: 'Nuova versione disponibile',
  description: "È disponibile una nuova versione. Aggiorna per ricaricare l'applicazione.",
  update: 'Aggiorna ora',
}

export const theme = {
  toggle: 'Cambia tema',
  light: 'Chiaro',
  dark: 'Scuro',
  system: 'Sistema',
}
