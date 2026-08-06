import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { attributeLayoutBlobSchema } from '@/features/attributes/attribute-layout-schema'
import type { LayoutBlob, LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import { fetchAttributeLayout, saveAttributeLayout } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import type { AttributeContext, AttributeLayoutData } from '@/features/product-categories/types'

const EMPTY_LAYOUT: LayoutBlob = { sections: [] }

export interface UseAttributeLayoutLabels {
  saved: string
  forbidden: string
  saveError: string
  invalid: string
  reset: string
}

interface UseAttributeLayoutArgs {
  categoryId: number
  context: AttributeContext
  scope: LayoutFormScope
  labels: UseAttributeLayoutLabels
}

/**
 * Owns MT-3.2's fetch/edit/save cycle for one (category, context, scope)
 * combination (spec 0062 `data_contract`): loads that scope's OWN layout, the
 * shared `all` layout it inherits while it has none, and the category's
 * effective attributes; mirrors the effective layout into a locally editable
 * `draft` the configurator controls; validates + `PUT`s on an explicit Save —
 * mirrors `useDefaultStatuses`'s "fresh load, local draft, explicit save"
 * shape (`features/quote-workflows`). Switching context or scope
 * changes the query key, so the draft always resyncs to the newly loaded
 * combination rather than carrying edits across it.
 *
 * Inheritance (D3 revised): on a per-mode scope with no override the draft is
 * seeded from the inherited shared layout and `isCustomizing` is false — the
 * host renders it read-only until `customize()` turns that seed into an
 * override-to-be, which only reaches the server on Save. `resetToShared()` is
 * the inverse: it deletes the persisted override right away.
 */
export function useAttributeLayout({ categoryId, context, scope, labels }: UseAttributeLayoutArgs) {
  const queryClient = useQueryClient()
  const queryKey = productCategoryKeys.attributeLayout(categoryId, context, scope)
  const query = useQuery({ queryKey, queryFn: () => fetchAttributeLayout(categoryId, context, scope) })

  const [draft, setDraft] = useState<LayoutBlob>(EMPTY_LAYOUT)
  const [syncedFrom, setSyncedFrom] = useState<AttributeLayoutData | undefined>(undefined)
  const [isCustomizing, setIsCustomizing] = useState(false)
  if (query.data && query.data !== syncedFrom) {
    setSyncedFrom(query.data)
    setDraft(query.data.layout ?? query.data.inherited ?? EMPTY_LAYOUT)
    setIsCustomizing(scope === 'all' || query.data.layout !== null)
  }

  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  /** Re-seeds query cache, draft and inheritance state from what the server now holds. */
  const applyPersisted = (persisted: LayoutBlob | null) => {
    const nextData: AttributeLayoutData = {
      layout: persisted,
      inherited: query.data?.inherited ?? null,
      attributes: query.data?.attributes ?? [],
    }
    setSyncedFrom(nextData)
    setDraft(persisted ?? nextData.inherited ?? EMPTY_LAYOUT)
    setIsCustomizing(scope === 'all' || persisted !== null)
    queryClient.setQueryData(queryKey, nextData)
  }

  const reportWriteFailure = (caughtError: unknown) => {
    if (axios.isAxiosError<ApiErrorResponse>(caughtError) && caughtError.response?.status === 403) {
      toast.error(labels.forbidden)
    } else {
      toast.error(labels.saveError)
    }
  }

  const save = async (): Promise<boolean> => {
    const parsed = attributeLayoutBlobSchema.safeParse(draft)
    if (!parsed.success) {
      setError(labels.invalid)
      return false
    }
    setError(null)
    setIsSaving(true)
    try {
      applyPersisted(await saveAttributeLayout(categoryId, context, scope, draft))
      toast.success(labels.saved)
      return true
    } catch (caughtError) {
      reportWriteFailure(caughtError)
      return false
    } finally {
      setIsSaving(false)
    }
  }

  /** Drops this mode's persisted override, so it goes back to inheriting the shared layout. */
  const resetToShared = async (): Promise<boolean> => {
    setError(null)
    setIsSaving(true)
    try {
      await saveAttributeLayout(categoryId, context, scope, EMPTY_LAYOUT)
      applyPersisted(null)
      toast.success(labels.reset)
      return true
    } catch (caughtError) {
      reportWriteFailure(caughtError)
      return false
    } finally {
      setIsSaving(false)
    }
  }

  return {
    attributes: query.data?.attributes ?? [],
    draft,
    setDraft,
    /** The shared layout this scope inherits while it has no override of its own. */
    inherited: query.data?.inherited ?? null,
    /** Whether the scope has its OWN persisted row (never true for an unsaved customization). */
    hasOverride: query.data?.layout != null,
    /** Whether the draft is this scope's own layout (editable) rather than a read-only inherited preview. */
    isCustomizing,
    customize: () => setIsCustomizing(true),
    resetToShared,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
    isSaving,
    error,
    save,
  }
}
