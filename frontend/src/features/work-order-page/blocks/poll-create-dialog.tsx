import { useFieldArray, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Plus, X } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  POLL_FORM_DEFAULTS,
  POLL_OPTIONS_MAX,
  POLL_OPTIONS_MIN,
  POLL_OPTION_MAX,
  POLL_QUESTION_MAX,
  buildPollSchema,
  toCreatePollPayload,
  type PollFormValues,
} from '@/features/work-order-page/blocks/poll-schema'
import type { CreatePollPayload } from '@/features/work-order-page/types'

interface PollCreateDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Creates the poll and inserts its block; rejects on failure so the dialog stays open. */
  onSubmit: (payload: CreatePollPayload) => Promise<void>
}

const FORM_ID = 'work-order-page-poll-form'

/** Small dialog: question + 2..10 options (spec 0380 D-7). */
export function PollCreateDialog({ open, onOpenChange, onSubmit }: PollCreateDialogProps) {
  const { t } = useTranslation()
  const form = useForm<PollFormValues>({
    resolver: zodResolver(buildPollSchema(t)),
    defaultValues: POLL_FORM_DEFAULTS,
  })
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'options' })
  const { isSubmitting } = form.formState

  const handleOpenChange = (next: boolean) => {
    if (!next) {
      form.reset(POLL_FORM_DEFAULTS)
    }
    onOpenChange(next)
  }

  const submit = async (values: PollFormValues) => {
    try {
      await onSubmit(toCreatePollPayload(values))
      handleOpenChange(false)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, ['question'])) {
        toast.error(t('workOrderPage.pollDialog.genericError'))
      }
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{t('workOrderPage.pollDialog.title')}</DialogTitle>
          <DialogDescription>{t('workOrderPage.pollDialog.description')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form id={FORM_ID} onSubmit={(event) => void form.handleSubmit(submit)(event)} className="flex flex-col gap-3">
            <FormField
              control={form.control}
              name="question"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{t('workOrderPage.pollDialog.question')}</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus maxLength={POLL_QUESTION_MAX} disabled={isSubmitting} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <div className="flex flex-col gap-2">
              {fields.map((item, index) => (
                <FormField
                  key={item.id}
                  control={form.control}
                  name={`options.${index}.label`}
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel className="sr-only">{t('workOrderPage.pollDialog.option', { number: index + 1 })}</FormLabel>
                      <div className="flex items-center gap-1.5">
                        <FormControl>
                          <Input
                            {...field}
                            maxLength={POLL_OPTION_MAX}
                            disabled={isSubmitting}
                            placeholder={t('workOrderPage.pollDialog.option', { number: index + 1 })}
                          />
                        </FormControl>
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon-sm"
                          aria-label={t('workOrderPage.pollDialog.removeOption', { number: index + 1 })}
                          disabled={isSubmitting || fields.length <= POLL_OPTIONS_MIN}
                          onClick={() => remove(index)}
                        >
                          <X className="size-3.5" aria-hidden="true" />
                        </Button>
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              ))}
              {fields.length < POLL_OPTIONS_MAX ? (
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="self-start"
                  disabled={isSubmitting}
                  onClick={() => append({ label: '' })}
                >
                  <Plus className="size-3.5" aria-hidden="true" />
                  {t('workOrderPage.pollDialog.addOption')}
                </Button>
              ) : null}
            </div>
          </form>
        </Form>
        <DialogFooter>
          <Button variant="outline" size="sm" className="bg-card" onClick={() => handleOpenChange(false)} disabled={isSubmitting}>
            {t('common.cancel')}
          </Button>
          <Button size="sm" type="submit" form={FORM_ID} disabled={isSubmitting}>
            {t('workOrderPage.pollDialog.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
