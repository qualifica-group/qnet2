/** App shell strings (`config.*`, `appVersion.*`, `theme.*`): split out of `en.ts` for the engineering size limit. */
export const config = {
  error: {
    title: 'Unable to start the application',
    description:
      "We couldn't load the application configuration. Check your connection and try again.",
    timeout: 'The server did not respond in time. Please try again in a moment.',
    retry: 'Retry',
  },
}

export const appVersion = {
  available: 'New version available',
  description: 'A new version is available. Click update to refresh the app.',
  update: 'Update now',
}

export const theme = {
  toggle: 'Toggle theme',
  light: 'Light',
  dark: 'Dark',
  system: 'System',
}
