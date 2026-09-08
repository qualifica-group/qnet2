/**
 * Centralized, typed access to build-time environment variables.
 * Never read `import.meta.env` directly outside this module.
 */
function required(key: keyof ImportMetaEnv): string {
  const value = import.meta.env[key]
  if (!value) {
    throw new Error(`Missing ${key} environment variable. See .env.example.`)
  }
  return value
}

/** Joins the API host and version into a single base URL (e.g. http://host/api). */
function buildApiUrl(base: string, version: string): string {
  const trimmedBase = base.replace(/\/+$/, '')
  const trimmedVersion = version.replace(/^\/+/, '')
  return trimmedVersion ? `${trimmedBase}/${trimmedVersion}` : trimmedBase
}

export const env = {
  appName: required('VITE_APP_NAME'),
  appNameSidebar: required('VITE_APP_NAME_SIDEBAR'),
  appDescription: import.meta.env.VITE_APP_DESCRIPTION ?? '',
  apiUrl: buildApiUrl(required('VITE_API_URL'), import.meta.env.VITE_API_VERSION ?? ''),
  isProduction: import.meta.env.VITE_IS_PRODUCTION === 'true',
  /**
   * AG Grid Enterprise license key. Optional at build time so the app can boot in
   * environments without a key (AG Grid only logs a watermark/console warning).
   * Never hardcode the key in the repo — it is injected via env only.
   */
  agGridLicenseKey: import.meta.env.VITE_AG_GRID_LICENSE_KEY ?? '',
  /**
   * Polling interval (ms) for the always-on unread notifications count. Falls
   * back to 30s when unset or non-numeric.
   */
  notificationsPollInterval:
    Number(import.meta.env.VITE_NOTIFICATIONS_POLL_INTERVAL ?? '') || 30000,
} as const
