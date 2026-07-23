/**
 * Maps a kebab-case module `domain` (e.g. `company-sites`) to its camelCase
 * i18n namespace (`companySites`), which is how every module's strings are
 * keyed in the locale files. Single-word domains pass through unchanged.
 *
 * Shared because BOTH module chromes key their strings off it — the sheet
 * (`use-module-opener`) and the dedicated page (`module-form-page`). The page
 * used to interpolate the raw kebab domain and rendered the untranslated key
 * as its title for every multi-word module.
 */
export function moduleI18nNamespace(domain: string): string {
  return domain.replace(/-([a-z])/g, (_, char: string) => char.toUpperCase())
}
