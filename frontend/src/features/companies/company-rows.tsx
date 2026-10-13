import { useTranslation } from 'react-i18next'
import { Building2, MapPin } from 'lucide-react'
import { useFormState, useWatch, type UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { CompanyFormValues } from '@/features/companies/use-company-form'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { formatOnBlur } from '@/lib/formatting/format-on-blur'
import { formatVatNumber } from '@/lib/formatting/input-format'

interface CompanyRowsProps {
  form: UseFormReturn<CompanyFormValues>
  inline: InlineEdit
  /** The place names the closed geo row shows, comune first: persisted on a detail, resolved from the draft's ids on create. */
  place: string[]
}

/** The geo cascade as ONE editor, bridged to the address block's four ids, with the server's refusal under it. */
function GeoCascadeEditor({ form }: { form: UseFormReturn<CompanyFormValues> }) {
  const { control, setValue } = form
  const { errors } = useFormState({ control })
  const value: GeoValue = {
    country_id: useWatch({ control, name: 'address.country_id' }) ?? null,
    state_id: useWatch({ control, name: 'address.state_id' }) ?? null,
    province_id: useWatch({ control, name: 'address.province_id' }) ?? null,
    city_id: useWatch({ control, name: 'address.city_id' }) ?? null,
  }
  const address = errors.address
  const message =
    address?.country_id?.message ?? address?.state_id?.message ?? address?.province_id?.message ?? address?.city_id?.message

  const handleChange = (next: GeoValue) => {
    setValue('address.country_id', next.country_id, { shouldDirty: true })
    setValue('address.state_id', next.state_id, { shouldDirty: true })
    setValue('address.province_id', next.province_id, { shouldDirty: true })
    setValue('address.city_id', next.city_id, { shouldDirty: true })
  }

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <GeoSelect value={value} onChange={handleChange} layout="compact" />
      {message ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {message}
        </p>
      ) : null}
    </div>
  )
}

/** The VAT number: a text row that re-writes the number into its canonical shape when the field loses focus. */
function VatNumberRow({ form, inline }: { form: UseFormReturn<CompanyFormValues>; inline: InlineEdit }) {
  const { t } = useTranslation()
  const label = t('companies.form.vatNumber')
  const value = useWatch({ control: form.control, name: 'vat_number' })

  return (
    <RecordInlineField
      field="vat_number"
      label={label}
      inline={inline}
      editor={
        <MetaField control={form.control} name="vat_number" metaKey="vat_number" label={label}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                autoComplete="off"
                disabled={disabled}
                readOnly={readOnly}
                {...field}
                onBlur={formatOnBlur(field, formatVatNumber)}
              />
            </FormControl>
          )}
        </MetaField>
      }
    >
      {value || <DetailEmpty />}
    </RecordInlineField>
  )
}

/**
 * The company's sections, shared by the detail (saved one row at a time) and
 * the create draft (kept until Salva): the fiscal identity, and the single
 * address (ADR 0010) gated as a whole on the `address` field permission, its
 * geo cascade as one row.
 */
export function CompanyRows({ form, inline, place }: CompanyRowsProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const generalVisible = fieldPermission('denomination').visible || fieldPermission('vat_number').visible

  return (
    <>
      {generalVisible ? (
        <RecordSection title={t('companies.form.sections.general.title')} icon={<Building2 />}>
          <RecordFieldList>
            <TextInlineRow<CompanyFormValues> name="denomination" label={t('companies.form.denomination')} inline={inline} />
            <VatNumberRow form={form} inline={inline} />
          </RecordFieldList>
        </RecordSection>
      ) : null}

      {fieldPermission('address').visible ? (
        <RecordSection title={t('companies.form.sections.address.title')} icon={<MapPin />}>
          <RecordFieldList>
            <TextInlineRow<CompanyFormValues>
              name="address.line1"
              metaKey="address"
              label={t('companies.form.line1')}
              inline={inline}
            />
            <TextInlineRow<CompanyFormValues>
              name="address.line2"
              metaKey="address"
              label={t('companies.form.line2')}
              inline={inline}
            />
            <TextInlineRow<CompanyFormValues>
              name="address.postal_code"
              metaKey="address"
              label={t('companies.form.postalCode')}
              inline={inline}
            />
            <RecordInlineField
              field="address.city_id"
              metaKey="address"
              label={t('companies.detail.city')}
              inline={inline}
              editor={<GeoCascadeEditor form={form} />}
            >
              {place.length > 0 ? place.join(', ') : <DetailEmpty />}
            </RecordInlineField>
          </RecordFieldList>
        </RecordSection>
      ) : null}
    </>
  )
}
