import { useTranslation } from 'react-i18next'
import { NumberInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { VatRateFormValues } from '@/features/vat-rates/use-vat-rate-form'

interface VatRateRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The VAT rate's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function VatRateRows({ inline, canEdit }: VatRateRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<VatRateFormValues> name="name" label={t('vatRates.form.name')} inline={inline} canEdit={canEdit} />
      <NumberInlineRow<VatRateFormValues>
        name="rate"
        label={t('vatRates.form.rate')}
        inline={inline}
        canEdit={canEdit}
        min={0}
        step={0.01}
        suffix="%"
      />
    </>
  )
}
