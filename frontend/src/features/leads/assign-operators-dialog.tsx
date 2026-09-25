import { Dialog, DialogContent } from '@/components/ui/dialog'
import type {
  AssignmentScopeBalancedGroup,
  BalancedOperatorsBySiteEntry,
} from '@/features/assignment/types'
import { AssignOperatorsDialogBody } from '@/features/leads/assign-operators-dialog-body'
import type { AssignmentMode } from '@/features/leads/assign-operators-mode-picker'
import type { AssignOperatorsDialogSite } from '@/features/leads/assign-operators-pickers'

export type { AssignmentMode, AssignOperatorsDialogSite }

/**
 * The "Smistamento equo" picker's scope (spec 0168), resolved by the call
 * site from its own `useAssignmentScope`/`useQuoteAssignmentScope` — the
 * dialog stays domain-agnostic. Omitted entirely (the two `showSiteField`
 * transfer dialogs, always `lockedMode="single"`) the dialog falls back to
 * the pre-0168 behaviour: balanced confirms on the mode alone, no
 * `operators_by_site` in the payload.
 */
export interface AssignOperatorsDialogBalancedScope {
  groups: AssignmentScopeBalancedGroup[] | undefined
  unassignableCount: number | undefined
  isResolving: boolean
  /** True when the selection-scope request failed; Confirm stays disabled and the picker names the failure. */
  isError: boolean
}

/**
 * Copy that names the assigned entity ("… lead selezionati", "Distribuisce i
 * lead selezionati…"). Everything else in the dialog is entity-neutral and
 * stays on the shared strings. `title`, optional (spec 0079): omitted, the
 * header keeps the Lead wording (`leads.assign.title`) — the transfer flow is
 * the only caller that overrides it.
 */
export interface AssignOperatorsDialogCopy {
  title?: string
  description: string
  modeHints: Record<AssignmentMode, string>
}

/** Input handed to `onAssign`, mirroring the `POST /leads/assign-operators` request body. */
export interface AssignOperatorsDialogInput {
  /**
   * Only present with `showSiteField`, i.e. the contact-transfer flow (spec
   * 0079), where the Sede is the transfer DESTINATION. The assignment
   * surfaces derive it server-side from each record and no longer send it
   * (spec 0113 D-2).
   */
  operational_site_id?: number
  mode: AssignmentMode
  operator_id?: number
  /**
   * Sent SEMPRE in balanced mode when `balancedScope` is wired (spec 0168):
   * one entry per Sede group with at least one operator left selected. Absent
   * when `balancedScope` was not passed (the two transfer dialogs).
   */
  operators_by_site?: BalancedOperatorsBySiteEntry[]
}

export interface AssignOperatorsDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many leads/rows are selected; drives the title/description copy. */
  selectionCount: number
  /**
   * Opt-in Sede select (spec 0113 D-2), default false. Only the two
   * contact-transfer call sites set it: there the Sede is the DESTINATION the
   * user picks, not a filter. On the assignment surfaces the Sede comes from
   * the record itself, so the field is not rendered at all (AC-026) and
   * `operational_site_id` leaves the payload.
   */
  showSiteField?: boolean
  /**
   * Sede the Operatore picker is scoped to when `showSiteField` is false,
   * resolved by the call site from the selected records. `undefined` = not
   * resolved yet (or resolution failed) and the picker stays disabled;
   * `null` = mixed/absent Sede, the picker filters by competence alone.
   */
  operatorSiteId?: number | null
  /** Seeds for the Sede select; meaningful only with `showSiteField`. */
  defaultSiteId?: number | null
  defaultSite?: AssignOperatorsDialogSite | null
  /**
   * The two entity-specific sentences, resolved by the consumer (the dialog
   * itself stays domain-agnostic). Omitted, both fall back to the Lead
   * wording, so the leads/import call sites read exactly as before.
   */
  copy?: AssignOperatorsDialogCopy
  /**
   * Additive, opt-in (spec 0079): when set, Step 1 (the mode picker) never
   * renders, `mode` is fixed to this value instead of user-picked, and the
   * Operatore field is always shown (not only for `single`).
   */
  lockedMode?: AssignmentMode
  /**
   * Modes the current selection cannot express: the card stays visible but is
   * not selectable. Used by the import review bar to rule out `single` on a
   * selection spanning several campaigns, while `balanced` stays available
   * (spec 0113 AC-029/D-5).
   */
  disabledModes?: readonly AssignmentMode[]
  /** Human-readable reason rendered on each disabled card. */
  disabledModeHints?: Partial<Record<AssignmentMode, string>>
  /**
   * Product categories the current selection requires (spec 0110 AC-041),
   * resolved by the CALL SITE — the dialog stays dumb and only forwards them
   * to the picker. `undefined` means NO competence filter (nothing selected,
   * still resolving, or a selection that expresses no requirement).
   */
  competenceCategoryIds?: number[]
  /**
   * True while the call site is still resolving the scope above: the
   * Operatore picker stays disabled rather than briefly listing operators the
   * filter is about to exclude (spec 0110 AC-043).
   */
  isResolvingCompetence?: boolean
  /** See `AssignOperatorsDialogBalancedScope`. Irrelevant (and unused) while `mode !== 'balanced'`. */
  balancedScope?: AssignOperatorsDialogBalancedScope
  /**
   * Wired by the consumer to its own endpoint (the Lead table via
   * `useAssignOperators`, the import review bar via its own PATCH). The
   * dialog never calls the API itself: it only collects the input, shows a
   * pending state, and closes on success. A rejection is assumed already
   * surfaced by the caller (toast) and just keeps the dialog open with the
   * current picks so the user can retry.
   */
  onAssign: (input: AssignOperatorsDialogInput) => Promise<void>
}

/**
 * Shared "Assegna operatori" popup (spec 0048, reshaped by spec 0113). The
 * user picks the assignment mode — "Smistamento equo" (`balanced`) or "Assegna
 * a operatore" (`single`) — and, for `single`, the Operatore. The Sede is no
 * longer part of the flow on the assignment surfaces: it is derived from each
 * record server-side and only scopes the picker via `operatorSiteId`. The
 * contact-transfer flow opts the field back in with `showSiteField`, where the
 * Sede is the destination of the transfer (spec 0079).
 */
export function AssignOperatorsDialog({
  open,
  onOpenChange,
  selectionCount,
  showSiteField = false,
  operatorSiteId,
  defaultSiteId,
  defaultSite,
  copy,
  lockedMode,
  disabledModes,
  disabledModeHints,
  competenceCategoryIds,
  isResolvingCompetence = false,
  balancedScope,
  onAssign,
}: AssignOperatorsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="gap-0 p-0">
        <AssignOperatorsDialogBody
          selectionCount={selectionCount}
          showSiteField={showSiteField}
          operatorSiteId={operatorSiteId}
          defaultSiteId={defaultSiteId}
          defaultSite={defaultSite}
          copy={copy}
          lockedMode={lockedMode}
          disabledModes={disabledModes}
          disabledModeHints={disabledModeHints}
          competenceCategoryIds={competenceCategoryIds}
          isResolvingCompetence={isResolvingCompetence}
          balancedScope={balancedScope}
          onAssign={onAssign}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}
