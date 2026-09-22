/**
 * "Nuova fase" dialog of the Task board header menu (spec 0146, D-2): asks
 * for the phase name before creating it, so a new phase never lands with a
 * placeholder label. The server appends it at the end of the order; a 422 on
 * `name` is mapped back onto the field.
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
import { useCreateWorkOrderStage } from '@/features/work-orders/task-board/use-task-board-mutations'

/** Mirrors `StoreWorkOrderStageRequest`'s `max:191`. */
const STAGE_NAME_MAX_LENGTH = 191

function buildSchema(t: TFunction) {
  return z.object({
    name: z
      .string()
      .trim()
      .min(1, t('workOrders.taskBoard.stage.createDialog.nameRequired'))
      .max(STAGE_NAME_MAX_LENGTH),
  })
}

type StageCreateFormValues = { name: string }

interface TaskBoardStageCreateDialogProps {
  workOrderId: number
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function TaskBoardStageCreateDialog({ workOrderId, open, onOpenChange }: TaskBoardStageCreateDialogProps) {
  const { t } = useTranslation()
  const form = useForm<StageCreateFormValues>({
    resolver: zodResolver(buildSchema(t)),
    defaultValues: { name: '' },
  })
  const createStage = useCreateWorkOrderStage(workOrderId)

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      form.reset()
    }
    onOpenChange(next)
  }

  const onSubmit = async (values: StageCreateFormValues) => {
    try {
      await createStage.mutateAsync(values.name)
      handleOpenChange(false)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['name'])) {
        toast.error(t('workOrders.taskBoard.stage.genericError'))
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-sm">
        <DialogHeader>
          <DialogTitle>{t('workOrders.taskBoard.stage.createDialog.title')}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form id="task-board-stage-create-form" onSubmit={(event) => void form.handleSubmit(onSubmit)(event)}>
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('workOrders.taskBoard.stage.createDialog.nameLabel')}</FormLabel>
                  <FormControl>
                    <Input
                      {...field}
                      autoFocus
                      maxLength={STAGE_NAME_MAX_LENGTH}
                      placeholder={t('workOrders.taskBoard.stage.namePlaceholder')}
                      disabled={createStage.isPending}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>

        <DialogFooter>
          <Button variant="outline" size="sm" onClick={() => handleOpenChange(false)} disabled={createStage.isPending}>
            {t('common.cancel')}
          </Button>
          <Button size="sm" type="submit" form="task-board-stage-create-form" disabled={createStage.isPending}>
            {t('workOrders.taskBoard.stage.createDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
