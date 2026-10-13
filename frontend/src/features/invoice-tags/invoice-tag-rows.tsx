import { useTranslation } from 'react-i18next'
import {
  ColorInlineRow,
  SwitchInlineRow,
  TextInlineRow,
} from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { InvoiceTagFormValues } from '@/features/invoice-tags/use-invoice-tag-form'

interface InvoiceTagRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The priority's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function InvoiceTagRows({ inline, canEdit }: InvoiceTagRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<InvoiceTagFormValues> name="name" label={t('invoiceTags.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<InvoiceTagFormValues>
        name="description"
        label={t('invoiceTags.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
      <ColorInlineRow<InvoiceTagFormValues> name="color" label={t('invoiceTags.form.color')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<InvoiceTagFormValues>
        name="is_active"
        label={t('invoiceTags.form.isActive')}
        inline={inline}
        canEdit={canEdit}
      />
    </>
  )
}
