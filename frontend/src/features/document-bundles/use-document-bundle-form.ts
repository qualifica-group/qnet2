import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createDocumentBundle, updateDocumentBundle } from '@/features/document-bundles/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/document-bundles/document-bundle-form-payload'
import {
  buildCreateDocumentBundleSchema,
  buildUpdateDocumentBundleSchema,
  type CreateDocumentBundleFormValues,
  type UpdateDocumentBundleFormValues,
} from '@/features/document-bundles/document-bundle-schema'
import type { DocumentBundle, DocumentBundleFormMode } from '@/features/document-bundles/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'description', 'is_active'] as const

export type DocumentBundleFormValues = CreateDocumentBundleFormValues & UpdateDocumentBundleFormValues

interface UseDocumentBundleFormArgs {
  mode: DocumentBundleFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (documentBundle: DocumentBundle) => void
}

/**
 * Owns every non-render concern of `DocumentBundleForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useDocumentBundleForm({ mode, onSuccess }: UseDocumentBundleFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateDocumentBundleSchema(t) : buildCreateDocumentBundleSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<DocumentBundleFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.documentBundle.name,
        description: mode.documentBundle.description,
        is_active: mode.documentBundle.is_active,
      }
    }
    return {
      name: '',
      description: null,
      is_active: true,
    }
  }, [mode])

  const form = useForm<DocumentBundleFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: DocumentBundleFormValues) => {
    setServerError(null)
    const errorFields: Path<DocumentBundleFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updateDocumentBundle(
          mode.documentBundle.id,
          buildUpdatePayload(values, mode.documentBundle),
        )
        queryClient.setQueryData(['document-bundles', 'detail', mode.documentBundle.id], saved)
        toast.success(t('documentBundles.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createDocumentBundle(buildCreatePayload(values))
      toast.success(t('documentBundles.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('documentBundles.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
