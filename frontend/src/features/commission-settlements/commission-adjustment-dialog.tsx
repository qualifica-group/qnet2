import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { ForSelectFormField } from '@/features/commission-settlements/for-select-form-field'
import { useCommissionAdjustmentForm } from '@/features/commission-settlements/use-commission-adjustment-form'
import type { AdjustmentMode } from '@/features/commission-settlements/types'

interface AdjustmentFormProps {
  mode: AdjustmentMode
  onClose: () => void
  onSaved: () => void
}

function AdjustmentForm({ mode, onClose, onSaved }: AdjustmentFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useCommissionAdjustmentForm(mode, () => {
    onSaved()
    onClose()
  })
  const control = form.control

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex max-h-[70vh] flex-col gap-3 overflow-y-auto p-4">
        {mode.type === 'standalone' ? (
          <>
            <ForSelectFormField
              control={control}
              name="referent_id"
              resource={REFERENTS_FOR_SELECT_RESOURCE}
              label={t('commissionSettlements.adjustment.referent')}
              placeholder={t('commissionSettlements.adjustment.referentPlaceholder')}
              required
            />
            <ForSelectFormField
              control={control}
              name="company_id"
              resource={COMPANIES_FOR_SELECT_RESOURCE}
              label={t('commissionSettlements.adjustment.company')}
              placeholder={t('commissionSettlements.adjustment.companyPlaceholder')}
              required
            />
          </>
        ) : null}
        {mode.type === 'draft' ? (
          <dl className="grid gap-1 rounded-lg border border-border bg-surface p-3 text-xs sm:grid-cols-2">
            <div>
              <dt className="text-muted-foreground">{t('commissionSettlements.adjustment.referent')}</dt>
              <dd className="font-medium">{mode.referent.name}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">{t('commissionSettlements.adjustment.company')}</dt>
              <dd className="font-medium">{mode.company.name}</dd>
            </div>
          </dl>
        ) : null}

        <div className="grid gap-3 sm:grid-cols-2">
          <FormField
            control={control}
            name="amount"
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel required className="text-xs">{t('commissionSettlements.adjustment.amount')}</FormLabel>
                <FormControl>
                  <Input type="number" step="0.01" inputMode="decimal" {...field} />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
          <FormField
            control={control}
            name="accrued_on"
            render={({ field }) => (
              <FormItem className="gap-1">
                <FormLabel required className="text-xs">{t('commissionSettlements.adjustment.accruedOn')}</FormLabel>
                <FormControl>
                  <Input type="date" {...field} />
                </FormControl>
                <FormMessage className="text-xs" />
              </FormItem>
            )}
          />
        </div>
        <p className="text-xs text-muted-foreground">{t('commissionSettlements.adjustment.amountHint')}</p>

        <FormField
          control={control}
          name="notes"
          render={({ field }) => (
            <FormItem className="gap-1">
              <FormLabel required className="text-xs">{t('commissionSettlements.adjustment.notes')}</FormLabel>
              <FormControl>
                <Textarea rows={3} {...field} />
              </FormControl>
              <FormMessage className="text-xs" />
            </FormItem>
          )}
        />

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {isPending ? t('commissionSettlements.adjustment.saving') : t('commissionSettlements.adjustment.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface CommissionAdjustmentDialogProps {
  /** `null` keeps the dialog closed; a mode opens it (standalone, inside a draft, or editing a movement). */
  mode: AdjustmentMode | null
  onClose: () => void
  /** Called after a successful save so the caller refreshes its grid. */
  onSaved: () => void
}

/** "Nuova rettifica" / "Modifica rettifica": one form for the standalone, draft and edit modes. */
export function CommissionAdjustmentDialog({ mode, onClose, onSaved }: CommissionAdjustmentDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={mode !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">
            {t(mode?.type === 'edit' ? 'commissionSettlements.adjustment.editTitle' : 'commissionSettlements.adjustment.title')}
          </DialogTitle>
          <DialogDescription className="text-xs">{t('commissionSettlements.adjustment.description')}</DialogDescription>
        </DialogHeader>
        {mode ? <AdjustmentForm mode={mode} onClose={onClose} onSaved={onSaved} /> : null}
      </DialogContent>
    </Dialog>
  )
}
