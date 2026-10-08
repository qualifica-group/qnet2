import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { sameIdSet } from '@/lib/utils'
import { fetchQuoteFormContext } from '@/features/quotes/api'
import type { QuoteFormContext } from '@/features/quotes/types'

/** Nessun prodotto scelto: nessuna categoria, quindi nessun attributo. Hoistato per riferimento stabile. */
const EMPTY_CONTEXT: QuoteFormContext = { applicable_attributes: [], attribute_layout: null }

/** What a persisted quote already resolved for its own offer lines' products (`QuoteResource`). */
export interface PersistedQuoteContext {
  productIds: number[]
  context: QuoteFormContext
}

/**
 * Risolve LIVE le "Informazioni aggiuntive" applicabili all'offerta in corso di
 * composizione (spec 0084, D-5).
 *
 * L'innesco e' la scelta del prodotto: la chiave della query sono gli id dei
 * prodotti effettivamente selezionati nelle righe offerta, ordinati e
 * deduplicati, cosi' che riordinare le righe o toccarne quantita' e prezzo NON
 * provochi una rifetch — solo un cambio dell'insieme dei prodotti lo fa.
 *
 * Disabilitata finche' nessuna riga ha un prodotto: senza categoria non c'e'
 * nulla da risolvere. Sul dettaglio (`persisted`, spec 0197) e' disabilitata
 * anche finche' i prodotti sono quelli salvati: il set lo porta gia'
 * l'offerta, e aprire il dettaglio non deve costare un round trip.
 *
 * `keepPreviousData`-like: durante una rifetch il set precedente resta montato
 * (`placeholderData`), cosi' aggiungere una seconda riga non fa sparire e
 * riapparire i campi gia' compilati.
 */
export function useQuoteFormContext(productIds: number[], persisted?: PersistedQuoteContext) {
  const stableIds = Array.from(new Set(productIds)).sort((a, b) => a - b)
  const isPersistedSet = persisted !== undefined && sameIdSet(stableIds, persisted.productIds)
  const enabled = stableIds.length > 0 && !isPersistedSet

  const query = useQuery<QuoteFormContext, AxiosError>({
    queryKey: ['quotes', 'form-context', stableIds],
    queryFn: () => fetchQuoteFormContext(stableIds),
    enabled,
    placeholderData: (previous) => previous,
  })

  if (isPersistedSet) {
    return { context: persisted.context, isLoading: false, hasPickedProduct: stableIds.length > 0 }
  }

  return {
    context: (enabled ? query.data : undefined) ?? EMPTY_CONTEXT,
    // `isLoading` copre solo la PRIMA risoluzione: durante le successive il set
    // precedente e' ancora valido e la sezione non deve tornare in "loading".
    isLoading: enabled && query.isLoading,
    hasPickedProduct: enabled,
  }
}
