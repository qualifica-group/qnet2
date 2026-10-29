import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { companyDetailQueryKey, createCompany, updateCompany } from '@/features/companies/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/companies/company-form-payload'
import {
  buildCreateCompanySchema,
  buildUpdateCompanySchema,
  type CreateCompanyFormValues,
  type UpdateCompanyFormValues,
} from '@/features/companies/company-schema'
import type { CompanyDetail, CompanyFormMode } from '@/features/companies/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'denomination',
  'vat_number',
  'address.line1',
  'address.line2',
  'address.postal_code',
  'address.country_id',
  'address.state_id',
  'address.province_id',
  'address.city_id',
] as const

export type CompanyFormValues = CreateCompanyFormValues & UpdateCompanyFormValues

/** The blank address block a new company (or one without an address) starts from. */
const EMPTY_ADDRESS: CompanyFormValues['address'] = {
  line1: '',
  line2: '',
  postal_code: '',
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

interface UseCompanyFormArgs {
  mode: CompanyFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (company: CompanyDetail) => void
}

/**
 * Owns every non-render concern of the company form: RHF/Zod wiring, default
 * values (including the single embedded address block and any custom
 * fields), server 422 mapping and the create/update submit. Create backs
 * `CompanyForm`; edit backs the detail's in-place rows (diff PATCH). The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 *
 * Custom fields (spec 0021, pilot rollout): reads `/meta/companies` via the
 * SAME `useResourceMeta` query the mounted `<CustomFieldsSection>` reads from
 * (deduped by TanStack Query, one request) purely to build the dynamic Zod
 * schema and the server-error paths — the section itself owns rendering.
 */
export function useCompanyForm({ mode, onSuccess }: UseCompanyFormArgs) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'companies',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.company.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCompanySchema(t, customFields.schema)
        : buildCreateCompanySchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<CompanyFormValues>(() => {
    if (mode.type === 'edit') {
      const address = mode.company.address
      return {
        denomination: mode.company.denomination,
        vat_number: mode.company.vat_number ?? '',
        address: address
          ? {
              line1: address.line1,
              line2: address.line2 ?? '',
              postal_code: address.postal_code ?? '',
              country_id: address.country_id,
              state_id: address.state_id,
              province_id: address.province_id,
              city_id: address.city_id,
            }
          : EMPTY_ADDRESS,
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      denomination: '',
      vat_number: '',
      address: EMPTY_ADDRESS,
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues])

  // Edit backs the detail's in-place rows: a saved record re-syncs the form,
  // `keepDirtyValues` spares a row still open over a background refetch.
  const form = useForm<CompanyFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: { keepDirtyValues: true },
  })

  const onSubmit = async (values: CompanyFormValues) => {
    setServerError(null)
    const errorFields: Path<CompanyFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<CompanyFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateCompany(
          mode.company.id,
          buildUpdatePayload(values, mode.company),
        )
        queryClient.setQueryData(companyDetailQueryKey(mode.company.id), saved)
        toast.success(t('companies.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCompany(buildCreatePayload(values))
      toast.success(t('companies.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(apiErrorMessage(error, t('companies.form.genericError'), i18n))
      }
    }
  }

  return { form, serverError, clearServerError: () => setServerError(null), onSubmit }
}
