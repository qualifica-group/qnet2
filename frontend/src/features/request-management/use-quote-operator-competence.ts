import { useRequiredCategories } from '@/features/assignment/use-required-categories'

/**
 * The two competence props of `AssignOperatorsDialog` (spec 0110), resolved
 * for a selection of Offerte. Returned as a bag so the three call sites of
 * this module — the bulk assignment, the row/bulk contact transfer and the
 * work panel's own transfer — spread it onto the dialog instead of restating
 * the same pair each time.
 */
export interface QuoteOperatorCompetence {
  competenceCategoryIds: number[] | undefined
  isResolvingCompetence: boolean
}

/**
 * Narrows the shared popup's Operatore picker to the users competent for the
 * selected Offerte (spec 0110 AC-041). The bulk assignment and the contact
 * transfer travel through different endpoints but write the SAME GA2
 * Operatore slot (`RequestOperatorWriter::apply`), so both filter identically;
 * `isOpen` keeps the lookup off the wire until the popup is actually shown.
 */
export function useQuoteOperatorCompetence(ids: number[], isOpen: boolean): QuoteOperatorCompetence {
  const { competenceCategoryIds, isResolving } = useRequiredCategories({
    selection: ids.length > 0 ? { domain: 'quotes', ids } : null,
    enabled: isOpen,
  })

  return { competenceCategoryIds, isResolvingCompetence: isResolving }
}
