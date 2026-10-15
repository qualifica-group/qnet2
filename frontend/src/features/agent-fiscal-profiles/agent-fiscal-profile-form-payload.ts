import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import type { AgentFiscalProfileDetail, AgentFiscalProfilePayload } from '@/features/agent-fiscal-profiles/types'

/** Empty form for a new profile: withholding on (server default), ENASARCO off. */
export const EMPTY_FORM_VALUES: AgentFiscalProfileFormValues = {
  referent_id: null,
  withholding_enabled: true,
  withholding_rate: '',
  withholding_base_percent: '',
  enasarco_enabled: false,
  enasarco_mandate_type: '',
  vat_rate_id: null,
  valid_from: '',
  valid_until: '',
  notes: '',
}

export function toFormValues(profile: AgentFiscalProfileDetail): AgentFiscalProfileFormValues {
  return {
    referent_id: profile.referent_id,
    withholding_enabled: profile.withholding_enabled,
    withholding_rate: profile.withholding_rate ?? '',
    withholding_base_percent: profile.withholding_base_percent ?? '',
    enasarco_enabled: profile.enasarco_enabled,
    enasarco_mandate_type: profile.enasarco_mandate_type ?? '',
    vat_rate_id: profile.vat_rate_id,
    valid_from: profile.valid_from,
    valid_until: profile.valid_until ?? '',
    notes: profile.notes ?? '',
  }
}

/** Conditional fields travel as null when their switch is off, never as stale values. */
export function buildAgentFiscalProfilePayload(values: AgentFiscalProfileSchemaOutput): AgentFiscalProfilePayload {
  const notes = values.notes.trim()
  return {
    referent_id: values.referent_id,
    withholding_enabled: values.withholding_enabled,
    withholding_rate: values.withholding_enabled ? Number(values.withholding_rate) : null,
    withholding_base_percent: values.withholding_enabled ? Number(values.withholding_base_percent) : null,
    enasarco_enabled: values.enasarco_enabled,
    enasarco_mandate_type:
      values.enasarco_enabled && values.enasarco_mandate_type !== '' ? values.enasarco_mandate_type : null,
    vat_rate_id: values.vat_rate_id,
    valid_from: values.valid_from,
    valid_until: values.valid_until === '' ? null : values.valid_until,
    notes: notes === '' ? null : notes,
  }
}

/** The fields an in-place row changed (spec 0264 D-4): the saved record is the baseline, the PATCH keeps the rest. */
export function buildAgentFiscalProfileUpdatePayload(
  values: AgentFiscalProfileSchemaOutput,
  profile: AgentFiscalProfileDetail,
): Partial<AgentFiscalProfilePayload> {
  const next = buildAgentFiscalProfilePayload(values)
  const saved = buildAgentFiscalProfilePayload({ ...toFormValues(profile), referent_id: profile.referent_id })
  const changed: Partial<Record<keyof AgentFiscalProfilePayload, unknown>> = {}
  for (const key of Object.keys(next) as (keyof AgentFiscalProfilePayload)[]) {
    if (next[key] !== saved[key]) {
      changed[key] = next[key]
    }
  }
  return changed as Partial<AgentFiscalProfilePayload>
}
