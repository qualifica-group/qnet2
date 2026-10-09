import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { PersonalDataFieldPermission } from '@/features/personal-data/types'
import {
  buildCreateRegistrySchema,
  buildUpdateRegistrySchema,
  type CreateRegistryFormValues,
  type UpdateRegistryFormValues,
} from '@/features/registries/registry-schema'
import type { RegistryDetail, RegistryFormMode } from '@/features/registries/types'

export type RegistryFormValues = CreateRegistryFormValues & UpdateRegistryFormValues

/** The persisted registry as the edit form holds it (the in-place detail's clean state). */
export function editDefaults(
  registry: RegistryDetail,
  customFields: Record<string, CustomFieldValue>,
): RegistryFormValues {
  return {
    source_id: registry.source_id,
    sector_ids: registry.sector_ids,
    referent_ids: registry.referent_ids,
    manager_slots: registry.manager_slots,
    supervisor_id: registry.supervisor_id,
    commercial_id: registry.commercial_id,
    reporter_id: registry.reporter_id,
    vat_group: registry.vat_group ?? '',
    is_supplier: registry.is_supplier,
    is_qualified_supplier: registry.is_qualified_supplier,
    agreement_status: registry.agreement_status,
    agreement_notes: registry.agreement_notes ?? '',
    size_class: registry.size_class,
    employee_count: registry.employee_count,
    general_notes: registry.general_notes ?? '',
    custom_fields: customFields,
  }
}

function createDefaults(
  customFields: Record<string, CustomFieldValue>,
  isSupplierPreset: boolean,
): RegistryFormValues {
  return {
    source_id: null,
    sector_ids: [],
    referent_ids: [],
    manager_slots: [],
    supervisor_id: null,
    commercial_id: null,
    reporter_id: null,
    vat_group: '',
    is_supplier: isSupplierPreset,
    is_qualified_supplier: false,
    agreement_status: null,
    agreement_notes: '',
    size_class: null,
    employee_count: null,
    general_notes: '',
    custom_fields: customFields,
  }
}

/**
 * Adapts the resolved authorization metadata to the personal-data domain's
 * own gating shape (spec 0008 D3): the shared PersonalDataCardForm/
 * ContactsManager/AddressesManager stay decoupled from `@/features/authorization`.
 */
export function usePersonalDataFieldPermission(): (key: string) => PersonalDataFieldPermission {
  const { field: fieldPermission } = useResourcePermissions()
  return (key: string) => {
    const permission = fieldPermission(key)
    return {
      visible: permission.visible,
      editable: permission.editable,
      required: permission.required,
      disabled: permission.disabled,
      readonly: permission.readonly,
    }
  }
}

interface UseRegistryFormArgs {
  mode: RegistryFormMode
  /** Create mode only: the new anagrafica starts flagged as a supplier. */
  isSupplierPreset?: boolean
}

/**
 * Owns the RHF/Zod wiring of the anagrafica create form and of the in-place
 * detail (`useRegistryInlineEdit`, edit mode), custom fields included (spec
 * 0021: schema, defaults and 422 paths from `useCustomFieldsForm`). The
 * anagraphic card is NOT an RHF field: it is a buffered draft each caller
 * owns. Submission lives in `useRegistryFormSubmit`.
 *
 * Edit mode IS the anagrafica detail (spec 0200): the persisted record can
 * change under the form, so `values` re-syncs it while `keepDirtyValues`
 * preserves the row still being edited. Every explicit `reset` that means to
 * DROP an edit passes `keepDirtyValues: false`.
 */
export function useRegistryForm({ mode, isSupplierPreset = false }: UseRegistryFormArgs) {
  const { t } = useTranslation()
  const isEdit = mode.type === 'edit'
  const customFields = useCustomFieldsForm(
    'registries',
    mode.type === 'edit' ? { type: 'edit', customFields: mode.registry.custom_fields } : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateRegistrySchema(t, customFields.schema)
        : buildCreateRegistrySchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<RegistryFormValues>(
    () =>
      mode.type === 'edit'
        ? editDefaults(mode.registry, customFields.defaultValues)
        : createDefaults(customFields.defaultValues, isSupplierPreset),
    [mode, customFields.defaultValues, isSupplierPreset],
  )

  const form = useForm<RegistryFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
    values: isEdit ? defaultValues : undefined,
    resetOptions: isEdit ? { keepDirtyValues: true } : undefined,
  })

  return { form, customFieldErrorPaths: customFields.errorPaths }
}
