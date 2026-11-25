import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { sameIdSet } from '@/lib/utils'
import { fetchWorkOrderFormContext, type StandaloneContextSource } from '@/features/work-orders/api'
import type { WorkOrderFormContext } from '@/features/work-orders/types'

/** Nessuna riga offerta scelta: nessuna categoria, quindi nessun attributo. Hoistato per riferimento stabile. */
const EMPTY_CONTEXT: WorkOrderFormContext = { applicable_attributes: [], attribute_layout: null, locked_attributes: [] }

/** What a persisted work order already resolved for its own lines (`WorkOrderResource`). */
export interface PersistedWorkOrderContext {
  quoteLineIds: number[]
  /** Spec 0431: the saved products and categories of a work order born without an offer. */
  standalone?: StandaloneContextSource
  context: WorkOrderFormContext
}

function sortedIds(ids: number[]): number[] {
  return Array.from(new Set(ids)).sort((a, b) => a - b)
}

/**
 * Risolve LIVE le "Informazioni aggiuntive" applicabili alla commessa in
 * corso di composizione (spec 0098, D-7). Twin di `useQuoteFormContext`
 * (spec 0084): l'innesco e' la scelta delle RIGHE OFFERTA (D-1: le categorie
 * vengono dalle righe della commessa, non da tutte quelle del preventivo).
 *
 * Disabilitata finche' nessuna riga e' selezionata: senza categoria non c'e'
 * nulla da risolvere. Sul dettaglio (`persisted`) e' disabilitata anche
 * finche' le righe sono quelle salvate: il set lo porta gia' la commessa, e
 * aprire il dettaglio non deve costare un round trip.
 *
 * `placeholderData`: durante una rifetch il set precedente resta montato,
 * cosi' aggiungere una seconda riga non fa sparire e riapparire i campi gia'
 * compilati.
 */
export function useWorkOrderFormContext(
  quoteLineIds: number[],
  persisted?: PersistedWorkOrderContext,
  standalone?: StandaloneContextSource,
) {
  const stableIds = sortedIds(quoteLineIds)
  // Spec 0431 (D-17): a work order born without an offer resolves from its products and categories.
  const productIds = sortedIds(standalone?.productIds ?? [])
  const categoryIds = sortedIds(standalone?.productCategoryIds ?? [])
  const isPersistedSet =
    persisted !== undefined &&
    sameIdSet(stableIds, persisted.quoteLineIds) &&
    sameIdSet(productIds, persisted.standalone?.productIds ?? []) &&
    sameIdSet(categoryIds, persisted.standalone?.productCategoryIds ?? [])
  const hasSource = stableIds.length > 0 || productIds.length > 0 || categoryIds.length > 0
  const enabled = hasSource && !isPersistedSet

  const query = useQuery<WorkOrderFormContext, AxiosError>({
    queryKey: ['work-orders', 'form-context', stableIds, productIds, categoryIds],
    queryFn: () =>
      standalone !== undefined
        ? fetchWorkOrderFormContext(stableIds, { productIds, productCategoryIds: categoryIds })
        : fetchWorkOrderFormContext(stableIds),
    enabled,
    placeholderData: (previous) => previous,
  })

  if (isPersistedSet) {
    return { context: persisted.context, isLoading: false, hasPickedLines: hasSource }
  }

  return {
    context: (enabled ? query.data : undefined) ?? EMPTY_CONTEXT,
    // `isLoading` copre solo la PRIMA risoluzione: durante le successive il
    // set precedente e' ancora valido e la sezione non deve tornare in "loading".
    isLoading: enabled && query.isLoading,
    hasPickedLines: enabled,
  }
}
