import { z } from 'zod'
import type { TFunction } from 'i18next'
import { ENASARCO_MANDATE_TYPES } from '@/features/agent-fiscal-profiles/types'

/**
 * Zod schema of the agent fiscal profile form (spec 0212 D-8), a factory so
 * messages are localized. Rates are kept as strings (decimal inputs); the
 * conditional requirements mirror the server `required_if` rules.
 */

const NOTES_MAX_LENGTH = 2000
const PERCENT_MAX = 100
const PERCENT_PATTERN = /^\d{1,3}(\.\d{1,2})?$/

function percentIssue(value: string, enabled: boolean, t: TFunction): string | null {
  if (!enabled) {
    return null
  }
  const trimmed = value.trim()
  if (trimmed === '') {
    return t('agentFiscalProfiles.form.percentRequired')
  }
  return PERCENT_PATTERN.test(trimmed) && Number(trimmed) <= PERCENT_MAX
    ? null
    : t('agentFiscalProfiles.form.percentInvalid')
}

export function buildAgentFiscalProfileSchema(t: TFunction) {
  return z
    .object({
      referent_id: z
        .number()
        .nullable()
        .transform((value, context) => {
          if (value === null) {
            context.addIssue({ code: 'custom', message: t('agentFiscalProfiles.form.referentRequired') })
            return z.NEVER
          }
          return value
        }),
      withholding_enabled: z.boolean(),
      withholding_rate: z.string(),
      withholding_base_percent: z.string(),
      enasarco_enabled: z.boolean(),
      enasarco_mandate_type: z.union([z.enum(ENASARCO_MANDATE_TYPES), z.literal('')]),
      valid_from: z.string().min(1, t('agentFiscalProfiles.form.validFromRequired')),
      valid_until: z.string(),
      notes: z.string().max(NOTES_MAX_LENGTH, t('agentFiscalProfiles.form.notesMax')),
    })
    .superRefine((values, context) => {
      const rateIssue = percentIssue(values.withholding_rate, values.withholding_enabled, t)
      if (rateIssue) {
        context.addIssue({ code: 'custom', path: ['withholding_rate'], message: rateIssue })
      }
      const baseIssue = percentIssue(values.withholding_base_percent, values.withholding_enabled, t)
      if (baseIssue) {
        context.addIssue({ code: 'custom', path: ['withholding_base_percent'], message: baseIssue })
      }
      if (values.enasarco_enabled && values.enasarco_mandate_type === '') {
        context.addIssue({
          code: 'custom',
          path: ['enasarco_mandate_type'],
          message: t('agentFiscalProfiles.form.mandateTypeRequired'),
        })
      }
      // ISO dates compare correctly as strings.
      if (values.valid_until !== '' && values.valid_from !== '' && values.valid_until < values.valid_from) {
        context.addIssue({
          code: 'custom',
          path: ['valid_until'],
          message: t('agentFiscalProfiles.form.validUntilBeforeFrom'),
        })
      }
    })
}

type AgentFiscalProfileSchema = ReturnType<typeof buildAgentFiscalProfileSchema>

/** RHF values (inputs): `referent_id` is nullable until the user picks one. */
export type AgentFiscalProfileFormValues = z.input<AgentFiscalProfileSchema>
/** Validated output consumed by the payload builder. */
export type AgentFiscalProfileSchemaOutput = z.output<AgentFiscalProfileSchema>
