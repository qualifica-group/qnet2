import type {
  CreateCustomFieldDefinitionPayload,
  CustomFieldDefinitionDetail,
  CustomFieldValidation,
  UpdateCustomFieldDefinitionPayload,
} from '@/features/custom-fields/types'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import {
  buildFieldDefinitionConfig,
  buildFieldDefinitionOptions,
  buildFieldDefinitionRelationTarget,
} from '@/features/custom-fields/field-definition-payload'

/**
 * Builds the `custom-fields` admin write payloads (spec 0021 AC-025). The
 * per-type `config`/`relation_target`/`options` projection is the shared
 * `field-definition-payload.ts` (mirrored 1:1 by `attribute-form-payload.ts`);
 * this module additionally owns `validation`, the one sub-form a custom field
 * definition carries that an attribute does not (spec 0017: the attribute's
 * `required` lives on the category pivot instead).
 */

/** Drops `undefined`/`null`/`''` entries; returns `undefined` when nothing is left (never send an empty validation object). */
function omitEmpty<T extends Record<string, unknown>>(input: T): Partial<T> | undefined {
  const entries = Object.entries(input).filter(
    ([, value]) => value !== undefined && value !== null && value !== '',
  )
  return entries.length > 0 ? (Object.fromEntries(entries) as Partial<T>) : undefined
}

function buildValidation(values: CustomFieldDefinitionFormValues): CustomFieldValidation | undefined {
  const validation = values.validation
  return omitEmpty({
    required: validation.required || undefined,
    unique: validation.unique || undefined,
    min: validation.min ?? undefined,
    max: validation.max ?? undefined,
    regex: validation.regex,
    email: validation.email || undefined,
    url: validation.url || undefined,
    exists: validation.exists || undefined,
    distinct: validation.distinct || undefined,
  })
}

/** A `table` value cannot be promoted to an index (server rejects `is_indexed=true`), so it is always sent as false. */
function isIndexedFor(values: CustomFieldDefinitionFormValues): boolean {
  return values.type === 'table' ? false : values.is_indexed
}

/** Builds the create payload: identity fields + the per-type config/validation/relation_target/options projection. */
export function buildCreatePayload(
  values: CustomFieldDefinitionFormValues,
): CreateCustomFieldDefinitionPayload {
  return {
    entity_type: values.entity_type,
    key: values.key,
    type: values.type,
    label: values.label,
    description: values.description || undefined,
    help_text: values.help_text || undefined,
    placeholder: values.placeholder || undefined,
    icon: values.icon || undefined,
    group: values.group || undefined,
    tab: values.tab || undefined,
    sort_order: values.sort_order,
    config: buildFieldDefinitionConfig(values),
    validation: buildValidation(values),
    relation_target: buildFieldDefinitionRelationTarget(values),
    is_indexed: isIndexedFor(values),
    is_active: values.is_active,
    options: buildFieldDefinitionOptions(values),
  }
}

/** `undefined`/`null`/`''` all mean "not set" for a plain optional text field diff. */
function textChanged(next: string, original: string | null | undefined): string | undefined {
  return next !== (original ?? '') ? next : undefined
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original definition (spec AC-019: `entity_type`/`type`/`key` are immutable
 * once the field has values — the server enforces that; sending them
 * unchanged, as the read-only edit fields do, is always safe).
 */
export function buildUpdatePayload(
  values: CustomFieldDefinitionFormValues,
  original: CustomFieldDefinitionDetail,
): UpdateCustomFieldDefinitionPayload {
  const payload: UpdateCustomFieldDefinitionPayload = {}

  if (values.entity_type !== original.entity_type) payload.entity_type = values.entity_type
  if (values.key !== original.key) payload.key = values.key
  if (values.type !== original.type) payload.type = values.type
  if (values.label !== original.label) payload.label = values.label

  const description = textChanged(values.description, original.description)
  if (description !== undefined) payload.description = description
  const helpText = textChanged(values.help_text, original.help_text)
  if (helpText !== undefined) payload.help_text = helpText
  const placeholder = textChanged(values.placeholder, original.placeholder)
  if (placeholder !== undefined) payload.placeholder = placeholder
  const icon = textChanged(values.icon, original.icon)
  if (icon !== undefined) payload.icon = icon
  const group = textChanged(values.group, original.group)
  if (group !== undefined) payload.group = group
  const tab = textChanged(values.tab, original.tab)
  if (tab !== undefined) payload.tab = tab

  if (values.sort_order !== original.sort_order) payload.sort_order = values.sort_order
  if (isIndexedFor(values) !== original.is_indexed) payload.is_indexed = isIndexedFor(values)
  if (values.is_active !== original.is_active) payload.is_active = values.is_active

  const nextConfig = buildFieldDefinitionConfig(values)
  if (JSON.stringify(nextConfig ?? null) !== JSON.stringify(original.config ?? null)) {
    payload.config = nextConfig ?? null
  }

  const nextValidation = buildValidation(values)
  if (JSON.stringify(nextValidation ?? null) !== JSON.stringify(original.validation ?? null)) {
    payload.validation = nextValidation ?? null
  }

  const nextRelationTarget = buildFieldDefinitionRelationTarget(values)
  if (JSON.stringify(nextRelationTarget ?? null) !== JSON.stringify(original.relation_target ?? null)) {
    payload.relation_target = nextRelationTarget ?? null
  }

  if (values.type === 'enum') {
    const nextOptions = buildFieldDefinitionOptions(values) ?? []
    const normalize = (list: { value: string; label: string; color?: string | null; icon?: string | null; is_default?: boolean }[]) =>
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

  return payload
}
