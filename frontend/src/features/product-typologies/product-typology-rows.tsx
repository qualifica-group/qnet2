import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ColorInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { SupplierCommissionFields } from '@/features/product-typologies/supplier-commission-fields'
import type { ProductTypologyFormValues } from '@/features/product-typologies/use-product-typology-form'

interface ProductTypologyRowsProps {
  inline: InlineEdit
  canEdit: boolean
}

/** Identity rows of the typology; `code` is create-only (D-2), so it is shown on the draft, never as a detail row. */
export function ProductTypologyIdentityRows({
  inline,
  canEdit,
  withCode = false,
}: ProductTypologyRowsProps & { withCode?: boolean }) {
  const { t } = useTranslation()

  return (
    <>
      <TextInlineRow<ProductTypologyFormValues> name="name" label={t('productTypologies.form.name')} inline={inline} canEdit={canEdit} />
      {withCode ? (
        <TextInlineRow<ProductTypologyFormValues> name="code" label={t('productTypologies.form.code')} inline={inline} canEdit={canEdit} />
      ) : null}
      <ColorInlineRow<ProductTypologyFormValues> name="color" label={t('productTypologies.form.color')} inline={inline} canEdit={canEdit} />
      <TextInlineRow<ProductTypologyFormValues>
        name="description"
        label={t('productTypologies.form.description')}
        inline={inline}
        canEdit={canEdit}
        multiline
      />
    </>
  )
}

/**
 * The supplier commission is ONE row: the switch and the direction only make
 * sense together (the direction is mandatory while the switch is on), so they
 * open, save and cancel as a unit.
 */
export function ProductTypologyCommissionRow({ inline, canEdit }: ProductTypologyRowsProps) {
  const { t } = useTranslation()
  const form = useFormContext<ProductTypologyFormValues>()
  const directionError = form.formState.errors.supplier_commission_direction?.message
  const [enabled, direction] = useWatch({
    control: form.control,
    name: ['supplier_commission_enabled', 'supplier_commission_direction'],
  })

  return (
    <RecordInlineField
      field="supplier_commission_enabled"
      label={t('productTypologies.form.supplierCommissionEnabled')}
      inline={inline}
      canEdit={canEdit}
      editor={<SupplierCommissionFields form={form} />}
    >
      {enabled
        ? [t('common.yes'), direction ? t(`productTypologies.supplierCommissionDirection.${direction}`) : null]
            .filter(Boolean)
            .join(' · ')
        : t('common.no')}
      {/* The refused rule sits on the direction, which a closed row does not render: say it here. */}
      {directionError ? (
        <p role="alert" className="mt-1 text-xs font-medium text-destructive">
          {directionError}
        </p>
      ) : null}
    </RecordInlineField>
  )
}
