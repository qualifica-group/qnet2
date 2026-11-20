import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { toast } from 'sonner'
import type { TFunction } from 'i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { applyServerValidationErrors } from '@/features/auth/form-errors'

/** Mirrors `StoreTaskRequest`'s title `max:191`. */
const TASK_TITLE_MAX = 191

function buildSchema(t: TFunction) {
  return z.object({
    title: z.string().trim().min(1, t('workOrderPage.taskDialog.titleRequired')).max(TASK_TITLE_MAX),
    end_date: z.string().min(1, t('workOrderPage.taskDialog.endDateRequired')),
  })
}

export type TaskCreateFormValues = z.infer<ReturnType<typeof buildSchema>>

interface TaskCreateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Creates the task and inserts its block; rejects on failure so the dialog stays open. */
  onSubmit: (values: TaskCreateFormValues) => Promise<void>
  /** Default due date (`YYYY-MM-DD`). */
  defaultEndDate: string
}

const FORM_ID = 'work-order-page-task-form'

/**
 * Minimal task creation from the page: title and due date. The requester and
 * the assignee are the current user, everything else takes the server defaults;
 * the full form stays reachable by opening the created task.
 */
export function TaskCreateDialog({ open, onOpenChange, onSubmit, defaultEndDate }: TaskCreateDialogProps) {
  const { t } = useTranslation()
  const form = useForm<TaskCreateFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { title: '', end_date: defaultEndDate },
  })
  const { isSubmitting } = form.formState

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      form.reset({ title: '', end_date: defaultEndDate })
    }
    onOpenChange(next)
  }

  const submit = async (values: TaskCreateFormValues) => {
    try {
      await onSubmit(values)
      handleOpenChange(false)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['title', 'end_date'])) {
        toast.error(t('workOrderPage.taskDialog.genericError'))
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>{t('workOrderPage.taskDialog.title')}</DialogTitle>
          <DialogDescription>{t('workOrderPage.taskDialog.description')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form id={FORM_ID} onSubmit={(event) => void form.handleSubmit(submit)(event)} className="flex flex-col gap-3">
            <FormField
              control={form.control}
              name="title"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('workOrderPage.taskDialog.titleLabel')}</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus maxLength={TASK_TITLE_MAX} disabled={isSubmitting} />
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
                  <FormLabel>{t('workOrderPage.taskDialog.endDateLabel')}</FormLabel>
                  <FormControl>
                    <Input {...field} type="date" disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
        <DialogFooter>
          <Button variant="outline" size="sm" className="bg-card" onClick={() => handleOpenChange(false)} disabled={isSubmitting}>
            {t('common.cancel')}
          </Button>
          <Button size="sm" type="submit" form={FORM_ID} disabled={isSubmitting}>
            {t('workOrderPage.taskDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
