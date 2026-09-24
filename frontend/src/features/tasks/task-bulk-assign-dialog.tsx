/**
 * Bulk "Assegna" (spec 0156 D-6): REPLACES the assignee set of every selected
 * task in one all-or-nothing request. Mirrors the task board's own
 * `TaskBoardBulkAssignDialog` (same picker, same shape) — bound to
 * `POST /api/tasks/bulk` instead of the board's per-work-order endpoint, whose
 * response/error contract differs (all-or-nothing vs per-row).
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
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { taskBulkErrorDescription } from '@/features/tasks/task-bulk-error'
import { useTaskSelectLabels } from '@/features/tasks/task-select-labels'
import { useTaskBulkMutation } from '@/features/tasks/use-task-bulk-mutation'
import type { TFunction } from 'i18next'

function buildSchema(t: TFunction) {
  return z.object({
    assignee_ids: z.array(z.number()).min(1, t('tasks.bulk.assignDialog.assigneesRequired')),
  })
}

type AssignFormValues = { assignee_ids: number[] }

interface TaskBulkAssignDialogProps {
  taskIds: number[]
  onClose: () => void
  onSuccess: (affected: number) => void
}

export function TaskBulkAssignDialog({ taskIds, onClose, onSuccess }: TaskBulkAssignDialogProps) {
  const { t } = useTranslation()
  const selectLabels = useTaskSelectLabels()
  const form = useForm<AssignFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { assignee_ids: [] },
  })
  const mutation = useTaskBulkMutation()

  const onSubmit = async (values: AssignFormValues) => {
    try {
      const result = await mutation.mutateAsync({ action: 'assign', task_ids: taskIds, assignee_ids: values.assignee_ids })
      toast.success(t('tasks.bulk.success', { count: result.affected }))
      onClose()
      onSuccess(result.affected)
    } catch (error) {
      const { message, reasons } = taskBulkErrorDescription(t, error)
      toast.error(message, reasons.length > 0 ? { description: reasons.join(' ') } : undefined)
    }
  }

  return (
    <Dialog open onOpenChange={(next) => !next && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('tasks.bulk.assignDialog.title', { count: taskIds.length })}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-bulk-assign-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
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
          <Button type="submit" form="task-bulk-assign-form" disabled={mutation.isPending}>
            {t('tasks.bulk.assignDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
