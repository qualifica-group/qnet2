import { useTranslation } from 'react-i18next'
import { Loader2, Trash2, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatDate } from '@/lib/formatting/date-display'
import { useWorkOrderProgress } from '@/features/work-orders/use-work-order-progress'

const PROGRESS_FORM_ID = 'work-order-progress-form'
const TOTAL_FIELD_ID = 'work-order-progress-total'

interface WorkOrderProgressDialogProps {
  /** The work order to show; `null` keeps the dialog closed. */
  workOrderId: number | null
  onOpenChange: (open: boolean) => void
  /** After a registration or deletion (the host refreshes its grid or record). */
  onMutated?: () => void
}

/**
 * "Lista avanzamenti commessa n. X" (spec 0321 R-5, legacy
 * `lista-avanzamento-commesse-modale`): total reached, the typed current
 * status and date, and the rows with an inline two-step delete. "Conferma"
 * saves and closes. Read-only only by permission (no form, no delete).
 */
export function WorkOrderProgressDialog({ workOrderId, onOpenChange, onMutated }: WorkOrderProgressDialogProps) {
  return (
    <Dialog open={workOrderId !== null} onOpenChange={onOpenChange}>
      {workOrderId !== null ? (
        <WorkOrderProgressContent workOrderId={workOrderId} onClose={() => onOpenChange(false)} onMutated={onMutated} />
      ) : null}
    </Dialog>
  )
}

interface WorkOrderProgressContentProps {
  workOrderId: number
  onClose: () => void
  onMutated?: () => void
}

function TotalField({ total }: { total: number }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={TOTAL_FIELD_ID}>{t('workOrders.progress.total')}</Label>
      <Input id={TOTAL_FIELD_ID} value={total} readOnly />
    </div>
  )
}

/** Mounted only while the dialog is open, so a closed host (grid) does not fetch. */
function WorkOrderProgressContent({ workOrderId, onClose, onMutated }: WorkOrderProgressContentProps) {
  const { t } = useTranslation()
  const progress = useWorkOrderProgress({ workOrderId, onMutated, onSaved: onClose })
  const { form, entries, canWrite } = progress

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{t('workOrders.progress.title', { code: progress.code })}</DialogTitle>
        <DialogDescription className="sr-only">{t('workOrders.progress.description')}</DialogDescription>
      </DialogHeader>

      {progress.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('workOrders.progress.loadError')}
        </p>
      ) : null}

      {canWrite ? (
        <Form {...form}>
          <form
            id={PROGRESS_FORM_ID}
            className="grid grid-cols-1 gap-3 sm:grid-cols-3"
            onSubmit={(event) => void progress.submit(event)}
            noValidate
          >
            <TotalField total={progress.total} />
            <FormField
              control={form.control}
              name="percentage"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('workOrders.progress.percentage')}</FormLabel>
                  <FormControl>
                    <Input type="number" inputMode="numeric" min={1} max={100} step={1} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name="recorded_on"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('workOrders.progress.recordedOn')}</FormLabel>
                  <FormControl>
                    <Input type="date" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
      ) : (
        <TotalField total={progress.total} />
      )}

      {progress.isLoading ? (
        <Loader2 className="size-4 animate-spin text-muted-foreground" aria-label={t('common.loading')} />
      ) : (
        <div className="max-h-64 overflow-auto rounded-lg border bg-card">
          <table className="w-full text-xs">
            <thead className="border-b text-left text-muted-foreground">
              <tr>
                <th scope="col" className="px-3 py-1.5 font-medium">{t('workOrders.progress.recordedOn')}</th>
                <th scope="col" className="px-3 py-1.5 font-medium">{t('workOrders.progress.percentageColumn')}</th>
                {canWrite ? (
                  <th scope="col" className="px-3 py-1.5 font-medium">{t('workOrders.progress.actions')}</th>
                ) : null}
              </tr>
            </thead>
            <tbody>
              {entries.map((entry) => {
                const date = formatDate(entry.recorded_on)
                return (
                  <tr key={entry.id} className="border-b last:border-b-0">
                    <td className="px-3 py-1.5 tabular-nums">{date}</td>
                    <td className="px-3 py-1.5 tabular-nums">{entry.percentage}</td>
                    {canWrite ? (
                      <td className="px-3 py-1">
                        {progress.pendingDeleteId === entry.id ? (
                          <span className="flex items-center gap-1">
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="size-7 text-destructive"
                              disabled={progress.isDeleting}
                              aria-label={t('workOrders.progress.confirmDelete', { date })}
                              onClick={() => progress.confirmDelete(entry)}
                            >
                              <Trash2 className="size-3.5" aria-hidden="true" />
                            </Button>
                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              className="size-7"
                              aria-label={t('workOrders.progress.cancelDelete')}
                              onClick={progress.cancelDelete}
                            >
                              <X className="size-3.5" aria-hidden="true" />
                            </Button>
                          </span>
                        ) : (
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label={t('workOrders.progress.deleteEntry', { date })}
                            onClick={() => progress.askDelete(entry.id)}
                          >
                            <Trash2 className="size-3.5" aria-hidden="true" />
                          </Button>
                        )}
                      </td>
                    ) : null}
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      <DialogFooter>
        <Button type="button" variant="secondary" onClick={onClose}>
          {t('workOrders.progress.close')}
        </Button>
        {canWrite ? (
          <Button type="submit" form={PROGRESS_FORM_ID} disabled={progress.isSaving}>
            {progress.isSaving ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
            {t('workOrders.progress.confirm')}
          </Button>
        ) : null}
      </DialogFooter>
    </DialogContent>
  )
}
