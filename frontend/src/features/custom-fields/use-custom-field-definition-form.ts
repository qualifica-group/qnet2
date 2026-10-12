import { useMemo, useState } from 'react'
import { useForm, type Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { applyTableConfigServerErrors } from '@/features/custom-fields/table-definition-errors'
import { createCustomFieldDefinition } from '@/features/custom-fields/api'
import { buildCreatePayload } from '@/features/custom-fields/custom-field-definition-payload'
import {
  buildCustomFieldDefinitionSchema,
  type CustomFieldDefinitionFormValues,
} from '@/features/custom-fields/custom-field-definition-schema'
import { emptyDefinitionValues } from '@/features/custom-fields/custom-field-definition-values'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/** Server-side field names mapped onto the form for 422 handling. */
export const CUSTOM_FIELD_SERVER_ERROR_FIELDS: Path<CustomFieldDefinitionFormValues>[] = [
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
]

interface UseCustomFieldDefinitionFormArgs {
  /** Called after a successful create so the caller can close + refresh. */
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
}

/**
 * Owns every non-render concern of the create form (spec 0231: the detail
 * edits in place, so this hook is create-only): RHF/Zod wiring, blank
 * defaults, server 422 mapping and the submit (mirrors `useAttributeForm`).
 */
export function useCustomFieldDefinitionForm({ onSuccess }: UseCustomFieldDefinitionFormArgs) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildCustomFieldDefinitionSchema(t), [t])
  const defaultValues = useMemo<CustomFieldDefinitionFormValues>(() => emptyDefinitionValues(), [])

  const form = useForm<CustomFieldDefinitionFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: CustomFieldDefinitionFormValues) => {
    setServerError(null)
    try {
      const created = await createCustomFieldDefinition(buildCreatePayload(values))
      toast.success(t('customFields.form.created'))
      onSuccess(created)
    } catch (error) {
      applyTableConfigServerErrors(error, form.setError)
      if (!applyServerValidationErrors(error, form.setError, CUSTOM_FIELD_SERVER_ERROR_FIELDS)) {
        setServerError(t('customFields.form.genericError'))
      }
    }
  }

  return { form, serverError, onSubmit }
}
