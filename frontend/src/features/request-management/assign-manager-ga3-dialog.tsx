import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { UserCog } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'

/** Crisp, compact styling of the single select, shared with the operators popup. */
const SELECT_CLASS = 'h-8 bg-card text-xs shadow-sm transition-colors hover:border-ring/50'

export interface AssignManagerGa3DialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** How many rows are selected; drives the description copy. */
  selectionCount: number
  /**
   * The resolved name of the slot — the scoped category's `manager_labels[3]`
   * ("Tutor" where configured so), or the column's own i18n fallback. Resolved
   * by the consumer, which is what owns the active category tab.
   */
  label: string
  /**
   * Wired by the consumer to `POST /request-management/assign-manager-ga3`.
   * `null` means "clear the slot on the whole selection". A rejection is
   * assumed already surfaced by the caller (toast) and just keeps the dialog
   * open with the current pick so the user can retry.
   */
  onAssign: (managerGa3Id: number | null) => Promise<void>
}

/**
 * Bulk GA3 assignment popup (spec 0104). A deliberately narrower flow than
 * `AssignOperatorsDialog`: ONE field, no Sede and no mode picker, because only
 * the GA2 Operatore slot is bound to a Sede operativa (D-1) and without one
 * there is no pool to balance across. It is a sibling of that dialog, not a
 * variant of it — reuse happens one level down, on the `components/ui`
 * primitives both compose.
 *
 * Confirming with no user picked is a legitimate submission, not an incomplete
 * one (D-2): it clears the slot on every selected request, and the copy says
 * so before the click.
 */
export function AssignManagerGa3Dialog({
  open,
  onOpenChange,
  selectionCount,
  label,
  onAssign,
}: AssignManagerGa3DialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="gap-0 p-0">
        <AssignManagerGa3DialogBody
          selectionCount={selectionCount}
          label={label}
          onAssign={onAssign}
          onClose={() => onOpenChange(false)}
        />
      </DialogContent>
    </Dialog>
  )
}

interface AssignManagerGa3DialogBodyProps {
  selectionCount: number
  label: string
  onAssign: AssignManagerGa3DialogProps['onAssign']
  onClose: () => void
}

/**
 * Radix unmounts `DialogContent`'s subtree while closed, so keeping the pick
 * in its own component — rather than in the dialog the consumer keeps mounted
 * across opens — is what makes every open start from a clean selection.
 */
function AssignManagerGa3DialogBody({
  selectionCount,
  label,
  onAssign,
  onClose,
}: AssignManagerGa3DialogBodyProps) {
  const { t } = useTranslation()
  const [managerGa3Id, setManagerGa3Id] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  function handleAssign() {
    setIsSubmitting(true)
    onAssign(managerGa3Id)
      .then(() => onClose())
      .catch(() => {
        // Already surfaced via toast by the caller; keep the pick so the user
        // can retry without reselecting.
      })
      .finally(() => setIsSubmitting(false))
  }

  return (
    <>
      {/* Header band: same brand-tinted strip and icon chip as the operators popup. */}
      <div className="flex items-start gap-3 rounded-t-lg border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pb-3.5">
        <span
          aria-hidden="true"
          className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
        >
          <UserCog className="size-4.5" />
        </span>
        <DialogHeader className="flex-1 gap-1">
          <DialogTitle className="text-sm">
            {t('requestManagement.assignManagerGa3.title', { label })}
          </DialogTitle>
          <DialogDescription className="text-xs">
            {t('requestManagement.assignManagerGa3.description', { count: selectionCount })}
          </DialogDescription>
        </DialogHeader>
      </div>

      <div className="px-4 py-4">
        <div className="grid gap-3 rounded-xl border bg-gradient-to-b from-card to-muted/20 p-3">
          <div className="space-y-1.5">
            <Label htmlFor="assign-manager-ga3-user" className="text-xs font-medium">
              {label}
            </Label>
            {/* No `params`: the GA3 is bound to no Sede, so the picker lists
                every user — exactly what the grid's own `manager_ga3` cell does. */}
            <AsyncPaginatedSelect
              id="assign-manager-ga3-user"
              resource={USERS_FOR_SELECT_RESOURCE}
              value={managerGa3Id}
              onChange={setManagerGa3Id}
              showAvatar
              disabled={isSubmitting}
              className={SELECT_CLASS}
              labels={{
                placeholder: t('requestManagement.assignManagerGa3.placeholder'),
                searchPlaceholder: t('requestManagement.assignManagerGa3.searchPlaceholder'),
                empty: t('requestManagement.assignManagerGa3.empty'),
                error: t('requestManagement.assignManagerGa3.selectError'),
                clearLabel: t('requestManagement.assignManagerGa3.selectClear'),
                triggerLabel: label,
                retry: t('requestManagement.assignManagerGa3.retry'),
              }}
            />
            <p className="text-[11px] text-muted-foreground">
              {managerGa3Id === null
                ? t('requestManagement.assignManagerGa3.clearHint')
                : t('requestManagement.assignManagerGa3.hint', { label })}
            </p>
          </div>
        </div>
      </div>

      <DialogFooter className="rounded-b-lg border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3.5">
        <Button
          type="button"
          size="sm"
          className="w-full gap-1.5 shadow-sm shadow-primary/20 transition-all hover:shadow-md hover:shadow-primary/25 motion-safe:active:translate-y-px sm:w-auto sm:min-w-44"
          onClick={handleAssign}
          disabled={isSubmitting}
        >
          <UserCog className="size-3.5" aria-hidden="true" />
          {isSubmitting
            ? t('requestManagement.assignManagerGa3.assigning')
            : t('requestManagement.assignManagerGa3.confirm')}
        </Button>
      </DialogFooter>
    </>
  )
}
