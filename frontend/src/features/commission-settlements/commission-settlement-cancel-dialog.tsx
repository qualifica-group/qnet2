import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'

const REASON_MAX_LENGTH = 500

interface CancelDialogProps {
  open: boolean
  isPending: boolean
  onClose: () => void
  onConfirm: (reason: string) => void
}

function CancelForm({ isPending, onClose, onConfirm }: Omit<CancelDialogProps, 'open'>) {
  const { t } = useTranslation()
  const schema = useMemo(
    () =>
      z.object({
        reason: z
          .string()
          .trim()
          .min(1, t('commissionSettlements.cancelDialog.reasonRequired'))
          .max(REASON_MAX_LENGTH, t('commissionSettlements.cancelDialog.reasonMax')),
      }),
    [t],
  )
  const form = useForm<z.input<typeof schema>, unknown, z.output<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { reason: '' },
  })

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit((values) => onConfirm(values.reason))} noValidate className="flex flex-col gap-3 p-4">
        <FormField
          control={form.control}
          name="reason"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">{t('commissionSettlements.cancelDialog.reason')}</FormLabel>
              <FormControl>
                <Textarea rows={3} {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" variant="destructive" disabled={isPending}>
            {t('commissionSettlements.cancelDialog.confirm')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

/** "Annulla estratto": the reason is mandatory (max 500) and goes to the server as `reason`. */
export function CommissionSettlementCancelDialog({ open, isPending, onClose, onConfirm }: CancelDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="sm" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('commissionSettlements.cancelDialog.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('commissionSettlements.cancelDialog.description')}</DialogDescription>
        </DialogHeader>
        {open ? <CancelForm isPending={isPending} onClose={onClose} onConfirm={onConfirm} /> : null}
      </DialogContent>
    </Dialog>
  )
}
