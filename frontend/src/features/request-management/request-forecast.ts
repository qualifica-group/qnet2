import { z } from 'zod'
import type { TFunction } from 'i18next'
import { normalizeDecimal } from '@/features/opportunities/opportunity-form-payload'
import {
  ESTIMATED_VALUE_MAX,
  SUCCESS_PROBABILITY_MAX,
  SUCCESS_PROBABILITY_MIN,
} from '@/features/opportunities/opportunity-schema'

/*
 * "Previsione commerciale" (spec 0313): the Opportunity's forecast trio, edited
 * from both request forms. One schema shape, one baseline, one diff — so the
 * work panel and the create form send exactly the same keys under the same
 * rules the Opportunities form applies.
 */

/** The trio as the server projects it (`RequestManagementResource`, top-level). */
export interface RequestForecast {
  expected_close_date: string | null
  /** `decimal(15,2)`: arrives as a decimal string. */
  estimated_value: string | number | null
  success_probability: number | null
}

/** Sparse wire keys: absent = untouched, `null` clears the column. */
export interface RequestForecastPayload {
  expected_close_date?: string | null
  estimated_value?: number | null
  success_probability?: number | null
}

/** Nothing persisted yet: the create form's baseline. */
export const NO_FORECAST: RequestForecast = {
  expected_close_date: null,
  estimated_value: null,
  success_probability: null,
}

/**
 * The form fields, with the Opportunities form's rules. The probability is a
 * slider that always holds a value (spec 0040 A-6): `0` stands for "not set".
 */
export function requestForecastSchemaShape(t: TFunction) {
  return {
    expected_close_date: z.string().nullable(),
    estimated_value: z
      .number()
      .nonnegative(t('opportunities.form.estimatedValueInvalid'))
      .max(ESTIMATED_VALUE_MAX, t('opportunities.form.estimatedValueInvalid'))
      .nullable(),
    success_probability: z
      .number()
      .int()
      .min(SUCCESS_PROBABILITY_MIN, t('opportunities.form.successProbabilityInvalid'))
      .max(SUCCESS_PROBABILITY_MAX, t('opportunities.form.successProbabilityInvalid')),
  }
}

export type RequestForecastValues = z.infer<z.ZodObject<ReturnType<typeof requestForecastSchemaShape>>>

export function toForecastValues(forecast: RequestForecast): RequestForecastValues {
  return {
    expected_close_date: forecast.expected_close_date,
    estimated_value: normalizeDecimal(forecast.estimated_value),
    success_probability: forecast.success_probability ?? 0,
  }
}

/** Each key travels only when it differs from the persisted value. */
export function buildForecastPayload(values: RequestForecastValues, original: RequestForecast): RequestForecastPayload {
  const baseline = toForecastValues(original)
  const payload: RequestForecastPayload = {}

  if (values.expected_close_date !== baseline.expected_close_date) {
    payload.expected_close_date = values.expected_close_date
  }
  if (values.estimated_value !== baseline.estimated_value) {
    payload.estimated_value = values.estimated_value
  }
  if (values.success_probability !== baseline.success_probability) {
    payload.success_probability = values.success_probability
  }

  return payload
}
