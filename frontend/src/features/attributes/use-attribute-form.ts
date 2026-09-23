import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createAttribute, updateAttribute } from '@/features/attributes/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/attributes/attribute-form-payload'
import {
  buildCreateAttributeSchema,
  buildUpdateAttributeSchema,
  CODE_MAX_LENGTH,
  type CreateAttributeFormValues,
} from '@/features/attributes/attribute-schema'
import type { AttributeDetail, AttributeFormMode } from '@/features/attributes/types'
import {
  emptyFieldDefinitionValues,
  hydrateFieldDefinitionValues,
} from '@/features/custom-fields/field-definition-defaults'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'code',
  'name',
  'type',
  'description',
  'help_text',
  'placeholder',
  'icon',
  'config',
  'relation_target',
  'relation_target.entity_type',
  'relation_target.cardinality',
  'relation_target.for_select_resource',
  'options',
] as const

/** Appended to the source `code` on duplicate: `code` is unique and snake_case, so the i18n copy suffix cannot be reused. */
const DUPLICATE_CODE_SUFFIX = '_copy'

export type AttributeFormValues = CreateAttributeFormValues

/** Suffixes the source `code`, trimming the source so the result stays within the backend limit. */
function duplicateCode(code: string): string {
  return code.slice(0, CODE_MAX_LENGTH - DUPLICATE_CODE_SUFFIX.length) + DUPLICATE_CODE_SUFFIX
}

interface UseAttributeFormArgs {
  mode: AttributeFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (attribute: AttributeDetail) => void
}

/**
 * Owns every non-render concern of `AttributeForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useAttributeForm({ mode, onSuccess }: UseAttributeFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  // Duplicate seeds its values from the source, exactly like edit, even
  // though it submits through the create payload builder.
  const customFields = useCustomFieldsForm(
    'attributes',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.attribute.custom_fields }
      : mode.type === 'duplicate'
        ? { type: 'edit', customFields: mode.source.custom_fields }
        : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateAttributeSchema(t, customFields.schema)
        : buildCreateAttributeSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<AttributeFormValues>(() => {
    if (mode.type === 'edit') {
      const { attribute } = mode
      return {
        code: attribute.code,
        name: attribute.name,
        ...hydrateFieldDefinitionValues(attribute),
        custom_fields: customFields.defaultValues,
      }
    }
    if (mode.type === 'duplicate') {
      const { source } = mode
      return {
        code: duplicateCode(source.code),
        name: source.name + t('common.copySuffix'),
        ...hydrateFieldDefinitionValues(source),
        custom_fields: customFields.defaultValues,
      }
    }
    return {
      code: '',
      name: '',
      ...emptyFieldDefinitionValues(),
      custom_fields: customFields.defaultValues,
    }
  }, [mode, customFields.defaultValues, t])

  const form = useForm<AttributeFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: AttributeFormValues) => {
    setServerError(null)
    const errorFields: Path<AttributeFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<AttributeFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateAttribute(
          mode.attribute.id,
          buildUpdatePayload(values, mode.attribute),
        )
        queryClient.setQueryData(['attributes', 'detail', mode.attribute.id], saved)
        toast.success(t('attributes.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createAttribute(buildCreatePayload(values))
      toast.success(t('attributes.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('attributes.form.genericError'))
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
