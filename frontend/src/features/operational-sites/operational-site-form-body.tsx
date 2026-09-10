import { MapPin } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Form, FormControl } from '@/components/ui/form'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import { useOperationalSiteForm } from '@/features/operational-sites/use-operational-site-form'
import type {
  OperationalSiteDetail,
  OperationalSiteFormMode,
} from '@/features/operational-sites/types'

interface OperationalSiteFormBodyProps {
  mode: OperationalSiteFormMode
  onSuccess: (operationalSite: OperationalSiteDetail) => void
  onCancel: () => void
}

/**
 * Field-permission keys backing the geo cascade (spec 0004/0011 meta
 * contract). `GeoSelect` renders the four levels as a single reusable visual
 * unit (country → region → province → comune), so — unlike `line1`/
 * `postal_code`, each wrapped in its own `MetaField` — their visibility/edit
 * state is aggregated here rather than gated per level: the cascade is not
 * worth rendering unless at least one level is visible, and it is locked as a
 * whole the moment any level is not editable.
 */
const GEO_META_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/**
 * The operational-site create/edit form UI. `line1`/`postal_code` are wrapped
 * in `MetaField` (spec 0004): hidden fields are absent, non-editable fields
 * render disabled, `required` comes from the resolved `ResourcePermissions`
 * — no hardcoded permission logic lives here. The geo cascade reuses
 * `features/geo/geo-select` (never duplicated) and is bridged to RHF via
 * `useWatch`/`setValue`, mirroring `features/personal-data/address-form`. All
 * non-render logic lives in `useOperationalSiteForm`.
 */
export function OperationalSiteFormBody({
  mode,
  onSuccess,
  onCancel,
}: OperationalSiteFormBodyProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { form, serverError, onSubmit } = useOperationalSiteForm({ mode, onSuccess })

  const geoPermissions = GEO_META_KEYS.map((key) => fieldPermission(key))
  const geoVisible = geoPermissions.some((permission) => permission.visible)
  const geoDisabled = geoPermissions.some(
    (permission) => permission.disabled || !permission.editable,
  )

  const geoValue: GeoValue = {
    country_id: useWatch({ control: form.control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control: form.control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control: form.control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control: form.control, name: 'city_id' }) ?? null,
  }

  const handleGeoChange = (next: GeoValue) => {
    form.setValue('country_id', next.country_id)
    form.setValue('state_id', next.state_id)
    form.setValue('province_id', next.province_id)
    form.setValue('city_id', next.city_id)
  }

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
          <FormSection
            icon={MapPin}
            title={t('operationalSites.form.sections.address.title')}
            description={t('operationalSites.form.sections.address.description')}
          >
            <MetaField
              control={form.control}
              name="alias"
              metaKey="alias"
              label={t('operationalSites.form.alias')}
            >
              {({ field, disabled, readOnly }) => (
                <FormControl>
                  <Input disabled={disabled} readOnly={readOnly} {...field} />
                </FormControl>
              )}
            </MetaField>

            {/* Street + postal code share the first row, the geo cascade
                spans it: the comune now reads after the street, as in every
                other address surface. */}
            <div className="grid gap-3 sm:grid-cols-3">
              <MetaField
                control={form.control}
                name="line1"
                metaKey="line1"
                label={t('operationalSites.form.line1')}
                className="sm:col-span-2"
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="address-line1"
                      disabled={disabled}
                      readOnly={readOnly}
                      {...field}
                    />
                  </FormControl>
                )}
              </MetaField>

              <MetaField
                control={form.control}
                name="postal_code"
                metaKey="postal_code"
                label={t('operationalSites.form.postalCode')}
              >
                {({ field, disabled, readOnly }) => (
                  <FormControl>
                    <Input
                      autoComplete="postal-code"
                      disabled={disabled}
                      readOnly={readOnly}
                      {...field}
                    />
                  </FormControl>
                )}
              </MetaField>

              {geoVisible && (
                <div className="sm:col-span-3">
                  <GeoSelect
                    value={geoValue}
                    onChange={handleGeoChange}
                    disabled={geoDisabled}
                    layout="compact"
                  />
                </div>
              )}
            </div>
          </FormSection>

          <CustomFieldsSection resource="operational-sites" control={form.control} />

          {serverError && (
            <p className="text-sm font-medium text-destructive" role="alert">
              {serverError}
            </p>
          )}

          <div className="mt-auto flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={form.formState.isSubmitting}
            >
              {t('operationalSites.form.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting
                ? t('operationalSites.form.saving')
                : t('operationalSites.form.save')}
            </Button>
          </div>
        </form>
      </Form>
    </div>
  )
}
