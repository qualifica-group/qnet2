import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchQuoteFormContext } from '@/features/quotes/api'
import type { QuoteFormContext } from '@/features/quotes/types'

/** Nessun prodotto scelto: nessuna categoria, quindi nessun attributo. Hoistato per riferimento stabile. */
const EMPTY_CONTEXT: QuoteFormContext = { applicable_attributes: [], attribute_layout: null }

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
 * nulla da risolvere, e chiamare l'endpoint restituirebbe comunque un set vuoto
 * al costo di un round trip.
 *
 * `keepPreviousData`-like: durante una rifetch il set precedente resta montato
 * (`placeholderData`), cosi' aggiungere una seconda riga non fa sparire e
 * riapparire i campi gia' compilati.
 */
export function useQuoteFormContext(productIds: number[]) {
  const stableIds = Array.from(new Set(productIds)).sort((a, b) => a - b)
  const enabled = stableIds.length > 0

  const query = useQuery<QuoteFormContext, AxiosError>({
    queryKey: ['quotes', 'form-context', stableIds],
    queryFn: () => fetchQuoteFormContext(stableIds),
    enabled,
    placeholderData: (previous) => previous,
  })

  return {
    context: query.data ?? EMPTY_CONTEXT,
    // `isLoading` copre solo la PRIMA risoluzione: durante le successive il set
    // precedente e' ancora valido e la sezione non deve tornare in "loading".
    isLoading: enabled && query.isLoading,
    hasPickedProduct: enabled,
  }
}
