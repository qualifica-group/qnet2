import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import { createOperationalSite, updateOperationalSite } from '@/features/operational-sites/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/operational-sites/operational-site-form-payload'
import {
  buildCreateOperationalSiteSchema,
  buildUpdateOperationalSiteSchema,
  type CreateOperationalSiteFormValues,
  type UpdateOperationalSiteFormValues,
} from '@/features/operational-sites/operational-site-schema'
import type {
  OperationalSiteDetail,
  OperationalSiteFormMode,
} from '@/features/operational-sites/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'alias',
  'line1',
  'postal_code',
  'country_id',
  'state_id',
  'province_id',
  'city_id',
  'is_active',
] as const

export type OperationalSiteFormValues = CreateOperationalSiteFormValues &
  UpdateOperationalSiteFormValues

interface UseOperationalSiteFormArgs {
  mode: OperationalSiteFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (operationalSite: OperationalSiteDetail) => void
}

/**
 * Owns every non-render concern of `OperationalSiteForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useOperationalSiteForm({ mode, onSuccess }: UseOperationalSiteFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'operational-sites',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.operationalSite.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateOperationalSiteSchema(t, customFields.schema)
        : buildCreateOperationalSiteSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<OperationalSiteFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        alias: mode.operationalSite.alias ?? '',
        line1: mode.operationalSite.line1,
        postal_code: mode.operationalSite.postal_code ?? '',
        country_id: mode.operationalSite.country_id,
        state_id: mode.operationalSite.state_id,
        province_id: mode.operationalSite.province_id,
        city_id: mode.operationalSite.city_id,
        is_active: mode.operationalSite.is_active,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      alias: '',
      line1: '',
      postal_code: '',
      country_id: null,
      state_id: null,
      province_id: null,
      city_id: null,
      is_active: true,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  const form = useForm<OperationalSiteFormValues>({
    // `schema` is a create/edit union (and one of its coerced/nullable numeric
    // fields widens the schema's own inferred
    // input), so `zodResolver` can't infer/unify its generics — asserting the
    // resolver's type at this one boundary (not `any`) is the fix: at
    // runtime it still validates through the same schema.
    resolver: zodResolver(schema) as Resolver<OperationalSiteFormValues>,
    defaultValues,
  })

  const onSubmit = async (values: OperationalSiteFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateOperationalSite(
          mode.operationalSite.id,
          buildUpdatePayload(values, mode.operationalSite),
        )
        queryClient.setQueryData(['operational-sites', 'detail', mode.operationalSite.id], saved)
        toast.success(t('operationalSites.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createOperationalSite(buildCreatePayload(values))
      toast.success(t('operationalSites.form.created'))
      onSuccess(created)
    } catch (error) {
      const errorFields: Path<OperationalSiteFormValues>[] = [
        ...SERVER_ERROR_FIELDS,
        ...(customFields.errorPaths as Path<OperationalSiteFormValues>[]),
      ]
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('operationalSites.form.genericError'))
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
