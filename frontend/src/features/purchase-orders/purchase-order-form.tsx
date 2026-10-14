import { useTranslation } from 'react-i18next'
import { ClipboardList, Paperclip, ShoppingBag } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { Form } from '@/components/ui/form'
import { PurchaseOrderDocuments } from '@/features/purchase-orders/purchase-order-documents'
import { PurchaseOrderHeaderFields } from '@/features/purchase-orders/purchase-order-header-fields'
import { PurchaseOrderLinesSection } from '@/features/purchase-orders/purchase-order-lines-section'
import { orderRefs } from '@/features/purchase-orders/purchase-order-refs'
import type { PurchaseOrder } from '@/features/purchase-orders/types'
import { usePurchaseOrderForm } from '@/features/purchase-orders/use-purchase-order-form'

const FORM_ID = 'purchase-order-form'

interface PurchaseOrderFormProps {
  /** `createdIds` holds every order created at once (more than one with copies). */
  onSaved: (saved: PurchaseOrder, createdIds: number[]) => void
  onCancel: () => void
}

/**
 * Create form of an ODA (spec 0257 D-8): the same record card as the detail,
 * its fields open (monthly copies included), and ONE Save that validates and
 * creates header, lines and the queued documents together. Expects a
 * `ResourcePermissionsProvider` above it (field matrix). Saved ODAs edit in
 * place on the detail instead.
 */
export function PurchaseOrderForm({ onSaved, onCancel }: PurchaseOrderFormProps) {
  const { t } = useTranslation()
  const { form, lineArray, lines, companyId, supplierId, supplierQualified, formErrors, addLine, proposeWorkOrder, submit, isSaving } =
    usePurchaseOrderForm({ onSaved })
  const description = form.watch('description')
  const title = description.trim() === '' ? t('purchaseOrderForm.createTitle') : description

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={FORM_ID}
        onSubmit={submit}
        title={title}
        media={<DetailMonogram name={title} icon={<ShoppingBag />} className="size-10 text-base [&>svg]:size-5" />}
        isSubmitting={isSaving}
        serverError={formErrors.length > 0 ? formErrors.join(' ') : null}
        onCancel={onCancel}
        labels={{ save: t('purchaseOrderForm.save'), saving: t('purchaseOrderForm.saving'), cancel: t('common.cancel') }}
      >
        <div className="flex flex-col gap-5 p-4">
          <RecordSection title={t('purchaseOrderForm.sections.header')} icon={<ClipboardList />}>
            <PurchaseOrderHeaderFields
              control={form.control}
              setValue={form.setValue}
              refs={orderRefs(undefined)}
              companyId={companyId}
              supplierId={supplierId}
              supplierQualified={supplierQualified}
              onWorkOrderChange={proposeWorkOrder}
            />
          </RecordSection>

          <PurchaseOrderLinesSection
            control={form.control}
            setValue={form.setValue}
            fields={lineArray.fields}
            lines={lines}
            onAdd={addLine}
            onRemove={lineArray.remove}
            error={form.formState.errors.lines?.root?.message ?? form.formState.errors.lines?.message}
          />

          <RecordSection title={t('purchaseOrderForm.sections.documents')} icon={<Paperclip />}>
            <PurchaseOrderDocuments control={form.control} />
          </RecordSection>
        </div>
      </RecordCreateLayout>
    </Form>
  )
}
