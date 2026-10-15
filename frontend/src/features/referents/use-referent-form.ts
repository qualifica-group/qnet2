import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import {
  buildCreateReferentSchema,
  buildUpdateReferentSchema,
  type CreateReferentFormValues,
  type UpdateReferentFormValues,
} from '@/features/referents/referent-schema'
import type { ReferentDetail, ReferentFormMode } from '@/features/referents/types'

export type ReferentFormValues = CreateReferentFormValues & UpdateReferentFormValues

/** Form pre-selects 'internal' (spec 0016, user-approved decision). */
const DEFAULT_CONTACT_SCOPE = 'internal' as const

/** The persisted referent as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(
  referent: ReferentDetail,
  customFields: Record<string, CustomFieldValue>,
): ReferentFormValues {
  return {
    referent_type_id: referent.referent_type_id,
    // Omitted entirely when not visible for field permission (spec 0090
    // AC-005): falls back to unlinked, same as a fresh create.
    user_id: referent.user_id ?? null,
    supplier_registry_id: referent.supplier_registry_id ?? null,
    contact_scope: referent.contact_scope,
    notes: referent.notes ?? '',
    custom_fields: customFields,
  }
}

function createDefaults(customFields: Record<string, CustomFieldValue>): ReferentFormValues {
  return {
    referent_type_id: null,
    user_id: null,
    supplier_registry_id: null,
    contact_scope: DEFAULT_CONTACT_SCOPE,
    notes: '',
    custom_fields: customFields,
  }
}

interface UseReferentFormArgs {
  mode: ReferentFormMode
}

/**
 * Owns the RHF/Zod wiring of the referente create form and of the in-place
 * detail (`useReferentInlineEdit`, edit mode), custom fields included (spec
 * 0021: schema, defaults and 422 paths from `useCustomFieldsForm`). The
 * anagraphic card is NOT an RHF field: it is a buffered draft each caller
 * owns. Submission lives in `useReferentFormSubmit`.
 *
 * Edit mode IS the referente detail (spec 0216): the persisted record can
 * change under the form, so `values` re-syncs it while `keepDirtyValues`
 * preserves the row still being edited. Every explicit `reset` that means to
 * DROP an edit passes `keepDirtyValues: false`.
 */
export function useReferentForm({ mode }: UseReferentFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'
  const customFields = useCustomFieldsForm(
    'referents',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.referent.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateReferentSchema(t, customFields.schema)
        : buildCreateReferentSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<ReferentFormValues>(
    () =>
      mode.type === 'edit'
        ? editDefaults(mode.referent, customFields.defaultValues)
        : createDefaults(customFields.defaultValues),
    [mode, customFields.defaultValues],
  )

  const form = useForm<ReferentFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
