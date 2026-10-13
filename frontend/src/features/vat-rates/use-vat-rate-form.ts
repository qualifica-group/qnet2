import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createVatRate, updateVatRate, vatRateDetailQueryKey } from '@/features/vat-rates/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/vat-rates/vat-rate-form-payload'
import {
  buildCreateVatRateSchema,
  buildUpdateVatRateSchema,
  type CreateVatRateFormValues,
  type UpdateVatRateFormValues,
} from '@/features/vat-rates/vat-rate-schema'
import type { VatRateDetail, VatRateFormMode } from '@/features/vat-rates/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'rate'] as const

export type VatRateFormValues = CreateVatRateFormValues & UpdateVatRateFormValues

interface UseVatRateFormArgs {
  mode: VatRateFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (vatRate: VatRateDetail) => void
}

/**
 * Owns every non-render concern of `VatRateForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. Create backs
 * `VatRateForm`; edit backs the detail's in-place rows (diff PATCH). The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useVatRateForm({ mode, onSuccess }: UseVatRateFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'vat-rates',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.vatRate.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateVatRateSchema(t, customFields.schema)
        : buildCreateVatRateSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<VatRateFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.vatRate.name,
        rate: mode.vatRate.rate,
        custom_fields: customFields.defaultValues,
      }
    }
    return { name: '', rate: null, custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<VatRateFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: VatRateFormValues) => {
    setServerError(null)
    const errorFields: Path<VatRateFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<VatRateFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateVatRate(mode.vatRate.id, buildUpdatePayload(values, mode.vatRate))
        queryClient.setQueryData(vatRateDetailQueryKey(mode.vatRate.id), saved)
        toast.success(t('vatRates.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createVatRate(buildCreatePayload(values))
      toast.success(t('vatRates.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('vatRates.form.genericError'))
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
