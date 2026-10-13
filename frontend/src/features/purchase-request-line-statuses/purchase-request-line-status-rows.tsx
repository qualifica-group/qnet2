import { useTranslation } from 'react-i18next'
import { ColorInlineRow, IconInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { PurchaseRequestLineStatusFormValues } from '@/features/purchase-request-line-statuses/purchase-request-line-status-schema'

interface PurchaseRequestLineStatusRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The editable rows of a line status: name, color and icon (spec 0248 D-2). */
export function PurchaseRequestLineStatusRows({ inline, canEdit }: PurchaseRequestLineStatusRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<PurchaseRequestLineStatusFormValues> name="name" label={t('purchaseRequestLineStatuses.form.name')} inline={inline} canEdit={canEdit} />
      <ColorInlineRow<PurchaseRequestLineStatusFormValues> name="color" label={t('purchaseRequestLineStatuses.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<PurchaseRequestLineStatusFormValues> name="icon" label={t('purchaseRequestLineStatuses.form.icon')} inline={inline} canEdit={canEdit} />
    </>
  )
}
