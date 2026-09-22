/**
 * Bulk "Assegna" (D-7): replaces the assignee set of every selected task in
 * ONE request. Reuses the same async user picker the task form's own
 * "Assegnatari" field uses (`AsyncPaginatedMultiSelect` + `USERS_FOR_SELECT_RESOURCE`),
 * without `RelationMultiSelectField`'s `MetaField` wrapper: that wrapper reads
 * a SINGLE record's field permissions, which has no meaning here — the server
 * authorizes per task, in the bulk response itself (AC-017).
 */

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useBulkBoardTaskAction } from '@/features/work-orders/task-board/use-task-board-mutations'
import { showBulkResultToast } from '@/features/work-orders/task-board/task-board-bulk-summary-toast'
import type { TFunction } from 'i18next'

function buildSchema(t: TFunction) {
  return z.object({
    assignee_ids: z.array(z.number()).min(1, t('workOrders.taskBoard.bulk.assignDialog.assigneesRequired')),
  })
}

type AssignFormValues = { assignee_ids: number[] }

interface TaskBoardBulkAssignDialogProps {
  workOrderId: number
  taskIds: number[]
  onClose: () => void
  onSuccess: () => void
}

export function TaskBoardBulkAssignDialog({ workOrderId, taskIds, onClose, onSuccess }: TaskBoardBulkAssignDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const form = useForm<AssignFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { assignee_ids: [] },
  })
  const mutation = useBulkBoardTaskAction(workOrderId)

  const onSubmit = async (values: AssignFormValues) => {
    try {
      const result = await mutation.mutateAsync({ action: 'assign', task_ids: taskIds, assignee_ids: values.assignee_ids })
      showBulkResultToast(t, result)
      onClose()
      onSuccess()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, ['assignee_ids'])
      if (!handled) {
        toast.error(t('workOrders.taskBoard.bulk.genericError'))
      }
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.bulk.assignDialog.title')}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-board-bulk-assign-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
            <FormField
              control={form.control}
              name="assignee_ids"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('tasks.form.assignees')}</FormLabel>
                  <FormControl>
                    <AsyncPaginatedMultiSelect
                      resource={USERS_FOR_SELECT_RESOURCE}
                      value={field.value}
                      onChange={field.onChange}
                      showAvatar
                      disabled={mutation.isPending}
                      labels={{
                        placeholder: selectLabels.placeholder,
                        searchPlaceholder: t('tasks.form.assigneesSearch'),
                        empty: selectLabels.emptyLabel,
                        error: selectLabels.errorLabel,
                        removeLabel: t('common.remove'),
                        triggerLabel: t('tasks.form.assignees'),
                        retry: selectLabels.retryLabel,
                      }}
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
          <Button type="submit" form="task-board-bulk-assign-form" disabled={mutation.isPending}>
            {t('workOrders.taskBoard.bulk.assignDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
