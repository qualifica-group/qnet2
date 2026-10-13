import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import {
  buildCreateCompanySiteSchema,
  buildUpdateCompanySiteSchema,
  type CreateCompanySiteFormValues,
  type UpdateCompanySiteFormValues,
} from '@/features/company-sites/company-site-schema'
import type { CompanySiteFormMode } from '@/features/company-sites/types'

export type CompanySiteFormValues = CreateCompanySiteFormValues & UpdateCompanySiteFormValues

interface UseCompanySiteFormArgs {
  mode: CompanySiteFormMode
}

/**
 * Owns the RHF/Zod wiring of the company-site create form and of the in-place
 * detail (edit mode), custom fields included (spec 0021: schema, defaults and
 * 422 paths from `useCustomFieldsForm`). The anagraphic card and the banks are
 * NOT RHF fields: they are buffered drafts each caller owns. Submission lives
 * in `useCompanySiteFormSubmit`.
 *
 * Edit mode IS the site detail: the persisted record can change under the
 * form, so `values` re-syncs it while `keepDirtyValues` preserves the row
 * still being edited. Every explicit `reset` that means to DROP an edit
 * passes `keepDirtyValues: false`.
 */
export function useCompanySiteForm({ mode }: UseCompanySiteFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'
  const customFields = useCustomFieldsForm(
    'company-sites',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.companySite.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateCompanySiteSchema(t, customFields.schema)
        : buildCreateCompanySiteSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<CompanySiteFormValues>(
    () =>
      mode.type === 'edit'
        ? {
            name: mode.companySite.name,
            notes: mode.companySite.notes ?? '',
            company_id: mode.companySite.company?.id ?? null,
            custom_fields: customFields.defaultValues,
          }
        : { name: '', notes: '', company_id: null, custom_fields: customFields.defaultValues },
    [mode, customFields.defaultValues],
  )

  const form = useForm<CompanySiteFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
