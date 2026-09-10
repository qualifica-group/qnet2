import { useAssignmentScope } from '@/features/assignment/use-assignment-scope'

/**
 * The scope props of `AssignOperatorsDialog` resolved for a selection of
 * Offerte (spec 0110 for the competence, extended by 0113 with the Sede).
 * Returned as a bag so the call sites of this module — the bulk assignment,
 * the row/bulk contact transfer and the work panel's own transfer — take what
 * each of them needs instead of restating the same lookup.
 */
export interface QuoteAssignmentScope {
  competenceCategoryIds: number[] | undefined
  isResolvingCompetence: boolean
  /**
   * Sede shared by the selected Offerte (`quotes.operational_site_id`, spec
   * 0113 D-4), which scopes the Operatore picker on the ASSIGNMENT surface.
   * `undefined` = not resolved yet or resolution failed (the picker stays
   * disabled, AC-034); `null` = mixed selection, competence alone filters.
   * The transfer surface ignores it: there the Sede is the destination the
   * user picks, not a filter (D-2).
   */
  operatorSiteId: number | null | undefined
  /**
   * Whether one operator can take ALL the selected Offerte. `false` on a
   * selection whose Offerte differ by Sede or products: since the rev.3
   * server-side check rejects `mode=single` unless the operator covers every
   * targeted Offerta, offering the picker there would only earn a 422.
   * `undefined` = not resolved yet or resolution failed, an unknown state the
   * call site must not read as "no operator available".
   */
  singleOperatorAvailable: boolean | undefined
}

/**
 * Narrows the shared popup's Operatore picker to the users of the selected
 * Offerte's Sede who are competent for them. The bulk assignment and the
 * contact transfer travel through different endpoints but write the SAME GA2
 * Operatore slot (`RequestOperatorWriter::apply`), so both filter by
 * competence identically; `isOpen` keeps the lookup off the wire until the
 * popup is actually shown.
 */
export function useQuoteAssignmentScope(ids: number[], isOpen: boolean): QuoteAssignmentScope {
  const { competenceCategoryIds, operationalSiteId, singleOperatorAvailable, isResolving } =
    useAssignmentScope({
      selection: ids.length > 0 ? { domain: 'quotes', ids } : null,
      enabled: isOpen,
    })

  return {
    competenceCategoryIds,
    isResolvingCompetence: isResolving,
    operatorSiteId: operationalSiteId,
    singleOperatorAvailable,
  }
}
