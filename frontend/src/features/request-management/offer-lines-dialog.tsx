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
import { OfferLinesDialogContext } from '@/features/request-management/offer-lines-dialog-context'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { RequestOfferLinesField } from '@/features/request-management/request-offer-lines-section'
import { useOfferLinesForm } from '@/features/request-management/use-offer-lines-form'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

interface OfferLinesDialogProviderProps {
  children: ReactNode
  /** Refreshes the grid once a save landed (the row's projected products changed). */
  onSaved?: () => void
}

/**
 * Mounts the offer-rows quick edit once for a whole grid and hands its opener
 * down through context (user directive 2026-09-07: "la colonna linee di
 * prodotto ... voglio che nell'edit si possa editare anche tutta la riga
 * dell'offerta").
 *
 * Why a dialog and not an AG Grid cell editor: an offer row is picked through
 * `AsyncPaginatedSelect` (product and aliquota), whose Radix popup portals to
 * `document.body` — inside a cell editor `stopEditingWhenCellsLoseFocus` then
 * tears the editor down mid-pick, which is exactly why `ProductLinesCellEditor`
 * and `MultiSelectCellEditor` had to hand-roll in-popup lists. Those pickers
 * DO work in a dialog (`setTrigger` portals them back into the content node),
 * so the dialog is what lets this reuse the Offerte row editor verbatim
 * instead of cloning it.
 */
export function OfferLinesDialogProvider({ children, onSaved }: OfferLinesDialogProviderProps) {
  const [quoteId, setQuoteId] = useState<number | null>(null)

  const openOfferLines = useCallback((id: number) => setQuoteId(id), [])
  const value = useMemo(() => ({ openOfferLines }), [openOfferLines])
  const close = useCallback(() => setQuoteId(null), [])

  return (
    <OfferLinesDialogContext.Provider value={value}>
      {children}
      <OfferLinesDialog quoteId={quoteId} onClose={close} onSaved={onSaved} />
    </OfferLinesDialogContext.Provider>
  )
}

interface OfferLinesDialogProps {
  /** `null` = closed. The Offerta id, which is the grid row's own id (spec 0086). */
  quoteId: number | null
  onClose: () => void
  onSaved?: () => void
}

function OfferLinesDialog({ quoteId, onClose, onSaved }: OfferLinesDialogProps) {
  const { t } = useTranslation()
  const open = quoteId !== null

  // Fresh on open, same contract as the work panel: the rows are edited
  // against what the server holds now, never against a grid projection that
  // only carries product names.
  const { data: panel, isLoading, isError, refetch } = useEntityDetail(
    requestManagementKeys.panel(quoteId),
    () => fetchRequestWorkPanel(quoteId as number),
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
        ) : isLoading || !panel ? (
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
              onClose={onClose}
              onSaved={onSaved}
            />
          </ResourcePermissionsProvider>
        )}
      </DialogContent>
    </Dialog>
  )
}

interface OfferLinesDialogFormProps {
  panel: RequestWorkPanelWithPermissions
  onClose: () => void
  onSaved?: () => void
}

function OfferLinesDialogForm({ panel, onClose, onSaved }: OfferLinesDialogFormProps) {
  const { t } = useTranslation()
  const { canResource } = useResourcePermissions()
  const { form, onSubmit, submitError, isSubmitting, vatRatePercentFor, rememberVatRatePercent } =
    useOfferLinesForm(panel, { onSaved, onDone: onClose })

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
