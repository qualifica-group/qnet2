import type {
  EnasarcoParameterFormValues,
  EnasarcoParameterSchemaOutput,
} from '@/features/enasarco-parameters/enasarco-parameter-schema'
import type { EnasarcoParameterDetail, EnasarcoParameterPayload } from '@/features/enasarco-parameters/types'

export const EMPTY_FORM_VALUES: EnasarcoParameterFormValues = {
  year: '',
  total_rate: '',
  agent_share_percent: '',
  single_firm_ceiling: '',
  multi_firm_ceiling: '',
  notes: '',
}

export function toFormValues(parameter: EnasarcoParameterDetail): EnasarcoParameterFormValues {
  return {
    year: String(parameter.year),
    total_rate: parameter.total_rate,
    agent_share_percent: parameter.agent_share_percent,
    single_firm_ceiling: parameter.single_firm_ceiling,
    multi_firm_ceiling: parameter.multi_firm_ceiling,
    notes: parameter.notes ?? '',
  }
}

export function buildEnasarcoParameterPayload(values: EnasarcoParameterSchemaOutput): EnasarcoParameterPayload {
  const notes = values.notes.trim()
  return {
    year: Number(values.year),
    total_rate: Number(values.total_rate),
    agent_share_percent: Number(values.agent_share_percent),
    single_firm_ceiling: Number(values.single_firm_ceiling),
    multi_firm_ceiling: Number(values.multi_firm_ceiling),
    notes: notes === '' ? null : notes,
  }
}

/** The fields an in-place row changed (spec 0264 D-5): the saved record is the baseline, the PATCH keeps the rest. */
export function buildEnasarcoParameterUpdatePayload(
  values: EnasarcoParameterSchemaOutput,
  parameter: EnasarcoParameterDetail,
): Partial<EnasarcoParameterPayload> {
  const next = buildEnasarcoParameterPayload(values)
  const saved = buildEnasarcoParameterPayload(toFormValues(parameter))
  const changed: Partial<Record<keyof EnasarcoParameterPayload, unknown>> = {}
  for (const key of Object.keys(next) as (keyof EnasarcoParameterPayload)[]) {
    if (next[key] !== saved[key]) {
      changed[key] = next[key]
    }
  }
  return changed as Partial<EnasarcoParameterPayload>
}
