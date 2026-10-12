import { useMemo, useState } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { customFieldDetailQueryKey, updateCustomFieldDefinition } from '@/features/custom-fields/api'
import { buildUpdatePayload } from '@/features/custom-fields/custom-field-definition-payload'
import {
  buildCustomFieldDefinitionSchema,
  type CustomFieldDefinitionFormValues,
} from '@/features/custom-fields/custom-field-definition-schema'
import { hydrateDefinitionValues } from '@/features/custom-fields/custom-field-definition-values'
import { CUSTOM_FIELD_SERVER_ERROR_FIELDS } from '@/features/custom-fields/use-custom-field-definition-form'
import { applyTableConfigServerErrors } from '@/features/custom-fields/table-definition-errors'
import type {
  CustomFieldDefinitionDetailWithPermissions,
  UpdateCustomFieldDefinitionPayload,
} from '@/features/custom-fields/types'

/** The grouped "Presentazione" row: description, help text, placeholder and icon are saved together. */
export const PRESENTATION_ROW = 'presentation'
/** The `table` columns editor row: its `config` is the table settings (the permission key stays `config`). */
export const TABLE_ROW = 'table'

type PayloadKey = keyof UpdateCustomFieldDefinitionPayload

/** What each row sends: its own fields alone, never the rest of the diff. */
const ROW_PAYLOAD_KEYS: Record<string, PayloadKey[]> = {
  label: ['label'],
  group: ['group'],
  sort_order: ['sort_order'],
  is_active: ['is_active'],
  is_indexed: ['is_indexed'],
  [PRESENTATION_ROW]: ['description', 'help_text', 'placeholder', 'icon'],
  config: ['config'],
  [TABLE_ROW]: ['config'],
  options: ['options'],
  relation_target: ['relation_target'],
  validation: ['validation'],
}

/** The form fields a row owns, to validate them alone (a legacy value elsewhere must not block this save). */
function rowFormFields(row: string): Path<CustomFieldDefinitionFormValues>[] {
  return row === PRESENTATION_ROW ? ['description', 'help_text', 'placeholder', 'icon'] : [row as Path<CustomFieldDefinitionFormValues>]
}

function pickKeys(payload: UpdateCustomFieldDefinitionPayload, keys: PayloadKey[]): UpdateCustomFieldDefinitionPayload {
  return Object.fromEntries(Object.entries(payload).filter(([key]) => keys.includes(key as PayloadKey)))
}

/**
 * Drives the custom field definition detail's in-place editing (spec 0231):
 * ONE row open at a time. Save validates that row alone, diffs the form
 * against the saved definition, and PATCHes only the row's own fields (the
 * backend update is a partial PATCH). The saved definition, with permissions,
 * replaces the cached detail. `entity_type`/`type`/`key` have no row here:
 * they are fixed at creation.
 */
export function useCustomFieldDefinitionInlineEdit(
  definition: CustomFieldDefinitionDetailWithPermissions,
  onChanged?: () => void,
) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const schema = useMemo(() => buildCustomFieldDefinitionSchema(t), [t])
  const values = useMemo(() => hydrateDefinitionValues(definition), [definition])
  // `values` re-syncs the rows on the saved definition; `keepDirtyValues` spares an open editor.
  const form = useForm<CustomFieldDefinitionFormValues>({
    resolver: zodResolver(schema),
    values,
    resetOptions: { keepDirtyValues: true },
  })

  const cancel = () => {
    form.reset(undefined, { keepDirtyValues: false })
    setServerError(null)
    setEditingField(null)
  }

  const start = (field: string) => {
    form.reset(undefined, { keepDirtyValues: false })
    setServerError(null)
    setEditingField(field)
  }

  const save = async () => {
    if (editingField === null) {
      return
    }
    // Step 1: validate the open row's fields alone
    if (!(await form.trigger(rowFormFields(editingField)))) {
      return
    }
    // Step 2: the row's own fields among those that differ from the saved definition
    const payload = pickKeys(buildUpdatePayload(form.getValues(), definition), ROW_PAYLOAD_KEYS[editingField])
    if (Object.keys(payload).length === 0) {
      cancel()
      return
    }
    // Step 3: PATCH and replace the cached detail
    setIsSaving(true)
    setServerError(null)
    try {
      const saved = await updateCustomFieldDefinition(definition.id, payload)
      queryClient.setQueryData(customFieldDetailQueryKey(definition.id), saved)
      toast.success(t('customFields.form.updated'))
      setEditingField(null)
      onChanged?.()
    } catch (error) {
      applyTableConfigServerErrors(error, form.setError)
      if (!applyServerValidationErrors(error, form.setError, CUSTOM_FIELD_SERVER_ERROR_FIELDS)) {
        setServerError(t('customFields.form.genericError'))
      }
    } finally {
      setIsSaving(false)
    }
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save: () => void save(),
    dismiss: cancel,
    isSaving,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline }
}
