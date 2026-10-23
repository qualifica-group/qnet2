import { useTranslation } from 'react-i18next'
import { Package } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetToolbar } from '@/components/ui/sheet'
import { StockMovementFields } from '@/features/stock/stock-movement-fields'
import type { StockMovementFormValues } from '@/features/stock/stock-movement-schema'
import { useStockMovementForm } from '@/features/stock/use-stock-movement-form'

const FORM_ID = 'stock-movement-form'

interface StockMovementFormProps {
  defaultValues: StockMovementFormValues
  onRecorded: () => void
  onClose: () => void
}

function StockMovementForm({ defaultValues, onRecorded, onClose }: StockMovementFormProps) {
  const { t } = useTranslation()
  const { form, isSubmitting, submit } = useStockMovementForm({ defaultValues, onRecorded })
  const title = t('stock.form.title')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={(event) => void submit(event)}
        title={title}
        subtitle={t('stock.form.description')}
        media={<DetailMonogram name={title} icon={<Package />} />}
        isSubmitting={isSubmitting}
        serverError={null}
        onCancel={onClose}
        labels={{ save: t('stock.form.save'), saving: t('stock.form.saving'), cancel: t('stock.form.cancel') }}
      >
        <StockMovementFields />
      </RecordCreateLayout>
    </Form>
  )
}

interface StockMovementSheetProps {
  /** `null` = closed; otherwise the values the form opens with (type and row prefill). */
  initialValues: StockMovementFormValues | null
  onClose: () => void
  onRecorded: () => void
}

/** Right-hand Sheet with the single movement form of the Dotazioni module (spec 0307 D-19). */
export function StockMovementSheet({ initialValues, onClose, onRecorded }: StockMovementSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={initialValues !== null} onOpenChange={(open) => (open ? undefined : onClose())}>
      <SheetContent className="gap-0" showCloseButton={false} storageKey="sheet-width:stock-movement">
        <SheetToolbar closeLabel={t('common.close')} />
        <SheetHeader className="sr-only">
          <SheetTitle>{t('stock.form.title')}</SheetTitle>
          <SheetDescription>{t('stock.form.description')}</SheetDescription>
        </SheetHeader>
        {initialValues !== null ? (
          <StockMovementForm
            defaultValues={initialValues}
            onRecorded={onRecorded}
            onClose={onClose}
          />
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
