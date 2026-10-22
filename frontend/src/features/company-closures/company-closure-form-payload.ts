import { canRepeatYearly, type CompanyClosureFormValues } from '@/features/company-closures/company-closure-schema'
import type { CompanyClosure, CompanyClosurePayload } from '@/features/company-closures/types'

/** Form defaults of a new closure. */
export const CREATE_DEFAULTS: CompanyClosureFormValues = {
  name: '',
  starts_on: '',
  ends_on: '',
  kind: 'holiday',
  absence_type_id: null,
  applies_to_all_sites: true,
  operational_site_ids: [],
  repeats_yearly: false,
}

/** Form values of a saved closure. */
export function toFormValues(closure: CompanyClosure): CompanyClosureFormValues {
  return {
    name: closure.name,
    starts_on: closure.starts_on,
    ends_on: closure.ends_on,
    kind: closure.kind,
    absence_type_id: closure.absence_type?.id ?? null,
    applies_to_all_sites: closure.applies_to_all_sites,
    operational_site_ids: closure.operational_sites.map((site) => site.id),
    repeats_yearly: closure.repeats_yearly,
  }
}

/** POST/PUT body: the conditional fields travel only when their rule applies (the server rejects them otherwise). */
export function buildPayload(values: CompanyClosureFormValues): CompanyClosurePayload {
  const payload: CompanyClosurePayload = {
    name: values.name.trim(),
    starts_on: values.starts_on,
    ends_on: values.ends_on,
    kind: values.kind,
    applies_to_all_sites: values.applies_to_all_sites,
    repeats_yearly: canRepeatYearly(values) && values.repeats_yearly,
  }
  if (values.kind === 'collective_leave' && values.absence_type_id !== null) {
    payload.absence_type_id = values.absence_type_id
  }
  if (!values.applies_to_all_sites) {
    payload.operational_site_ids = values.operational_site_ids
  }
  return payload
}
