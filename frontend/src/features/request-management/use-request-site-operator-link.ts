import { useEffect, useRef, useState } from 'react'
import { useWatch, type FieldValues, type Path, type PathValue, type UseFormReturn } from 'react-hook-form'
import type { ForSelectItem } from '@/features/for-select/types'
import {
  clearManagerSlot,
  operatorSlotParams,
  operatorSlotSite,
  shouldAutoFillSite,
} from '@/features/request-management/request-team-slots'
import { OPERATOR_MANAGER_POSITION } from '@/features/request-management/types'

/**
 * The Sede <-> Operatore reciprocal link, CABLED (spec 0097 rev-2 D-7/AC-011).
 * `request-team-slots.ts` says what the rule is; this hook is where it is
 * wired to a form.
 *
 * It lives here — not in a section — because since rev-2 the two ends sit in
 * two different sections: the Sede in "Attribuzione", the operator slot in
 * "Team". They write the SAME RHF form, so the link belongs to whoever owns
 * that form (the work panel body, the create form), which then hands each
 * section its half. Left inside one of the two, the other would stop reacting.
 *
 * `previousSiteIdRef` is what tells a REAL Sede change apart from the
 * programmatic auto-fill (which never goes through the Sede picker's own
 * `onItemChange`) and from the actor's Sede seeded at mount. It is never read
 * or written during render: the effect below syncs it AFTER the handlers have
 * compared against the previous value, and only event handlers read it.
 */

/** The two fields the link reads and writes — both forms carry them under these exact names. */
interface SiteOperatorLinkValues {
  manager_slots: (number | null)[]
  operational_site_id: number | null
}

interface UseRequestSiteOperatorLinkOptions {
  /**
   * Whether the actor may set the Sede at all. `false` suppresses the
   * auto-fill: writing a field they cannot see would submit a key the endpoint
   * answers 403 on (the create form's own case — the work panel gates the
   * field from inside `MetaField` instead).
   */
  canPickSite?: boolean
}

export interface RequestSiteOperatorLink {
  /** The Sede currently on the form, watched once here rather than in each section. */
  siteId: number | null
  /** The Sede just hydrated from a picked operator, for the picker's own trigger label. */
  autoFilledSite: ForSelectItem | null
  /** `ManagerSlotsField.paramsFor`: only the operator slot is scoped to the Sede. */
  slotParamsFor: (position: number) => Record<string, string | number> | undefined
  /** `ManagerSlotsField.onItemChange`: an operator pick hydrates the Sede from its own `meta`. */
  onSlotItemChange: (position: number, item: ForSelectItem | null) => void
  /** The Sede picker's `onItemChange`: a real change empties the operator slot alone. */
  onSiteItemChange: (item: ForSelectItem | null) => void
}

export function useRequestSiteOperatorLink<TValues extends SiteOperatorLinkValues & FieldValues>(
  form: UseFormReturn<TValues>,
  { canPickSite = true }: UseRequestSiteOperatorLinkOptions = {},
): RequestSiteOperatorLink {
  const siteField = 'operational_site_id' as Path<TValues>
  const slotsField = 'manager_slots' as Path<TValues>

  const siteId = useWatch({ control: form.control, name: siteField }) as number | null
  const [autoFilledSite, setAutoFilledSite] = useState<ForSelectItem | null>(null)
  const previousSiteIdRef = useRef<number | null>(null)
  useEffect(() => {
    previousSiteIdRef.current = siteId
  }, [siteId])

  // Operatore -> Sede: picking a user on the OPERATOR slot hydrates its own
  // Sede from `meta` (no extra fetch) — but ONLY while the Sede field is
  // still empty (spec 0103 D-6). A Sede already on the form is never
  // overwritten: with remote appartenenze the operator picker, scoped to
  // that Sede, can return operators whose physical Sede differs from it. A
  // user with no Sede, or a pick on any other slot, leaves the current value
  // alone either way.
  const onSlotItemChange = (position: number, item: ForSelectItem | null) => {
    const site = operatorSlotSite(position, item)
    if (!canPickSite || !site || !shouldAutoFillSite(siteId)) return
    form.setValue(siteField, site.id as PathValue<TValues, Path<TValues>>, {
      shouldDirty: true,
      shouldValidate: true,
    })
    setAutoFilledSite({ id: site.id, label: site.label })
  }

  // Sede -> Operatore: a real pick/clear re-scopes that slot's list, so a user
  // from another Sede can no longer be assumed valid and the slot is emptied —
  // only on an ACTUAL change, never on the programmatic auto-fill above, and
  // never touching the other G.A. slots.
  const onSiteItemChange = (item: ForSelectItem | null) => {
    const nextSiteId = item?.id ?? null
    if (nextSiteId === previousSiteIdRef.current) return

    const slots = form.getValues(slotsField) as (number | null)[]
    form.setValue(
      slotsField,
      clearManagerSlot(slots, OPERATOR_MANAGER_POSITION) as PathValue<TValues, Path<TValues>>,
      { shouldDirty: true },
    )
  }

  return {
    siteId,
    autoFilledSite,
    slotParamsFor: (position) => operatorSlotParams(position, siteId),
    onSlotItemChange,
    onSiteItemChange,
  }
}
