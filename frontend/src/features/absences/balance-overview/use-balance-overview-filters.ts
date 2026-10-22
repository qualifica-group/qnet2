import { useMemo, useState } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import {
  BALANCE_OVERVIEW_PER_PAGE,
  type AbsenceBalanceOverviewFilters,
  type AbsenceBalanceOverviewParams,
} from '@/features/absences/types'

/** Filters and page of the HR overview; any filter change goes back to the first page. */
export function useBalanceOverviewFilters() {
  const [year, setYearState] = useState(() => new Date().getFullYear())
  const [businessFunctionId, setBusinessFunctionIdState] = useState<number | null>(null)
  const [siteId, setSiteIdState] = useState<number | null>(null)
  const [searchText, setSearchTextState] = useState('')
  const [page, setPage] = useState(1)
  const search = useDebouncedValue(searchText.trim())

  const filters = useMemo<AbsenceBalanceOverviewFilters>(
    () => ({
      year,
      business_function_id: businessFunctionId ?? undefined,
      operational_site_id: siteId ?? undefined,
      search: search === '' ? undefined : search,
    }),
    [year, businessFunctionId, siteId, search],
  )
  const params = useMemo<AbsenceBalanceOverviewParams>(
    () => ({ ...filters, page, per_page: BALANCE_OVERVIEW_PER_PAGE }),
    [filters, page],
  )

  const resetting =
    <T,>(setter: (value: T) => void) =>
    (value: T) => {
      setter(value)
      setPage(1)
    }

  return {
    year,
    businessFunctionId,
    siteId,
    searchText,
    page,
    filters,
    params,
    setPage,
    setYear: resetting(setYearState),
    setBusinessFunctionId: resetting(setBusinessFunctionIdState),
    setSiteId: resetting(setSiteIdState),
    setSearchText: resetting(setSearchTextState),
  }
}
