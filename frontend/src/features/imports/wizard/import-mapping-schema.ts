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

/** Global field ids the Campaign -> Fonte run-level required rule below reasons about (spec 0176 D-5). */
const CAMPAIGN_GLOBAL_FIELD_ID = 'campaign_id'
const SOURCE_GLOBAL_FIELD_ID = 'source_id'

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

      const mappedTargets = Object.values(values.mapping)

      for (const field of globalFields) {
        // Spec 0108 (D-2): a global field whose `required_unless_mapped`
        // field is mapped takes its value per row from that column, so the
        // run-wide control is neither required nor submitted.
        if (field.required_unless_mapped && mappedTargets.includes(field.required_unless_mapped)) {
          continue
        }

        if (field.required && isGlobalConfigValueMissing(values.global_config[field.id])) {
          ctx.addIssue({
            code: 'custom',
            path: ['global_config', field.id],
            message: t('config.errors.required'),
          })
        }
      }

      // Spec 0176 D-5: the catalog keeps `source_id` optional (it is only
      // ever required when the row's EFFECTIVE Fonte would otherwise be
      // missing, a per-row staging concern), but the wizard itself marks it
      // required whenever the Campaign is chosen run-wide — a run-level
      // campaign with no Fonte of its own guarantees every row fails at
      // staging, so the operator fixes it here instead. A per-row (file)
      // campaign is unaffected: each row still resolves its own effective
      // Fonte server-side (D-5).
      const campaignField = globalFields.find((field) => field.id === CAMPAIGN_GLOBAL_FIELD_ID)
      const sourceField = globalFields.find((field) => field.id === SOURCE_GLOBAL_FIELD_ID)
      const campaignIsRunLevel =
        campaignField != null &&
        !(campaignField.required_unless_mapped != null && mappedTargets.includes(campaignField.required_unless_mapped))
      if (
        campaignField &&
        sourceField &&
        campaignIsRunLevel &&
        isGlobalConfigValueMissing(values.global_config[SOURCE_GLOBAL_FIELD_ID])
      ) {
        ctx.addIssue({
          code: 'custom',
          path: ['global_config', SOURCE_GLOBAL_FIELD_ID],
          message: t('config.errors.required'),
        })
      }
    })
}
