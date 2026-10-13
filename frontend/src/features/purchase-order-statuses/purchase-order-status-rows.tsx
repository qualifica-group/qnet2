import { useTranslation } from 'react-i18next'
import { ColorInlineRow, IconInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { PurchaseOrderStatusFormValues } from '@/features/purchase-order-statuses/purchase-order-status-schema'

interface PurchaseOrderStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The editable rows of an ODA status: name, color and icon (each saved on its own). */
export function PurchaseOrderStatusRows({ inline, canEdit }: PurchaseOrderStatusRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<PurchaseOrderStatusFormValues> name="name" label={t('purchaseOrderStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <ColorInlineRow<PurchaseOrderStatusFormValues> name="color" label={t('purchaseOrderStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<PurchaseOrderStatusFormValues> name="icon" label={t('purchaseOrderStatuses.form.icon')} inline={inline} canEdit={canEdit} />
    </>
  )
}
