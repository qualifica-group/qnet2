import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  IconInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { PurchaseRequestPriorityFormValues } from '@/features/purchase-request-priorities/use-purchase-request-priority-form'

interface PurchaseRequestPriorityRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The priority's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function PurchaseRequestPriorityRows({ inline, canEdit }: PurchaseRequestPriorityRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<PurchaseRequestPriorityFormValues> name="name" label={t('purchaseRequestPriorities.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<PurchaseRequestPriorityFormValues>
        name="description"
        label={t('purchaseRequestPriorities.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<PurchaseRequestPriorityFormValues> name="color" label={t('purchaseRequestPriorities.form.color')} inline={inline} canEdit={canEdit} />
      <IconInlineRow<PurchaseRequestPriorityFormValues> name="icon" label={t('purchaseRequestPriorities.form.icon')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<PurchaseRequestPriorityFormValues>
        name="is_active"
        label={t('purchaseRequestPriorities.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
      <SwitchInlineRow<PurchaseRequestPriorityFormValues>
        name="is_default"
        label={t('purchaseRequestPriorities.form.isDefault')}
        hint={t('purchaseRequestPriorities.form.isDefaultHint')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
