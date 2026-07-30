import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useDefaultCountryId } from '@/features/geo/use-default-country'
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
}

interface GeoOption {
  id: number
  name: string
}

interface GeoFieldProps {
  label: string
  /** Renders a required marker (asterisk) next to the label, mirroring `FormLabel required`. */
  required?: boolean
  placeholder: string
  value: number | null
  options: GeoOption[]
  isPending: boolean
  isError: boolean
  disabled: boolean
  emptyLabel: string
  errorLabel: string
  retryLabel: string
  searchPlaceholder: string
  noMatchLabel: string
  onChange: (id: number) => void
  onRetry: () => void
  /** Set false when the caller narrows the list server-side (city level). */
  filter?: boolean
  /** Debounced search term, for the server-searched city level. */
  onSearchChange?: (term: string) => void
  hasNextPage?: boolean
  isFetchingNextPage?: boolean
  onLoadMore?: () => void
}

/**
 * A single dependent geo select: a searchable dropdown that owns its own
 * loading/error/empty states inside the popover, so a re-search never unmounts
 * it. Disabled until its parent has been chosen.
 */
function GeoField({
  label,
  required = false,
  placeholder,
  value,
  options,
  isPending,
  isError,
  disabled,
  emptyLabel,
  errorLabel,
  retryLabel,
  searchPlaceholder,
  noMatchLabel,
  onChange,
  onRetry,
  filter,
  onSearchChange,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
}: GeoFieldProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-sm font-medium">
        {label}
        {required && (
          <span className="ml-1 text-destructive" aria-hidden="true">
            *
          </span>
        )}
      </span>
      <SearchableSelect
        value={value}
        onChange={onChange}
        options={options}
        disabled={disabled}
        isPending={!disabled && isPending}
        isError={!disabled && isError}
        onRetry={onRetry}
        filter={filter}
        onSearchChange={onSearchChange}
        hasNextPage={hasNextPage}
        isFetchingNextPage={isFetchingNextPage}
        onLoadMore={onLoadMore}
        labels={{
          placeholder,
          searchPlaceholder,
          empty: emptyLabel,
          noMatch: noMatchLabel,
          error: errorLabel,
          retry: retryLabel,
        }}
      />
    </div>
  )
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
 * only speaks the generic `GeoValue`/`GeoScope` contract.
 */
export function GeoSelect({
  value,
  onChange,
  disabled = false,
  lockedLevels = NO_LOCKED_LEVELS,
  requiredLevels = NO_LOCKED_LEVELS,
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

  return (
    <div className="flex flex-col gap-3">
      <GeoField
        label={t('geo.country')}
        required={requiredLevels.includes('country')}
        placeholder={t('geo.countryPlaceholder')}
        value={value.country_id}
        options={countries.data ?? []}
        isPending={countries.isPending}
        isError={countries.isError}
        disabled={disabled || countryLocked}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        retryLabel={t('geo.retry')}
        searchPlaceholder={t('geo.search')}
        noMatchLabel={t('geo.noMatch')}
        onChange={handleCountry}
        onRetry={countries.refetch}
      />

      <GeoField
        label={t('geo.state')}
        required={requiredLevels.includes('state')}
        placeholder={t('geo.statePlaceholder')}
        value={value.state_id}
        options={states.data ?? []}
        isPending={states.isPending}
        isError={states.isError}
        disabled={disabled || stateLocked || value.country_id == null}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        retryLabel={t('geo.retry')}
        searchPlaceholder={t('geo.search')}
        noMatchLabel={t('geo.noMatch')}
        onChange={handleState}
        onRetry={states.refetch}
      />

      <GeoField
        label={t('geo.province')}
        required={requiredLevels.includes('province')}
        placeholder={t('geo.provincePlaceholder')}
        value={value.province_id}
        options={provinces.data ?? []}
        isPending={provinces.isPending}
        isError={provinces.isError}
        disabled={disabled || provinceLocked || value.state_id == null}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        retryLabel={t('geo.retry')}
        searchPlaceholder={t('geo.search')}
        noMatchLabel={t('geo.noMatch')}
        onChange={handleProvince}
        onRetry={provinces.refetch}
      />

      <GeoField
        label={t('geo.city')}
        required={requiredLevels.includes('city')}
        placeholder={t('geo.cityPlaceholder')}
        value={value.city_id}
        options={cityOptions}
        isPending={cities.isPending}
        isError={cities.isError}
        disabled={disabled || cityLocked || (!cityFirst && value.state_id == null)}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        retryLabel={t('geo.retry')}
        searchPlaceholder={t('geo.search')}
        noMatchLabel={t('geo.noMatch')}
        filter={false}
        onSearchChange={setCitySearch}
        hasNextPage={cities.hasNextPage}
        isFetchingNextPage={cities.isFetchingNextPage}
        onLoadMore={cities.fetchNextPage}
        onChange={handleCity}
        onRetry={cities.refetch}
      />
    </div>
  )
}
