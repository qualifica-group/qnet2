import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { cloneDocumentLayout } from '@/features/document-layouts/api'
import {
  buildCloneDocumentLayoutSchema,
  CODE_MAX_LENGTH,
  NAME_MAX_LENGTH,
  type CloneDocumentLayoutFormValues,
} from '@/features/document-layouts/document-layout-schema'
import {
  DOCUMENT_LAYOUTS_BASE_PATH,
  type DocumentLayoutCloneSource,
} from '@/features/document-layouts/types'
import { apiErrorMessage } from '@/lib/forbidden-message'

/** Appended to the source code: the copy's suggested code stays a valid snake_case identifier. */
const CLONE_CODE_SUFFIX = '_copy'

/**
 * Owns the clone dialog's form (spec 0339): name/code pre-filled from the
 * source ("{name} (copia)", "{code}_copy", clipped to the column limits),
 * the POST, server 422 mapping on name/code, and — on success — the
 * navigation to the new layout's page, where the user keeps editing it.
 */
export function useDocumentLayoutCloneForm(source: DocumentLayoutCloneSource) {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildCloneDocumentLayoutSchema(t), [t])

  const form = useForm<CloneDocumentLayoutFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: `${source.name} ${t('documentLayouts.clone.nameSuffix')}`.slice(0, NAME_MAX_LENGTH),
      code: `${source.code}${CLONE_CODE_SUFFIX}`.slice(0, CODE_MAX_LENGTH),
    },
  })

  const onSubmit = async (values: CloneDocumentLayoutFormValues) => {
    setServerError(null)
    try {
      const clone = await cloneDocumentLayout(source.id, values)
      toast.success(t('documentLayouts.clone.success'))
      void navigate(`${DOCUMENT_LAYOUTS_BASE_PATH}/${clone.id}`)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['name', 'code'])) {
        setServerError(apiErrorMessage(error, t('documentLayouts.clone.error'), i18n))
      }
    }
  }

  return { form, onSubmit, serverError }
}
