import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useDefaultCountryId } from '@/features/geo/use-default-country'
import type { AppConfig } from '@/features/config/types'
import type { Country } from '@/features/geo/types'

/**
 * National vs international mode: the backend ships a country CODE
 * (`localization.default_country_iso2`, from DEFAULT_COUNTRY_ISO2) and this hook
 * resolves it against the loaded country list into the id the geo cascade uses.
 */

const useConfigMock = vi.fn<() => { data: AppConfig | undefined }>()
const useCountriesMock = vi.fn<() => { data: Country[] | undefined }>()

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => useConfigMock(),
}))
vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => useCountriesMock(),
}))

const COUNTRIES: Country[] = [
  { id: 1, name: 'France', iso2: 'FR' },
  { id: 2, name: 'Italy', iso2: 'IT' },
]

function config(iso2: string | null): AppConfig {
  return { enums: {}, localization: { default_country_iso2: iso2 } }
}

beforeEach(() => {
  useConfigMock.mockReset()
  useCountriesMock.mockReset()
  useConfigMock.mockReturnValue({ data: config('IT') })
  useCountriesMock.mockReturnValue({ data: COUNTRIES })
})

describe('useDefaultCountryId', () => {
  it('resolves the configured code to the matching country id', () => {
    const { result } = renderHook(() => useDefaultCountryId())

    expect(result.current).toBe(2)
  })

  it('matches the code case-insensitively', () => {
    useConfigMock.mockReturnValue({ data: config('it') })

    expect(renderHook(() => useDefaultCountryId()).result.current).toBe(2)
  })

  it('returns null in international mode (no code configured)', () => {
    useConfigMock.mockReturnValue({ data: config(null) })

    expect(renderHook(() => useDefaultCountryId()).result.current).toBeNull()
  })

  it('returns null for a code no country matches', () => {
    useConfigMock.mockReturnValue({ data: config('ZZ') })

    expect(renderHook(() => useDefaultCountryId()).result.current).toBeNull()
  })

  it('returns null while the config or the country list is still loading', () => {
    useConfigMock.mockReturnValue({ data: undefined })
    expect(renderHook(() => useDefaultCountryId()).result.current).toBeNull()

    useConfigMock.mockReturnValue({ data: config('IT') })
    useCountriesMock.mockReturnValue({ data: undefined })
    expect(renderHook(() => useDefaultCountryId()).result.current).toBeNull()
  })
})
