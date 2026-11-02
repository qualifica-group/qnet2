import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createTag,
  tagDetailQueryKey,
  updateTag,
} from '@/features/tags/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/tags/tag-form-payload'
import {
  buildCreateTagSchema,
  buildUpdateTagSchema,
  type CreateTagFormValues,
  type UpdateTagFormValues,
} from '@/features/tags/tag-schema'
import type {
  TagDetail,
  TagFormMode,
} from '@/features/tags/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name'] as const

export type TagFormValues = CreateTagFormValues & UpdateTagFormValues

interface UseTagFormArgs {
  mode: TagFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (tag: TagDetail) => void
}

/**
 * Owns every non-render concern of the tag form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `TagForm`; edit backs the detail's in-place rows (diff
 * PATCH). The component stays UI-only; this hook is the orchestration point
 * (`onSubmit`).
 */
export function useTagForm({ mode, onSuccess }: UseTagFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; the record sections render.
  const customFields = useCustomFieldsForm(
    'tags',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.tag.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateTagSchema(t, customFields.schema)
        : buildCreateTagSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<TagFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.tag.name, custom_fields: customFields.defaultValues }
    }
    return { name: '', custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<TagFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: TagFormValues) => {
    setServerError(null)
    const errorFields: Path<TagFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<TagFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateTag(
          mode.tag.id,
          buildUpdatePayload(values, mode.tag),
        )
        queryClient.setQueryData(tagDetailQueryKey(mode.tag.id), saved)
        toast.success(t('tags.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createTag(buildCreatePayload(values))
      toast.success(t('tags.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(apiErrorMessage(error, t('tags.form.genericError'), i18n))
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
