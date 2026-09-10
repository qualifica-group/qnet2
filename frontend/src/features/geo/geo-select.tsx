import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useDefaultCountryId } from '@/features/geo/use-default-country'
import { GeoCompactFields } from '@/features/geo/geo-compact-fields'
import { GeoField, type GeoFieldProps } from '@/features/geo/geo-field'
import {
  useCities,
  useCountries,
  useProvinces,
  useStates,
} from '@/features/geo/use-geo'
import type { GeoScope } from '@/features/geo/geo-scope'

/** The cascading geo selection, all ids nullable until chosen. */
export interface GeoValue {
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
}

/**
 * How the four levels are laid out.
 * - `cascade` (default): the four selects stacked top-down, country first.
 * - `compact`: comune-first, the three ancestors behind a disclosure.
 */
export type GeoLayout = 'cascade' | 'compact'

/** Stable empty default (no level locked), hoisted so it never changes identity across renders. */
const NO_LOCKED_LEVELS: ReadonlyArray<GeoScope> = []

interface GeoSelectProps {
  value: GeoValue
  onChange: (value: GeoValue) => void
  disabled?: boolean
  /**
   * Levels owned by a linked parent entity (spec 0027 BR-5): rendered
   * disabled, value still shown, independently of whether their own parent
   * level is chosen. Defaults to none, the pre-existing behaviour.
   */
  lockedLevels?: ReadonlyArray<GeoScope>
  /**
   * Levels whose label shows a required marker (asterisk). Defaults to none,
   * the pre-existing behaviour; the geo cascade is not rendered via
   * `MetaField`, so its requiredness is surfaced here instead.
   */
  requiredLevels?: ReadonlyArray<GeoScope>
  /**
   * Visual arrangement of the four levels. Defaults to `cascade`, the
   * pre-existing behaviour every caller had; the address surfaces opt into
   * `compact` (see `GeoCompactFields`).
   */
  layout?: GeoLayout
}

/**
 * Controlled, domain-agnostic country → state → province → city cascade
 * (ADR 0010). When a parent changes, the descendant values are reset and the
 * children stay disabled until their parent is chosen. Data comes from dependent
 * geo queries; each select shows a skeleton while loading and an inline
 * error/empty state.
 *
 * National mode (backend `DEFAULT_COUNTRY_ISO2`): when a default country is
 * configured, a cascade that opens completely empty is seeded with it — the
 * field is still editable, so nothing prevents an international selection.
 *
 * The province level is optional: many countries have none, so the province
 * select simply shows its empty state and the city select falls back to filter
 * by state (cities load as soon as a state is chosen, with or without a
 * province). This keeps every country reachable.
 *
 * This component must NOT depend on any feature domain (e.g. personal-data): it
 * only speaks the generic `GeoValue`/`GeoScope` contract. It owns the data and
 * the cascade rules; the arrangement of the four levels belongs to a layout
 * (`GeoCompactFields`), which is why every level is described here as props
 * rather than rendered inline.
 */
export function GeoSelect({
  value,
  onChange,
  disabled = false,
  lockedLevels = NO_LOCKED_LEVELS,
  requiredLevels = NO_LOCKED_LEVELS,
  layout = 'cascade',
}: GeoSelectProps) {
  const { t } = useTranslation()
  const countryLocked = lockedLevels.includes('country')
  const stateLocked = lockedLevels.includes('state')
  const provinceLocked = lockedLevels.includes('province')
  const cityLocked = lockedLevels.includes('city')

  // City-first selection: the city level is searchable on its own (no parent
  // required) and picking a city backfills its ancestors. Suppressed when a
  // linked parent has locked an ancestor (spec 0027 BR-5) — the cascade then
  // stays strictly top-down so a pick can never contradict the locked scope.
  const cityFirst = !countryLocked && !stateLocked && !provinceLocked && !cityLocked

  // Cities are capped server-side, so their list is narrowed by a server search
  // term rather than filtered client-side like the other levels.
  const [citySearch, setCitySearch] = useState('')

  const countries = useCountries()
  const states = useStates(value.country_id)
  const provinces = useProvinces(value.state_id)
  const cities = useCities(value.state_id, value.province_id, citySearch)

  // Flatten the paged city results into a single option list for the select.
  const cityOptions = useMemo(
    () => cities.data?.pages.flat() ?? [],
    [cities.data?.pages],
  )

  // National mode: preselect the configured default country, but ONLY on a
  // pristine cascade. Requiring all four levels to be empty is what makes this
  // safe for every caller — an existing selection, a scope inherited from a
  // linked parent entity or the ids already resolved onto an import row are
  // never rewritten, so no data can be lost to a default.
  const defaultCountryId = useDefaultCountryId()
  const isPristine =
    value.country_id === null &&
    value.state_id === null &&
    value.province_id === null &&
    value.city_id === null

  useEffect(() => {
    if (disabled || countryLocked || defaultCountryId === null || !isPristine) {
      return
    }
    onChange({
      country_id: defaultCountryId,
      state_id: null,
      province_id: null,
      city_id: null,
    })
  }, [countryLocked, defaultCountryId, disabled, isPristine, onChange])

  const handleCountry = (countryId: number) => {
    setCitySearch('')
    onChange({
      country_id: countryId,
      state_id: null,
      province_id: null,
      city_id: null,
    })
  }

  const handleState = (stateId: number) => {
    setCitySearch('')
    onChange({ ...value, state_id: stateId, province_id: null, city_id: null })
  }

  const handleProvince = (provinceId: number) => {
    setCitySearch('')
    onChange({ ...value, province_id: provinceId, city_id: null })
  }

  const handleCity = (cityId: number) => {
    const picked = cityOptions.find((city) => city.id === cityId)
    if (!picked) {
      onChange({ ...value, city_id: cityId })
      return
    }
    // City-first: backfill the ancestor chain from the picked city. A locked
    // level is never overwritten, but city-first is off whenever any level is
    // locked, so in practice this always backfills the full chain.
    onChange({
      country_id: countryLocked ? value.country_id : picked.country_id,
      state_id: stateLocked ? value.state_id : picked.state_id,
      province_id: provinceLocked ? value.province_id : picked.province_id,
      city_id: picked.id,
    })
  }

  /** The labels every level shares, so each descriptor only states its own. */
  const sharedLabels = {
    emptyLabel: t('geo.empty'),
    errorLabel: t('geo.error'),
    retryLabel: t('geo.retry'),
    searchPlaceholder: t('geo.search'),
    noMatchLabel: t('geo.noMatch'),
  }

  const country: GeoFieldProps = {
    ...sharedLabels,
    label: t('geo.country'),
    required: requiredLevels.includes('country'),
    placeholder: t('geo.countryPlaceholder'),
    value: value.country_id,
    options: countries.data ?? [],
    isPending: countries.isPending,
    isError: countries.isError,
    disabled: disabled || countryLocked,
    onChange: handleCountry,
    onRetry: countries.refetch,
  }

  const state: GeoFieldProps = {
    ...sharedLabels,
    label: t('geo.state'),
    required: requiredLevels.includes('state'),
    placeholder: t('geo.statePlaceholder'),
    value: value.state_id,
    options: states.data ?? [],
    isPending: states.isPending,
    isError: states.isError,
    disabled: disabled || stateLocked || value.country_id == null,
    onChange: handleState,
    onRetry: states.refetch,
  }

  const province: GeoFieldProps = {
    ...sharedLabels,
    label: t('geo.province'),
    required: requiredLevels.includes('province'),
    placeholder: t('geo.provincePlaceholder'),
    value: value.province_id,
    options: provinces.data ?? [],
    isPending: provinces.isPending,
    isError: provinces.isError,
    disabled: disabled || provinceLocked || value.state_id == null,
    onChange: handleProvince,
    onRetry: provinces.refetch,
  }

  const city: GeoFieldProps = {
    ...sharedLabels,
    label: t('geo.city'),
    required: requiredLevels.includes('city'),
    placeholder: t('geo.cityPlaceholder'),
    value: value.city_id,
    options: cityOptions,
    isPending: cities.isPending,
    isError: cities.isError,
    disabled: disabled || cityLocked || (!cityFirst && value.state_id == null),
    filter: false,
    onSearchChange: setCitySearch,
    hasNextPage: cities.hasNextPage,
    isFetchingNextPage: cities.isFetchingNextPage,
    onLoadMore: cities.fetchNextPage,
    onChange: handleCity,
    onRetry: cities.refetch,
  }

  if (layout === 'compact') {
    return (
      <GeoCompactFields
        country={country}
        state={state}
        province={province}
        city={city}
        collapsible={cityFirst}
      />
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <GeoField {...country} />
      <GeoField {...state} />
      <GeoField {...province} />
      <GeoField {...city} />
    </div>
  )
}
