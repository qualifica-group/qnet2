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
