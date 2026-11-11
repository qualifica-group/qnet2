import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { sameIdSet } from '@/lib/utils'
import { fetchQuoteFormContext } from '@/features/quotes/api'
import type { QuoteFormContext } from '@/features/quotes/types'

/** Nessuna Opportunita' ne' prodotto scelti: nessuna categoria, quindi nessun attributo. Hoistato per riferimento stabile. */
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
 * Le categorie sono quelle che risolverebbe il salvataggio: righe prodotto
 * dell'Opportunita' scelta + prodotti delle righe offerta (segnalazione
 * 2026-11-11: Offerte e Gestione Richieste mostrano lo stesso record e devono
 * mostrare gli stessi campi). La chiave della query sono l'Opportunita' e gli
 * id dei prodotti, ordinati e deduplicati, cosi' che riordinare le righe o
 * toccarne quantita' e prezzo NON provochi una rifetch.
 *
 * Disabilitata finche' non ci sono ne' Opportunita' ne' prodotti: senza
 * categoria non c'e' nulla da risolvere. Sul dettaglio (`persisted`, spec
 * 0197; l'Opportunita' li' non cambia) e' disabilitata anche finche' i
 * prodotti sono quelli salvati: il set lo porta gia' l'offerta, e aprire il
 * dettaglio non deve costare un round trip.
 *
 * `keepPreviousData`-like: durante una rifetch il set precedente resta montato
 * (`placeholderData`), cosi' aggiungere una seconda riga non fa sparire e
 * riapparire i campi gia' compilati.
 */
export function useQuoteFormContext(
  opportunityId: number | null,
  productIds: number[],
  persisted?: PersistedQuoteContext,
) {
  const stableIds = Array.from(new Set(productIds)).sort((a, b) => a - b)
  const isPersistedSet = persisted !== undefined && sameIdSet(stableIds, persisted.productIds)
  const enabled = (opportunityId !== null || stableIds.length > 0) && !isPersistedSet

  const query = useQuery<QuoteFormContext, AxiosError>({
    queryKey: ['quotes', 'form-context', opportunityId, stableIds],
    queryFn: () => fetchQuoteFormContext(opportunityId, stableIds),
    enabled,
    placeholderData: (previous) => previous,
  })

  if (isPersistedSet) {
    return { context: persisted.context, isLoading: false }
  }

  return {
    context: (enabled ? query.data : undefined) ?? EMPTY_CONTEXT,
    // `isLoading` copre solo la PRIMA risoluzione: durante le successive il set
    // precedente e' ancora valido e la sezione non deve tornare in "loading".
    isLoading: enabled && query.isLoading,
  }
}
