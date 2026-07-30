/**
 * Public application bootstrap config served by GET /api/config (ADR 0008).
 *
 * The backend exposes domain enum options (for selects/badges) as a fixed,
 * server-side allowlist with labels already localized via the request locale.
 * The frontend consumes them instead of hardcoding enum values/labels, so a new
 * case added on the backend appears in the UI with no frontend change.
 */

/** A single selectable enum case with its presentation metadata. */
export interface EnumOption {
  value: string
  label: string
  color: string | null
  icon: string | null
  is_default: boolean
  hidden_on_form: boolean
}

/**
 * Localization defaults telling the client whether the app runs in national or
 * international mode (backend config/geo.php ← DEFAULT_COUNTRY_ISO2).
 *
 * `default_country_iso2` is the ISO 3166-1 alpha-2 code (uppercase) of the
 * country the geo cascade preselects when it opens empty, or null in
 * international mode. It is a code, never a database id, so it stays valid
 * across environments whose geo reference data was seeded differently.
 */
export interface AppLocalization {
  default_country_iso2: string | null
}

/**
 * The bootstrap payload (envelope `data`). `enums` is keyed by the snake_case
 * enum key declared in the backend allowlist (config/config.php → form_enums),
 * e.g. `personal_data_type`, `contact_type`.
 */
export interface AppConfig {
  enums: Record<string, EnumOption[]>
  localization: AppLocalization
}
