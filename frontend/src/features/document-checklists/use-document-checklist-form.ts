import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { createDocumentChecklist, updateDocumentChecklist } from '@/features/document-checklists/api'
import {
  buildChecklistPayload,
  buildDefaultChecklistValues,
  checklistToFormValues,
} from '@/features/document-checklists/document-checklist-form-values'
import {
  buildDocumentChecklistSchema,
  type DocumentChecklistFormValues,
} from '@/features/document-checklists/document-checklist-schema'
import { applyChecklistServerErrors } from '@/features/document-checklists/document-checklist-server-errors'
import { documentChecklistKeys } from '@/features/document-checklists/query-keys'
import type { DocumentChecklist, DocumentChecklistWithPermissions } from '@/features/document-checklists/types'

interface UseDocumentChecklistFormArgs {
  /** Saved checklist to edit; absent = create. */
  checklist?: DocumentChecklist
  /** Called after a successful create/update with the saved checklist. */
  onSuccess: (saved: DocumentChecklistWithPermissions) => void
}

/**
 * Owns every non-render concern of the checklist form: RHF/Zod wiring, the
 * create/update submit (full replacement, entries in the order shown) and the
 * 422 mapping onto fields, entries included. `onSubmit` is the orchestration point.
 */
export function useDocumentChecklistForm({ checklist, onSuccess }: UseDocumentChecklistFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const form = useForm<DocumentChecklistFormValues>({
    resolver: zodResolver(buildDocumentChecklistSchema(t)),
    defaultValues: checklist ? checklistToFormValues(checklist) : buildDefaultChecklistValues(),
  })

  const resetToSaved = () => {
    form.reset(checklist ? checklistToFormValues(checklist) : buildDefaultChecklistValues())
    setServerError(null)
  }

  const onSubmit = async (values: DocumentChecklistFormValues) => {
    setServerError(null)
    try {
      // Step 1: persist (the backend queues the ArchivioSmart sync)
      const payload = buildChecklistPayload(values)
      const saved = checklist
        ? await updateDocumentChecklist(checklist.id, payload)
        : await createDocumentChecklist(payload)

      // Step 2: keep the cache and the form on the saved record (new entries now have ids)
      queryClient.setQueryData(documentChecklistKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: documentChecklistKeys.syncLogs(saved.id) })
      form.reset(checklistToFormValues(saved))

      // Step 3: tell the caller
      toast.success(t(checklist ? 'documentChecklists.form.updated' : 'documentChecklists.form.created'))
      onSuccess(saved)
    } catch (error) {
      if (!applyChecklistServerErrors(error, form.setError)) {
        setServerError(apiErrorMessage(error, t('documentChecklists.form.genericError'), i18n))
      }
    }
  }

  return { form, serverError, onSubmit, resetToSaved }
}
