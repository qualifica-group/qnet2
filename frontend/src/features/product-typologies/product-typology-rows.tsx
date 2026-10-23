import { useFormContext, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { ColorInlineRow, SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { PROFORMA_KINDS, type ProformaKind } from '@/features/product-typologies/types'
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

/** Whether the Supplier commission is calculated for this typology; the direction lives on the rule (spec 0306 D-2). */
export function ProductTypologyCommissionRow({ inline, canEdit }: ProductTypologyRowsProps) {
  const { t } = useTranslation()

  return (
    <SwitchInlineRow<ProductTypologyFormValues>
      name="supplier_commission_enabled"
      label={t('productTypologies.form.supplierCommissionEnabled')}
      inline={inline}
      canEdit={canEdit}
    />
  )
}

/** Radix `Select` cannot hold an empty-string value: `proforma_kind=null` ("none") is this sentinel, translated at the RHF boundary. */
const NONE_PROFORMA_KIND = 'none'

/** Which proforma request the lines of this typology feed (spec 0247 D-6): none, consultancy or institution. */
export function ProductTypologyProformaKindRow({ inline, canEdit }: ProductTypologyRowsProps) {
  const { t } = useTranslation()
  const { control } = useFormContext<ProductTypologyFormValues>()
  const kind = useWatch({ control, name: 'proforma_kind' })
  const label = t('productTypologies.form.proformaKind')

  return (
    <RecordInlineField
      field="proforma_kind"
      label={label}
      inline={inline}
      canEdit={canEdit}
      editor={
        <MetaField
          control={control}
          name="proforma_kind"
          metaKey="proforma_kind"
          label={label}
          hint={t('productTypologies.form.hints.proformaKind')}
        >
          {({ field, disabled }) => (
            <Select
              value={field.value ?? NONE_PROFORMA_KIND}
              onValueChange={(next) => field.onChange(next === NONE_PROFORMA_KIND ? null : (next as ProformaKind))}
              disabled={disabled}
            >
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                <SelectItem value={NONE_PROFORMA_KIND}>{t('productTypologies.proformaKind.none')}</SelectItem>
                {PROFORMA_KINDS.map((option) => (
                  <SelectItem key={option} value={option}>
                    {t(`productTypologies.proformaKind.${option}`)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>
      }
    >
      {t(`productTypologies.proformaKind.${kind ?? NONE_PROFORMA_KIND}`)}
    </RecordInlineField>
  )
}
