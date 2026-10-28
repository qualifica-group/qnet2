import { useTranslation } from 'react-i18next'
import { Loader2, Trash2 } from 'lucide-react'
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
import { CompletionBar } from '@/components/completion-bar'
import { formatDate } from '@/lib/formatting/date-display'
import { useWorkOrderProgress } from '@/features/work-orders/use-work-order-progress'

const PROGRESS_FORM_ID = 'work-order-progress-form'

interface WorkOrderProgressDialogProps {
  /** The work order to show; `null` keeps the dialog closed. */
  workOrderId: number | null
  onOpenChange: (open: boolean) => void
  /** After a registration or deletion (the host refreshes its grid or record). */
  onMutated?: () => void
}

/**
 * "Avanzamento commessa" (spec 0321): the manual progress entries of a work
 * order, a form to register a new cumulative percentage and a per-entry
 * delete. Read-only when the actor cannot update the work order or it is
 * force-closed (no form, no delete).
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

/** Mounted only while the dialog is open, so a closed host (grid) neither fetches nor needs the confirm service. */
function WorkOrderProgressContent({ workOrderId, onClose, onMutated }: WorkOrderProgressContentProps) {
  const { t } = useTranslation()
  const progress = useWorkOrderProgress({ workOrderId, onMutated })
  const { form, entries, canWrite } = progress

  return (
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{t('workOrders.progress.title')}</DialogTitle>
        <DialogDescription>
          {canWrite ? t('workOrders.progress.description') : t('workOrders.progress.readOnly')}
        </DialogDescription>
      </DialogHeader>

      {progress.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('workOrders.progress.loadError')}
        </p>
      ) : null}

      {progress.isLoading ? (
        <Loader2 className="size-4 animate-spin text-muted-foreground" aria-label={t('common.loading')} />
      ) : entries.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('workOrders.progress.empty')}</p>
      ) : (
        <ul aria-label={t('workOrders.progress.listLabel')} className="flex max-h-64 flex-col gap-1 overflow-auto">
          {entries.map((entry) => (
            <li key={entry.id} className="flex items-center gap-3 rounded-lg border bg-card px-3 py-1.5 text-xs">
              <CompletionBar value={entry.percentage} label={t('workOrders.progress.entryLabel')} />
              <span className="tabular-nums">{formatDate(entry.recorded_on)}</span>
              <span className="min-w-0 flex-1 truncate text-muted-foreground">{entry.created_by?.name ?? ''}</span>
              {canWrite ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-7"
                  disabled={progress.isDeleting}
                  aria-label={t('workOrders.progress.deleteEntry', { percentage: entry.percentage })}
                  onClick={() => void progress.removeEntry(entry)}
                >
                  <Trash2 className="size-3.5" aria-hidden="true" />
                </Button>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {canWrite ? (
        <Form {...form}>
          <form
            id={PROGRESS_FORM_ID}
            className="grid grid-cols-2 gap-3"
            onSubmit={(event) => void progress.submit(event)}
            noValidate
          >
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
      ) : null}

      <DialogFooter>
        <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
          {t('workOrders.progress.close')}
        </Button>
        {canWrite ? (
          <Button type="submit" form={PROGRESS_FORM_ID} disabled={progress.isSaving}>
            {progress.isSaving ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
            {t('workOrders.progress.add')}
          </Button>
        ) : null}
      </DialogFooter>
    </DialogContent>
  )
}
