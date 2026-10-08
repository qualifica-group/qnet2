import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type {
  AgreementStatus,
  ManagerRef,
  ReferenceRef,
  RegistryDetail,
  SizeClass,
} from '@/features/registries/types'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/**
 * Key of the anagraphic card's single in-place row (spec 0200 D-4). Not an RHF
 * path: the card is a buffered draft, so this only names the open editor.
 */
export const REGISTRY_CARD_FIELD = 'personal_data'

/** What the record's rows show: the persisted anagrafica on the detail, the draft (with resolved labels) on create. */
export interface RegistryRecordValues {
  source: ReferenceRef | null
  sectors: ReferenceRef[]
  referents: ReferenceRef[]
  commercial: ReferenceRef | null
  reporter: ReferenceRef | null
  supervisor: ReferenceRef | null
  managers: ManagerRef[]
  /** Gap-aware: index+1 = G.A. number, `null` = an empty slot that stays visible. */
  manager_slots: (number | null)[]
  vat_group: string | null
  is_supplier: boolean
  is_qualified_supplier: boolean
  agreement_status: AgreementStatus | null
  agreement_notes: string | null
  size_class: SizeClass | null
  employee_count: number | null
  custom_fields: Record<string, CustomFieldValue>
}

/** Everything a record section edits with: the shown values, the form and the single open row. */
export interface RegistryRecordSectionProps {
  values: RegistryRecordValues
  form: UseFormReturn<RegistryFormValues>
  inline: InlineEdit
}

/** The persisted anagrafica as the record's rows read it. */
export function persistedRegistryValues(registry: RegistryDetail): RegistryRecordValues {
  return {
    source: registry.source,
    sectors: registry.sectors,
    referents: registry.referents,
    commercial: registry.commercial,
    reporter: registry.reporter,
    supervisor: registry.supervisor,
    managers: registry.managers,
    manager_slots: registry.manager_slots,
    vat_group: registry.vat_group,
    is_supplier: registry.is_supplier,
    is_qualified_supplier: registry.is_qualified_supplier,
    agreement_status: registry.agreement_status,
    agreement_notes: registry.agreement_notes,
    size_class: registry.size_class,
    employee_count: registry.employee_count,
    custom_fields: registry.custom_fields ?? {},
  }
}
