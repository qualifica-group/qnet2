import { useMemo } from 'react'
import { useConfig } from '@/features/config/use-config'
import { useCountries } from '@/features/geo/use-geo'

/**
 * Resolves the configured national default country into the id the geo cascade
 * speaks (national vs international mode, backend `DEFAULT_COUNTRY_ISO2`
 * surfaced as `localization.default_country_iso2` by GET /api/config).
 *
 * The backend ships a country CODE, not an id, so the id is looked up in the
 * country list already loaded by the cascade — same query key, so this costs no
 * extra request. Returns null whenever there is nothing to preselect:
 * international mode (code unset), the list not loaded yet, or a code matching
 * no country. Callers must treat null as "leave the field empty".
 */
export function useDefaultCountryId(): number | null {
  const { data: config } = useConfig()
  const { data: countries } = useCountries()
  const iso2 = config?.localization?.default_country_iso2 ?? null

  return useMemo(() => {
    if (iso2 === null || iso2 === '') {
      return null
    }
    const wanted = iso2.toUpperCase()

    return (
      countries?.find((country) => country.iso2.toUpperCase() === wanted)?.id ?? null
    )
  }, [countries, iso2])
}
