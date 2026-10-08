import { useTranslation } from 'react-i18next'
import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Loader2, TriangleAlert } from 'lucide-react'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { forceClosePayload, useWorkOrderClosure } from '@/features/work-orders/use-work-order-closure'

const FORCE_CLOSE_FORM_ID = 'work-order-force-close-form'

function buildForceCloseSchema(t: TFunction) {
  return z.object({
    force_close_reason: z.string().trim().min(1, t('workOrders.form.forceCloseReasonRequired')),
  })
}

type ForceCloseFormValues = z.infer<ReturnType<typeof buildForceCloseSchema>>

const DEFAULT_VALUES: ForceCloseFormValues = { force_close_reason: '' }

interface WorkOrderForceCloseDialogProps {
  /** The work order to close; `null` keeps the dialog closed (a grid with no row picked). */
  workOrderId: number | null
  onOpenChange: (open: boolean) => void
  /**
   * Spec 0146 D-8/AC-032: the commessa's `open_tasks_count` when the host
   * already holds the record (the detail); omitted, the dialog reads it from
   * the record itself (a grid row does not carry it).
   */
  openTasksCount?: number
  /** After a successful closure (the grid refreshes its rows). */
  onClosed?: () => void
}

/**
 * "Chiusura forzata" as an action (user directive 2026-10-06), shared by the
 * detail and the Commesse grid: the mandatory reason (D-4/AC-073), and — when
 * the commessa still has open tasks — the warning that they will be closed
 * with a negative outcome (spec 0146 D-8, informational: the effect is
 * unconditional server-side).
 */
export function WorkOrderForceCloseDialog({
  workOrderId,
  onOpenChange,
  openTasksCount,
  onClosed,
}: WorkOrderForceCloseDialogProps) {
  const { t } = useTranslation()
  const open = workOrderId !== null
  const form = useForm<ForceCloseFormValues>({
    resolver: zodResolver(buildForceCloseSchema(t)),
    defaultValues: DEFAULT_VALUES,
  })

  const recordQuery = useQuery({
    queryKey: workOrderDetailQueryKey(workOrderId ?? 0),
    queryFn: () => fetchWorkOrder(workOrderId as number),
    enabled: open && openTasksCount === undefined,
  })
  const tasksToClose = openTasksCount ?? recordQuery.data?.open_tasks_count ?? 0

  const closure = useWorkOrderClosure(() => {
    toast.success(t('workOrders.actions.forceClose.success'))
    close()
    onClosed?.()
  })

  function close() {
    form.reset(DEFAULT_VALUES)
    onOpenChange(false)
  }

  const onSubmit = async (values: ForceCloseFormValues) => {
    if (workOrderId === null) {
      return
    }
    try {
      await closure.mutateAsync({ workOrderId, payload: forceClosePayload(values.force_close_reason.trim()) })
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['force_close_reason'])) {
        toast.error(t('workOrders.form.genericError'))
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => (next ? onOpenChange(true) : close())}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.actions.forceClose.title')}</DialogTitle>
          <DialogDescription>{t('workOrders.actions.forceClose.description')}</DialogDescription>
        </DialogHeader>

        {tasksToClose > 0 ? (
          <p role="alert" className="flex items-start gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
            <TriangleAlert aria-hidden="true" className="mt-0.5 size-3.5 shrink-0" />
            <span>{t('workOrders.form.openTasksWarning', { count: tasksToClose })}</span>
          </p>
        ) : null}

        <Form {...form}>
          <form
            id={FORCE_CLOSE_FORM_ID}
            className="flex flex-col gap-4"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
            noValidate
          >
            <FormField
              control={form.control}
              name="force_close_reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('workOrders.form.forceCloseReason')}</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={close} disabled={closure.isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form={FORCE_CLOSE_FORM_ID} disabled={closure.isPending}>
            {closure.isPending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
            {t('workOrders.actions.forceClose.submit')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
