import { useQueryClient, type InfiniteData } from '@tanstack/react-query'
import { geoKeys } from '@/features/geo/query-keys'
import type { City } from '@/features/geo/types'
import { useCountries, useProvinces, useStates } from '@/features/geo/use-geo'
import type { GeoValue } from '@/features/geo/geo-select'

/** A geo level as a record row names it. */
export interface GeoNameRef {
  id: number
  name: string
}

/** Shown while a level's list resolves: never the bare id. */
const PENDING_LABEL = '…'

function refOf(items: { id: number; name: string }[] | undefined, id: number | null): GeoNameRef | null {
  if (id === null) {
    return null
  }
  return { id, name: items?.find((item) => item.id === id)?.name ?? PENDING_LABEL }
}

/**
 * The names of a geo cascade value held as ids (a create draft): country,
 * state and province from the lists the cascade itself loads (same query
 * keys, same cache); the city — there is no lookup by id — from whichever
 * cached city page the picker took it from (a parent's list or a city-first
 * search), since a city can only be picked out of one of them.
 */
export function useGeoNames(value: GeoValue) {
  const queryClient = useQueryClient()
  const countries = useCountries()
  const states = useStates(value.country_id)
  const provinces = useProvinces(value.state_id)

  let city: GeoNameRef | null = null
  if (value.city_id !== null) {
    const pages = queryClient.getQueriesData<InfiniteData<City[]>>({ queryKey: [...geoKeys.all, 'cities'] })
    const found = pages
      .flatMap(([, data]) => data?.pages.flat() ?? [])
      .find((candidate) => candidate.id === value.city_id)
    city = { id: value.city_id, name: found?.name ?? PENDING_LABEL }
  }

  return {
    country: refOf(countries.data, value.country_id),
    state: refOf(states.data, value.state_id),
    province: refOf(provinces.data, value.province_id),
    city,
  }
}
