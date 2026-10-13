import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createSource,
  sourceDetailQueryKey,
  updateSource,
} from '@/features/sources/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/sources/source-form-payload'
import {
  buildCreateSourceSchema,
  buildUpdateSourceSchema,
  type CreateSourceFormValues,
  type UpdateSourceFormValues,
} from '@/features/sources/source-schema'
import type {
  SourceDetail,
  SourceFormMode,
} from '@/features/sources/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name'] as const

export type SourceFormValues = CreateSourceFormValues & UpdateSourceFormValues

interface UseSourceFormArgs {
  mode: SourceFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (source: SourceDetail) => void
}

/**
 * Owns every non-render concern of the source form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `SourceForm`; edit backs the detail's in-place rows (diff
 * PATCH). The component stays UI-only; this hook is the orchestration point
 * (`onSubmit`).
 */
export function useSourceForm({ mode, onSuccess }: UseSourceFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; the record sections render.
  const customFields = useCustomFieldsForm(
    'sources',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.source.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateSourceSchema(t, customFields.schema)
        : buildCreateSourceSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<SourceFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.source.name, custom_fields: customFields.defaultValues }
    }
    return { name: '', custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<SourceFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: SourceFormValues) => {
    setServerError(null)
    const errorFields: Path<SourceFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<SourceFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateSource(
          mode.source.id,
          buildUpdatePayload(values, mode.source),
        )
        queryClient.setQueryData(sourceDetailQueryKey(mode.source.id), saved)
        toast.success(t('sources.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createSource(buildCreatePayload(values))
      toast.success(t('sources.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('sources.form.genericError'))
      }
    }
  }

  return {
    form,
    serverError,
    clearServerError: () => setServerError(null),
    onSubmit,
  }
}
