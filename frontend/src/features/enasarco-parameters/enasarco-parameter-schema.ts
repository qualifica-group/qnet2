import { z } from 'zod'
import type { TFunction } from 'i18next'

/**
 * Zod schema of the ENASARCO parameters form (spec 0213 data_contract), a
 * factory so messages are localized. Numbers stay strings (decimal inputs) and
 * are converted by the payload builder; the rules mirror the server ones.
 */

const NOTES_MAX_LENGTH = 2000
const YEAR_MIN = 2000
const YEAR_MAX = 2100
const PERCENT_MAX = 100
const PERCENT_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
const AMOUNT_PATTERN = /^\d{1,13}(\.\d{1,2})?$/

export function buildEnasarcoParameterSchema(t: TFunction) {
  const percent = z
    .string()
    .trim()
    .min(1, t('enasarcoParameters.form.percentRequired'))
    .refine((value) => PERCENT_PATTERN.test(value) && Number(value) <= PERCENT_MAX, {
      message: t('enasarcoParameters.form.percentInvalid'),
    })
  const ceiling = z
    .string()
    .trim()
    .min(1, t('enasarcoParameters.form.ceilingRequired'))
    .refine((value) => AMOUNT_PATTERN.test(value), { message: t('enasarcoParameters.form.ceilingInvalid') })

  return z.object({
    year: z
      .string()
      .trim()
      .min(1, t('enasarcoParameters.form.yearRequired'))
      .refine((value) => /^\d{4}$/.test(value) && Number(value) >= YEAR_MIN && Number(value) <= YEAR_MAX, {
        message: t('enasarcoParameters.form.yearInvalid'),
      }),
    total_rate: percent,
    agent_share_percent: percent,
    single_firm_ceiling: ceiling,
    multi_firm_ceiling: ceiling,
    notes: z.string().max(NOTES_MAX_LENGTH, t('enasarcoParameters.form.notesMax')),
  })
}

type EnasarcoParameterSchema = ReturnType<typeof buildEnasarcoParameterSchema>

export type EnasarcoParameterFormValues = z.input<EnasarcoParameterSchema>
export type EnasarcoParameterSchemaOutput = z.output<EnasarcoParameterSchema>
