import { useWatch, type Control } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { REFERENT_TYPES_FOR_SELECT_RESOURCE } from '@/features/referent-types/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ReferentRecordValues } from '@/features/referents/referent-record'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty set: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []

function refOf(labels: Map<number, ForSelectItem>, id: number | null | undefined) {
  return id != null ? { id, name: labels.get(id)?.label ?? PENDING_LABEL } : null
}

/**
 * The create draft as the record's closed rows read it (spec 0216): the form
 * holds ids only, so every relation is named through `useForSelectLabels` —
 * the very cache the pickers fill for their own trigger.
 */
export function useReferentDraftValues(control: Control<ReferentFormValues>): ReferentRecordValues {
  const draft = useWatch({ control })
  const referentTypeId = draft.referent_type_id ?? null
  const userId = draft.user_id ?? null
  const referentTypes = useForSelectLabels({
    resource: REFERENT_TYPES_FOR_SELECT_RESOURCE,
    ids: referentTypeId !== null ? [referentTypeId] : NO_IDS,
  })
  const users = useForSelectLabels({ resource: USERS_FOR_SELECT_RESOURCE, ids: userId !== null ? [userId] : NO_IDS })

  return {
    referent_type: refOf(referentTypes, referentTypeId),
    user: refOf(users, userId),
    contact_scope: draft.contact_scope ?? 'internal',
    notes: draft.notes ?? null,
    custom_fields: (draft.custom_fields ?? {}) as ReferentRecordValues['custom_fields'],
  }
}
