import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { ResourcePermissionsProvider, useResourcePermissions } from '@/features/authorization/permissions'
import type { QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import { fetchRequestWorkPanel } from '@/features/request-management/api'
import {
  OfferLinesDialogContext,
  type OfferLinesEditTarget,
} from '@/features/request-management/offer-lines-dialog-context'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { RequestOfferLinesField } from '@/features/request-management/request-offer-lines-section'
import { useOfferLinesForm } from '@/features/request-management/use-offer-lines-form'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Mounts the "Linee di prodotto" editor once for a whole grid and hands its
 * opener down through context, for `OfferLinesCellEditor` to call.
 *
 * The dialog is only the SURFACE of an ordinary inline cell edit (user
 * directive 2026-09-07): the gesture, the per-row gate and the commit are the
 * grid's own — see `OfferLinesCellEditor` for why the rows cannot be composed
 * inside a cell popup, and `useOfferLinesForm` for the commit itself.
 */
export function OfferLinesDialogProvider({ children }: { children: ReactNode }) {
  const [target, setTarget] = useState<OfferLinesEditTarget | null>(null)

  const openOfferLines = useCallback((next: OfferLinesEditTarget) => setTarget(next), [])
  const value = useMemo(() => ({ openOfferLines }), [openOfferLines])
  const close = useCallback(() => setTarget(null), [])

  return (
    <OfferLinesDialogContext.Provider value={value}>
      {children}
      <OfferLinesDialog target={target} onClose={close} />
    </OfferLinesDialogContext.Provider>
  )
}

interface OfferLinesDialogProps {
  /** `null` = closed. */
  target: OfferLinesEditTarget | null
  onClose: () => void
}

function OfferLinesDialog({ target, onClose }: OfferLinesDialogProps) {
  const { t } = useTranslation()
  const open = target !== null

  // Fresh on open, same contract as the work panel: the rows are edited
  // against what the server holds now, and the panel is also what carries the
  // classification scoping the product picker plus the actor's field
  // permissions — none of which the grid row projects.
  const { data: panel, isLoading, isError, refetch } = useEntityDetail(
    requestManagementKeys.panel(target?.quoteId ?? null),
    () => fetchRequestWorkPanel(target?.quoteId as number),
    open,
  )

  return (
    <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
      {/* No `overflow` on the content itself: `AsyncPaginatedSelect` portals
          its menu INTO this node, and any overflow but `visible` would clip
          the menu at the dialog's edge. The scroller is the inner wrapper. */}
      <DialogContent size="xl" className="max-h-[85vh] gap-0 p-0">
        <DialogHeader className="rounded-t-lg border-b bg-surface p-4">
          <DialogTitle>{t('requestManagement.offerLines.dialogTitle')}</DialogTitle>
          <DialogDescription>
            {panel?.name ?? t('requestManagement.offerLines.dialogDescription')}
          </DialogDescription>
        </DialogHeader>

        {isError ? (
          <div className="flex flex-col items-start gap-3 p-4">
            <p className="text-sm text-destructive" role="alert">
              {t('requestManagement.workPanel.loadError')}
            </p>
            <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
              {t('common.retry')}
            </Button>
          </div>
        ) : isLoading || !panel || target === null ? (
          <div className="grid gap-2 p-4">
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-2/3" />
          </div>
        ) : (
          <ResourcePermissionsProvider permissions={panel.permissions}>
            <OfferLinesDialogForm
              // A fresh form per record: the defaults are derived from the
              // panel, and Radix keeps the subtree mounted across a reopen.
              key={panel.id}
              panel={panel}
              target={target}
              onClose={onClose}
            />
          </ResourcePermissionsProvider>
        )}
      </DialogContent>
    </Dialog>
  )
}

interface OfferLinesDialogFormProps {
  panel: RequestWorkPanelWithPermissions
  target: OfferLinesEditTarget
  onClose: () => void
}

function OfferLinesDialogForm({ panel, target, onClose }: OfferLinesDialogFormProps) {
  const { t } = useTranslation()
  const { canResource } = useResourcePermissions()
  const { form, onSubmit, submitError, isSubmitting, vatRatePercentFor, rememberVatRatePercent } =
    useOfferLinesForm(panel, { node: target.node, onDone: onClose })

  return (
    <Form {...form}>
      {/* `display: contents`: the native form only scopes the submit boundary,
          it must not become an extra box between the header and the footer. */}
      <form onSubmit={onSubmit} className="contents" noValidate>
        <div className="grid gap-3 overflow-y-auto p-4">
          <RequestOfferLinesField
            control={form.control}
            knownLines={panel.offer_lines}
            errors={
              // Same cast the work panel applies: RHF types an array field's
              // errors as one node, the row editor reads them per index.
              form.formState.errors.offer_lines as unknown as (QuoteLineRowErrors | undefined)[] | undefined
            }
            vatRatePercentFor={vatRatePercentFor}
            rememberVatRatePercent={rememberVatRatePercent}
          />

          {submitError !== null && (
            <p className="text-xs text-destructive" role="alert">
              {submitError}
            </p>
          )}
        </div>

        <DialogFooter className="rounded-b-lg border-t bg-surface p-4">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isSubmitting || !canResource('update')}>
            {isSubmitting ? t('requestManagement.workPanel.saving') : t('requestManagement.workPanel.save')}
          </Button>
        </DialogFooter>
      </form>
    </Form>
  )
}
