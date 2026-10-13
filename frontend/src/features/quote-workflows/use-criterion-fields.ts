import { useQuery } from '@tanstack/react-query'
import { fetchCriterionFields } from '@/features/quote-workflows/api'
import type { CriterionFieldOption } from '@/features/quote-workflows/types'

/** How long the (static reference data) allow-listed criterion fields stay fresh. */
const CRITERION_FIELDS_STALE_TIME_MS = 5 * 60 * 1000

const NO_CRITERION_FIELDS: CriterionFieldOption[] = []

/** The criterion fields a workflow may be keyed on, for the criteria editor. */
export function useCriterionFields(): CriterionFieldOption[] {
  const query = useQuery({
    queryKey: ['quote-workflows', 'criterion-fields'],
    queryFn: fetchCriterionFields,
    staleTime: CRITERION_FIELDS_STALE_TIME_MS,
  })
  return query.data ?? NO_CRITERION_FIELDS
}
