import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  createReferentType,
  referentTypeDetailQueryKey,
  updateReferentType,
} from '@/features/referent-types/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/referent-types/referent-type-form-payload'
import {
  buildCreateReferentTypeSchema,
  buildUpdateReferentTypeSchema,
  type CreateReferentTypeFormValues,
  type UpdateReferentTypeFormValues,
} from '@/features/referent-types/referent-type-schema'
import type {
  ReferentTypeDetail,
  ReferentTypeFormMode,
} from '@/features/referent-types/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name'] as const

export type ReferentTypeFormValues = CreateReferentTypeFormValues & UpdateReferentTypeFormValues

interface UseReferentTypeFormArgs {
  mode: ReferentTypeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (referentType: ReferentTypeDetail) => void
}

/**
 * Owns every non-render concern of the referent-type form: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. Create
 * backs `ReferentTypeForm`; edit backs the detail's in-place rows (diff
 * PATCH). The component stays UI-only; this hook is the orchestration point
 * (`onSubmit`).
 */
export function useReferentTypeForm({ mode, onSuccess }: UseReferentTypeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; the record sections render.
  const customFields = useCustomFieldsForm(
    'referent-types',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.referentType.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateReferentTypeSchema(t, customFields.schema)
        : buildCreateReferentTypeSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ReferentTypeFormValues>(() => {
    if (mode.type === 'edit') {
      return { name: mode.referentType.name, custom_fields: customFields.defaultValues }
    }
    return { name: '', custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<ReferentTypeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: ReferentTypeFormValues) => {
    setServerError(null)
    const errorFields: Path<ReferentTypeFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<ReferentTypeFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateReferentType(
          mode.referentType.id,
          buildUpdatePayload(values, mode.referentType),
        )
        queryClient.setQueryData(referentTypeDetailQueryKey(mode.referentType.id), saved)
        toast.success(t('referentTypes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createReferentType(buildCreatePayload(values))
      toast.success(t('referentTypes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('referentTypes.form.genericError'))
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
