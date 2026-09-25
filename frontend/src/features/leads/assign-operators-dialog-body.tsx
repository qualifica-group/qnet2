import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Scale, UserCheck, Users } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { BalancedOperatorsPicker } from '@/features/assignment/balanced-operators-picker'
import { useBalancedOperatorSelection } from '@/features/assignment/use-balanced-operator-selection'
import { AssignOperatorsModePicker } from '@/features/leads/assign-operators-mode-picker'
import type { AssignmentMode } from '@/features/leads/assign-operators-mode-picker'
import { AssignOperatorsPickers } from '@/features/leads/assign-operators-pickers'
import type { AssignOperatorsDialogProps } from '@/features/leads/assign-operators-dialog'

export interface AssignOperatorsDialogBodyProps
  extends Omit<AssignOperatorsDialogProps, 'open' | 'onOpenChange' | 'showSiteField' | 'isResolvingCompetence'> {
  showSiteField: boolean
  isResolvingCompetence: boolean
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed, so keeping this
 * state in its own component (rather than in `AssignOperatorsDialog` itself,
 * which the consumer keeps mounted across opens) is what makes every open a
 * fresh selection instead of carrying over the previous one — including the
 * "Smistamento equo" selection (spec 0168 D-3), owned by
 * `useBalancedOperatorSelection` below.
 */
export function AssignOperatorsDialogBody({
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
  balancedScope,
  onAssign,
  onClose,
}: AssignOperatorsDialogBodyProps) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<AssignmentMode | null>(lockedMode ?? null)
  const [siteId, setSiteId] = useState<number | null>(defaultSiteId ?? defaultSite?.id ?? null)
  const [operatorId, setOperatorId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  // Fed `undefined` groups whenever `balancedScope` itself is omitted (the
  // transfer dialogs): the hook then reports no selection at all, which is
  // fine since `canSubmit`/`handleAssign` below never read it in that case.
  const balancedSelection = useBalancedOperatorSelection(balancedScope?.groups)

  // A Sede change re-scopes the whole operator list: the previous pick can
  // no longer be assumed to belong to it, so it is always cleared.
  function handleSiteChange(nextSiteId: number | null) {
    setSiteId(nextSiteId)
    setOperatorId(null)
  }

  // The Sede only gates submission where the user picks it. Locked mode (spec
  // 0079) always needs an Operatore; free mode keeps the per-mode gating:
  // single needs the Operatore, balanced needs nothing else UNLESS a
  // `balancedScope` is wired, in which case it needs the scope resolved
  // WITHOUT error and at least one operator still selected (spec 0168
  // AC-013/AC-015). Without a `balancedScope` (the transfer dialogs, always
  // `lockedMode="single"`) this branch is unreachable, so the legacy gating
  // below never applies to them.
  const isSiteReady = !showSiteField || siteId !== null
  const isBalancedReady =
    !balancedScope ||
    (!balancedScope.isResolving && !balancedScope.isError && balancedSelection.hasSelection)
  const canSubmit = lockedMode
    ? isSiteReady && operatorId !== null
    : mode !== null && isSiteReady && (mode === 'balanced' ? isBalancedReady : operatorId !== null)

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
      ...(balancedScope && effectiveMode === 'balanced'
        ? { operators_by_site: balancedSelection.operatorsBySite }
        : {}),
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

        {/* Step 3: the "Smistamento equo" operator list, one Sede group at a time
            (spec 0168). Only when the call site wired a `balancedScope` — the two
            transfer dialogs (always `lockedMode="single"`) never reach here. */}
        {effectiveMode === 'balanced' && balancedScope && (
          <BalancedOperatorsPicker
            groups={balancedScope.groups}
            unassignableCount={balancedScope.unassignableCount}
            isResolving={balancedScope.isResolving}
            isError={balancedScope.isError}
            selection={balancedSelection}
            disabled={isSubmitting}
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
