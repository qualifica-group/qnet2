import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { managerSlotsFromRefs, padManagerSlots } from '@/lib/utils'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import { flattenForSelectPages, useForSelect, useForSelectLabels } from '@/features/for-select/use-for-select'
import {
  OPPORTUNITIES_FOR_SELECT_RESOURCE,
  type OpportunityForSelectItem,
  type OpportunityForSelectMeta,
} from '@/features/opportunities/for-select-api'
import { PRODUCTS_FOR_SELECT_RESOURCE } from '@/features/products/for-select-api'
import { parseQuoteCreateProductIds, QUOTE_CREATE_REGISTRY_PARAM } from '@/features/quotes/quote-create-params'
import { QUOTES_LAYOUT_MODULE_PARAM } from '@/features/quotes/quote-field-strings'
import { DEFAULT_MANAGER_SLOTS } from '@/features/quotes/quote-schema'
import { createEmptyLineRow, DEFAULT_LINE_QUANTITY, lineValuesFromProduct } from '@/features/quotes/use-quote-lines-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'
import type { QuoteFormState } from '@/features/quotes/use-quote-form'
import type { QuoteCreateFormMode } from '@/features/quotes/types'

/** Stable empty id set: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []

/**
 * Everything the create form fills in for the operator before they type
 * (moved out of the old form body, spec 0197): the Opportunita' it inherits
 * from, the products a deep link seeds, the module's default layout. Each
 * prefill applies ONCE, so a later edit is never overwritten by a slow
 * response.
 */
export function useQuoteCreateDefaults(mode: QuoteCreateFormMode, quoteForm: QuoteFormState) {
  const { form, rememberVatRatePercent, rememberProductTypology, rememberProductName } = quoteForm

  // Directive 2026-07-29: Commerciale, Segnalatore and Supervisore are always
  // inherited from the picked Opportunita' — straight from its for-select
  // `meta` (no extra fetch), then freely editable (spec 0065 D-3: the quote
  // keeps a snapshot, never a live link). Wired as the opportunity select's
  // `onItemChange`, so it only ever runs on an actual user pick/clear.
  const [pickedMeta, setPickedMeta] = useState<OpportunityForSelectMeta | null>(null)
  /** Writes the inherited RHF fields only — no React state — so the effect below may call it too. */
  const applyInheritedValues = useCallback(
    (meta: OpportunityForSelectMeta | null) => {
      form.setValue('commercial_id', meta?.commercial?.id ?? null, { shouldDirty: true })
      form.setValue('reporter_id', meta?.reporter?.id ?? null, { shouldDirty: true })
      form.setValue('supervisor_id', meta?.supervisor?.id ?? null, { shouldDirty: true })
      // Directive 2026-07-30: the sede operativa is inherited on the same terms
      // (the server applies the same rule when the key is absent).
      form.setValue('operational_site_id', meta?.operational_site?.id ?? null, { shouldDirty: true })
      // Spec 0087, D-5: the team is prefilled on the same terms, padded to the
      // standard card count so fewer managers still open a full set of slots.
      form.setValue('manager_slots', padManagerSlots(managerSlotsFromRefs(meta?.managers ?? []), DEFAULT_MANAGER_SLOTS), {
        shouldDirty: true,
      })
    },
    [form],
  )
  const handleOpportunityItemChange = useCallback(
    (item: ForSelectItem | null) => {
      const meta = (item as OpportunityForSelectItem | null)?.meta ?? null
      setPickedMeta(meta)
      applyInheritedValues(meta)
    },
    [applyInheritedValues],
  )

  // Spec 0067 AC-050/AC-052: an Opportunity preset via create params (the
  // opportunity detail's "Crea Offerta" panel) is locked, and the same `meta`
  // — the for-select item its own row is labelled from — feeds the roles.
  const forcedOpportunityId = typeof mode.params?.opportunity_id === 'number' ? mode.params.opportunity_id : null
  const registryParam = mode.params?.[QUOTE_CREATE_REGISTRY_PARAM]
  const opportunityRegistryId = typeof registryParam === 'number' ? registryParam : null
  const forcedOpportunityLabels = useForSelectLabels({
    resource: OPPORTUNITIES_FOR_SELECT_RESOURCE,
    ids: forcedOpportunityId !== null ? [forcedOpportunityId] : NO_IDS,
    enabled: forcedOpportunityId !== null,
  })
  const forcedOpportunity =
    forcedOpportunityId !== null
      ? ((forcedOpportunityLabels.get(forcedOpportunityId) as OpportunityForSelectItem | undefined) ?? null)
      : null
  const appliedForcedOpportunity = useRef(false)
  useEffect(() => {
    if (appliedForcedOpportunity.current || !forcedOpportunity) {
      return
    }
    appliedForcedOpportunity.current = true
    applyInheritedValues(forcedOpportunity.meta ?? null)
  }, [forcedOpportunity, applyInheritedValues])

  // User directive 2026-08-31: an offer opened from the "questa anagrafica ha
  // gia' un'opportunita' aperta" refusal carries the products the refused
  // opportunity was going to classify — its rows must already be there.
  const seededProductIds = useMemo(() => parseQuoteCreateProductIds(mode.params), [mode])
  const seededProductLabels = useForSelectLabels({
    resource: PRODUCTS_FOR_SELECT_RESOURCE,
    ids: seededProductIds,
    enabled: seededProductIds.length > 0,
  })
  const appliedSeededProducts = useRef(false)
  useEffect(() => {
    if (seededProductIds.length === 0 || appliedSeededProducts.current) {
      return
    }
    const items = seededProductIds
      .map((id) => seededProductLabels.get(id) as QuoteProductForSelectItem | undefined)
      .filter((item): item is QuoteProductForSelectItem => item !== undefined)
    if (items.length < seededProductIds.length) {
      return
    }
    appliedSeededProducts.current = true
    form.setValue(
      'offer_lines',
      items.map((item) => {
        if (item.meta.vat_rate_id !== null && item.meta.vat_rate !== null) {
          rememberVatRatePercent(item.meta.vat_rate_id, Number(item.meta.vat_rate))
        }
        // Spec 0099/0144: bucketed and named exactly like a manual pick.
        if (item.meta.product_typology) {
          rememberProductTypology(item.id, item.meta.product_typology.id)
        }
        rememberProductName(item.id, item.label)
        return { ...createEmptyLineRow(), ...lineValuesFromProduct(item, 'revenue'), quantity: DEFAULT_LINE_QUANTITY }
      }),
      { shouldDirty: true },
    )
  }, [seededProductIds, seededProductLabels, form, rememberVatRatePercent, rememberProductTypology, rememberProductName])

  // Spec 0070 AC-310: the `quotes` module's active default layout (the
  // for-select sorts it first) is applied to the still-empty field, so the
  // operator SEES it preselected; the server would apply it anyway (D-3).
  const defaultLayoutQuery = useForSelect({
    resource: DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE,
    search: '',
    params: QUOTES_LAYOUT_MODULE_PARAM,
  })
  const defaultLayout = flattenForSelectPages(defaultLayoutQuery.data?.pages)[0] ?? null
  const appliedDefaultLayout = useRef(false)
  useEffect(() => {
    if (appliedDefaultLayout.current || !defaultLayout) {
      return
    }
    appliedDefaultLayout.current = true
    form.setValue('layout_id', defaultLayout.id, { shouldDirty: false })
  }, [defaultLayout, form])

  return {
    /** The meta of the picked (or preset) Opportunita': the inherited refs' names. */
    inheritedMeta: pickedMeta ?? forcedOpportunity?.meta ?? null,
    forcedOpportunity,
    isOpportunityForced: forcedOpportunityId !== null,
    /** Spec 0199: the anagrafica the Opportunita' picker is narrowed to (its Offerte tab), `null` = none. */
    opportunityRegistryId,
    handleOpportunityItemChange,
    defaultLayout,
  }
}
