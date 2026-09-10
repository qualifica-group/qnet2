import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Scale, UserCheck, Users } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { AssignOperatorsModePicker } from '@/features/leads/assign-operators-mode-picker'
import type { AssignmentMode } from '@/features/leads/assign-operators-mode-picker'
import { AssignOperatorsPickers } from '@/features/leads/assign-operators-pickers'
import type { AssignOperatorsDialogSite } from '@/features/leads/assign-operators-pickers'

export type { AssignmentMode, AssignOperatorsDialogSite }

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
          onAssign={onAssign}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface AssignOperatorsDialogBodyProps
  extends Omit<AssignOperatorsDialogProps, 'open' | 'onOpenChange' | 'showSiteField' | 'isResolvingCompetence'> {
  showSiteField: boolean
  isResolvingCompetence: boolean
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed, so keeping this
 * state in its own component (rather than in `AssignOperatorsDialog` itself,
 * which the consumer keeps mounted across opens) is what makes every open a
 * fresh selection instead of carrying over the previous one.
 */
function AssignOperatorsDialogBody({
  selectionCount,
  showSiteField,
  operatorSiteId,
  defaultSiteId,
  defaultSite,
  copy,
  lockedMode,
  disabledModes,
  disabledModeHints,
  competenceCategoryIds,
  isResolvingCompetence,
  onAssign,
  onClose,
}: AssignOperatorsDialogBodyProps) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<AssignmentMode | null>(lockedMode ?? null)
  const [siteId, setSiteId] = useState<number | null>(defaultSiteId ?? defaultSite?.id ?? null)
  const [operatorId, setOperatorId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // A Sede change re-scopes the whole operator list: the previous pick can
  // no longer be assumed to belong to it, so it is always cleared.
  function handleSiteChange(nextSiteId: number | null) {
    setSiteId(nextSiteId)
    setOperatorId(null)
  }

  // The Sede only gates submission where the user picks it. Locked mode (spec
  // 0079) always needs an Operatore; free mode keeps the per-mode gating:
  // single needs the Operatore, balanced needs nothing else (spec 0113 AC-026).
  const isSiteReady = !showSiteField || siteId !== null
  const canSubmit = lockedMode
    ? isSiteReady && operatorId !== null
    : mode !== null && isSiteReady && (mode === 'balanced' || operatorId !== null)

  const effectiveMode = lockedMode ?? mode
  const ConfirmIcon = effectiveMode === 'single' ? UserCheck : Scale
  // Locked mode always shows the Operatore field (spec 0079 AC-027); free
  // mode keeps it gated behind the user's own `single` pick.
  const showOperatorField = lockedMode !== undefined || mode === 'single'

  function handleAssign() {
    if (!canSubmit || effectiveMode === null) {
      return
    }
    const needsOperator = lockedMode !== undefined || effectiveMode === 'single'
    setIsSubmitting(true)
    onAssign({
      ...(showSiteField ? { operational_site_id: siteId as number } : {}),
      mode: effectiveMode,
      ...(needsOperator ? { operator_id: operatorId as number } : {}),
    })
      .then(() => onClose())
      .catch(() => {
        // Already surfaced via toast by the caller; keep the picks so the
        // user can retry without reselecting.
      })
      .finally(() => setIsSubmitting(false))
  }

  return (
    <>
      {/* Header band: brand-tinted strip with an icon chip for identity. */}
      <div className="flex items-start gap-3 rounded-t-lg border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pb-3.5">
        <span
          aria-hidden="true"
          className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
        >
          <Users className="size-4.5" />
        </span>
        <DialogHeader className="flex-1 gap-1">
          <DialogTitle className="text-sm">{copy?.title ?? t('leads.assign.title')}</DialogTitle>
          <DialogDescription className="text-xs">
            {copy?.description ?? t('leads.assign.description', { count: selectionCount })}
          </DialogDescription>
        </DialogHeader>
      </div>

      <div className="space-y-4 px-4 py-4">
        {/* Step 1: pick the assignment mode — skipped entirely when locked (spec 0079 AC-027). */}
        {!lockedMode && (
          <AssignOperatorsModePicker
            value={mode}
            onChange={setMode}
            hints={copy?.modeHints}
            disabledModes={disabledModes}
            disabledModeHints={disabledModeHints}
            isSubmitting={isSubmitting}
          />
        )}

        {/* Step 2: the Sede (transfer only) and the Operatore (single, or locked). */}
        {mode !== null && (showSiteField || showOperatorField) && (
          <AssignOperatorsPickers
            showSiteField={showSiteField}
            showOperatorField={showOperatorField}
            siteId={siteId}
            onSiteChange={handleSiteChange}
            defaultSite={defaultSite}
            operatorSiteId={operatorSiteId}
            operatorId={operatorId}
            onOperatorChange={setOperatorId}
            competenceCategoryIds={competenceCategoryIds}
            isResolvingCompetence={isResolvingCompetence}
            isSubmitting={isSubmitting}
          />
        )}
      </div>

      <DialogFooter className="rounded-b-lg border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3.5">
        <Button
          type="button"
          size="sm"
          className="w-full gap-1.5 sm:w-auto sm:min-w-44 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px"
          onClick={handleAssign}
          disabled={!canSubmit || isSubmitting}
        >
          <ConfirmIcon className="size-3.5" aria-hidden="true" />
          {isSubmitting ? t('leads.assign.actions.assigning') : t('leads.assign.actions.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}
