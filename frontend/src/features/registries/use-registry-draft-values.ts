import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { SECTORS_FOR_SELECT_RESOURCE } from '@/features/sectors/for-select-api'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { RegistryRecordValues } from '@/features/registries/registry-record'
import type { ReferenceRef } from '@/features/registries/types'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty sets: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []
const NO_SLOTS: (number | null)[] = []

/** The draft's id(s) as the label hooks read them. */
function idsOf(...ids: (number | null | undefined)[]): number[] {
  const present = ids.filter((id): id is number => id != null)
  return present.length > 0 ? present : NO_IDS
}

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined): ReferenceRef | null {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the record's closed rows read it (spec 0200): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger.
 */
export function useRegistryDraftValues(control: Control<RegistryFormValues>): RegistryRecordValues {
  const draft = useWatch({ control })
  const slots = draft.manager_slots ?? NO_SLOTS
  const sectorIds = useMemo(() => idsOf(...(draft.sector_ids ?? [])), [draft.sector_ids])
  const listedReferentIds = useMemo(() => idsOf(...(draft.referent_ids ?? [])), [draft.referent_ids])
  const referentIds = useMemo(
    () => Array.from(new Set(idsOf(...listedReferentIds, draft.commercial_id, draft.reporter_id))),
    [listedReferentIds, draft.commercial_id, draft.reporter_id],
  )
  const userIds = useMemo(
    () => Array.from(new Set(idsOf(draft.supervisor_id, ...slots))),
    [draft.supervisor_id, slots],
  )

  const sources = useForSelectLabels({ resource: SOURCES_FOR_SELECT_RESOURCE, ids: idsOf(draft.source_id) })
  const sectors = useForSelectLabels({ resource: SECTORS_FOR_SELECT_RESOURCE, ids: sectorIds })
  const referents = useForSelectLabels({ resource: REFERENTS_FOR_SELECT_RESOURCE, ids: referentIds })
  const users = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: userIds })

  return {
    source: refOf(sources, draft.source_id),
    sectors: sectorIds.map((id) => ({ id, name: sectors.get(id)?.label ?? PENDING_LABEL })),
    referents: listedReferentIds.map((id) => ({ id, name: referents.get(id)?.label ?? PENDING_LABEL })),
    commercial: refOf(referents, draft.commercial_id),
    reporter: refOf(referents, draft.reporter_id),
    supervisor: refOf(users, draft.supervisor_id),
    managers: slots.flatMap((id, index) =>
      id != null ? [{ id, name: users.get(id)?.label ?? PENDING_LABEL, position: index + 1 }] : [],
    ),
    manager_slots: slots.map((id) => id ?? null),
    vat_group: draft.vat_group ?? null,
    is_supplier: draft.is_supplier ?? false,
    is_qualified_supplier: draft.is_qualified_supplier ?? false,
    agreement_status: draft.agreement_status ?? null,
    agreement_notes: draft.agreement_notes ?? null,
    size_class: draft.size_class ?? null,
    employee_count: draft.employee_count ?? null,
    custom_fields: (draft.custom_fields ?? {}) as RegistryRecordValues['custom_fields'],
  }
}
