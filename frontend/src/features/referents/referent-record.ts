import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  ReferentContactScope,
  ReferentDetail,
  ReferentSupplierRef,
  ReferentTypeRef,
  ReferentUserRef,
} from '@/features/referents/types'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/** What the record's rows show: the persisted referente on the detail, the draft (with resolved labels) on create. */
export interface ReferentRecordValues {
  referent_type: ReferentTypeRef | null
  user: ReferentUserRef | null
  supplier: ReferentSupplierRef | null
  contact_scope: ReferentContactScope
  notes: string | null
  custom_fields: Record<string, CustomFieldValue>
}

/** Everything a record section edits with: the shown values, the form and the single open row. */
export interface ReferentRecordSectionProps {
  values: ReferentRecordValues
  form: UseFormReturn<ReferentFormValues>
  inline: InlineEdit
}

/** The persisted referente as the record's rows read it. */
export function persistedReferentValues(referent: ReferentDetail): ReferentRecordValues {
  return {
    referent_type: referent.referent_type,
    user: referent.user ?? null,
    supplier: referent.supplier ?? null,
    contact_scope: referent.contact_scope,
    notes: referent.notes,
    custom_fields: referent.custom_fields ?? {},
  }
}
