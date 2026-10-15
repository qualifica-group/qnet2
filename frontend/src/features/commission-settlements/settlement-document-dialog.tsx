import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  DOCUMENT_AMOUNT_FIELDS,
  type DocumentFormValues,
  type DocumentSchemaOutput,
} from '@/features/commission-settlements/settlement-document-schema'
import type { SettlementDetail } from '@/features/commission-settlements/types'
import { useSettlementDocumentForm } from '@/features/commission-settlements/use-settlement-document-form'

type DocumentControl = Control<DocumentFormValues, unknown, DocumentSchemaOutput>
type AmountName = (typeof DOCUMENT_AMOUNT_FIELDS)[number]

const AMOUNT_LABEL_KEYS: Record<AmountName, string> = {
  document_taxable_amount: 'taxable',
  document_vat_amount: 'vat',
  document_withholding_amount: 'withholding',
  document_enasarco_amount: 'enasarco',
  document_total_amount: 'total',
}

interface TextFieldProps {
  control: DocumentControl
  name: 'document_number' | 'document_date'
  label: string
  type: 'text' | 'date'
}

function TextField({ control, name, label, type }: TextFieldProps) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required className="text-xs">{label}</FormLabel>
          <FormControl>
            <Input type={type} className="h-8 text-sm" {...field} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

function AmountField({ control, name, label }: { control: DocumentControl; name: AmountName; label: string }) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className="gap-1">
          <FormLabel required className="text-xs">{label}</FormLabel>
          <FormControl>
            <Input type="number" step="0.01" min="0" inputMode="decimal" className="h-8 text-sm" {...field} />
          </FormControl>
          <FormMessage className="text-xs" />
        </FormItem>
      )}
    />
  )
}

interface DocumentFormProps {
  settlement: SettlementDetail
  onChanged: () => void
  onClose: () => void
}

function DocumentForm({ settlement, onChanged, onClose }: DocumentFormProps) {
  const { t } = useTranslation()
  const { form, submit, isPending } = useSettlementDocumentForm(settlement, onChanged, onClose)

  return (
    <Form {...form}>
      <form onSubmit={submit} noValidate className="flex flex-col gap-3 p-4">
        <div className="grid gap-3 sm:grid-cols-2">
          <TextField control={form.control} name="document_number" type="text" label={t('commissionSettlements.documentDialog.number')} />
          <TextField control={form.control} name="document_date" type="date" label={t('commissionSettlements.documentDialog.date')} />
          {DOCUMENT_AMOUNT_FIELDS.map((name) => (
            <AmountField
              key={name}
              control={form.control}
              name={name}
              label={t(`commissionSettlements.documentDialog.${AMOUNT_LABEL_KEYS[name]}`)}
            />
          ))}
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" size="sm" disabled={isPending}>
            {t('commissionSettlements.documentDialog.confirm')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

interface SettlementDocumentDialogProps {
  open: boolean
  settlement: SettlementDetail
  onChanged: () => void
  onClose: () => void
}

/** "Registra documento": the invoice or fee note received from the agent, compared with the statement by the server. */
export function SettlementDocumentDialog({ open, settlement, onChanged, onClose }: SettlementDocumentDialogProps) {
  const { t } = useTranslation()
  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent size="md" className="gap-0 p-0">
        <DialogHeader className="border-b px-4 py-3 text-left">
          <DialogTitle className="text-base">{t('commissionSettlements.documentDialog.title')}</DialogTitle>
          <DialogDescription className="text-xs">{t('commissionSettlements.documentDialog.description')}</DialogDescription>
        </DialogHeader>
        {open ? <DocumentForm settlement={settlement} onChanged={onChanged} onClose={onClose} /> : null}
      </DialogContent>
    </Dialog>
  )
}
