import type {
  AttributeDetail,
  CreateAttributePayload,
  UpdateAttributePayload,
} from '@/features/attributes/types'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { buildCustomFieldsCreate, buildCustomFieldsUpdate } from '@/features/custom-fields/custom-fields-payload'
import {
  buildFieldDefinitionConfig,
  buildFieldDefinitionOptions,
  buildFieldDefinitionRelationTarget,
} from '@/features/custom-fields/field-definition-payload'

/**
 * Builds the `attributes` write payloads (spec 0017, aligned to spec 0021).
 * The per-type `config`/`relation_target`/`options` projection is the shared
 * `field-definition-payload.ts` (mirrored 1:1 by
 * `custom-field-definition-payload.ts`) — an attribute has no `validation`
 * sub-form (the required flag lives on the category pivot instead).
 */

/** `undefined`/`null`/`''` all mean "not set" for a plain optional text field diff. */
function textChanged(next: string, original: string | null | undefined): string | undefined {
  return next !== (original ?? '') ? next : undefined
}

/** Builds the create payload: identity fields + the per-type config/relation_target/options projection. */
export function buildCreatePayload(values: AttributeFormValues): CreateAttributePayload {
  const customFields = buildCustomFieldsCreate(values.custom_fields)
  return {
    code: values.code,
    name: values.name,
    type: values.type,
    description: values.description || undefined,
    help_text: values.help_text || undefined,
    placeholder: values.placeholder || undefined,
    icon: values.icon || undefined,
    config: buildFieldDefinitionConfig(values),
    relation_target: buildFieldDefinitionRelationTarget(values),
    current_attribute_id: values.current_attribute_id ?? undefined,
    options: buildFieldDefinitionOptions(values),
    ...(Object.keys(customFields).length > 0 ? { custom_fields: customFields } : {}),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original attribute (spec 0017 AC-004). `options` is a full-replace: sent
 * whenever the ENUM option set differs from the original (by value/label/
 * color/icon/order/default), or whenever `type` itself changed.
 */
export function buildUpdatePayload(
  values: AttributeFormValues,
  original: AttributeDetail,
): UpdateAttributePayload {
  const payload: UpdateAttributePayload = {}

  if (values.code !== original.code) {
    payload.code = values.code
  }
  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.type !== original.type) {
    payload.type = values.type
  }

  const description = textChanged(values.description, original.description)
  if (description !== undefined) payload.description = description
  const helpText = textChanged(values.help_text, original.help_text)
  if (helpText !== undefined) payload.help_text = helpText
  const placeholder = textChanged(values.placeholder, original.placeholder)
  if (placeholder !== undefined) payload.placeholder = placeholder
  const icon = textChanged(values.icon, original.icon)
  if (icon !== undefined) payload.icon = icon

  const nextConfig = buildFieldDefinitionConfig(values)
  if (JSON.stringify(nextConfig ?? null) !== JSON.stringify(original.config ?? null)) {
    payload.config = nextConfig ?? null
  }

  const nextRelationTarget = buildFieldDefinitionRelationTarget(values)
  if (JSON.stringify(nextRelationTarget ?? null) !== JSON.stringify(original.relation_target ?? null)) {
    payload.relation_target = nextRelationTarget ?? null
  }

  if ((values.current_attribute_id ?? null) !== (original.current_attribute_id ?? null)) {
    payload.current_attribute_id = values.current_attribute_id ?? null
  }

  if (values.type === 'enum') {
    const nextOptions = buildFieldDefinitionOptions(values) ?? []
    const normalize = (
      list: { value: string; label: string; color?: string | null; icon?: string | null; is_default?: boolean }[],
    ) =>
      list.map((option) => ({
        value: option.value,
        label: option.label,
        color: option.color ?? undefined,
        icon: option.icon ?? undefined,
        is_default: option.is_default,
      }))
    const normalizedNext = normalize(nextOptions)
    const normalizedOriginal = normalize(
      [...original.options].sort((a, b) => a.sort_order - b.sort_order),
    )
    if (JSON.stringify(normalizedNext) !== JSON.stringify(normalizedOriginal)) {
      payload.options = nextOptions
    }
  }

  const customFields = buildCustomFieldsUpdate(values.custom_fields, original.custom_fields ?? {})
  if (Object.keys(customFields).length > 0) {
    payload.custom_fields = customFields
  }

  return payload
}
