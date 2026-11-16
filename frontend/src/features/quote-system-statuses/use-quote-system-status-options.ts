import { useQuery } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { forSelectKeys } from '@/features/for-select/query-keys'
import type { ForSelectItem } from '@/features/for-select/types'
import type { WorkflowSystemStatusOption } from '@/features/workflow-statuses/types'
import type { QuoteSystemStatusBehavior, QuoteSystemStatusKey } from '@/features/quote-system-statuses/types'

/** Resource segment of `GET /api/quote-system-statuses/for-select`. */
export const QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE = 'quote-system-statuses'

/** Statuses are a small lookup table: one page covers the whole set (the endpoint caps at 100). */
const OPTIONS_PAGE_SIZE = 100

interface QuoteSystemStatusForSelectItem extends ForSelectItem {
  meta?: { color: string | null; behavior: QuoteSystemStatusBehavior; system_key: QuoteSystemStatusKey }
}

const NO_OPTIONS: WorkflowSystemStatusOption[] = []

/** Every system status as a select option (in `sort_order`), for the quote workflow configurators. */
export function useQuoteSystemStatusOptions(enabled = true) {
  const query = useQuery({
    // Under the shared for-select prefix so reorders and edits that invalidate it refresh these options too.
    queryKey: [...forSelectKeys.resource(QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE), 'all-options'],
    queryFn: async (): Promise<WorkflowSystemStatusOption[]> => {
      const response = await fetchForSelect(QUOTE_SYSTEM_STATUSES_FOR_SELECT_RESOURCE, { limit: OPTIONS_PAGE_SIZE })
      return (response.items as QuoteSystemStatusForSelectItem[]).map((item) => ({
        id: item.id,
        label: item.label,
        color: item.meta?.color ?? null,
        behavior: item.meta?.behavior ?? 'open',
        systemKey: item.meta?.system_key ?? null,
      }))
    },
    enabled,
  })

  return { options: query.data ?? NO_OPTIONS, isLoading: query.isLoading }
}
