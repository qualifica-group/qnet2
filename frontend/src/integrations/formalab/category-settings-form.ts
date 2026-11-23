import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import type {
  FormalabCategoryAttribute,
  FormalabCategoryField,
  FormalabCategorySettings,
  FormalabCategorySettingsPayload,
} from '@/integrations/formalab/types'

/** The Formalab field the work order finds its edition by (a select on the Formalab classes). */
export const KEY_FIELD = 'label'

/**
 * Attributes a Formalab field can be written into: a compatible type and, for
 * a field bound to a relation target (the key field), the same target.
 */
export function attributeOptions(
  field: FormalabCategoryField,
  attributes: FormalabCategoryAttribute[],
): FormalabCategoryAttribute[] {
  return attributes.filter(
    (attribute) =>
      field.compatible_types.includes(attribute.type) &&
      (field.relation_entity_type === null || attribute.relation_entity_type === field.relation_entity_type),
  )
}

function buildSchema(messages: { keyMissing: string; duplicate: string }) {
  return z
    .object({
      enabled: z.boolean(),
      /** Child only: use the ancestor's map instead of declaring one. */
      inherit: z.boolean(),
      /** formalab_field => attribute_code, '' = not mapped. */
      map: z.record(z.string(), z.string()),
    })
    .superRefine((values, ctx) => {
      if (values.inherit) {
        return
      }
      const mapped = Object.entries(values.map).filter(([, code]) => code !== '')
      if (mapped.length > 0 && !values.map[KEY_FIELD]) {
        ctx.addIssue({ code: 'custom', path: ['map', KEY_FIELD], message: messages.keyMissing })
      }
      const seen = new Set<string>()
      for (const [field, code] of mapped) {
        if (seen.has(code)) {
          ctx.addIssue({ code: 'custom', path: ['map', field], message: messages.duplicate })
        }
        seen.add(code)
      }
    })
}

export type CategorySettingsFormValues = z.infer<ReturnType<typeof buildSchema>>

/**
 * The editor starts from the effective map; with nothing mapped yet it
 * proposes the suggested attributes (the course fields of the first release).
 */
export function initialValues(settings: FormalabCategorySettings): CategorySettingsFormValues {
  const effective = Object.fromEntries(settings.field_map.map((pair) => [pair.formalab_field, pair.attribute_code]))
  const proposeSuggestions = settings.field_map.length === 0
  const map = Object.fromEntries(
    settings.fields.map((field) => [
      field.key,
      effective[field.key] ?? (proposeSuggestions ? (field.suggested_attribute_code ?? '') : ''),
    ]),
  )

  return { enabled: settings.enabled, inherit: !settings.is_root && !settings.own_field_map, map }
}

export function toPayload(settings: FormalabCategorySettings, values: CategorySettingsFormValues): FormalabCategorySettingsPayload {
  const fieldMap = values.inherit
    ? null
    : settings.fields
        .filter((field) => (values.map[field.key] ?? '') !== '')
        .map((field) => ({ formalab_field: field.key, attribute_code: values.map[field.key] }))

  return settings.is_root ? { enabled: values.enabled, field_map: fieldMap } : { field_map: fieldMap }
}

export function useCategorySettingsForm(settings: FormalabCategorySettings) {
  const { t } = useTranslation('formalab')

  return useForm<CategorySettingsFormValues>({
    resolver: zodResolver(
      buildSchema({ keyMissing: t('category.errors.keyMissing'), duplicate: t('category.errors.duplicate') }),
    ),
    defaultValues: initialValues(settings),
  })
}
