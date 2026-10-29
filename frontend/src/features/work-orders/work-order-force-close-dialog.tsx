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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { forceClosePayload, useWorkOrderClosure } from '@/features/work-orders/use-work-order-closure'
import { WorkflowStatusOption } from '@/features/workflow-statuses/workflow-status-option'
import { WorkflowStatusSwatch } from '@/features/workflow-statuses/workflow-status-swatch'
import type { WorkOrderDetailWithPermissions, WorkOrderWorkflowStatusRef } from '@/features/work-orders/types'

const FORCE_CLOSE_FORM_ID = 'work-order-force-close-form'
const CLOSED_GROUP = 'closed'
const FORM_FIELDS = ['force_close_reason', 'force_close_status_id'] as const

function buildForceCloseSchema(t: TFunction) {
  return z.object({
    force_close_reason: z.string().trim().min(1, t('workOrders.form.forceCloseReasonRequired')),
    force_close_status_id: z.number({ message: t('workOrders.form.forceCloseStatusRequired') }),
  })
}

type ForceCloseFormValues = z.infer<ReturnType<typeof buildForceCloseSchema>>

const EMPTY_STATUSES: WorkOrderWorkflowStatusRef[] = []

/** The statuses a forced closure may land on (spec 0324 D-6): the `closed` group of the resolved set. */
function closedStatuses(record: WorkOrderDetailWithPermissions | undefined): WorkOrderWorkflowStatusRef[] {
  return record?.work_order_workflow_statuses.filter((status) => status.group === CLOSED_GROUP) ?? EMPTY_STATUSES
}

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

interface ForceCloseFormProps {
  workOrderId: number
  statuses: WorkOrderWorkflowStatusRef[]
  tasksToClose: number
  onClose: () => void
  onClosed?: () => void
}

/**
 * Mounted only once the record is loaded, so the closing status can be a plain
 * form default (the row keyed `closed`) instead of an effect patching it in.
 */
function ForceCloseForm({ workOrderId, statuses, tasksToClose, onClose, onClosed }: ForceCloseFormProps) {
  const { t } = useTranslation()
  const form = useForm<ForceCloseFormValues>({
    resolver: zodResolver(buildForceCloseSchema(t)),
    defaultValues: {
      force_close_reason: '',
      force_close_status_id: statuses.find((status) => status.system_key === 'closed')?.id,
    },
  })

  const closure = useWorkOrderClosure(() => {
    toast.success(t('workOrders.actions.forceClose.success'))
    onClose()
    onClosed?.()
  })

  const onSubmit = async (values: ForceCloseFormValues) => {
    try {
      await closure.mutateAsync({
        workOrderId,
        payload: forceClosePayload(values.force_close_reason.trim(), values.force_close_status_id),
      })
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...FORM_FIELDS])) {
        toast.error(t('workOrders.form.genericError'))
      }
    }
  }

  return (
    <>
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
            name="force_close_status_id"
            render={({ field }) => (
              <FormItem>
                <FormLabel required>{t('workOrders.actions.forceClose.status')}</FormLabel>
                <Select
                  value={field.value !== undefined ? String(field.value) : ''}
                  onValueChange={(next) => field.onChange(Number(next))}
                >
                  <FormControl>
                    <SelectTrigger className="h-9 w-full">
                      <SelectValue placeholder={t('workOrders.actions.forceClose.statusPlaceholder')} />
                    </SelectTrigger>
                  </FormControl>
                  <SelectContent>
                    {statuses.map((status) => (
                      <SelectItem key={status.id} value={String(status.id)}>
                        <span className="flex min-w-0 items-center gap-2">
                          <WorkflowStatusSwatch color={status.color} />
                          <WorkflowStatusOption
                            name={status.name}
                            description={status.description}
                            color={status.color}
                            requiresNote={false}
                          />
                        </span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <FormMessage />
              </FormItem>
            )}
          />
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
        <Button type="button" variant="outline" className="bg-card" onClick={onClose} disabled={closure.isPending}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" form={FORCE_CLOSE_FORM_ID} disabled={closure.isPending}>
          {closure.isPending ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
          {t('workOrders.actions.forceClose.submit')}
        </Button>
      </DialogFooter>
    </>
  )
}

/**
 * "Chiusura forzata" as an action (user directive 2026-10-06), shared by the
 * detail and the Commesse grid: the mandatory closing status (spec 0324 D-6)
 * and reason (D-4/AC-073), and — when the commessa still has open tasks — the
 * warning that they will be closed with a negative outcome (spec 0146 D-8,
 * informational: the effect is unconditional server-side).
 */
export function WorkOrderForceCloseDialog({
  workOrderId,
  onOpenChange,
  openTasksCount,
  onClosed,
}: WorkOrderForceCloseDialogProps) {
  const { t } = useTranslation()
  const open = workOrderId !== null

  // The detail host shares this cache key; the set of closing statuses always comes from the record.
  const recordQuery = useQuery({
    queryKey: workOrderDetailQueryKey(workOrderId ?? 0),
    queryFn: () => fetchWorkOrder(workOrderId as number),
    enabled: open,
  })
  const record = recordQuery.data
  const tasksToClose = openTasksCount ?? record?.open_tasks_count ?? 0

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.actions.forceClose.title')}</DialogTitle>
          <DialogDescription>{t('workOrders.actions.forceClose.description')}</DialogDescription>
        </DialogHeader>

        {workOrderId !== null && record ? (
          <ForceCloseForm
            key={workOrderId}
            workOrderId={workOrderId}
            statuses={closedStatuses(record)}
            tasksToClose={tasksToClose}
            onClose={() => onOpenChange(false)}
            onClosed={onClosed}
          />
        ) : (
          <Skeleton className="h-40 w-full" aria-hidden="true" />
        )}
      </DialogContent>
    </Dialog>
  )
}
