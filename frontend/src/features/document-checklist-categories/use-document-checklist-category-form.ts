import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createDocumentChecklistCategory,
  updateDocumentChecklistCategory,
} from '@/features/document-checklist-categories/api'
import {
  buildCategoryPayload,
  buildDefaultCategoryValues,
  categoryToFormValues,
} from '@/features/document-checklist-categories/document-checklist-category-form-values'
import {
  buildDocumentChecklistCategorySchema,
  type DocumentChecklistCategoryFormValues,
} from '@/features/document-checklist-categories/document-checklist-category-schema'
import { documentChecklistCategoryKeys } from '@/features/document-checklist-categories/query-keys'
import type {
  DocumentChecklistCategory,
  DocumentChecklistCategoryWithPermissions,
} from '@/features/document-checklist-categories/types'

const SERVER_FIELDS: (keyof DocumentChecklistCategoryFormValues)[] = ['name', 'description', 'sort_order']

interface UseDocumentChecklistCategoryFormArgs {
  /** Saved category to edit; absent = create. */
  category?: DocumentChecklistCategory
  onSuccess: (saved: DocumentChecklistCategoryWithPermissions) => void
}

/**
 * Owns every non-render concern of the category form: RHF/Zod wiring, the
 * create/update submit and the 422 mapping onto fields (a name already in use
 * lands on `name`). `onSubmit` is the orchestration point.
 */
export function useDocumentChecklistCategoryForm({ category, onSuccess }: UseDocumentChecklistCategoryFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const form = useForm<DocumentChecklistCategoryFormValues>({
    resolver: zodResolver(buildDocumentChecklistCategorySchema(t)),
    defaultValues: category ? categoryToFormValues(category) : buildDefaultCategoryValues(),
  })

  const resetToSaved = () => {
    form.reset(category ? categoryToFormValues(category) : buildDefaultCategoryValues())
    setServerError(null)
  }

  const onSubmit = async (values: DocumentChecklistCategoryFormValues) => {
    setServerError(null)
    try {
      // Step 1: persist (the backend queues the ArchivioSmart sync)
      const payload = buildCategoryPayload(values)
      const saved = category
        ? await updateDocumentChecklistCategory(category.id, payload)
        : await createDocumentChecklistCategory(payload)

      // Step 2: keep the cache and the form on the saved record
      queryClient.setQueryData(documentChecklistCategoryKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: documentChecklistCategoryKeys.syncLogs(saved.id) })
      form.reset(categoryToFormValues(saved))

      // Step 3: tell the caller
      toast.success(t(category ? 'documentChecklistCategories.form.updated' : 'documentChecklistCategories.form.created'))
      onSuccess(saved)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, SERVER_FIELDS)) {
        setServerError(apiErrorMessage(error, t('documentChecklistCategories.form.genericError'), i18n))
      }
    }
  }

  return { form, serverError, onSubmit, resetToSaved }
}
