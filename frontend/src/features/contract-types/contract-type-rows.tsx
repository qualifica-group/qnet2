import { useTranslation } from 'react-i18next'
import { NumberInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { ContractTypeFormValues } from '@/features/contract-types/contract-type-schema'

interface ContractTypeRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** The contract type's rows, shared by the detail (saved one at a time) and the create draft (kept until Save). */
export function ContractTypeRows({ inline, canEdit }: ContractTypeRowsProps) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<ContractTypeFormValues> name="name" label={t('contractTypes.form.name')} inline={inline} canEdit={canEdit} />
      <SwitchInlineRow<ContractTypeFormValues> name="is_active" label={t('contractTypes.form.isActive')} inline={inline} canEdit={canEdit} />
      <NumberInlineRow<ContractTypeFormValues>
        name="sort_order"
        label={t('contractTypes.form.sortOrder')}
        inline={inline}
        canEdit={canEdit}
        min={0}
        step={1}
      />
    </>
  )
}
