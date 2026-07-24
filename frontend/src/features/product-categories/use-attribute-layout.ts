import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { attributeLayoutBlobSchema } from '@/features/attributes/attribute-layout-schema'
import type { LayoutBlob, LayoutFormMode } from '@/features/attributes/attribute-layout-types'
import { fetchAttributeLayout, saveAttributeLayout } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import type { AttributeContext, AttributeLayoutData } from '@/features/product-categories/types'

const EMPTY_LAYOUT: LayoutBlob = { sections: [] }

export interface UseAttributeLayoutLabels {
  saved: string
  forbidden: string
  saveError: string
  invalid: string
}

interface UseAttributeLayoutArgs {
  categoryId: number
  context: AttributeContext
  formMode: LayoutFormMode
  labels: UseAttributeLayoutLabels
}

/**
 * Owns MT-3.2's fetch/edit/save cycle for one (category, context, form_mode)
 * combination (spec 0062 `data_contract`): loads the persisted layout plus
 * the category's effective attributes, mirrors the layout into a locally
 * editable `draft` the configurator controls, and validates + `PUT`s on an
 * explicit Save — mirrors `useDefaultStatuses`'s "fresh load, local draft,
 * explicit save" shape (`features/opportunity-workflows`). Switching context
 * or form_mode changes the query key, so the draft always resyncs to the
 * newly loaded combination rather than carrying edits across it.
 */
export function useAttributeLayout({ categoryId, context, formMode, labels }: UseAttributeLayoutArgs) {
  const queryClient = useQueryClient()
  const queryKey = productCategoryKeys.attributeLayout(categoryId, context, formMode)
  const query = useQuery({ queryKey, queryFn: () => fetchAttributeLayout(categoryId, context, formMode) })

  const [draft, setDraft] = useState<LayoutBlob>(EMPTY_LAYOUT)
  const [syncedFrom, setSyncedFrom] = useState<AttributeLayoutData | undefined>(undefined)
  if (query.data && query.data !== syncedFrom) {
    setSyncedFrom(query.data)
    setDraft(query.data.layout ?? EMPTY_LAYOUT)
  }

  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const save = async (): Promise<boolean> => {
    const parsed = attributeLayoutBlobSchema.safeParse(draft)
    if (!parsed.success) {
      setError(labels.invalid)
      return false
    }
    setError(null)
    setIsSaving(true)
    try {
      const persisted = await saveAttributeLayout(categoryId, context, formMode, draft)
      const nextDraft = persisted ?? EMPTY_LAYOUT
      const nextData: AttributeLayoutData = { layout: persisted, attributes: query.data?.attributes ?? [] }
      setDraft(nextDraft)
      setSyncedFrom(nextData)
      queryClient.setQueryData(queryKey, nextData)
      toast.success(labels.saved)
      return true
    } catch (caughtError) {
      if (axios.isAxiosError<ApiErrorResponse>(caughtError) && caughtError.response?.status === 403) {
        toast.error(labels.forbidden)
      } else {
        toast.error(labels.saveError)
      }
      return false
    } finally {
      setIsSaving(false)
    }
  }

  return {
    attributes: query.data?.attributes ?? [],
    draft,
    setDraft,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
    isSaving,
    error,
    save,
  }
}
