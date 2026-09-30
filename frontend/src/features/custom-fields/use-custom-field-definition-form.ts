import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { applyTableConfigServerErrors } from '@/features/custom-fields/table-definition-errors'
import {
  createCustomFieldDefinition,
  updateCustomFieldDefinition,
} from '@/features/custom-fields/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/custom-fields/custom-field-definition-payload'
import {
  buildCreateCustomFieldDefinitionSchema,
  buildUpdateCustomFieldDefinitionSchema,
  type CustomFieldDefinitionFormValues,
} from '@/features/custom-fields/custom-field-definition-schema'
import {
  emptyFieldDefinitionValues,
  hydrateFieldDefinitionValues,
} from '@/features/custom-fields/field-definition-defaults'
import type {
  CustomFieldDefinitionDetail,
  CustomFieldDefinitionFormMode,
  CustomFieldValidation,
} from '@/features/custom-fields/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'entity_type',
  'key',
  'type',
  'label',
  'description',
  'help_text',
  'placeholder',
  'icon',
  'group',
  'tab',
  'sort_order',
  'config',
  'validation',
  'relation_target',
  'relation_target.entity_type',
  'relation_target.cardinality',
  'relation_target.for_select_resource',
  'is_indexed',
  'is_active',
  'options',
] as const

export type CustomFieldDefinitionFormFields = CustomFieldDefinitionFormValues

interface UseCustomFieldDefinitionFormArgs {
  mode: CustomFieldDefinitionFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
}

function emptyValidation() {
  return {
    required: false,
    unique: false,
    min: null,
    max: null,
    regex: '',
    email: false,
    url: false,
    exists: false,
    distinct: false,
  }
}

/** Hydrates the flat config/validation bag from a persisted definition, defaulting every field the type does not use. */
function hydrateValues(definition: CustomFieldDefinitionDetail): CustomFieldDefinitionFormValues {
  const validation = definition.validation as (CustomFieldValidation & Record<string, unknown>) | null

  return {
    ...hydrateFieldDefinitionValues(definition),
    entity_type: definition.entity_type,
    key: definition.key,
    label: definition.label,
    group: definition.group ?? '',
    tab: definition.tab ?? '',
    sort_order: definition.sort_order,
    is_indexed: definition.is_indexed,
    is_active: definition.is_active,
    validation: {
      ...emptyValidation(),
      required: Boolean(validation?.required),
      unique: Boolean(validation?.unique),
      min: validation?.min ?? null,
      max: validation?.max ?? null,
      regex: validation?.regex ?? '',
      email: Boolean(validation?.email),
      url: Boolean(validation?.url),
      exists: Boolean(validation?.exists),
      distinct: Boolean(validation?.distinct),
    },
  }
}

function defaultValuesFor(mode: CustomFieldDefinitionFormMode): CustomFieldDefinitionFormValues {
  if (mode.type === 'edit') {
    return hydrateValues(mode.definition)
  }
  return {
    ...emptyFieldDefinitionValues(),
    entity_type: '',
    key: '',
    label: '',
    group: '',
    tab: '',
    sort_order: 0,
    is_indexed: false,
    is_active: true,
    validation: emptyValidation(),
  }
}

/**
 * Owns every non-render concern of `CustomFieldDefinitionFormBody`: RHF/Zod
 * wiring, default values, server 422 mapping and the create/update submit
 * (mirrors `useAttributeForm`).
 */
export function useCustomFieldDefinitionForm({ mode, onSuccess }: UseCustomFieldDefinitionFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateCustomFieldDefinitionSchema(t) : buildCreateCustomFieldDefinitionSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<CustomFieldDefinitionFormValues>(() => defaultValuesFor(mode), [mode])

  const form = useForm<CustomFieldDefinitionFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: CustomFieldDefinitionFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateCustomFieldDefinition(
          mode.definition.id,
          buildUpdatePayload(values, mode.definition),
        )
        queryClient.setQueryData(['custom-fields', 'detail', mode.definition.id], saved)
        toast.success(t('customFields.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createCustomFieldDefinition(buildCreatePayload(values))
      toast.success(t('customFields.form.created'))
      onSuccess(created)
    } catch (error) {
      applyTableConfigServerErrors(error, form.setError)
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('customFields.form.genericError'))
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
