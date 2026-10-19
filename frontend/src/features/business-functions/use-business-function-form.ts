import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  businessFunctionDetailQueryKey,
  createBusinessFunction,
  updateBusinessFunction,
} from '@/features/business-functions/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/business-functions/business-function-form-payload'
import {
  buildCreateBusinessFunctionSchema,
  buildUpdateBusinessFunctionSchema,
  type CreateBusinessFunctionFormValues,
  type UpdateBusinessFunctionFormValues,
} from '@/features/business-functions/business-function-schema'
import type {
  BusinessFunctionDetail,
  BusinessFunctionFormMode,
} from '@/features/business-functions/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'type',
  'manager_id',
  'users',
  'parent_id',
  'is_active',
  'operational_sites',
  'company_ids',
] as const

export type BusinessFunctionFormValues = CreateBusinessFunctionFormValues &
  UpdateBusinessFunctionFormValues

interface UseBusinessFunctionFormArgs {
  mode: BusinessFunctionFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
}

/**
 * Owns every non-render concern of the business-function form: RHF/Zod
 * wiring, default values, server 422 mapping and the create/update submit.
 * Create backs `BusinessFunctionForm`; edit backs the detail's in-place rows
 * (diff PATCH). The component stays UI-only; this hook is the orchestration
 * point (`onSubmit`).
 */
export function useBusinessFunctionForm({ mode, onSuccess }: UseBusinessFunctionFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; the record sections render.
  const customFields = useCustomFieldsForm(
    'business-functions',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.businessFunction.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateBusinessFunctionSchema(t, customFields.schema)
        : buildCreateBusinessFunctionSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<BusinessFunctionFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.businessFunction.name,
        type: mode.businessFunction.type,
        manager_id: mode.businessFunction.manager_id,
        users: mode.businessFunction.user_ids,
        parent_id: mode.businessFunction.parent_id,
        is_active: mode.businessFunction.is_active,
        operational_sites: mode.businessFunction.operational_site_ids,
        company_ids: mode.businessFunction.company_ids,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      name: '',
      type: null,
      manager_id: null,
      users: [],
      parent_id: null,
      is_active: true,
      operational_sites: [],
      company_ids: [],
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<BusinessFunctionFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: BusinessFunctionFormValues) => {
    setServerError(null)
    const errorFields: Path<BusinessFunctionFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<BusinessFunctionFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateBusinessFunction(
          mode.businessFunction.id,
          buildUpdatePayload(values, mode.businessFunction),
        )
        queryClient.setQueryData(businessFunctionDetailQueryKey(mode.businessFunction.id), saved)
        toast.success(t('businessFunctions.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createBusinessFunction(buildCreatePayload(values))
      toast.success(t('businessFunctions.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('businessFunctions.form.genericError'))
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
