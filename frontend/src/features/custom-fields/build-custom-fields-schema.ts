import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ResourcePermissions } from '@/features/authorization/types'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import { getTableConfig, isRowSelected, toColumnDescriptor } from '@/features/custom-fields/table-field-model'
import {
  rawKey,
  type CustomFieldDescriptor,
  type CustomFieldValue,
  type TableFieldConfig,
} from '@/features/custom-fields/types'

/**
 * Dynamic Zod schema for a resource's custom fields (spec 0021 AC-023),
 * mirroring the product-schema factory pattern: built with `t` so validation
 * messages are localized, `required` resolved the same way the backend does
 * (`permission.required OR descriptor.mandatory`), remaining rules read off
 * `descriptor.config`. Client-side validation MIRRORS the server (backend.md
 * §8 `FieldTypeHandler::validationRules`), it does not replace it — enum
 * membership and relation existence are still authoritative server-side.
 */

/** Text-family constraints (min/max length, regex) applied only to a non-empty value. */
function refineTextConstraints(
  value: string | null,
  descriptor: CustomFieldDescriptor,
  t: TFunction,
  ctx: z.RefinementCtx,
): void {
  if (value === null || value === '') {
    return
  }
  const config = descriptor.config
  if (config?.minLength !== undefined && value.length < config.minLength) {
    ctx.addIssue({ code: 'custom', message: t('customFields.validation.minLength', { min: config.minLength }) })
  }
  if (config?.maxLength !== undefined && value.length > config.maxLength) {
    ctx.addIssue({ code: 'custom', message: t('customFields.validation.maxLength', { max: config.maxLength }) })
  }
  if (config?.regex) {
    const pattern = safeRegExp(config.regex)
    if (pattern && !pattern.test(value)) {
      ctx.addIssue({ code: 'custom', message: t('customFields.validation.pattern') })
    }
  }
}

/** A malformed admin-authored regex must not crash the form — skip the rule instead. */
function safeRegExp(source: string): RegExp | null {
  try {
    return new RegExp(source)
  } catch {
    return null
  }
}

function buildTextSchema(descriptor: CustomFieldDescriptor, t: TFunction) {
  return z
    .string()
    .nullable()
    .superRefine((value, ctx) => refineTextConstraints(value, descriptor, t, ctx))
}

function buildNumberSchema(descriptor: CustomFieldDescriptor, t: TFunction) {
  const config = descriptor.config
  return z
    .number()
    .nullable()
    .superRefine((value, ctx) => {
      if (value === null) {
        return
      }
      if (config?.min !== undefined && value < config.min) {
        ctx.addIssue({ code: 'custom', message: t('customFields.validation.min', { min: config.min }) })
      }
      if (config?.max !== undefined && value > config.max) {
        ctx.addIssue({ code: 'custom', message: t('customFields.validation.max', { max: config.max }) })
      }
    })
}

function buildEnumSchema(descriptor: CustomFieldDescriptor, t: TFunction) {
  const optionValues = new Set((descriptor.options ?? []).map((option) => option.value))
  const invalidMessage = t('customFields.validation.enumInvalid')

  if (descriptor.config?.display === 'multiselect') {
    // `null` = not set, as the server returns a multi value nobody filled yet.
    return z.array(z.string()).nullable().superRefine((values, ctx) => {
      values?.forEach((value, index) => {
        if (!optionValues.has(value)) {
          ctx.addIssue({ code: 'custom', path: [index], message: invalidMessage })
        }
      })
    })
  }

  return z
    .string()
    .nullable()
    .superRefine((value, ctx) => {
      if (value !== null && !optionValues.has(value)) {
        ctx.addIssue({ code: 'custom', message: invalidMessage })
      }
    })
}

function buildRelationSchema(descriptor: CustomFieldDescriptor) {
  // `null` = not set on both arities, as the server returns an unfilled value.
  return descriptor.relation?.cardinality === 'many' ? z.array(z.number()).nullable() : z.number().nullable()
}

/**
 * `table`: `{ rows }` (nullable). Cells reuse the per-type builders through a
 * synthesized column descriptor; a missing cell reads as unset. Errors land on
 * `rows.N.<col>` (cell) and `rows` (min/max rows, more than one selected row),
 * the same paths the server uses. `summary` is stripped: it is server-computed.
 */
export function buildTableFieldSchema(config: TableFieldConfig, t: TFunction) {
  const cellShape: Record<string, z.ZodTypeAny> = { id: z.string().nullish() }
  for (const column of config.columns) {
    const unset = column.type === 'boolean' ? false : null
    cellShape[column.key] = z.preprocess(
      (cell) => (cell === undefined ? unset : cell),
      buildFieldSchema(toColumnDescriptor(column), t),
    )
  }
  if (config.selectable) {
    cellShape[config.selectable.key] = z.boolean().optional()
  }

  const rowSchema = z.object(cellShape).superRefine((row, ctx) => {
    for (const column of config.columns) {
      if (column.required && isEmptyCustomFieldValue((row as Record<string, unknown>)[column.key])) {
        ctx.addIssue({ code: 'custom', path: [column.key], message: t('customFields.validation.required') })
      }
    }
  })

  return z
    .object({ rows: z.array(rowSchema) })
    .nullable()
    .superRefine((value, ctx) => {
      if (value === null) {
        return
      }
      const { min_rows: minRows, max_rows: maxRows } = config
      if (minRows !== undefined && value.rows.length < minRows) {
        ctx.addIssue({ code: 'custom', path: ['rows'], message: t('customFields.validation.minRows', { min: minRows }) })
      }
      if (maxRows !== undefined && value.rows.length > maxRows) {
        ctx.addIssue({ code: 'custom', path: ['rows'], message: t('customFields.validation.maxRows', { max: maxRows }) })
      }
      if (value.rows.filter((row) => isRowSelected(row, config)).length > 1) {
        ctx.addIssue({ code: 'custom', path: ['rows'], message: t('customFields.validation.singleSelected') })
      }
    })
}

function buildFieldSchema(descriptor: CustomFieldDescriptor, t: TFunction): z.ZodTypeAny {
  switch (descriptor.type) {
    // text/textarea + the string-backed scalars (date/datetime/time/email/url/
    // color) share the nullable-string schema: the native input constrains the
    // shape client-side and the backend rule is authoritative (a bad value
    // surfaces inline via the custom_fields.<key> 422 mapping).
    case 'text':
    case 'textarea':
    case 'date':
    case 'datetime':
    case 'time':
    case 'email':
    case 'url':
    case 'color':
      return buildTextSchema(descriptor, t)
    case 'integer':
    case 'decimal':
      return buildNumberSchema(descriptor, t)
    case 'boolean':
      return z.boolean()
    case 'enum':
      return buildEnumSchema(descriptor, t)
    case 'relation':
      return buildRelationSchema(descriptor)
    case 'table':
      return buildTableFieldSchema(getTableConfig(descriptor), t)
    default:
      return z.unknown()
  }
}

/** `required` mirrors the backend's own derivation: the role's flag, floored by the definition's own `mandatory`. */
function isRequired(descriptor: CustomFieldDescriptor, permissions: ResourcePermissions): boolean {
  return Boolean(permissions.fields[descriptor.key]?.required) || Boolean(descriptor.mandatory)
}

export function buildCustomFieldsSchema(
  fields: CustomFieldDescriptor[],
  permissions: ResourcePermissions,
  t: TFunction,
) {
  const shape: Record<string, z.ZodTypeAny> = {}
  const requiredKeys: string[] = []

  for (const descriptor of fields) {
    const key = rawKey(descriptor.key)
    shape[key] = buildFieldSchema(descriptor, t)
    if (isRequired(descriptor, permissions)) {
      requiredKeys.push(key)
    }
  }

  return z.object(shape).superRefine((values, ctx) => {
    for (const key of requiredKeys) {
      if (isEmptyCustomFieldValue((values as Record<string, unknown>)[key])) {
        ctx.addIssue({
          code: 'custom',
          path: [key],
          message: t('customFields.validation.required'),
        })
      }
    }
  })
}

export type CustomFieldsFormValues = z.infer<ReturnType<typeof buildCustomFieldsSchema>>

/** The dynamic schema instance built by {@link buildCustomFieldsSchema}. */
export type CustomFieldsSchema = ReturnType<typeof buildCustomFieldsSchema>

/**
 * `buildCustomFieldsSchema` derives its shape from a runtime-keyed
 * `Record<string, ZodTypeAny>`, so Zod can only infer its output as
 * `Record<string, unknown>`. This is the SAME schema instance re-typed to its
 * real value domain (`CustomFieldValue`) so it embeds cleanly under a form's
 * `custom_fields` key without breaking `zodResolver` inference — no runtime
 * change.
 */
export type TypedCustomFieldsSchema = z.ZodType<
  Record<string, CustomFieldValue>,
  Record<string, CustomFieldValue>
>

/**
 * Embed the toolbox-built custom-fields schema under a form's `custom_fields`
 * key. Every module form uses this so the cast lives in ONE place.
 */
export function asCustomFieldsField(schema: CustomFieldsSchema): TypedCustomFieldsSchema {
  return schema as unknown as TypedCustomFieldsSchema
}
