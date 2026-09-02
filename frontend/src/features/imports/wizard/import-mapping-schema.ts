import { z } from 'zod'
import type { TFunction } from 'i18next'
import type { ImportConfigFormValues } from '@/features/imports/wizard/import-config-schema'
import type {
  ImportFieldDescriptor,
  ImportGlobalFieldDescriptor,
} from '@/features/imports/wizard/types'

/**
 * The mapping step's form values: column name -> target (field id | sentinel),
 * the dedup strategy, and the global configuration values (campaign/source/
 * operator/status/products) — the configuration controls now live inside this
 * step, so a single submit persists mapping + config + dedup together.
 */
export interface ImportMappingFormValues {
  mapping: Record<string, string>
  dedup_strategy: string
  global_config: ImportConfigFormValues
}

/** Whether a global-config value counts as "unset" for the required check (spec 0094 AC-052: `[]` counts as missing). */
function isGlobalConfigValueMissing(value: number | number[] | null | undefined): boolean {
  return value == null || (Array.isArray(value) && value.length === 0)
}

/**
 * Zod schema for the mapping step. Neither `mapping` nor `global_config` has a
 * per-key shape (both are data-driven), so required-field coverage — a required
 * mappable field must have a target, a required global field must be set — is
 * enforced by a top-level `superRefine`, mirroring `projects/project-schema.ts`.
 */
export function buildImportMappingSchema(
  fields: ImportFieldDescriptor[],
  globalFields: ImportGlobalFieldDescriptor[],
  t: TFunction,
) {
  return z
    .object({
      mapping: z.record(z.string(), z.string()),
      dedup_strategy: z.string().min(1, t('mapping.errors.dedupRequired')),
      global_config: z.record(z.string(), z.union([z.number(), z.array(z.number()), z.null()])),
    })
    .superRefine((values, ctx) => {
      const mappedFieldIds = new Set(Object.values(values.mapping))
      const missing = fields.filter((field) => field.required && !mappedFieldIds.has(field.id))
      if (missing.length > 0) {
        ctx.addIssue({
          code: 'custom',
          path: ['mapping'],
          message: t('mapping.badges.requiredMissing', {
            fields: missing.map((field) => field.label).join(', '),
          }),
        })
      }

      for (const field of globalFields) {
        if (field.required && isGlobalConfigValueMissing(values.global_config[field.id])) {
          ctx.addIssue({
            code: 'custom',
            path: ['global_config', field.id],
            message: t('config.errors.required'),
          })
        }
      }
    })
}
