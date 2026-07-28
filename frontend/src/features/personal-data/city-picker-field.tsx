import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useCities } from '@/features/geo/use-geo'
import type { GeoRef } from '@/features/personal-data/types'

interface CityPickerFieldProps {
  /** The chosen comune id, or null when it is unknown. */
  value: number | null
  /**
   * The hydrated {id, name} of `value` as it arrived from the server, so the
   * control can label the current selection without searching for it first.
   */
  hydrated?: GeoRef | null
  onChange: (cityId: number) => void
  /**
   * What to search for, in the caller's words (e.g. "Search the town of
   * birth"). Doubles as the empty state: nothing is listed until a term is
   * typed, so the instruction IS the empty message.
   */
  placeholder: string
  disabled?: boolean
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

/**
 * A single, server-searched comune picker: the city-first level of the geo
 * cascade, without its country/state/province parents — the card stores only
 * the comune, the ancestors are reachable through it. Shared by every card
 * field that references one (place of birth, comune of residence).
 *
 * The options are the current search page, so the picked comune is remembered
 * locally: the popover clears the term on close, which drops the result page
 * that carried it, and the trigger would otherwise fall back to the placeholder.
 */
export function CityPickerField({
  value,
  hydrated,
  onChange,
  placeholder,
  disabled,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: CityPickerFieldProps) {
  const { t } = useTranslation()
  const [search, setSearch] = useState('')
  const [picked, setPicked] = useState<GeoRef | null>(null)
  const cities = useCities(null, null, search)

  // The label of the current value: the last local pick wins over the hydrated
  // one, which is stale as soon as the user chooses a different comune.
  const current = picked ?? hydrated ?? null

  const options = useMemo(() => {
    const pages = cities.data?.pages.flat() ?? []
    const listed = pages.map((city) => ({ id: city.id, name: city.name }))
    return current !== null && !listed.some((option) => option.id === current.id)
      ? [current, ...listed]
      : listed
  }, [cities.data?.pages, current])

  const handleChange = (cityId: number) => {
    setPicked(options.find((option) => option.id === cityId) ?? null)
    onChange(cityId)
  }

  const searching = search.trim() !== ''

  return (
    <SearchableSelect
      id={id}
      aria-describedby={ariaDescribedBy}
      aria-invalid={ariaInvalid}
      value={value}
      onChange={handleChange}
      options={options}
      disabled={disabled}
      // The list is narrowed server-side by the typed term, never client-side.
      filter={false}
      isPending={searching && cities.isPending}
      isError={searching && cities.isError}
      onRetry={cities.refetch}
      onSearchChange={setSearch}
      hasNextPage={cities.hasNextPage}
      isFetchingNextPage={cities.isFetchingNextPage}
      onLoadMore={cities.fetchNextPage}
      labels={{
        placeholder,
        searchPlaceholder: t('geo.search'),
        // The comuni are far too many to browse, so the empty state is the
        // instruction to search.
        empty: placeholder,
        noMatch: t('geo.noMatch'),
        error: t('geo.error'),
        retry: t('geo.retry'),
      }}
    />
  )
}
