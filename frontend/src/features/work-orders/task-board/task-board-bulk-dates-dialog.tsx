/**
 * Bulk "Cambia date" (D-7): `start_date`/`end_date`, at least one required.
 * Plain `<Input type="date">` pair, same control the task form's own
 * Pianificazione section renders (`task-planning-section.tsx`), without its
 * `MetaField` wrapper (no single record's field permissions apply here).
 * `end_date < start_date` is a SERVER-only 422 (no client mirror, no local
 * i18n string for it yet): `applyServerValidationErrors` wires it onto
 * `end_date` when it comes back.
 */

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'

function buildSchema(t: TFunction) {
  return z.object({ start_date: z.string().nullable(), end_date: z.string().nullable() }).superRefine((values, ctx) => {
    if (values.start_date === null && values.end_date === null) {
      ctx.addIssue({
        code: 'custom',
        path: ['start_date'],
        message: t('workOrders.taskBoard.bulk.datesDialog.datesRequired'),
      })
    }
  })
}

type DatesFormValues = { start_date: string | null; end_date: string | null }

interface TaskBoardBulkDatesDialogProps {
  workOrderId: number
  taskIds: number[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkDatesDialog({ workOrderId, taskIds, onClose, onSuccess }: TaskBoardBulkDatesDialogProps) {
  const { t } = useTranslation()
  const form = useForm<DatesFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { start_date: null, end_date: null },
  })
  const mutation = useBulkBoardTaskAction(workOrderId)

  const onSubmit = async (values: DatesFormValues) => {
    try {
      const result = await mutation.mutateAsync({
        action: 'dates',
        task_ids: taskIds,
        start_date: values.start_date,
        end_date: values.end_date,
      })
      showBulkResultToast(t, result)
      onClose()
      onSuccess()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, ['start_date', 'end_date'])
      if (!handled) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
      }
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.bulk.datesDialog.title')}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form
            id="task-board-bulk-dates-form"
            className="grid grid-cols-2 gap-3"
            onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}
          >
            <FormField
              control={form.control}
              name="start_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('tasks.form.startDate')}</FormLabel>
                  <FormControl>
                    <Input
                      type="date"
                      disabled={mutation.isPending}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="end_date"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('tasks.form.endDate')}</FormLabel>
                  <FormControl>
                    <Input
                      type="date"
                      disabled={mutation.isPending}
                      value={field.value ?? ''}
                      onChange={(event) => field.onChange(event.target.value || null)}
                      onBlur={field.onBlur}
                      name={field.name}
                      ref={field.ref}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form="task-board-bulk-dates-form" disabled={mutation.isPending}>
            {t('workOrders.taskBoard.bulk.datesDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
