import { useState } from 'react'
import {
  collectDescendantsWithOwnBusinessFunction,
  type BusinessFunctionResetCandidate,
} from '@/features/product-categories/business-function-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

interface PendingBusinessFunctionReset {
  values: ProductCategoryFormValues
  categories: BusinessFunctionResetCandidate[]
}

interface UseBusinessFunctionResetConfirmationArgs {
  mode: ProductCategoryFormMode
  /** The real save, run only once the operator has accepted the reset (or when none is due). */
  onSubmit: (values: ProductCategoryFormValues) => Promise<void>
}

/**
 * Guards the save behind an explicit confirmation whenever assigning a
 * business function to THIS category would wipe the ones its descendants own
 * (user directive 2026-09-16). The backend already cascades those to null
 * (spec 0023: at most one business function per root->leaf chain); until now
 * it did so silently, so the operator lost the children's setting without
 * ever being told.
 *
 * The affected rows are read from the cached category tree (`business_function_id`
 * per node, the same source the inherited-value hint uses) — no extra request.
 */
export function useBusinessFunctionResetConfirmation({
  mode,
  onSubmit,
}: UseBusinessFunctionResetConfirmationArgs) {
  const treeQuery = useProductCategoryTree()
  const [pending, setPending] = useState<PendingBusinessFunctionReset | null>(null)
  const [isConfirming, setIsConfirming] = useState(false)

  // Step 1: only an edit that ASSIGNS a business function different from the
  // saved one can trigger the cascade; clearing it, or leaving it untouched,
  // never does.
  function resetCandidatesFor(values: ProductCategoryFormValues): BusinessFunctionResetCandidate[] {
    if (
      mode.type !== 'edit' ||
      values.business_function_id === null ||
      values.business_function_id === mode.category.business_function_id
    ) {
      return []
    }

    return collectDescendantsWithOwnBusinessFunction(treeQuery.data ?? [], mode.category.id)
  }

  // Step 2: hold the submitted values back while the dialog is open, so the
  // confirmation saves exactly what was validated, not a later form state.
  const submit = async (values: ProductCategoryFormValues) => {
    const categories = resetCandidatesFor(values)

    if (categories.length > 0) {
      setPending({ values, categories })
      return
    }

    await onSubmit(values)
  }

  // Step 3: accepted - run the held save, then drop the dialog either way
  // (a failed save surfaces through the form's own server-error channel).
  const confirm = async () => {
    if (pending === null) {
      return
    }

    setIsConfirming(true)
    try {
      await onSubmit(pending.values)
    } finally {
      setIsConfirming(false)
      setPending(null)
    }
  }

  return {
    submit,
    confirm,
    cancel: () => setPending(null),
    /** The descendants about to lose their own business function, null while no confirmation is pending. */
    pendingCategories: pending?.categories ?? null,
    isConfirming,
  }
}
