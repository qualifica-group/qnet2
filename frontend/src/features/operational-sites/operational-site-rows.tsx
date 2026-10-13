import { useTranslation } from 'react-i18next'
import { MapPinned, Power } from 'lucide-react'
import { useFormState, useWatch, type UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { SwitchInlineRow, TextInlineRow } from '@/components/record-form/inline-rows'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import type { GeoNameRef } from '@/features/geo/use-geo-names'
import type { OperationalSiteFormValues } from '@/features/operational-sites/use-operational-site-form'

/** The four ids the geo cascade writes; the row is editable only when every one is. */
const GEO_FIELDS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/** The names the closed geo row shows: persisted on a detail, resolved from the draft's ids on create. */
export interface OperationalSiteGeoNames {
  city: GeoNameRef | null
  province: GeoNameRef | null
  state: GeoNameRef | null
  country: GeoNameRef | null
}

interface OperationalSiteRowsProps {
  form: UseFormReturn<OperationalSiteFormValues>
  inline: InlineEdit
  geo: OperationalSiteGeoNames
}

/** The geo cascade as ONE editor, bridged to the form's four ids, with the server's refusal under it. */
function GeoCascadeEditor({ form }: { form: UseFormReturn<OperationalSiteFormValues> }) {
  const { control, setValue } = form
  const { errors } = useFormState({ control })
  const message =
    errors.country_id?.message ?? errors.state_id?.message ?? errors.province_id?.message ?? errors.city_id?.message
  const value: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  const handleChange = (next: GeoValue) => {
    for (const key of GEO_FIELDS) {
      setValue(key, next[key], { shouldValidate: true, shouldDirty: true })
    }
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

/**
 * The site's sections, shared by the detail (saved one row at a time) and the
 * create draft (kept until Salva): the address (alias, street, CAP, the geo
 * cascade as one row) and the active flag.
 */
export function OperationalSiteRows({ form, inline, geo }: OperationalSiteRowsProps) {
  const { t } = useTranslation()
  const geoEditable = useCascadeEditable(GEO_FIELDS)
  const place = [geo.city, geo.province, geo.state, geo.country].flatMap((level) => (level ? [level.name] : []))

  return (
    <>
      <RecordSection title={t('operationalSites.form.sections.address.title')} icon={<MapPinned />}>
        <RecordFieldList>
          <TextInlineRow<OperationalSiteFormValues>
            name="alias"
            label={t('operationalSites.form.alias')}
            inline={inline}
          />
          <TextInlineRow<OperationalSiteFormValues>
            name="line1"
            label={t('operationalSites.form.line1')}
            inline={inline}
          />
          <TextInlineRow<OperationalSiteFormValues>
            name="postal_code"
            label={t('operationalSites.form.postalCode')}
            inline={inline}
          />
          <RecordInlineField
            field="city_id"
            label={t('operationalSites.detail.city')}
            inline={inline}
            canEdit={geoEditable}
            editor={<GeoCascadeEditor form={form} />}
          >
            {place.length > 0 ? place.join(', ') : <DetailEmpty />}
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('operationalSites.form.sections.status.title')} icon={<Power />}>
        <RecordFieldList>
          <SwitchInlineRow<OperationalSiteFormValues>
            name="is_active"
            label={t('operationalSites.form.isActive')}
            inline={inline}
          />
        </RecordFieldList>
      </RecordSection>
    </>
  )
}
