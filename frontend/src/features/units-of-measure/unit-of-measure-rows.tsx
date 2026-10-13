import { useTranslation } from 'react-i18next'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { UnitOfMeasureFormValues } from '@/features/units-of-measure/use-unit-of-measure-form'

interface UnitOfMeasureRowsProps {
  inline: InlineEdit
  canEdit: boolean
  /** `code` is create-only (D-1): a row on the draft, the identity band's subtitle on the detail. */
  withCode?: boolean
}

/** The unit's rows, shared by the detail (saved one at a time) and the create draft (kept until Salva). */
export function UnitOfMeasureRows({ inline, canEdit, withCode = false }: UnitOfMeasureRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<UnitOfMeasureFormValues> name="name" label={t('unitsOfMeasure.form.name')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<UnitOfMeasureFormValues> name="symbol" label={t('unitsOfMeasure.form.symbol')} inline={inline} canEdit={canEdit} />
      {withCode ? (
        <TextInlineRow<UnitOfMeasureFormValues> name="code" label={t('unitsOfMeasure.form.code')} inline={inline} canEdit={canEdit} />
      ) : null}
      <TextInlineRow<UnitOfMeasureFormValues>
        name="description"
        label={t('unitsOfMeasure.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
    </>
  )
}
