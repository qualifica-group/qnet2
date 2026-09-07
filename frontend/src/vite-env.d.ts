/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_NAME: string
  readonly VITE_APP_NAME_SIDEBAR: string
  readonly VITE_APP_DESCRIPTION: string
  readonly VITE_API_URL: string
  readonly VITE_API_VERSION: string
  readonly VITE_IS_PRODUCTION: string
  readonly VITE_AG_GRID_LICENSE_KEY: string
  readonly VITE_NOTIFICATIONS_POLL_INTERVAL: string
  readonly VITE_VERSION_POLL_INTERVAL: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

/**
 * Build identifier injected by Vite (`define`, see vite/build-version.ts). It is
 * the version the running bundle was built from, compared against the deployed
 * one served by /version.json.
 */
declare const __APP_BUILD_VERSION__: string
