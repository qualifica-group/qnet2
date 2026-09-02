import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchWorkOrderFormContext } from '@/features/work-orders/api'
import type { WorkOrderFormContext } from '@/features/work-orders/types'

/** Nessuna riga offerta scelta: nessuna categoria, quindi nessun attributo. Hoistato per riferimento stabile. */
const EMPTY_CONTEXT: WorkOrderFormContext = { applicable_attributes: [], attribute_layout: null }

/**
 * Risolve LIVE le "Informazioni aggiuntive" applicabili alla commessa in
 * corso di composizione (spec 0098, D-7). Twin di `useQuoteFormContext`
 * (spec 0084): l'innesco e' la scelta delle RIGHE OFFERTA (D-1: le categorie
 * vengono dalle righe della commessa, non da tutte quelle del preventivo).
 *
 * Disabilitata finche' nessuna riga e' selezionata: senza categoria non c'e'
 * nulla da risolvere, e chiamare l'endpoint restituirebbe comunque un set
 * vuoto al costo di un round trip.
 *
 * `placeholderData`: durante una rifetch il set precedente resta montato,
 * cosi' aggiungere una seconda riga non fa sparire e riapparire i campi gia'
 * compilati.
 */
export function useWorkOrderFormContext(quoteLineIds: number[]) {
  const stableIds = Array.from(new Set(quoteLineIds)).sort((a, b) => a - b)
  const enabled = stableIds.length > 0

  const query = useQuery<WorkOrderFormContext, AxiosError>({
    queryKey: ['work-orders', 'form-context', stableIds],
    queryFn: () => fetchWorkOrderFormContext(stableIds),
    enabled,
    placeholderData: (previous) => previous,
  })

  return {
    context: query.data ?? EMPTY_CONTEXT,
    // `isLoading` copre solo la PRIMA risoluzione: durante le successive il
    // set precedente e' ancora valido e la sezione non deve tornare in "loading".
    isLoading: enabled && query.isLoading,
    hasPickedLines: enabled,
  }
}
